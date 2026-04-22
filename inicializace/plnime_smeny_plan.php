<?php
// inicializace/plnime_smeny_plan.php * Verze: V16 * Aktualizace: 21.04.2026

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';

const GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const MIN_DATE = '2020-10-26';
const REQUEST_TIMEOUT_SEC = 60;
const SLEEP_BETWEEN_WEEKS_US = 500000;
const LOG_DIR = __DIR__ . '/../log/smeny';
const SKIPWEEK_FILE = LOG_DIR . '/skipweek.txt';

function out(string $text): void
{
    // Tichy beh, zapis jen do TXT logu.
}

function nowSql(): string
{
    return (new DateTimeImmutable())->format('Y-m-d H:i:s');
}

function formatDuration(float $seconds): string
{
    return number_format($seconds, 2, '.', '') . ' s';
}

function currentWeekMonday(): DateTimeImmutable
{
    return new DateTimeImmutable('monday this week');
}

function normalizeMonday(string $date): DateTimeImmutable
{
    return (new DateTimeImmutable($date))->modify('monday this week');
}

function calculateSkipWeeksByStartDay(string $startDay): int
{
    $currentMonday = currentWeekMonday();
    $weekMonday = normalizeMonday($startDay);
    $seconds = $weekMonday->getTimestamp() - $currentMonday->getTimestamp();

    return (int)round($seconds / 604800);
}

function oldestSkipWeeks(): int
{
    return calculateSkipWeeksByStartDay(MIN_DATE);
}

function startDayBySkipWeeks(int $skipWeeks): string
{
    return currentWeekMonday()->modify(($skipWeeks >= 0 ? '+' : '') . $skipWeeks . ' week')->format('Y-m-d');
}

function ensureLogDir(): void
{
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0777, true);
    }
}

function currentLogFilePath(): string
{
    static $path = null;

    if (is_string($path) && $path !== '') {
        return $path;
    }

    ensureLogDir();
    $path = LOG_DIR . '/historie_' . (new DateTimeImmutable())->format('Y_m_d_H_i') . '.txt';

    return $path;
}

function appendTxtLog(string $text): void
{
    file_put_contents(currentLogFilePath(), trim($text) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function skipweekBrakeExists(): bool
{
    return is_file(SKIPWEEK_FILE);
}

function smenyPlanSelectedBranchId(): int
{
    return (int)($_SESSION['cb_smeny_plan_id_pob'] ?? 0);
}

function smenyPlanSetSelectedBranchId(int $idPob): void
{
    if ($idPob > 0) {
        $_SESSION['cb_smeny_plan_id_pob'] = $idPob;
    } else {
        unset($_SESSION['cb_smeny_plan_id_pob']);
    }
}

function smenyPlanFindBranchById(array $branches, int $idPob): ?array
{
    foreach ($branches as $branch) {
        if ((int)($branch['id_pob'] ?? 0) === $idPob) {
            return $branch;
        }
    }

    return null;
}

function smenyPlanBranchLastImportLabel(array $branch): string
{
    $lastImportDay = trim((string)($branch['last_import_day'] ?? ''));

    if ($lastImportDay === '') {
        return 'bez importu';
    }

    return 'do ' . $lastImportDay;
}

function getBranches(string $token = ''): array
{
    $db = db();
    $sql = "
        SELECT
            p.id_pob,
            p.nazev,
            p.start_smeny,
            hist.last_import_day,
            hist.last_import_skip_weeks
        FROM pobocka p
        LEFT JOIN (
            SELECT sa.id_pob, sa.start_day AS last_import_day, sa.skip_weeks AS last_import_skip_weeks
            FROM smeny_aktualizace sa
            INNER JOIN (
                SELECT id_pob, MAX(start_day) AS max_start_day
                FROM smeny_aktualizace
                WHERE stav = 1
                GROUP BY id_pob
            ) last_ok
                ON last_ok.id_pob = sa.id_pob
               AND last_ok.max_start_day = sa.start_day
            WHERE sa.stav = 1
        ) hist ON hist.id_pob = p.id_pob
        WHERE p.aktivni = 1
          AND p.start_smeny IS NOT NULL
          AND p.start_smeny <> ''
          AND p.start_smeny <> '0000-00-00'
        ORDER BY p.start_smeny ASC, p.id_pob ASC
    ";
    $res = $db->query($sql);

    if (!$res instanceof mysqli_result) {
        throw new RuntimeException('Nepodarilo se nacist pobocky se start_smeny: ' . $db->error);
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }
    $res->free();

    return $rows;
}

function branchStartDay(array $branch): string
{
    $startSmeny = trim((string)($branch['start_smeny'] ?? ''));
    if ($startSmeny === '') {
        throw new RuntimeException('Pobocka nema start_smeny.');
    }

    return normalizeMonday($startSmeny)->format('Y-m-d');
}

function branchSkipWeeks(array $branch): int
{
    $startSmeny = trim((string)($branch['start_smeny'] ?? ''));
    if ($startSmeny === '') {
        throw new RuntimeException('Pobocka nema start_smeny.');
    }

    return calculateSkipWeeksByStartDay($startSmeny);
}

function getWeekRaw(string $token, int $branchId, int $skipWeeks): ?array
{
    $query = <<<'GQL'
query($branchId:Int!, $skipWeeks:Int!){
  branchGetShiftWeek(branchId:$branchId, skipWeeks:$skipWeeks){
    id
    startDay
    shiftDays{
      day
      shiftRoles{
        id
        type{ id }
        shiftHours{
          id
          startHour
          employee{ id }
        }
      }
    }
  }
}
GQL;

    $response = cb_smeny_graphql(
        GQL_URL,
        $query,
        [
            'branchId' => $branchId,
            'skipWeeks' => $skipWeeks,
        ],
        $token,
        REQUEST_TIMEOUT_SEC
    );

    $week = $response['branchGetShiftWeek'] ?? null;

    return is_array($week) ? $week : null;
}

function dayOffset(string $day): int
{
    return match ($day) {
        'monday' => 0,
        'tuesday' => 1,
        'wednesday' => 2,
        'thursday' => 3,
        'friday' => 4,
        'saturday' => 5,
        'sunday' => 6,
        default => throw new RuntimeException('Neznamy den: ' . $day),
    };
}

function makeRowFromTimestamps(int $idPob, array $block): array
{
    $start = (new DateTimeImmutable())->setTimestamp((int)$block['start_ts']);
    $end = (new DateTimeImmutable())->setTimestamp((int)$block['end_ts']);

    return [
        'datum' => $start->format('Y-m-d'),
        'id_pob' => $idPob,
        'id_user' => (int)$block['user'],
        'id_slot' => (int)$block['slot'],
        'cas_od' => $start->format('H:i:s'),
        'cas_do' => $end->format('H:i:s'),
    ];
}

function buildBlocks(array $week, int $idPob): array
{
    $startDay = (string)($week['startDay'] ?? '');
    $shiftDays = $week['shiftDays'] ?? [];

    if ($startDay === '' || !is_array($shiftDays)) {
        return [];
    }

    $timeline = [];

    foreach ($shiftDays as $day) {
        if (!is_array($day) || !isset($day['day']) || !isset($day['shiftRoles']) || !is_array($day['shiftRoles'])) {
            continue;
        }

        $dayDate = (new DateTimeImmutable($startDay))
            ->modify('+' . dayOffset((string)$day['day']) . ' day')
            ->format('Y-m-d');

        foreach ($day['shiftRoles'] as $role) {
            if (!is_array($role)) {
                continue;
            }

            $slotId = (int)($role['type']['id'] ?? 0);
            $shiftHours = $role['shiftHours'] ?? [];

            if ($slotId <= 0 || !is_array($shiftHours)) {
                continue;
            }

            foreach ($shiftHours as $hour) {
                if (!is_array($hour)) {
                    continue;
                }

                $userId = (int)($hour['employee']['id'] ?? 0);
                $startHour = (int)($hour['startHour'] ?? -1);

                if ($userId <= 0 || $startHour < 0 || $startHour > 23) {
                    continue;
                }

                $actualDayDate = $startHour < 6
                    ? (new DateTimeImmutable($dayDate))->modify('+1 day')->format('Y-m-d')
                    : $dayDate;

                $startTs = strtotime(sprintf('%s %02d:00:00', $actualDayDate, $startHour));
                if ($startTs === false) {
                    continue;
                }

                $groupKey = $userId . '|' . $slotId;
                $timeline[$groupKey][] = [
                    'user' => $userId,
                    'slot' => $slotId,
                    'start_ts' => $startTs,
                    'end_ts' => $startTs + 3600,
                ];
            }
        }
    }

    $rows = [];

    foreach ($timeline as $entries) {
        usort($entries, static fn(array $a, array $b): int => $a['start_ts'] <=> $b['start_ts']);

        $current = null;

        foreach ($entries as $entry) {
            if ($current === null) {
                $current = $entry;
                continue;
            }

            if ($entry['start_ts'] === $current['end_ts']) {
                $current['end_ts'] = $entry['end_ts'];
                continue;
            }

            $rows[] = makeRowFromTimestamps($idPob, $current);
            $current = $entry;
        }

        if ($current !== null) {
            $rows[] = makeRowFromTimestamps($idPob, $current);
        }
    }

    usort(
        $rows,
        static fn(array $a, array $b): int =>
            [$a['datum'], $a['cas_od'], $a['id_user'], $a['id_slot']]
            <=>
            [$b['datum'], $b['cas_od'], $b['id_user'], $b['id_slot']]
    );

    return $rows;
}

function countHours(array $rows): int
{
    $hours = 0;

    foreach ($rows as $row) {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['datum'] . ' ' . $row['cas_od']);
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['datum'] . ' ' . $row['cas_do']);

        if (!$start || !$end) {
            continue;
        }

        $startTs = $start->getTimestamp();
        $endTs = $end->getTimestamp();

        if ($endTs <= $startTs) {
            $endTs += 86400;
        }

        $hours += (int)(($endTs - $startTs) / 3600);
    }

    return $hours;
}

function deletePlanRows(string $startDay, int $idPob): void
{
    $db = db();
    $sql = "DELETE FROM smeny_plan WHERE start_day='" . $db->real_escape_string($startDay) . "' AND id_pob=" . (int)$idPob;
    $db->query($sql);
}

function saveBlocks(string $startDay, int $idPob, array $rows): int
{
    deletePlanRows($startDay, $idPob);

    if ($rows === []) {
        return 0;
    }

    $db = db();
    $stmt = $db->prepare(
        'INSERT INTO smeny_plan (start_day, datum, id_pob, id_user, id_slot, cas_od, cas_do, zdroj) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );

    if (!$stmt) {
        throw new RuntimeException('Prepare INSERT smeny_plan selhal.');
    }

    $saved = 0;

    foreach ($rows as $row) {
        $stmt->bind_param(
            'ssiiiss',
            $startDay,
            $row['datum'],
            $row['id_pob'],
            $row['id_user'],
            $row['id_slot'],
            $row['cas_od'],
            $row['cas_do']
        );

        if (!$stmt->execute()) {
            $msg = $stmt->error;
            $stmt->close();
            throw new RuntimeException('INSERT smeny_plan selhal: ' . $msg);
        }

        $saved++;
    }

    $stmt->close();

    return $saved;
}

function saveStateRow(array $state): void
{
    $db = db();

    $stmt = $db->prepare(
        'INSERT INTO smeny_aktualizace (start_day, skip_weeks, id_pob, stav, datum_od, started_at, finished_at, posledni_ok, pocet_bloku, pocet_hodin, chyba_text)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            skip_weeks = VALUES(skip_weeks),
            stav = VALUES(stav),
            datum_od = VALUES(datum_od),
            started_at = VALUES(started_at),
            finished_at = VALUES(finished_at),
            posledni_ok = VALUES(posledni_ok),
            pocet_bloku = VALUES(pocet_bloku),
            pocet_hodin = VALUES(pocet_hodin),
            chyba_text = VALUES(chyba_text)'
    );

    if (!$stmt) {
        throw new RuntimeException('Prepare smeny_aktualizace selhal.');
    }

    $stmt->bind_param(
        'siiissssiis',
        $state['start_day'],
        $state['skip_weeks'],
        $state['id_pob'],
        $state['stav'],
        $state['datum_od'],
        $state['started_at'],
        $state['finished_at'],
        $state['posledni_ok'],
        $state['pocet_bloku'],
        $state['pocet_hodin'],
        $state['chyba_text']
    );

    if (!$stmt->execute()) {
        $msg = $stmt->error;
        $stmt->close();
        throw new RuntimeException('UPSERT smeny_aktualizace selhal: ' . $msg);
    }

    $stmt->close();
}

function markBranchAsOk(int $skipWeeks, int $idPob, string $startDay, int $blocks, int $hours, ?string $note): void
{
    $now = nowSql();

    saveStateRow([
        'start_day' => $startDay,
        'skip_weeks' => $skipWeeks,
        'id_pob' => $idPob,
        'stav' => 1,
        'datum_od' => $startDay,
        'started_at' => $now,
        'finished_at' => $now,
        'posledni_ok' => $now,
        'pocet_bloku' => $blocks,
        'pocet_hodin' => $hours,
        'chyba_text' => $note,
    ]);
}

function markBranchAsError(int $skipWeeks, int $idPob, string $startDay, string $message): void
{
    $now = nowSql();

    saveStateRow([
        'start_day' => $startDay,
        'skip_weeks' => $skipWeeks,
        'id_pob' => $idPob,
        'stav' => 2,
        'datum_od' => $startDay,
        'started_at' => $now,
        'finished_at' => $now,
        'posledni_ok' => null,
        'pocet_bloku' => 0,
        'pocet_hodin' => 0,
        'chyba_text' => $message,
    ]);
}

function printBranchProgress(int $skipWeeks, int $idPob, string $startDay, int $blocks, int $hours, string $status, ?string $note): void
{
    $line = sprintf(
        'skipWeeks=%d | id_pob=%d | startDay=%s | pocet_bloku=%d | pocet_hodin=%d | stav=%s',
        $skipWeeks,
        $idPob,
        $startDay,
        $blocks,
        $hours,
        $status
    );

    if ($note !== null && $note !== '') {
        $line .= ' | poznamka=' . $note;
    }

    appendTxtLog($line);
    out($line);
}

function saveAcceptedShifts(array $week, int $idPob): void
{
    if (!isset($week['id'], $week['startDay'], $week['shiftDays']) || !is_array($week['shiftDays'])) {
        return;
    }

    $smenyWeekId = (int)$week['id'];
    $startDayApi = (string)$week['startDay'];
    $rows = [];

    foreach ($week['shiftDays'] as $shiftDay) {
        if (!is_array($shiftDay)) {
            continue;
        }

        $smenyDayId = (int)($shiftDay['id'] ?? 0);
        $den = (string)($shiftDay['day'] ?? '');
        $datum = (new DateTimeImmutable($startDayApi))->modify('+' . dayOffset($den) . ' day')->format('Y-m-d');

        foreach (($shiftDay['shiftRoles'] ?? []) as $shiftRole) {
            if (!is_array($shiftRole)) {
                continue;
            }

            $smenyRoleId = (int)($shiftRole['id'] ?? 0);
            $idSlot = (int)($shiftRole['type']['id'] ?? 0);

            foreach (($shiftRole['shiftHours'] ?? []) as $shiftHour) {
                if (!is_array($shiftHour) || !isset($shiftHour['employee']['id'])) {
                    continue;
                }

                $rows[] = [
                    'smeny_week_id' => $smenyWeekId,
                    'smeny_day_id' => $smenyDayId,
                    'smeny_role_id' => $smenyRoleId,
                    'shift_hour_id' => (int)($shiftHour['id'] ?? 0),
                    'datum' => $datum,
                    'den' => $den,
                    'id_pob' => $idPob,
                    'id_slot' => $idSlot,
                    'start_hour' => (int)($shiftHour['startHour'] ?? 0),
                    'confirmed' => isset($shiftHour['confirmed']) && $shiftHour['confirmed'] ? 1 : 0,
                    'is_first' => isset($shiftHour['isFirst']) && $shiftHour['isFirst'] ? 1 : 0,
                    'id_user' => (int)$shiftHour['employee']['id'],
                ];
            }
        }
    }

    if (count($rows) === 0) {
        return;
    }

    $db = db();
    $values = [];

    foreach ($rows as $row) {
        $values[] = '(' .
            (int)$row['smeny_week_id'] . ',' .
            (int)$row['smeny_day_id'] . ',' .
            (int)$row['smeny_role_id'] . ',' .
            (int)$row['shift_hour_id'] . ", '" .
            $db->real_escape_string($row['datum']) . "', '" .
            $db->real_escape_string($row['den']) . "'," .
            (int)$row['id_pob'] . ',' .
            (int)$row['id_slot'] . ',' .
            (int)$row['start_hour'] . ',' .
            (int)$row['confirmed'] . ',' .
            (int)$row['is_first'] . ',' .
            (int)$row['id_user'] .
            ')';
    }

    $sql = 'INSERT INTO smeny_akceptovane (
        smeny_week_id, smeny_day_id, smeny_role_id, shift_hour_id,
        datum, den, id_pob, id_slot, start_hour, confirmed, is_first, id_user
    ) VALUES ' . implode(",\n", $values);
    $db->query($sql);
}

function processBranch(string $token, int $skipWeeks, int $idPob, string $nazevPob = ''): array
{
    $defaultStartDay = startDayBySkipWeeks($skipWeeks);

    try {
        $week = getWeekRaw($token, $idPob, $skipWeeks);
        if ($week === null) {
            deletePlanRows($defaultStartDay, $idPob);
            markBranchAsOk($skipWeeks, $idPob, $defaultStartDay, 0, 0, 'API vratilo prazdny tyden');

            return [
                'start_day' => $defaultStartDay,
                'blocks' => 0,
                'hours' => 0,
                'status' => 'OK',
                'note' => 'API vratilo prazdny tyden',
            ];
        }

        $startDay = normalizeMonday((string)($week['startDay'] ?? $defaultStartDay))->format('Y-m-d');
        $rows = buildBlocks($week, $idPob);
        $blocks = saveBlocks($startDay, $idPob, $rows);
        $hours = countHours($rows);
        saveAcceptedShifts($week, $idPob);
        markBranchAsOk($skipWeeks, $idPob, $startDay, $blocks, $hours, null);

        return [
            'start_day' => $startDay,
            'blocks' => $blocks,
            'hours' => $hours,
            'status' => 'OK',
            'note' => null,
        ];
    } catch (Throwable $e) {
        $message = mb_substr($e->getMessage(), 0, 1000, 'UTF-8');
        markBranchAsError($skipWeeks, $idPob, $defaultStartDay, $message);

        return [
            'start_day' => $defaultStartDay,
            'blocks' => 0,
            'hours' => 0,
            'status' => 'CHYBA',
            'note' => $message,
        ];
    }
}

function renderSmenyPlanScreen(array $info, string $mode = 'pick'): void
{
    $branches = $info['branches_list'] ?? [];
    $selectedBranch = $info['selected_branch'] ?? null;
    $selectedBranchId = (int)($info['selected_branch_id'] ?? 0);
    $title = 'Inicializace směn plán';
    $headline = match ($mode) {
        'confirm' => 'Vybraná pobočka je připravená ke spuštění.',
        'run' => 'Import probíhá nebo právě doběhl.',
        default => 'Vyber pobočku, kterou chceš importovat.',
    };
    $message = (string)($info['message'] ?? '');
    $selectedStartDay = (string)($info['start_day'] ?? '');
    $selectedSkipWeeks = (int)($info['skip_weeks'] ?? 0);
    $brakeExists = !empty($info['brake_exists']);
    $branchesCount = count($branches);
    $blocks = (int)($info['blocks'] ?? 0);
    $hours = (int)($info['hours'] ?? 0);
    $status = strtoupper((string)($info['status'] ?? ''));
    $messageClass = $mode === 'run'
        ? ($status === 'OK' ? 'txt_zelena' : 'txt_cervena')
        : 'txt_seda';
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0"><?= h($title) ?></h2>
      <p class="card_text txt_seda"><?= h($headline) ?></p>
      <p class="card_text txt_seda">Směny se počítají po pobočkách podle start_smeny v tabulce pobocka.</p>
      <p class="card_text txt_seda">Počet poboček: <?= h((string)$branchesCount) ?>.</p>
      <p class="card_text txt_seda">Nouzová brzda skipweek.txt: <?= $brakeExists ? 'soubor existuje' : 'soubor chybí' ?>.</p>
      <?php if ($selectedBranchId > 0 && is_array($selectedBranch)): ?>
        <?php $selectedImportLabel = smenyPlanBranchLastImportLabel($selectedBranch); ?>
        <p class="card_text txt_seda">Pobočka: <?= h((string)($selectedBranch['nazev'] ?? '')) ?> (<?= h((string)$selectedBranchId) ?>)</p>
        <p class="card_text txt_seda">Start historie pobočky: <?= h((string)($selectedBranch['start_smeny'] ?? '')) ?> | v DB je <?= h($selectedImportLabel) ?></p>
        <p class="card_text txt_seda">První týden importu: <?= h($selectedStartDay !== '' ? $selectedStartDay : 'neznámý') ?> | skipWeeks=<?= h((string)$selectedSkipWeeks) ?>.</p>
      <?php endif; ?>
      <?php if ($message !== ''): ?>
        <p class="card_text <?= h($messageClass) ?> text_tucny"><?= h($message) ?></p>
      <?php endif; ?>
      <?php if ($mode === 'run'): ?>
        <p class="card_text txt_seda">Poboček: <?= h((string)$branchesCount) ?> | bloků: <?= h((string)$blocks) ?> | hodin: <?= h((string)$hours) ?>.</p>
      <?php endif; ?>

      <?php if ($mode === 'pick'): ?>
        <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
          <input type="hidden" name="run_smeny_plan" value="1">
          <input type="hidden" name="cb_action" value="start" id="cb_action_field">
          <div class="displ_flex gap_8 align_items_center flex_wrap">
            <select name="cb_id_pob" class="card_select ram_sedy txt_seda bg_bila zaobleni_8" style="min-width:260px; height:28px; margin-right:8px;" onchange="var a=document.getElementById('cb_action_field');if(a){a.value='select_branch';}var f=this.form;if(f){var o=this.options[this.selectedIndex];var t=o?String(o.textContent||o.innerText||'').trim():'';var p=t.indexOf(' | ');if(p>=0){t=t.substring(0,p);}f.setAttribute('data-cb-loader-text','Připravuji směny pobočky '+t);if(f.requestSubmit){f.requestSubmit();}else{f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));}}">
              <option value="0">Vyber pobočku</option>
              <?php foreach ($branches as $branch): ?>
                <?php
                    $branchId = (int)($branch['id_pob'] ?? 0);
                    $branchName = trim((string)($branch['nazev'] ?? ''));
                    $label = $branchName !== '' ? $branchName : ('Pobočka ' . $branchId);
                    $label .= ' | ' . smenyPlanBranchLastImportLabel($branch);
                ?>
                <option value="<?= h((string)$branchId) ?>"<?= $branchId === $selectedBranchId ? ' selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Načíst</button>
            <button type="submit" name="back_admin_init" value="1" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex" style="align-self:center;">Konec</button>
          </div>
        </form>
      <?php elseif ($mode === 'confirm'): ?>
        <div class="card_actions gap_8 displ_flex odstup_horni_10">
          <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1" data-cb-loader-text="Probíhá import směn">
            <input type="hidden" name="run_smeny_plan" value="1">
            <input type="hidden" name="cb_action" value="start">
            <input type="hidden" name="cb_id_pob" value="<?= h((string)$selectedBranchId) ?>">
            <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-loader-text="Probíhá import směn">Spustit import</button>
          </form>
          <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
            <input type="hidden" name="cb_action" value="back">
            <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex">Zpět</button>
          </form>
          <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
            <input type="hidden" name="back_admin_init" value="1">
            <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex">Konec</button>
          </form>
        </div>
      <?php else: ?>
        <div class="card_actions gap_8 displ_flex odstup_horni_10">
          <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
            <input type="hidden" name="cb_action" value="back">
            <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 displ_inline_flex">Zpět</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

function run(array $branch): array
{
    set_time_limit(0);

    $totalStart = microtime(true);
    $token = (string)($_SESSION['cb_token'] ?? '');
    $idPob = (int)($branch['id_pob'] ?? 0);
    $nazevPob = trim((string)($branch['nazev'] ?? ''));
    $startSmeny = trim((string)($branch['start_smeny'] ?? ''));

    if ($token === '') {
        return [
            'status' => 'CHYBA',
            'message' => 'Chybí token v session (cb_token).',
            'start_day' => '',
            'skip_weeks' => 0,
            'branches_list' => [],
            'selected_branch' => null,
            'selected_branch_id' => 0,
            'blocks' => 0,
            'hours' => 0,
            'brake_exists' => skipweekBrakeExists(),
        ];
    }

    if ($idPob <= 0 || $startSmeny === '') {
        return [
            'status' => 'CHYBA',
            'message' => 'Neplatná pobočka nebo chybí start_smeny.',
            'start_day' => '',
            'skip_weeks' => 0,
            'branches_list' => [],
            'selected_branch' => $branch,
            'selected_branch_id' => $idPob,
            'blocks' => 0,
            'hours' => 0,
            'brake_exists' => skipweekBrakeExists(),
        ];
    }

    $totalBlocks = 0;
    $totalHours = 0;
    $status = 'OK';
    $stopMessage = '';
    $branchStartDay = branchStartDay($branch);
    $skipWeeks = branchSkipWeeks($branch);
    $weeksSincePause = 0;

    $startLine = sprintf(
        'Start importu směn | %s | pobočka=%d | %s | start_smeny=%s | pondělí=%s | od skipWeeks=%d | do skipWeeks=0',
        nowSql(),
        $idPob,
        $nazevPob !== '' ? $nazevPob : 'bez názvu',
        $startSmeny,
        $branchStartDay,
        $skipWeeks
    );
    appendTxtLog($startLine);
    out($startLine);

    while ($skipWeeks <= 0) {
        if (!skipweekBrakeExists()) {
            $status = 'STOP';
            $stopMessage = 'Import korektně ukončen, protože chybí nouzová brzda skipweek.txt.';
            appendTxtLog($stopMessage);
            break;
        }

        $weekResult = processBranch($token, $skipWeeks, $idPob, $nazevPob);

        printBranchProgress(
            $skipWeeks,
            $idPob,
            (string)$weekResult['start_day'],
            (int)$weekResult['blocks'],
            (int)$weekResult['hours'],
            (string)$weekResult['status'],
            $weekResult['note'] ?? null
        );

        if ((string)$weekResult['status'] !== 'OK') {
            $status = 'CHYBA';
            $stopMessage = 'Pobočka ' . $idPob . ' skončila chybou na skipWeek ' . $skipWeeks . '.';
            appendTxtLog($stopMessage);
            break;
        }

        $totalBlocks += (int)$weekResult['blocks'];
        $totalHours += (int)$weekResult['hours'];

        $skipWeeks++;
        $weeksSincePause++;
        if ($weeksSincePause >= 50) {
            usleep(SLEEP_BETWEEN_WEEKS_US);
            $weeksSincePause = 0;
        }
    }

    if ($stopMessage === '') {
        $stopMessage = 'Import doběhl až do konce historie.';
    }

    $stopMessage .= ' Celkový čas: ' . formatDuration(microtime(true) - $totalStart) . '.';

    return [
        'status' => $status,
        'message' => $stopMessage,
        'start_day' => $branchStartDay,
        'skip_weeks' => branchSkipWeeks($branch),
        'branches_list' => [],
        'selected_branch' => $branch,
        'selected_branch_id' => $idPob,
        'blocks' => $totalBlocks,
        'hours' => $totalHours,
        'brake_exists' => skipweekBrakeExists(),
    ];
}

$token = (string)($_SESSION['cb_token'] ?? '');
$branches = [];
if ($token !== '') {
    $branches = getBranches($token);
}

$selectedBranchId = smenyPlanSelectedBranchId();
$selectedBranch = $selectedBranchId > 0 ? smenyPlanFindBranchById($branches, $selectedBranchId) : null;
$action = (string)($_POST['cb_action'] ?? '');
$inputBranchId = (int)($_POST['cb_id_pob'] ?? 0);

if ($action === 'back') {
    smenyPlanSetSelectedBranchId(0);
    $selectedBranchId = 0;
    $selectedBranch = null;
}

if ($action === 'select_branch') {
    if ($inputBranchId > 0) {
        smenyPlanSetSelectedBranchId($inputBranchId);
        $selectedBranchId = $inputBranchId;
        $selectedBranch = smenyPlanFindBranchById($branches, $selectedBranchId);
    }
}

if ($selectedBranch === null && $selectedBranchId > 0) {
    $selectedBranch = smenyPlanFindBranchById($branches, $selectedBranchId);
    if ($selectedBranch === null) {
        smenyPlanSetSelectedBranchId(0);
        $selectedBranchId = 0;
    }
}

if ($action === 'start' && $selectedBranch !== null) {
    $runInfo = run($selectedBranch);
    $runInfo['branches_list'] = $branches;
    renderSmenyPlanScreen($runInfo, 'run');
    return;
}

if ($selectedBranch !== null && $action !== '') {
    renderSmenyPlanScreen([
        'status' => 'OK',
        'message' => 'Pobočka je připravená ke spuštění importu.',
        'branches_list' => $branches,
        'selected_branch' => $selectedBranch,
        'selected_branch_id' => $selectedBranchId,
        'start_day' => branchStartDay($selectedBranch),
        'skip_weeks' => branchSkipWeeks($selectedBranch),
        'blocks' => 0,
        'hours' => 0,
        'brake_exists' => skipweekBrakeExists(),
    ], 'confirm');
    return;
}

if ($selectedBranch !== null && $action === '') {
    renderSmenyPlanScreen([
        'status' => 'OK',
        'message' => 'Pobočka je připravená ke spuštění importu.',
        'branches_list' => $branches,
        'selected_branch' => $selectedBranch,
        'selected_branch_id' => $selectedBranchId,
        'start_day' => branchStartDay($selectedBranch),
        'skip_weeks' => branchSkipWeeks($selectedBranch),
        'blocks' => 0,
        'hours' => 0,
        'brake_exists' => skipweekBrakeExists(),
    ], 'confirm');
    return;
}

renderSmenyPlanScreen([
    'status' => 'OK',
    'message' => $token === ''
        ? 'Chybí token v session (cb_token).'
        : 'Script stáhne směny plán z API smeny.pizzacomeback.cz do DB.',
    'branches_list' => $branches,
    'selected_branch' => null,
    'selected_branch_id' => 0,
    'start_day' => '',
    'skip_weeks' => 0,
    'blocks' => 0,
    'hours' => 0,
    'brake_exists' => skipweekBrakeExists(),
], 'pick');
