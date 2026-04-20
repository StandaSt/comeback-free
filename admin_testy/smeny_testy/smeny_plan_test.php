<?php
// admin_testy/smeny_plan_test.php * Verze: V1 * Aktualizace: 18.04.2026

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';

const CB_SPT_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const CB_SPT_MIN_DATE = '2020-10-26';
const CB_SPT_REQUEST_TIMEOUT_SEC = 60;
const CB_SPT_TXT_LOG_FILE = __DIR__ . '/../log/historie_smeny_2026.txt';
const CB_SPT_STATE_FILE = __DIR__ . '/../log/smeny_week_state.txt';

function cb_spt_now(): string
{
    return (new DateTimeImmutable())->format('Y-m-d H:i:s');
}

function cb_spt_next_monday_base(): DateTimeImmutable
{
    return new DateTimeImmutable('monday next week');
}

function cb_spt_default_start_day_by_skip(int $skipWeeks): string
{
    return cb_spt_next_monday_base()
        ->modify(($skipWeeks >= 0 ? '+' : '') . $skipWeeks . ' week')
        ->format('Y-m-d');
}

function cb_spt_oldest_skip(): int
{
    $base = cb_spt_next_monday_base();
    $min = new DateTimeImmutable(CB_SPT_MIN_DATE);
    $seconds = $min->getTimestamp() - $base->getTimestamp();

    return (int)round($seconds / 604800);
}

function cb_spt_day_offset(string $day): int
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

function cb_spt_read_state(): array
{
    if (!is_file(CB_SPT_STATE_FILE)) {
        return [];
    }

    $raw = (string)file_get_contents(CB_SPT_STATE_FILE);
    $json = json_decode($raw, true);

    return is_array($json) ? $json : [];
}

function cb_spt_write_state(int $nextSkip): void
{
    $json = json_encode(['next_skip' => $nextSkip], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Nepodarilo se vytvorit state JSON.');
    }

    file_put_contents(CB_SPT_STATE_FILE, $json, LOCK_EX);
}

function cb_spt_get_start_skip_once(): int
{
    if (!is_file(CB_SPT_STATE_FILE)) {
        $startSkip = cb_spt_oldest_skip();
        cb_spt_write_state($startSkip);

        return $startSkip;
    }

    $state = cb_spt_read_state();
    if (!isset($state['next_skip']) || !is_numeric($state['next_skip'])) {
        throw new RuntimeException('State soubor nema platny next_skip.');
    }

    return (int)$state['next_skip'];
}

function cb_spt_require_state_file(): int
{
    if (!is_file(CB_SPT_STATE_FILE)) {
        throw new RuntimeException('State soubor byl za behu smazan. Import je ukoncen.');
    }

    $state = cb_spt_read_state();
    if (!isset($state['next_skip']) || !is_numeric($state['next_skip'])) {
        throw new RuntimeException('State soubor nema platny next_skip.');
    }

    return (int)$state['next_skip'];
}

function cb_spt_read_txt_lines(): array
{
    if (!is_file(CB_SPT_TXT_LOG_FILE)) {
        return [];
    }

    $lines = file(CB_SPT_TXT_LOG_FILE, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    $out = [];

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }

        $line = preg_replace('/^\d+\)\s*/', '', $line);
        if (!is_string($line) || $line === '') {
            continue;
        }

        $out[] = $line;
    }

    return $out;
}

function cb_spt_write_txt_lines(array $lines): void
{
    $numbered = [];
    $i = 1;

    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }

        $numbered[] = $i . ') ' . $line;
        $i++;
    }

    $content = $numbered === [] ? '' : implode(PHP_EOL, $numbered) . PHP_EOL;
    file_put_contents(CB_SPT_TXT_LOG_FILE, $content, LOCK_EX);
}

function cb_spt_prepend_log_lines(array $newLines): void
{
    $current = cb_spt_read_txt_lines();
    $merged = [];

    foreach ($newLines as $line) {
        $line = trim((string)$line);
        if ($line !== '') {
            $merged[] = $line;
        }
    }

    foreach ($current as $line) {
        $merged[] = $line;
    }

    cb_spt_write_txt_lines($merged);
}

function cb_spt_get_branches(string $token): array
{
    $query = 'query{ branchFindAll{ id name } }';
    $response = cb_smeny_graphql(CB_SPT_GQL_URL, $query, [], $token, CB_SPT_REQUEST_TIMEOUT_SEC);
    $branches = $response['branchFindAll'] ?? [];

    return is_array($branches) ? $branches : [];
}

function cb_spt_get_week_raw(string $token, int $branchId, int $skipWeeks): ?array
{
    $query = <<<'GQL'
query($skipWeeks:Int!, $branchId:Int!){
  branchGetShiftWeek(skipWeeks:$skipWeeks, branchId:$branchId){
    id
    startDay
    shiftDays{
      day
      shiftRoles{
        type{
          id
        }
        shiftHours{
          startHour
          employee{
            id
          }
        }
      }
    }
  }
}
GQL;

    $response = cb_smeny_graphql(
        CB_SPT_GQL_URL,
        $query,
        [
            'skipWeeks' => $skipWeeks,
            'branchId' => $branchId,
        ],
        $token,
        CB_SPT_REQUEST_TIMEOUT_SEC
    );

    $week = $response['branchGetShiftWeek'] ?? null;

    return is_array($week) ? $week : null;
}

function cb_spt_make_row_from_timestamps(int $idPob, array $block): array
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

function cb_spt_build_blocks(array $week, int $idPob): array
{
    $startDay = (string)($week['startDay'] ?? '');
    $shiftDays = $week['shiftDays'] ?? [];

    if ($startDay === '' || !is_array($shiftDays)) {
        return [];
    }

    $timeline = [];

    foreach ($shiftDays as $day) {
        if (!is_array($day)) {
            continue;
        }

        $dayName = (string)($day['day'] ?? '');
        $roles = $day['shiftRoles'] ?? [];

        if ($dayName === '' || !is_array($roles)) {
            continue;
        }

        $dayDate = (new DateTimeImmutable($startDay))
            ->modify('+' . cb_spt_day_offset($dayName) . ' day')
            ->format('Y-m-d');

        foreach ($roles as $role) {
            if (!is_array($role)) {
                continue;
            }

            $slotId = (int)($role['type']['id'] ?? 0);
            $hours = $role['shiftHours'] ?? [];

            if ($slotId <= 0 || !is_array($hours)) {
                continue;
            }

            foreach ($hours as $hour) {
                if (!is_array($hour)) {
                    continue;
                }

                $userId = (int)($hour['employee']['id'] ?? 0);
                $startHour = (int)($hour['startHour'] ?? -1);

                if ($userId <= 0 || $startHour < 0 || $startHour > 23) {
                    continue;
                }

                $startTs = strtotime(sprintf('%s %02d:00:00', $dayDate, $startHour));
                if ($startTs === false) {
                    continue;
                }

                $groupKey = $dayDate . '|' . $userId . '|' . $slotId;
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

            $rows[] = cb_spt_make_row_from_timestamps($idPob, $current);
            $current = $entry;
        }

        if ($current !== null) {
            $rows[] = cb_spt_make_row_from_timestamps($idPob, $current);
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

function cb_spt_count_hours(array $rows): int
{
    $hours = 0;

    foreach ($rows as $row) {
        $from = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['datum'] . ' ' . $row['cas_od']);
        $to = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['datum'] . ' ' . $row['cas_do']);

        if (!$from || !$to) {
            continue;
        }

        $fromTs = $from->getTimestamp();
        $toTs = $to->getTimestamp();

        if ($toTs <= $fromTs) {
            $toTs += 86400;
        }

        $hours += (int)(($toTs - $fromTs) / 3600);
    }

    return $hours;
}

function cb_spt_delete_plan_rows(string $startDay, int $idPob): void
{
    $db = db();
    $sql = "DELETE FROM smeny_plan WHERE start_day='" . $db->real_escape_string($startDay) . "' AND id_pob=" . (int)$idPob;
    $db->query($sql);
}

function cb_spt_save_blocks(string $startDay, int $idPob, array $rows): int
{
    cb_spt_delete_plan_rows($startDay, $idPob);

    if ($rows === []) {
        return 0;
    }

    $db = db();
    $stmt = $db->prepare(
        'INSERT INTO smeny_plan (start_day, datum, id_pob, id_user, id_slot, cas_od, cas_do, zdroj) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );

    if ($stmt === false) {
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

function cb_spt_save_state_row(array $state): void
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

    if ($stmt === false) {
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

function cb_spt_mark_ok(int $skipWeeks, int $idPob, string $startDay, int $blocks, int $hours, ?string $note): void
{
    $now = cb_spt_now();

    cb_spt_save_state_row([
        'start_day' => $startDay,
        'skip_weeks' => $skipWeeks,
        'id_pob' => $idPob,
        'stav' => 1,
        'datum_od' => CB_SPT_MIN_DATE,
        'started_at' => $now,
        'finished_at' => $now,
        'posledni_ok' => $now,
        'pocet_bloku' => $blocks,
        'pocet_hodin' => $hours,
        'chyba_text' => $note,
    ]);
}

function cb_spt_mark_error(int $skipWeeks, int $idPob, string $startDay, string $message): void
{
    $now = cb_spt_now();

    cb_spt_save_state_row([
        'start_day' => $startDay,
        'skip_weeks' => $skipWeeks,
        'id_pob' => $idPob,
        'stav' => 2,
        'datum_od' => CB_SPT_MIN_DATE,
        'started_at' => $now,
        'finished_at' => $now,
        'posledni_ok' => null,
        'pocet_bloku' => 0,
        'pocet_hodin' => 0,
        'chyba_text' => $message,
    ]);
}

function cb_spt_process_branch(string $token, int $skipWeeks, int $idPob, string $nazev): array
{
    $defaultStartDay = cb_spt_default_start_day_by_skip($skipWeeks);

    try {
        $week = cb_spt_get_week_raw($token, $idPob, $skipWeeks);

        if ($week === null) {
            cb_spt_delete_plan_rows($defaultStartDay, $idPob);
            cb_spt_mark_ok($skipWeeks, $idPob, $defaultStartDay, 0, 0, 'API vratilo prazdny tyden');

            return [
                'id_pob' => $idPob,
                'nazev' => $nazev,
                'start_day' => $defaultStartDay,
                'blocks' => 0,
                'hours' => 0,
                'status' => 'OK',
                'note' => 'API vratilo prazdny tyden',
            ];
        }

        $startDay = (string)($week['startDay'] ?? $defaultStartDay);
        if ($startDay === '') {
            $startDay = $defaultStartDay;
        }

        $rows = cb_spt_build_blocks($week, $idPob);
        $blocks = cb_spt_save_blocks($startDay, $idPob, $rows);
        $hours = cb_spt_count_hours($rows);
        $note = $blocks === 0 ? 'Tyden je prazdny' : null;

        cb_spt_mark_ok($skipWeeks, $idPob, $startDay, $blocks, $hours, $note);

        return [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'start_day' => $startDay,
            'blocks' => $blocks,
            'hours' => $hours,
            'status' => 'OK',
            'note' => $note,
        ];
    } catch (Throwable $e) {
        $message = mb_substr($e->getMessage(), 0, 1000, 'UTF-8');
        cb_spt_mark_error($skipWeeks, $idPob, $defaultStartDay, $message);

        return [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'start_day' => $defaultStartDay,
            'blocks' => 0,
            'hours' => 0,
            'status' => 'CHYBA',
            'note' => $message,
        ];
    }
}

function cb_spt_process_one_week(string $token, int $skipWeeks, array $branches): array
{
    $weekLines = [];
    $weekStartDay = cb_spt_default_start_day_by_skip($skipWeeks);
    $weekBlocks = 0;
    $weekHours = 0;
    $weekErrors = 0;
    $processedBranches = 0;

    foreach ($branches as $branch) {
        $idPob = (int)($branch['id'] ?? 0);
        if ($idPob <= 0) {
            continue;
        }

        $nazev = trim((string)($branch['name'] ?? ''));
        $result = cb_spt_process_branch($token, $skipWeeks, $idPob, $nazev);

        $weekStartDay = $result['start_day'];
        $weekBlocks += $result['blocks'];
        $weekHours += $result['hours'];
        $processedBranches++;

        if ($result['status'] !== 'OK') {
            $weekErrors++;
        }

        $line = sprintf(
            'skipweek %d | start_day %s | id_pob %d | nazev %s | stav %s | pocet_zaznamu %d | pocet_hodin %d',
            $skipWeeks,
            $result['start_day'],
            $idPob,
            $nazev === '' ? '-' : $nazev,
            $result['status'],
            $result['blocks'],
            $result['hours']
        );

        if ($result['note'] !== null && $result['note'] !== '') {
            $line .= ' | poznamka ' . $result['note'];
        }

        $weekLines[] = $line;
    }

    $summaryState = $weekErrors === 0 ? 'OK' : 'CHYBA';
    $weekLines[] = sprintf(
        'souhrn skipweek %d | start_day %s | pobocky %d | pocet_zaznamu %d | pocet_hodin %d | stav %s',
        $skipWeeks,
        $weekStartDay,
        $processedBranches,
        $weekBlocks,
        $weekHours,
        $summaryState
    );

    if ($weekErrors === 0) {
        cb_spt_write_state($skipWeeks + 1);
        $weekLines[] = 'tyden ukoncen OK, state posunut na skipweek ' . ($skipWeeks + 1);
    } else {
        $weekLines[] = 'tyden ukoncen s chybou, state zustava na skipweek ' . $skipWeeks;
    }

    $weekLines[] = '';

    cb_spt_prepend_log_lines($weekLines);

    return [
        'errors' => $weekErrors,
        'start_day' => $weekStartDay,
    ];
}

function cb_spt_run(): void
{
    $token = (string)($_SESSION['cb_token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('Chybi token v session (cb_token).');
    }

    $branches = cb_spt_get_branches($token);
    if ($branches === []) {
        throw new RuntimeException('Nenalezeny pobocky z branchFindAll.');
    }

    $startSkip = cb_spt_get_start_skip_once();
    $skipWeeks = cb_spt_require_state_file();

    if ($skipWeeks > 0) {
        cb_spt_prepend_log_lines([
            'Start testu | ' . cb_spt_now() . ' | pobocky=' . count($branches) . ' | od skipWeeks=' . $startSkip . ' | dalsi skipweeks=' . $skipWeeks,
            'HOTOVO',
            '',
        ]);
        return;
    }

    cb_spt_prepend_log_lines([
        'Start testu | ' . cb_spt_now() . ' | pobocky=' . count($branches) . ' | od skipWeeks=' . $startSkip . ' | dalsi skipweeks=' . $skipWeeks,
    ]);

    cb_spt_process_one_week($token, $skipWeeks, $branches);
}

if (isset($_POST['run_smeny_plan_test']) && (string)$_POST['run_smeny_plan_test'] === '1') {
    cb_spt_run();
} else {
    $state = cb_spt_read_state();
    $nextSkip = isset($state['next_skip']) && is_numeric($state['next_skip'])
        ? (int)$state['next_skip']
        : cb_spt_oldest_skip();
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Test směny plán</h2>
      <p class="card_text txt_seda">Jedno spuštění = právě jeden týden.</p>
      <p class="card_text txt_seda">State: <?= h((string)$nextSkip) ?></p>
      <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 odstup_horni_10">
        <input type="hidden" name="run_smeny_plan_test" value="1">
        <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit test</button>
      </form>
    </div>
    <?php
}
