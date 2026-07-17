<?php
// inicializace/plnime_hr_mzdy.php * Verze: V3 * Aktualizace: 03.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../../www/lib/session_boot.php';

require_once __DIR__ . '/../../www/lib/app.php';
require_once __DIR__ . '/../../www/config/secrets.php';

const HR_MZDY_XLSX = __DIR__ . '/../google_data/HR 2024.xlsx';

if (!defined('HR_MZDY_HELPERS_ONLY') || HR_MZDY_HELPERS_ONLY !== true) {
    if (isset($_POST['run_hr_mzdy']) && (string)$_POST['run_hr_mzdy'] === '1') {
        hrMzdyRun(true);
    } else {
        hrMzdyRun(false);
    }
}

function hrMzdyH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hrMzdyRun(bool $write): void
{
    set_time_limit(0);

    $db = db();
    $db->set_charset('utf8mb4');

    $importState = hrMzdyImportState($db);
    $rows = hrMzdyBuildRows($db, $importState);
    $summary = hrMzdySummary($rows, $importState);
    $result = [];

    if ($write) {
        $result = hrMzdyWrite($db, $rows);
    }

    hrMzdyRender($summary, $result, $write);
}

/**
 * @return array<string, mixed>
 */
function hrMzdyImportState(mysqli $db): array
{
    $state = [
        'existing_total' => 0,
        'max_datum_od' => null,
        'max_datum_do' => null,
        'last_closed_do' => hrMzdyLastClosedDate(),
        'imported_months' => [],
    ];

    $res = $db->query("
        SELECT COUNT(*) AS total, MAX(datum_od) AS max_datum_od, MAX(datum_do) AS max_datum_do
        FROM hr_mzdy_mesic
        WHERE zdroj = 'HR 2024.xlsx'
    ");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc() ?: [];
        $state['existing_total'] = (int)($row['total'] ?? 0);
        $state['max_datum_od'] = $row['max_datum_od'] ?? null;
        $state['max_datum_do'] = $row['max_datum_do'] ?? null;
        $res->free();
    }

    $resMonths = $db->query("
        SELECT DISTINCT datum_od
        FROM hr_mzdy_mesic
        WHERE zdroj = 'HR 2024.xlsx'
    ");
    if ($resMonths instanceof mysqli_result) {
        while ($row = $resMonths->fetch_assoc()) {
            $month = (string)($row['datum_od'] ?? '');
            if ($month !== '') {
                $state['imported_months'][$month] = true;
            }
        }
        $resMonths->free();
    }

    return $state;
}

function hrMzdyLastClosedDate(): string
{
    return (new DateTimeImmutable('first day of this month'))->modify('-1 day')->format('Y-m-d');
}

/**
 * @return array<int, array<string, mixed>>
 */
function hrMzdyBuildRows(mysqli $db, array $importState): array
{
    $users = hrMzdyUserMap($db);
    $targets = hrMzdyWorkbookSheetTargets(HR_MZDY_XLSX);
    $out = [];
    $hasExistingData = (int)($importState['existing_total'] ?? 0) > 0;
    $importedMonths = (array)($importState['imported_months'] ?? []);
    $lastClosedDo = (string)($importState['last_closed_do'] ?? hrMzdyLastClosedDate());

    foreach ($targets as $sheetName => $sheetPath) {
        if (!str_starts_with((string)$sheetName, 'Mzdy') || $sheetName === 'Mzdy' || $sheetName === '__TEMP_SORT_MZDY__') {
            continue;
        }

        $rows = hrMzdySheetRows(HR_MZDY_XLSX, (string)$sheetPath);
        if ($rows === []) {
            continue;
        }

        $period = hrMzdySheetPeriod($rows[1] ?? []);
        if ($period === null || $period['datum_do'] > $lastClosedDo) {
            continue;
        }
        if ($hasExistingData && isset($importedMonths[(string)$period['datum_od']])) {
            continue;
        }

        $format = hrMzdySheetFormat($rows[4] ?? []);
        $slot = null;
        foreach ($rows as $rowNo => $row) {
            $sectionName = hrMzdySlotFromHeader((string)($row['B'] ?? ''));
            if ($sectionName !== null) {
                $slot = $sectionName;
                continue;
            }

            if ($rowNo < 5) {
                continue;
            }

            $importName = trim((string)($row['B'] ?? ''));
            if (!hrMzdyIsPersonName($importName)) {
                continue;
            }
            if ($slot === null) {
                continue;
            }

            $branchIds = hrMzdyBranchIds($row);
            $match = hrMzdyResolveUser($importName, $users, $branchIds);
            $values = hrMzdyRowValues($row, $format);

            $out[] = [
                'id_user' => $match['id_user'],
                'import_jmeno' => $match['id_user'] > 0 ? null : $importName,
                'import_jmeno_raw' => $importName,
                'match_method' => $match['method'],
                'rok' => (int)substr($period['datum_od'], 0, 4),
                'mesic' => (int)substr($period['datum_od'], 5, 2),
                'datum_od' => $period['datum_od'],
                'datum_do' => $period['datum_do'],
                'import_list' => (string)$sheetName,
                'mzda_typ' => $values['mzda_typ'],
                'slot' => $slot,
                'hodiny' => $values['hodiny'],
                'hodinova_sazba' => $values['hodinova_sazba'],
                'mesicni_fix' => $values['mesicni_fix'],
                'isk' => $values['isk'],
                'bonus_1' => $values['bonus_1'],
                'bonus_2' => $values['bonus_2'],
                'bonus_cista' => $values['bonus_cista'],
                'cista_mzda' => $values['cista_mzda'],
                'hruba_mzda' => $values['hruba_mzda'],
                'superhruba_mzda' => $values['superhruba_mzda'],
                'naklad_col_hod' => $values['naklad_col_hod'],
                'naklad_col_den' => $values['naklad_col_den'],
                'je_manager_col' => $values['je_manager_col'],
            ];
        }
    }

    usort($out, static function (array $a, array $b): int {
        $cmp = strcmp((string)$a['datum_od'], (string)$b['datum_od']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['import_jmeno_raw'], (string)$b['import_jmeno_raw']);
    });

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function hrMzdySummary(array $rows, array $importState): array
{
    $months = [];
    $importMonths = [];
    $unmatched = [];
    $ambiguous = [];
    $matched = 0;

    foreach ($rows as $row) {
        $month = (string)$row['datum_od'];
        $importMonths[$month] = true;
        if (!isset($months[$month])) {
            $months[$month] = ['total' => 0, 'matched' => 0, 'unmatched' => 0, 'ambiguous' => 0];
        }
        $months[$month]['total']++;
        if ((int)$row['id_user'] > 0) {
            $months[$month]['matched']++;
            $matched++;
            continue;
        }

        $method = (string)$row['match_method'];
        if ($method === 'ambiguous') {
            $months[$month]['ambiguous']++;
            $ambiguous[(string)$row['import_jmeno_raw']] = true;
        } else {
            $months[$month]['unmatched']++;
            $unmatched[(string)$row['import_jmeno_raw']] = true;
        }
    }

    ksort($months);
    ksort($importMonths);

    $unmatchedNames = array_keys($unmatched);
    $ambiguousNames = array_keys($ambiguous);
    $unknownNames = array_values(array_unique(array_merge($unmatchedNames, $ambiguousNames)));
    sort($unknownNames, SORT_STRING);

    return [
        'total' => count($rows),
        'matched' => $matched,
        'unmatched' => count($rows) - $matched,
        'unmatched_unique' => count($unknownNames),
        'months' => $months,
        'import_months' => array_keys($importMonths),
        'unmatched_names' => $unmatchedNames,
        'ambiguous_names' => $ambiguousNames,
        'unknown_names' => $unknownNames,
        'existing_total' => (int)($importState['existing_total'] ?? 0),
        'max_datum_do' => $importState['max_datum_do'] ?? null,
        'last_closed_do' => (string)($importState['last_closed_do'] ?? hrMzdyLastClosedDate()),
    ];
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, int>
 */
function hrMzdyWrite(mysqli $db, array $rows): array
{
    if ($rows === []) {
        return ['inserted' => 0];
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('
            INSERT INTO hr_mzdy_mesic (
                id_user, rok, mesic, datum_od, datum_do, mzda_typ, slot,
                hodiny, hodinova_sazba, mesicni_fix, isk,
                bonus_1, bonus_2, bonus_cista,
                cista_mzda, hruba_mzda, superhruba_mzda,
                naklad_col_hod, naklad_col_den, je_manager_col,
                import_jmeno, import_list, zdroj, stav, poznamka
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?
            )
        ');
        if (!$stmt instanceof mysqli_stmt) {
            throw new RuntimeException('Nepodařilo se připravit INSERT hr_mzdy_mesic.');
        }

        $inserted = 0;
        foreach ($rows as $row) {
            $idUser = (int)$row['id_user'];
            $idUserDb = $idUser > 0 ? $idUser : null;
            $rok = (int)$row['rok'];
            $mesic = (int)$row['mesic'];
            $datumOd = (string)$row['datum_od'];
            $datumDo = (string)$row['datum_do'];
            $mzdaTyp = (string)$row['mzda_typ'];
            $slot = (string)$row['slot'];
            $hodiny = $row['hodiny'];
            $hodinovaSazba = $row['hodinova_sazba'];
            $mesicniFix = $row['mesicni_fix'];
            $isk = $row['isk'];
            $bonus1 = $row['bonus_1'];
            $bonus2 = $row['bonus_2'];
            $bonusCista = $row['bonus_cista'];
            $cistaMzda = $row['cista_mzda'];
            $hrubaMzda = $row['hruba_mzda'];
            $superhrubaMzda = $row['superhruba_mzda'];
            $nakladColHod = $row['naklad_col_hod'];
            $nakladColDen = $row['naklad_col_den'];
            $jeManagerCol = (int)$row['je_manager_col'];
            $importJmeno = $row['import_jmeno'];
            $importList = (string)$row['import_list'];
            $zdroj = 'HR 2024.xlsx';
            $stav = 'import';
            $poznamka = (int)$row['id_user'] > 0 ? null : 'Čeká na ruční spárování s user.';

            $stmt->bind_param(
                'iiissssddddddddddddisssss',
                $idUserDb,
                $rok,
                $mesic,
                $datumOd,
                $datumDo,
                $mzdaTyp,
                $slot,
                $hodiny,
                $hodinovaSazba,
                $mesicniFix,
                $isk,
                $bonus1,
                $bonus2,
                $bonusCista,
                $cistaMzda,
                $hrubaMzda,
                $superhrubaMzda,
                $nakladColHod,
                $nakladColDen,
                $jeManagerCol,
                $importJmeno,
                $importList,
                $zdroj,
                $stav,
                $poznamka
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('INSERT hr_mzdy_mesic selhal: ' . $stmt->error);
            }
            $inserted++;
        }

        $stmt->close();
        $db->commit();

        return ['inserted' => $inserted];
    } catch (Throwable $e) {
        $db->rollback();
        return ['inserted' => 0, 'error' => 1, 'error_message' => $e->getMessage()];
    }
}

function hrMzdyMonthLabel(?string $date): string
{
    $date = trim((string)$date);
    if ($date === '') {
        return 'Ne';
    }

    try {
        $dt = new DateTimeImmutable($date);
    } catch (Throwable) {
        return $date;
    }

    $months = [
        1 => 'leden',
        2 => 'únor',
        3 => 'březen',
        4 => 'duben',
        5 => 'květen',
        6 => 'červen',
        7 => 'červenec',
        8 => 'srpen',
        9 => 'září',
        10 => 'říjen',
        11 => 'listopad',
        12 => 'prosinec',
    ];

    return $months[(int)$dt->format('n')] . ' ' . $dt->format('Y');
}

/**
 * @param array<int, string> $months
 */
function hrMzdyMonthListLabel(array $months): string
{
    if ($months === []) {
        return 'Žádné';
    }

    return implode(', ', array_map(static fn (string $date): string => hrMzdyMonthLabel($date), $months));
}

/**
 * @param array<string, mixed> $summary
 * @param array<string, int> $result
 */
function hrMzdyRender(array $summary, array $result, bool $write): void
{
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Inicializace HR mezd</h2>
      <p class="card_text txt_seda">Script načte uzavřené měsíce z HR 2024.xlsx do tabulky hr_mzdy_mesic. Již importované měsíce a rozpracovaný list Mzdy se neimportují.</p>

      <?php if ($write): ?>
        <?php if ((int)($result['error'] ?? 0) === 1): ?>
          <p class="card_text txt_cervena text_tucny">Import selhal. Do DB nebylo nic uloženo.</p>
          <p class="card_text txt_seda"><?= hrMzdyH((string)($result['error_message'] ?? 'Neznámá chyba zápisu.')) ?></p>
        <?php else: ?>
          <p class="card_text txt_zelena text_tucny">Import dokončen. Vloženo řádků: <?= hrMzdyH(number_format((int)($result['inserted'] ?? 0), 0, ',', ' ')) ?></p>
        <?php endif; ?>
      <?php endif; ?>

      <table class="table ram_normal bg_bila radek_1_35 sirka100">
        <tbody>
          <tr><td>Staženo do</td><td class="txt_r"><strong><?= hrMzdyH(hrMzdyMonthLabel($summary['max_datum_do'] ?? null)) ?></strong></td></tr>
          <tr><td>Poslední uzavřený měsíc</td><td class="txt_r"><strong><?= hrMzdyH(hrMzdyMonthLabel((string)$summary['last_closed_do'])) ?></strong></td></tr>
          <tr><td>Měsíce k importu</td><td class="txt_r"><strong><?= hrMzdyH(hrMzdyMonthListLabel((array)$summary['import_months'])) ?></strong></td></tr>
          <tr><td>Řádků k importu celkem</td><td class="txt_r"><strong><?= hrMzdyH(number_format((int)$summary['total'], 0, ',', ' ')) ?></strong></td></tr>
          <tr><td>Spárované řádky</td><td class="txt_r"><strong><?= hrMzdyH(number_format((int)$summary['matched'], 0, ',', ' ')) ?></strong></td></tr>
          <tr><td>Neurčené řádky</td><td class="txt_r"><strong><?= hrMzdyH(number_format((int)$summary['unmatched'], 0, ',', ' ')) ?></strong></td></tr>
          <tr><td>Neurčená unikátní jména</td><td class="txt_r"><strong><?= hrMzdyH(number_format((int)$summary['unmatched_unique'], 0, ',', ' ')) ?></strong></td></tr>
        </tbody>
      </table>

      <h3 class="card_title txt_seda text_18 text_tucny odstup_horni_15">Měsíce</h3>
      <table class="table ram_normal bg_bila radek_1_35 sirka100">
        <thead>
          <tr><th class="txt_l">Měsíc</th><th class="txt_r">Celkem</th><th class="txt_r">Spárováno</th><th class="txt_r">Neurčeno</th></tr>
        </thead>
        <tbody>
          <?php foreach (($summary['months'] ?? []) as $month => $row): ?>
            <tr>
              <td><?= hrMzdyH((string)$month) ?></td>
              <td class="txt_r"><?= hrMzdyH(number_format((int)$row['total'], 0, ',', ' ')) ?></td>
              <td class="txt_r"><?= hrMzdyH(number_format((int)$row['matched'], 0, ',', ' ')) ?></td>
              <td class="txt_r"><?= hrMzdyH(number_format((int)$row['unmatched'] + (int)$row['ambiguous'], 0, ',', ' ')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h3 class="card_title txt_seda text_18 text_tucny odstup_horni_15">Neurčená jména</h3>
      <p class="card_text txt_seda"><?= hrMzdyH(implode(', ', (array)$summary['unknown_names'])) ?></p>

      <div class="card_actions gap_8 displ_flex odstup_horni_10">
        <form method="post" action="<?= hrMzdyH(cb_url('/index_is.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1" data-cb-loader-text="Probíhá import HR mezd">
          <input type="hidden" name="run_hr_mzdy" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-loader-text="Probíhá import HR mezd">Spustit import</button>
        </form>
        <form method="post" action="<?= hrMzdyH(cb_url('/index_is.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
          <input type="hidden" name="back_admin_init" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex">Zpět</button>
        </form>
      </div>
    </div>
    <?php
}

/**
 * @return array<string, mixed>
 */
function hrMzdyUserMap(mysqli $db): array
{
    $res = $db->query('
        SELECT
            u.id_user, u.jmeno, u.prijmeni, u.alias, u.aktivni,
            GROUP_CONCAT(DISTINCT up.id_pob ORDER BY up.id_pob SEPARATOR ",") AS pobocky
        FROM `user` u
        LEFT JOIN user_pobocka up ON up.id_user = u.id_user
        GROUP BY u.id_user, u.jmeno, u.prijmeni, u.alias, u.aktivni
    ');

    $map = [
        'full' => [],
        'reverse' => [],
        'full_plain' => [],
        'reverse_plain' => [],
        'alias' => [],
        'alias_plain' => [],
        'surname' => [],
        'surname_plain' => [],
        'by_id' => [],
    ];

    if (!$res instanceof mysqli_result) {
        return $map;
    }

    while ($row = $res->fetch_assoc()) {
        $user = [
            'id_user' => (int)$row['id_user'],
            'jmeno' => (string)$row['jmeno'],
            'prijmeni' => (string)$row['prijmeni'],
            'alias' => (string)($row['alias'] ?? ''),
            'aktivni' => (int)$row['aktivni'],
            'pobocky' => array_filter(array_map('intval', explode(',', (string)($row['pobocky'] ?? '')))),
        ];
        $map['by_id'][(int)$user['id_user']] = $user;
        hrMzdyAddCandidate($map['full'], hrMzdyNameKey($user['jmeno'] . ' ' . $user['prijmeni']), $user);
        hrMzdyAddCandidate($map['reverse'], hrMzdyNameKey($user['prijmeni'] . ' ' . $user['jmeno']), $user);
        hrMzdyAddCandidate($map['full_plain'], hrMzdyPlainNameKey($user['jmeno'] . ' ' . $user['prijmeni']), $user);
        hrMzdyAddCandidate($map['reverse_plain'], hrMzdyPlainNameKey($user['prijmeni'] . ' ' . $user['jmeno']), $user);
        foreach (hrMzdyAliasList($user['alias']) as $alias) {
            hrMzdyAddCandidate($map['alias'], hrMzdyNameKey($alias), $user);
            hrMzdyAddCandidate($map['alias_plain'], hrMzdyPlainNameKey($alias), $user);
        }
        hrMzdyAddCandidate($map['surname'], hrMzdyNameKey($user['prijmeni']), $user);
        hrMzdyAddCandidate($map['surname_plain'], hrMzdyPlainNameKey($user['prijmeni']), $user);
    }
    $res->free();

    return $map;
}

/**
 * @return array<int, string>
 */
function hrMzdyAliasList(string $alias): array
{
    $items = array_map('trim', explode('+!+', $alias));
    return array_values(array_unique(array_filter($items, static fn (string $item): bool => $item !== '')));
}

/**
 * @param array<string, array<int, array<string, mixed>>> $index
 * @param array<string, mixed> $user
 */
function hrMzdyAddCandidate(array &$index, string $key, array $user): void
{
    if ($key === '') {
        return;
    }
    if (!isset($index[$key])) {
        $index[$key] = [];
    }
    $index[$key][(int)$user['id_user']] = $user;
}

/**
 * @param array<string, mixed> $users
 * @param array<int, int> $branchIds
 * @return array{id_user:int, method:string}
 */
function hrMzdyResolveUser(string $name, array $users, array $branchIds): array
{
    $manual = [
        'Hegedüs Erik' => 291,
        'Pekárková Milena' => 408,
        'Pechová Barbora' => 431,
        'Lanský Zoltan' => 385,
        'Rothová Anastasia' => 234,
        'Chlubnová Adéla' => 577,
        'Eszenyiová Patrícia' => 493,
        'Jáklová Dominika' => 464,
        'Šarközi Dávid' => 469,
        'Roth Stanislav st.' => 1,
        'Roth Stanislav ml.' => 3,
        'Hegedüs Gergö' => 520,
        'Chyský David' => 147,
        'Hofmanová Jana' => 37,
        'Jana Hofmanová' => 37,
        'Bušek Pavel' => 604,
        'Čonka Ludovít' => 611,
    ];
    if (isset($manual[$name], $users['by_id'][$manual[$name]])) {
        return ['id_user' => (int)$manual[$name], 'method' => 'bordel_smeny'];
    }

    $attempts = hrMzdyResolveAttempts($name);
    foreach ($attempts as $attempt) {
        $matches = $users[$attempt['index']][$attempt['key']] ?? [];
        $selected = hrMzdySelectCandidate(array_values($matches), $branchIds);
        if ($selected !== null) {
            return ['id_user' => (int)$selected['id_user'], 'method' => (string)$attempt['method']];
        }
    }

    return ['id_user' => 0, 'method' => 'unmatched'];
}

/**
 * @return array<int, array{index:string,key:string,method:string}>
 */
function hrMzdyResolveAttempts(string $name): array
{
    $swapped = hrMzdySwapName($name);
    $attempts = [
        ['index' => 'full', 'key' => hrMzdyNameKey($name), 'method' => 'jmeno_prijmeni'],
        ['index' => 'reverse', 'key' => hrMzdyNameKey($name), 'method' => 'prijmeni_jmeno'],
        ['index' => 'full', 'key' => hrMzdyNameKey($swapped), 'method' => 'prohozene'],
        ['index' => 'full_plain', 'key' => hrMzdyPlainNameKey($name), 'method' => 'bez_diakritiky_jmeno_prijmeni'],
        ['index' => 'reverse_plain', 'key' => hrMzdyPlainNameKey($name), 'method' => 'bez_diakritiky_prijmeni_jmeno'],
        ['index' => 'full_plain', 'key' => hrMzdyPlainNameKey($swapped), 'method' => 'bez_diakritiky_prohozene'],
        ['index' => 'alias', 'key' => hrMzdyNameKey($name), 'method' => 'alias'],
        ['index' => 'alias_plain', 'key' => hrMzdyPlainNameKey($name), 'method' => 'alias_bez_diakritiky'],
    ];

    return array_values(array_filter($attempts, static fn (array $row): bool => (string)$row['key'] !== ''));
}

/**
 * @param array<int, array<string, mixed>> $matches
 * @param array<int, int> $branchIds
 * @return array<string, mixed>|null
 */
function hrMzdySelectCandidate(array $matches, array $branchIds): ?array
{
    if (count($matches) === 0) {
        return null;
    }
    if (count($matches) === 1) {
        return $matches[0];
    }

    $active = array_values(array_filter($matches, static fn (array $u): bool => (int)$u['aktivni'] === 1));
    if (count($active) === 1) {
        return $active[0];
    }

    $pool = $active !== [] ? $active : $matches;
    if ($branchIds !== []) {
        $branch = [];
        foreach ($pool as $user) {
            if (array_intersect($branchIds, (array)$user['pobocky']) !== []) {
                $branch[] = $user;
            }
        }
        if (count($branch) === 1) {
            return $branch[0];
        }
    }

    return null;
}

function hrMzdyNameKey(string $value): string
{
    return trim((string)preg_replace('/\s+/u', ' ', mb_strtolower($value, 'UTF-8')));
}

function hrMzdyPlainNameKey(string $value): string
{
    $value = hrMzdyNameKey($value);
    $map = [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
        'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u',
        'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        'ä' => 'a', 'ö' => 'o', 'ő' => 'o', 'ü' => 'u', 'ű' => 'u',
    ];
    return strtr($value, $map);
}

function hrMzdySwapName(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    if (count($parts) < 2) {
        return '';
    }
    $last = (string)array_pop($parts);
    return trim($last . ' ' . implode(' ', $parts));
}

/**
 * @return array<int, string>
 */
function hrMzdySurnameCandidates(string $name): array
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    if ($parts === []) {
        return [];
    }
    return array_values(array_unique(array_filter([(string)reset($parts), (string)end($parts)])));
}

function hrMzdyIsPersonName(string $name): bool
{
    $key = hrMzdyNameKey($name);
    if ($key === '' || !str_contains($key, ' ')) {
        return false;
    }
    return preg_match('/^(celkem|suma|kurýr|end|radi|system\.xml\.xmlelement)/u', $key) !== 1;
}

/**
 * @param array<string, string> $row
 * @return array<int, int>
 */
function hrMzdyBranchIds(array $row): array
{
    $map = ['Malešice' => 1, 'Chodov' => 2, 'Zličín' => 3, 'Bolevec' => 4, 'Libuš' => 5, 'Prosek' => 6, 'Výroba' => 7];
    $out = [];
    foreach (['C', 'D', 'E', 'F'] as $col) {
        $name = trim((string)($row[$col] ?? ''));
        if (isset($map[$name])) {
            $out[] = $map[$name];
        }
    }
    return array_values(array_unique($out));
}

/**
 * @param array<string, string> $row
 * @return array<string, string>|null
 */
function hrMzdySheetPeriod(array $row): ?array
{
    $start = hrMzdyExcelDate($row['M'] ?? '');
    $end = hrMzdyExcelDate($row['S'] ?? '');
    if ($start === null) {
        $start = hrMzdyExcelDate($row['U'] ?? '');
        $end = hrMzdyExcelDate($row['AA'] ?? '');
    }
    if ($start === null) {
        return null;
    }
    if ($end === null) {
        $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    }
    return ['datum_od' => $start, 'datum_do' => $end];
}

/**
 * @param array<string, string> $header
 */
function hrMzdySheetFormat(array $header): string
{
    return trim((string)($header['T'] ?? '')) === 'Instore' ? 'new' : 'old';
}

function hrMzdySlotFromHeader(string $value): ?string
{
    $key = hrMzdyPlainNameKey($value);
    if ($key === 'instore' || $key === 'instor') {
        return 'instor';
    }
    if ($key === 'kuryr') {
        return 'kuryr';
    }
    return null;
}

/**
 * @param array<string, string> $row
 * @return array<string, mixed>
 */
function hrMzdyRowValues(array $row, string $format): array
{
    $g = hrMzdyDecimal($row['G'] ?? null);
    $h = hrMzdyDecimal($row['H'] ?? null);
    $isFix = $g !== null && abs($g - 1.0) < 0.0001;

    return [
        'mzda_typ' => $isFix ? 'fix' : 'hodinova',
        'hodiny' => hrMzdyDecimal($format === 'new' ? ($row['U'] ?? null) : ($row['M'] ?? null)),
        'hodinova_sazba' => $isFix ? null : $h,
        'mesicni_fix' => $isFix ? $h : null,
        'isk' => hrMzdyDecimal($row['I'] ?? null),
        'bonus_1' => hrMzdyDecimal($row['J'] ?? null),
        'bonus_2' => hrMzdyDecimal($row['K'] ?? null),
        'bonus_cista' => $format === 'new' ? hrMzdyDecimal($row['L'] ?? null) : null,
        'cista_mzda' => hrMzdyDecimal($format === 'new' ? ($row['V'] ?? null) : ($row['N'] ?? null)),
        'hruba_mzda' => hrMzdyDecimal($format === 'new' ? ($row['W'] ?? null) : ($row['O'] ?? null)),
        'superhruba_mzda' => hrMzdyDecimal($format === 'new' ? ($row['X'] ?? null) : ($row['P'] ?? null)),
        'naklad_col_hod' => hrMzdyDecimal($format === 'new' ? ($row['AC'] ?? null) : ($row['U'] ?? null)),
        'naklad_col_den' => hrMzdyDecimal($format === 'new' ? ($row['AD'] ?? null) : ($row['V'] ?? null)),
        'je_manager_col' => (int)(hrMzdyDecimal($row['A'] ?? null) ?? 0),
    ];
}

function hrMzdyDecimal(mixed $value): ?float
{
    $text = trim(str_replace(',', '.', (string)$value));
    if ($text === '' || strtoupper($text) === '#DIV/0!') {
        return null;
    }
    return is_numeric($text) ? (float)$text : null;
}

function hrMzdyExcelDate(string $value): ?string
{
    $num = hrMzdyDecimal($value);
    if ($num === null || $num <= 0) {
        return null;
    }
    return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)$num . ' days')->format('Y-m-d');
}

/**
 * @return array<string, string>
 */
function hrMzdyWorkbookSheetTargets(string $filePath): array
{
    $workbookXml = hrMzdyZipEntry($filePath, 'xl/workbook.xml');
    $relsXml = hrMzdyZipEntry($filePath, 'xl/_rels/workbook.xml.rels');
    if ($workbookXml === null || $relsXml === null) {
        return [];
    }

    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);
    if ($workbook === false || $rels === false) {
        return [];
    }

    $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $rels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $relMap = [];
    $relNodes = $rels->xpath('/p:Relationships/p:Relationship');
    if (is_array($relNodes)) {
        foreach ($relNodes as $node) {
            $attrs = $node->attributes();
            if ($attrs === null) {
                continue;
            }
            $id = trim((string)$attrs['Id']);
            $target = trim((string)$attrs['Target']);
            if ($id !== '' && $target !== '') {
                $relMap[$id] = str_starts_with($target, 'xl/') ? $target : 'xl/' . ltrim($target, '/');
            }
        }
    }

    $out = [];
    $sheetNodes = $workbook->xpath('/x:workbook/x:sheets/x:sheet');
    if (is_array($sheetNodes)) {
        foreach ($sheetNodes as $sheet) {
            $attrs = $sheet->attributes();
            $attrsR = $sheet->attributes('r', true);
            if ($attrs === null || $attrsR === null) {
                continue;
            }
            $name = trim((string)$attrs['name']);
            $rid = trim((string)$attrsR['id']);
            if ($name !== '' && isset($relMap[$rid])) {
                $out[$name] = $relMap[$rid];
            }
        }
    }

    return $out;
}

/**
 * @return array<int, array<string, string>>
 */
function hrMzdySheetRows(string $filePath, string $sheetPath): array
{
    $sheetXml = hrMzdyZipEntry($filePath, $sheetPath);
    if ($sheetXml === null || $sheetXml === '') {
        return [];
    }

    $sharedStrings = hrMzdySharedStrings($filePath);
    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        return [];
    }

    $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rowNodes = $sheet->xpath('/x:worksheet/x:sheetData/x:row');
    if (!is_array($rowNodes)) {
        return [];
    }

    $rows = [];
    foreach ($rowNodes as $rowNode) {
        $rowAttrs = $rowNode->attributes();
        if ($rowAttrs === null) {
            continue;
        }
        $rowNo = (int)$rowAttrs['r'];
        $rows[$rowNo] = [];
        foreach ($rowNode->c as $cell) {
            $cellAttrs = $cell->attributes();
            if ($cellAttrs === null) {
                continue;
            }
            $ref = trim((string)$cellAttrs['r']);
            if (preg_match('/^([A-Z]+)\d+$/', $ref, $m) !== 1) {
                continue;
            }
            $type = isset($cellAttrs['t']) ? (string)$cellAttrs['t'] : '';
            if ($type === 's') {
                $value = $sharedStrings[(int)$cell->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = trim((string)$cell->is->t);
            } else {
                $value = trim((string)$cell->v);
            }
            $rows[$rowNo][$m[1]] = $value;
        }
    }

    return $rows;
}

/**
 * @return array<int, string>
 */
function hrMzdySharedStrings(string $filePath): array
{
    static $cache = [];
    if (isset($cache[$filePath])) {
        return $cache[$filePath];
    }

    $xml = hrMzdyZipEntry($filePath, 'xl/sharedStrings.xml');
    if ($xml === null || $xml === '') {
        $cache[$filePath] = [];
        return $cache[$filePath];
    }
    $doc = simplexml_load_string($xml);
    if ($doc === false) {
        $cache[$filePath] = [];
        return $cache[$filePath];
    }
    $doc->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $items = $doc->xpath('/x:sst/x:si');
    if (!is_array($items)) {
        $cache[$filePath] = [];
        return $cache[$filePath];
    }
    $out = [];
    foreach ($items as $si) {
        $txt = '';
        if (isset($si->t)) {
            $txt = (string)$si->t;
        } elseif (isset($si->r)) {
            foreach ($si->r as $run) {
                $txt .= (string)$run->t;
            }
        }
        $out[] = trim($txt);
    }
    $cache[$filePath] = $out;
    return $cache[$filePath];
}

function hrMzdyZipEntry(string $filePath, string $entryPath): ?string
{
    static $entryCache = [];
    $cacheKey = $filePath . "\n" . $entryPath;
    if (array_key_exists($cacheKey, $entryCache)) {
        return $entryCache[$cacheKey];
    }

    if (!is_file($filePath)) {
        return null;
    }
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $content = $zip->getFromName($entryPath);
            $zip->close();
            $entryCache[$cacheKey] = is_string($content) ? $content : null;
            return $entryCache[$cacheKey];
        }
    }

    $cacheDir = hrMzdyEnsureXlsxCache($filePath);
    if ($cacheDir === null) {
        $entryCache[$cacheKey] = null;
        return null;
    }

    $path = $cacheDir . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $entryPath);
    $entryCache[$cacheKey] = is_file($path) ? file_get_contents($path) : null;
    return $entryCache[$cacheKey];
}

function hrMzdyEnsureXlsxCache(string $filePath): ?string
{
    static $cache = [];
    if (isset($cache[$filePath])) {
        return $cache[$filePath];
    }

    $root = __DIR__ . '/../_kandidati/codex/hr_xlsx_cache';
    $key = md5($filePath . '|' . (string)filemtime($filePath) . '|' . (string)filesize($filePath));
    $dir = $root . '/' . $key;
    $marker = $dir . '/.ready';
    if (is_file($marker)) {
        $cache[$filePath] = $dir;
        return $cache[$filePath];
    }

    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $cache[$filePath] = null;
        return null;
    }

    $escapedFile = str_replace("'", "''", $filePath);
    $escapedDir = str_replace("'", "''", $dir);
    $script = <<<PS
\$ErrorActionPreference='Stop'
\$ProgressPreference='SilentlyContinue'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::ExtractToDirectory('$escapedFile', '$escapedDir')
PS;
    $encodedScript = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    shell_exec('powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . $encodedScript);

    if (!is_file($dir . '/xl/workbook.xml')) {
        $cache[$filePath] = null;
        return null;
    }

    file_put_contents($marker, date('c'));
    $cache[$filePath] = $dir;
    return $cache[$filePath];
}

// inicializace/plnime_hr_mzdy.php * Verze: V3 * Aktualizace: 03.06.2026
