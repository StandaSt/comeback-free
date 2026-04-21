<?php
// inicializace/plnime_smeny_plan.php  * Verze: V14 * Aktualizace: 21.04.2026

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
    // tichy beh, zapis jen do TXT logu
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

function readSkipweekFile(): ?int
{
    if (!is_file(SKIPWEEK_FILE)) {
        return null;
    }

    $raw = trim((string)file_get_contents(SKIPWEEK_FILE));
    if ($raw === '' || !preg_match('/^-?[0-9]+$/', $raw)) {
        throw new RuntimeException('Soubor log/smeny/skipweek.txt nema platne cele cislo.');
    }

    return (int)$raw;
}

function writeSkipweekFile(int $nextSkip): void
{
    ensureLogDir();
    file_put_contents(SKIPWEEK_FILE, (string)$nextSkip . PHP_EOL, LOCK_EX);
}

function ensureSkipweekFileOnStart(): int
{
    $skip = readSkipweekFile();

    if ($skip === null) {
        throw new RuntimeException('Chybi soubor log/smeny/skipweek.txt.');
    }

    return $skip;
}

function getBranches(string $token): array
{
    $query = 'query{ branchFindAll{ id name } }';
    $response = cb_smeny_graphql(GQL_URL, $query, [], $token, REQUEST_TIMEOUT_SEC);
    $branches = $response['branchFindAll'] ?? [];

    return is_array($branches) ? $branches : [];
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
        'datum_od' => MIN_DATE,
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
        'datum_od' => MIN_DATE,
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
        $line .= ' | poznamka=' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
    }

    out($line);
}

function saveAcceptedShifts(array $week, int $idPob): void
{
    // Funkce na základě admin_testy/smeny_testy/plnime_smeny_akceptovane.php
    if (!isset($week['id'], $week['startDay'], $week['shiftDays']) || !is_array($week['shiftDays'])) {
        return;
    }

    $smenyWeekId = (int)$week['id'];
    $startDayApi = (string)$week['startDay'];
    $rows = [];

    foreach ($week['shiftDays'] as $shiftDay) {
        if (!is_array($shiftDay)) continue;
        $smenyDayId = (int)($shiftDay['id'] ?? 0);
        $den = (string)($shiftDay['day'] ?? '');
        $datum = (new DateTimeImmutable($startDayApi))->modify('+' . dayOffset($den) . ' day')->format('Y-m-d');

        foreach (($shiftDay['shiftRoles'] ?? []) as $shiftRole) {
            if (!is_array($shiftRole)) continue;
            $smenyRoleId = (int)($shiftRole['id'] ?? 0);
            $idSlot = (int)($shiftRole['type']['id'] ?? 0);

            foreach (($shiftRole['shiftHours'] ?? []) as $shiftHour) {
                if (!is_array($shiftHour)) continue;
                if (!isset($shiftHour['employee']['id'])) continue;

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

    if (count($rows) > 0) {
        $db = db();
        $values = [];
        foreach ($rows as $row) {
            $values[] = '('.
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
        // Chybová obsluha minimalni (převzato ze vzoru): nemění nic
    }
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
        
        // Zápis do smeny_akceptovane podle vzoru
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

function processSingleWeek(string $token, int $skipWeeks, array $branches): bool
{
    $weekStartDay = startDayBySkipWeeks($skipWeeks);
    $weekBlocks = 0;
    $weekHours = 0;
    $processedBranches = 0;
    $weekHasError = false;
    $weekLines = [];

    foreach ($branches as $branch) {
        $idPob = (int)($branch['id'] ?? 0);
        if ($idPob <= 0) {
            continue;
        }

        $nazevPob = trim((string)($branch['name'] ?? ''));
        $result = processBranch($token, $skipWeeks, $idPob, $nazevPob);
        $weekStartDay = $result['start_day'];
        $processedBranches++;

        if ($result['status'] === 'OK') {
            $weekBlocks += $result['blocks'];
            $weekHours += $result['hours'];
        } else {
            $weekHasError = true;
        }

        $line = sprintf(
            'skipweek %d | start_day %s | id_pob %d',
            $skipWeeks,
            $result['start_day'],
            $idPob
        );

        if ($nazevPob !== '') {
            $line .= ' | nazev ' . $nazevPob;
        }

        $line .= sprintf(
            ' | stav %s | pocet_zaznamu %d | pocet_hodin %d',
            $result['status'],
            $result['blocks'],
            $result['hours']
        );

        if ($result['note'] !== null && $result['note'] !== '') {
            $line .= ' | poznamka ' . $result['note'];
        }

        $weekLines[] = $line;
    }

    $summary = sprintf(
        'souhrn skipweek %d | start_day %s | pobocky %d | pocet_zaznamu %d | pocet_hodin %d | stav %s',
        $skipWeeks,
        $weekStartDay,
        $processedBranches,
        $weekBlocks,
        $weekHours,
        $weekHasError ? 'CHYBA' : 'OK'
    );

    array_unshift($weekLines, $summary);
    appendTxtLog('');

    foreach ($weekLines as $line) {
        appendTxtLog($line);
    }

    return !$weekHasError;
}

function run(): void
{
    set_time_limit(0);

    $totalStart = microtime(true);
    $token = (string)($_SESSION['cb_token'] ?? '');

    if ($token === '') {
        out('Chybi token v session (cb_token).');
        return;
    }

    $branches = getBranches($token);
    if ($branches === []) {
        out('Nenalezeny pobocky (branchFindAll).');
        return;
    }

    $startSkip = ensureSkipweekFileOnStart();

    $startLine = sprintf(
        'Start tahu historie | %s | pobocky=%d | od skipWeeks=%d | do skipWeeks=0 | min_date=%s',
        nowSql(),
        count($branches),
        $startSkip,
        MIN_DATE
    );

    appendTxtLog($startLine);
    out($startLine);

    while (true) {
        $skipWeeks = readSkipweekFile();
        if ($skipWeeks === null) {
            appendTxtLog('Soubor log/smeny/skipweek.txt nebyl nalezen. Import korektne ukoncen.');
            break;
        }

        if ($skipWeeks > 0) {
            break;
        }

        $weekOk = processSingleWeek($token, $skipWeeks, $branches);

        if (!$weekOk) {
            appendTxtLog('Tyden ukoncen s chybou, skipweek.txt zustava na hodnote ' . $skipWeeks . '.');
            break;
        }

        writeSkipweekFile($skipWeeks + 1);
        usleep(SLEEP_BETWEEN_WEEKS_US);
    }

    out('Konec tahu historie | celkovy cas: ' . formatDuration(microtime(true) - $totalStart));
}

if (isset($_POST['run_smeny_plan']) && (string)$_POST['run_smeny_plan'] === '1') {
    run();
} else {
    $nextSkip = null;
    $stateText = 'Chybi soubor log/smeny/skipweek.txt.';

    try {
        $nextSkip = readSkipweekFile();
        if ($nextSkip !== null) {
            $stateText = 'Navazani ze souboru skipweek.txt: skipweek=' . $nextSkip . '.';
        }
    } catch (Throwable $e) {
        $stateText = $e->getMessage();
    }
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Inicializace směny plán</h2>
      <p class="card_text txt_seda">Script stáhne směny plán z API smeny.pizzacomeback.cz do DB.</p>
      <p class="card_text txt_seda"><?= h($stateText) ?></p>
      <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0 odstup_horni_10">
        <input type="hidden" name="run_smeny_plan" value="1">
        <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit import</button>
      </form>
    </div>
    <?php
}
