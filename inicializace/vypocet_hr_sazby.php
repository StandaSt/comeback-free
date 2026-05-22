<?php
// inicializace/vypocet_hr_sazby.php * Verze: V1 * Aktualizace: 22.05.2026
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../config/secrets.php';

define('HR_MZDY_HELPERS_ONLY', true);
require_once __DIR__ . '/plnime_hr_mzdy.php';

if (isset($_POST['run_hr_sazby']) && (string)$_POST['run_hr_sazby'] === '1') {
    hrSazbyRun(true);
} else {
    hrSazbyRun(false);
}

function hrSazbyH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hrSazbyRun(bool $write): void
{
    set_time_limit(0);

    $db = db();
    $db->set_charset('utf8mb4');

    $rows = hrSazbyBuildSourceRows($db);
    $summary = hrSazbySummary($rows);
    $intervals = hrSazbyBuildIntervals($rows);
    $result = [];

    if ($write) {
        $result = hrSazbyWrite($db, $intervals);
    }

    hrSazbyRender($summary, $intervals, $result, $write);
}

/**
 * @return array<int, array<string, mixed>>
 */
function hrSazbyBuildSourceRows(mysqli $db): array
{
    $entities = hrSazbyMissingEntities($db);
    if ($entities === []) {
        return [];
    }

    $rows = hrSazbyRowsFromMzdyMesic($db, $entities);
    foreach (hrSazbyRowsFromCurrentSheet($db, $entities) as $row) {
        $rows[] = $row;
    }

    usort($rows, static function (array $a, array $b): int {
        $cmp = strcmp(hrSazbyEntityKey($a), hrSazbyEntityKey($b));
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = strcmp((string)$a['platnost_od'], (string)$b['platnost_od']);
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp((string)$a['zdroj_detail'], (string)$b['zdroj_detail']);
    });

    return $rows;
}

/**
 * @return array<string, array<string, mixed>>
 */
function hrSazbyMissingEntities(mysqli $db): array
{
    $sql = '
        SELECT DISTINCT m.id_user, m.import_jmeno
        FROM hr_mzdy_mesic m
        WHERE (
            m.id_user IS NOT NULL
            AND NOT EXISTS (
                SELECT 1
                FROM hr_sazby s
                WHERE s.id_user = m.id_user
            )
        ) OR (
            m.id_user IS NULL
            AND m.import_jmeno IS NOT NULL
            AND m.import_jmeno <> ""
            AND NOT EXISTS (
                SELECT 1
                FROM hr_sazby s
                WHERE s.id_user IS NULL
                  AND s.import_jmeno = m.import_jmeno
            )
        )
        ORDER BY m.id_user, m.import_jmeno
    ';
    $res = $db->query($sql);
    if (!$res instanceof mysqli_result) {
        return [];
    }

    $entities = [];
    while ($row = $res->fetch_assoc()) {
        $entity = [
            'id_user' => $row['id_user'] !== null ? (int)$row['id_user'] : null,
            'import_jmeno' => trim((string)($row['import_jmeno'] ?? '')),
        ];
        $entities[hrSazbyEntityKey($entity)] = $entity;
    }
    $res->free();

    return $entities;
}

/**
 * @param array<string, array<string, mixed>> $entities
 * @return array<int, array<string, mixed>>
 */
function hrSazbyRowsFromMzdyMesic(mysqli $db, array $entities): array
{
    if ($entities === []) {
        return [];
    }

    $ids = [];
    $names = [];
    foreach ($entities as $entity) {
        if (($entity['id_user'] ?? null) !== null) {
            $ids[] = (int)$entity['id_user'];
        } else {
            $name = trim((string)($entity['import_jmeno'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
    }

    $where = [];
    if ($ids !== []) {
        $where[] = 'id_user IN (' . implode(',', array_values(array_unique($ids))) . ')';
    }
    if ($names !== []) {
        $escaped = array_map(static fn(string $v): string => "'" . $db->real_escape_string($v) . "'", array_values(array_unique($names)));
        $where[] = '(id_user IS NULL AND import_jmeno IN (' . implode(',', $escaped) . '))';
    }
    if ($where === []) {
        return [];
    }

    $sql = '
        SELECT
            id_user, import_jmeno, datum_od AS platnost_od, mzda_typ,
            hodinova_sazba, mesicni_fix, je_manager_col,
            naklad_col_hod, naklad_col_den, import_list
        FROM hr_mzdy_mesic
        WHERE ' . implode(' OR ', $where) . '
        ORDER BY id_user, import_jmeno, datum_od, id_hr_mzda_mesic
    ';
    $res = $db->query($sql);
    if (!$res instanceof mysqli_result) {
        return [];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = hrSazbyNormalizeSourceRow([
            'id_user' => $row['id_user'] !== null ? (int)$row['id_user'] : null,
            'import_jmeno' => trim((string)($row['import_jmeno'] ?? '')),
            'platnost_od' => (string)$row['platnost_od'],
            'mzda_typ' => (string)$row['mzda_typ'],
            'hodinova_sazba' => $row['hodinova_sazba'],
            'mesicni_fix' => $row['mesicni_fix'],
            'je_manager_col' => (int)$row['je_manager_col'],
            'naklad_col_hod' => $row['naklad_col_hod'],
            'naklad_col_den' => $row['naklad_col_den'],
            'zdroj_detail' => (string)$row['import_list'],
        ]);
    }
    $res->free();

    return $rows;
}

/**
 * @param array<string, array<string, mixed>> $entities
 * @return array<int, array<string, mixed>>
 */
function hrSazbyRowsFromCurrentSheet(mysqli $db, array $entities): array
{
    if ($entities === []) {
        return [];
    }

    $targets = hrMzdyWorkbookSheetTargets(HR_MZDY_XLSX);
    if (!isset($targets['Mzdy'])) {
        return [];
    }

    $sheetRows = hrMzdySheetRows(HR_MZDY_XLSX, (string)$targets['Mzdy']);
    if ($sheetRows === []) {
        return [];
    }

    $period = hrMzdySheetPeriod($sheetRows[1] ?? []);
    if ($period === null) {
        return [];
    }

    $format = hrMzdySheetFormat($sheetRows[4] ?? []);
    $users = hrMzdyUserMap($db);
    $rows = [];

    foreach ($sheetRows as $rowNo => $row) {
        if ($rowNo < 5) {
            continue;
        }

        $importName = trim((string)($row['B'] ?? ''));
        if (!hrMzdyIsPersonName($importName)) {
            continue;
        }

        $match = hrMzdyResolveUser($importName, $users, hrMzdyBranchIds($row));
        $idUser = (int)$match['id_user'];
        $entity = [
            'id_user' => $idUser > 0 ? $idUser : null,
            'import_jmeno' => $idUser > 0 ? '' : $importName,
        ];
        if (!isset($entities[hrSazbyEntityKey($entity)])) {
            continue;
        }

        $values = hrMzdyRowValues($row, $format);
        $rows[] = hrSazbyNormalizeSourceRow([
            'id_user' => $idUser > 0 ? $idUser : null,
            'import_jmeno' => $idUser > 0 ? '' : $importName,
            'platnost_od' => $period['datum_od'],
            'mzda_typ' => $values['mzda_typ'],
            'hodinova_sazba' => $values['hodinova_sazba'],
            'mesicni_fix' => $values['mesicni_fix'],
            'je_manager_col' => $values['je_manager_col'],
            'naklad_col_hod' => $values['naklad_col_hod'],
            'naklad_col_den' => $values['naklad_col_den'],
            'zdroj_detail' => 'Mzdy',
        ]);
    }

    return $rows;
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function hrSazbyNormalizeSourceRow(array $row): array
{
    return [
        'id_user' => ($row['id_user'] ?? null) !== null ? (int)$row['id_user'] : null,
        'import_jmeno' => trim((string)($row['import_jmeno'] ?? '')),
        'platnost_od' => (string)$row['platnost_od'],
        'id_mzda_typ' => hrSazbyMzdaTypId((string)$row['mzda_typ']),
        'hodinova_sazba' => hrSazbyDecimalOrNull($row['hodinova_sazba'] ?? null),
        'mesicni_fix' => hrSazbyDecimalOrNull($row['mesicni_fix'] ?? null),
        'je_manager_col' => (int)($row['je_manager_col'] ?? 0),
        'naklad_col_hod' => hrSazbyDecimalOrNull($row['naklad_col_hod'] ?? null),
        'naklad_col_den' => hrSazbyDecimalOrNull($row['naklad_col_den'] ?? null),
        'zdroj_detail' => (string)($row['zdroj_detail'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $row
 */
function hrSazbyEntityKey(array $row): string
{
    if (($row['id_user'] ?? null) !== null && (int)$row['id_user'] > 0) {
        return 'u:' . (string)(int)$row['id_user'];
    }

    return 'n:' . mb_strtolower(trim((string)($row['import_jmeno'] ?? '')), 'UTF-8');
}

function hrSazbyMzdaTypId(string $type): int
{
    return match ($type) {
        'fix' => 2,
        'kombinovana' => 3,
        'bez_mzdy' => 4,
        default => 1,
    };
}

function hrSazbyDecimalOrNull(mixed $value): ?float
{
    if ($value === null) {
        return null;
    }
    $text = trim(str_replace(',', '.', (string)$value));
    if ($text === '') {
        return null;
    }
    return is_numeric($text) ? round((float)$text, 2) : null;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function hrSazbySummary(array $rows): array
{
    $entities = [];
    foreach ($rows as $row) {
        $entities[hrSazbyEntityKey($row)] = true;
    }

    return [
        'source_rows' => count($rows),
        'users' => count($entities),
    ];
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function hrSazbyBuildIntervals(array $rows): array
{
    $intervals = [];
    $lastByEntity = [];
    $seenSameMonth = [];

    foreach ($rows as $row) {
        $entityKey = hrSazbyEntityKey($row);
        $monthKey = $entityKey . '|' . (string)$row['platnost_od'];
        $state = hrSazbyStateKey($row);
        if (isset($seenSameMonth[$monthKey])) {
            if ($seenSameMonth[$monthKey] === $state) {
                continue;
            }
            $row['poznamka'] = 'Ve stejném měsíci nalezena rozdílná sazba; vložena první sazba měsíce.';
            continue;
        }
        $seenSameMonth[$monthKey] = $state;

        if (!isset($lastByEntity[$entityKey])) {
            $intervals[] = hrSazbyIntervalFromRow($row);
            $lastByEntity[$entityKey] = count($intervals) - 1;
            continue;
        }

        $lastIndex = $lastByEntity[$entityKey];
        if (hrSazbyStateKey($intervals[$lastIndex]) === $state) {
            continue;
        }

        $intervals[$lastIndex]['platnost_do'] = hrSazbyPreviousDay((string)$row['platnost_od']);
        $intervals[] = hrSazbyIntervalFromRow($row);
        $lastByEntity[$entityKey] = count($intervals) - 1;
    }

    return $intervals;
}

/**
 * @param array<string, mixed> $row
 */
function hrSazbyStateKey(array $row): string
{
    return implode('|', [
        (string)$row['id_mzda_typ'],
        hrSazbyDecimalKey($row['hodinova_sazba'] ?? null),
        hrSazbyDecimalKey($row['mesicni_fix'] ?? null),
    ]);
}

function hrSazbyDecimalKey(mixed $value): string
{
    return $value === null ? 'NULL' : number_format((float)$value, 2, '.', '');
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function hrSazbyIntervalFromRow(array $row): array
{
    return [
        'id_user' => $row['id_user'],
        'import_jmeno' => (string)($row['import_jmeno'] ?? ''),
        'platnost_od' => (string)$row['platnost_od'],
        'platnost_do' => null,
        'id_mzda_typ' => (int)$row['id_mzda_typ'],
        'hodinova_sazba' => $row['hodinova_sazba'],
        'mesicni_fix' => $row['mesicni_fix'],
        'je_manager_col' => (int)$row['je_manager_col'],
        'naklad_col_hod' => $row['naklad_col_hod'],
        'naklad_col_den' => $row['naklad_col_den'],
        'zdroj' => 'HR 2024.xlsx',
        'poznamka' => ((string)$row['zdroj_detail'] === 'Mzdy') ? 'Dopočteno z rozpracovaného listu Mzdy.' : null,
    ];
}

function hrSazbyPreviousDay(string $date): string
{
    return (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
}

/**
 * @param array<int, array<string, mixed>> $intervals
 * @return array<string, int|string>
 */
function hrSazbyWrite(mysqli $db, array $intervals): array
{
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('
            INSERT INTO hr_sazby (
                id_user, import_jmeno, platnost_od, platnost_do, id_mzda_typ,
                hodinova_sazba, mesicni_fix, je_manager_col,
                naklad_col_hod, naklad_col_den, zdroj, poznamka
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?
            )
        ');
        if (!$stmt instanceof mysqli_stmt) {
            throw new RuntimeException('Nepodařilo se připravit INSERT hr_sazby.');
        }

        $inserted = 0;
        foreach ($intervals as $row) {
            $idUser = $row['id_user'];
            $importJmeno = (string)$row['import_jmeno'];
            if ($idUser !== null && (int)$idUser > 0) {
                $importJmeno = '';
            }
            $platnostOd = (string)$row['platnost_od'];
            $platnostDo = $row['platnost_do'];
            $idMzdaTyp = (int)$row['id_mzda_typ'];
            $hodinovaSazba = $row['hodinova_sazba'];
            $mesicniFix = $row['mesicni_fix'];
            $jeManagerCol = (int)$row['je_manager_col'];
            $nakladColHod = $row['naklad_col_hod'];
            $nakladColDen = $row['naklad_col_den'];
            $zdroj = (string)$row['zdroj'];
            $poznamka = $row['poznamka'];

            $stmt->bind_param(
                'isssiddiddss',
                $idUser,
                $importJmeno,
                $platnostOd,
                $platnostDo,
                $idMzdaTyp,
                $hodinovaSazba,
                $mesicniFix,
                $jeManagerCol,
                $nakladColHod,
                $nakladColDen,
                $zdroj,
                $poznamka
            );
            if (!$stmt->execute()) {
                throw new RuntimeException('INSERT hr_sazby selhal: ' . $stmt->error);
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

/**
 * @param array<string, mixed> $summary
 * @param array<int, array<string, mixed>> $intervals
 * @param array<string, int|string> $result
 */
function hrSazbyRender(array $summary, array $intervals, array $result, bool $write): void
{
    $open = 0;
    foreach ($intervals as $row) {
        if ($row['platnost_do'] === null) {
            $open++;
        }
    }
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Výpočet HR sazeb</h2>
      <p class="card_text txt_seda">Script doplní sazby jen pro uživatele, kteří ještě nejsou v hr_sazby.</p>

      <?php if ($write): ?>
        <?php if ((int)($result['error'] ?? 0) === 1): ?>
          <p class="card_text txt_cervena text_tucny">Výpočet selhal. Do DB nebylo nic uloženo.</p>
          <p class="card_text txt_seda"><?= hrSazbyH((string)($result['error_message'] ?? 'Neznámá chyba zápisu.')) ?></p>
        <?php else: ?>
          <p class="card_text txt_zelena text_tucny">Výpočet dokončen. Vloženo sazeb: <?= hrSazbyH(number_format((int)($result['inserted'] ?? 0), 0, ',', ' ')) ?></p>
        <?php endif; ?>
      <?php endif; ?>

      <table class="table ram_normal bg_bila radek_1_35 sirka100">
        <tbody>
          <tr><td>Uživatelů ke zpracování</td><td class="txt_r"><strong><?= hrSazbyH(number_format((int)$summary['users'], 0, ',', ' ')) ?></strong></td></tr>
          <tr><td>Zdrojových řádků</td><td class="txt_r"><strong><?= hrSazbyH(number_format((int)$summary['source_rows'], 0, ',', ' ')) ?></strong></td></tr>
          <tr><td>Nových intervalů sazeb</td><td class="txt_r"><strong><?= hrSazbyH(number_format(count($intervals), 0, ',', ' ')) ?></strong></td></tr>
          <tr><td>Aktuálně otevřených sazeb</td><td class="txt_r"><strong><?= hrSazbyH(number_format($open, 0, ',', ' ')) ?></strong></td></tr>
        </tbody>
      </table>

      <div class="card_actions gap_8 displ_flex odstup_horni_10">
        <form method="post" action="<?= hrSazbyH(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1" data-cb-loader-text="Počítám HR sazby">
          <input type="hidden" name="run_hr_sazby" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-loader-text="Počítám HR sazby">Spustit výpočet</button>
        </form>
        <form method="post" action="<?= hrSazbyH(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
          <input type="hidden" name="back_admin_init" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex">Zpět</button>
        </form>
      </div>
    </div>
    <?php
}

// inicializace/vypocet_hr_sazby.php * Verze: V1 * Aktualizace: 22.05.2026
?>
