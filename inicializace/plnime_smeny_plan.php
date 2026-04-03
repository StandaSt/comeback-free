<?php
// inicializace/plnime_smeny_plan.php  * Verze: V8 * Aktualizace: 03.04.2026

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';

const GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const MIN_DATE = '2020-10-12';
const MAX_WEEKS_PER_RUN = 4;
const TARGET_MAX_SKIP = 1;
const PROGRESS_LOG_FILE = __DIR__ . '/../_kandidati/tahame_smeny.txt';

@ob_end_flush();
@ob_implicit_flush(true);

function out(string $text): void
{
    file_put_contents(PROGRESS_LOG_FILE, strip_tags($text) . PHP_EOL, FILE_APPEND);
    echo $text . "<br>\n";
    flush();
}

function resetProgressLogIfFirstBatch(): void
{
    if (isset($_GET['startDay'])) {
        return;
    }

    file_put_contents(PROGRESS_LOG_FILE, '');
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
    $day = new DateTimeImmutable($date);

    return $day->modify('monday this week');
}

function targetWeekMonday(): DateTimeImmutable
{
    return currentWeekMonday()->modify('+' . TARGET_MAX_SKIP . ' week');
}

function calculateSkipWeeksByStartDay(string $startDay): int
{
    $currentMonday = currentWeekMonday();
    $weekMonday = normalizeMonday($startDay);
    $seconds = $weekMonday->getTimestamp() - $currentMonday->getTimestamp();
    // Korekce -1 týdne pro shodné chování s testovacím skriptem a správný záporný posun:
    return (int)round($seconds / 604800) - 1;
}

function nextWeekStartDay(string $startDay): string
{
    return normalizeMonday($startDay)->modify('+1 week')->format('Y-m-d');
}

function getBranches(string $token): array
{
    $query = 'query{ branchFindAll{ id name } }';
    $response = cb_smeny_graphql(GQL_URL, $query, [], $token);

    return $response['branchFindAll'] ?? [];
}

function getWeek(string $token, int $branchId, int $skipWeeks): ?array
{
    $query = <<<'GQL'
query($skipWeeks:Int!, $branchId:Int!){
  branchGetShiftWeek(skipWeeks:$skipWeeks, branchId:$branchId){
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
            'skipWeeks' => $skipWeeks,
            'branchId' => $branchId,
        ],
        $token
    );

    return $response['branchGetShiftWeek'] ?? null;
}

function findResumeStartDay(array $branches): string
{
    if ($branches === []) {
        return MIN_DATE;
    }

    $branchIds = [];

    foreach ($branches as $branch) {
        $idPob = (int)($branch['id'] ?? 0);
        if ($idPob > 0) {
            $branchIds[] = $idPob;
        }
    }

    if ($branchIds === []) {
        return MIN_DATE;
    }

    $db = db();
    $in = implode(',', array_map('intval', $branchIds));
    $requiredCount = count($branchIds);

    $sql = "
        SELECT MAX(t.start_day) AS start_day
        FROM (
            SELECT start_day
            FROM smeny_aktualizace
            WHERE stav = 1
              AND id_pob IN ($in)
            GROUP BY start_day
            HAVING COUNT(DISTINCT id_pob) = $requiredCount
        ) AS t
    ";

    $result = $db->query($sql);
    if (!($result instanceof mysqli_result)) {
        return MIN_DATE;
    }

    $row = $result->fetch_assoc();
    $result->free();

    $startDay = trim((string)($row['start_day'] ?? ''));
    if ($startDay === '') {
        return MIN_DATE;
    }

    return normalizeMonday($startDay)->format('Y-m-d');
}

function requestedStartDay(): ?string
{
    $value = trim((string)($_GET['startDay'] ?? ''));
    if ($value === '') {
        return null;
    }

    return normalizeMonday($value)->format('Y-m-d');
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

function buildBlocks(array $week, int $idPob): array
{
    $startDay = $week['startDay'] ?? null;
    $shiftDays = $week['shiftDays'] ?? [];

    if (!$startDay || !is_array($shiftDays)) {
        return [];
    }

    $timeline = [];

    foreach ($shiftDays as $day) {
        if (!isset($day['day'], $day['shiftRoles']) || !is_array($day['shiftRoles'])) {
            continue;
        }

        $dayDate = (new DateTimeImmutable($startDay))
            ->modify('+' . dayOffset((string)$day['day']) . ' day')
            ->format('Y-m-d');

        foreach ($day['shiftRoles'] as $role) {
            $slotId = (int)($role['type']['id'] ?? 0);
            if ($slotId <= 0 || !isset($role['shiftHours']) || !is_array($role['shiftHours'])) {
                continue;
            }

            foreach ($role['shiftHours'] as $hour) {
                $userId = (int)($hour['employee']['id'] ?? 0);
                $startHour = (int)($hour['startHour'] ?? -1);

                if ($userId <= 0 || $startHour < 0 || $startHour > 23) {
                    continue;
                }

                $startTs = strtotime(sprintf('%s %02d:00:00', $dayDate, $startHour));
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

function saveBlocks(array $rows, string $startDay, int $idPob): int
{
    $db = db();

    $db->query("DELETE FROM smeny_plan WHERE start_day='" . $db->real_escape_string($startDay) . "' AND id_pob=" . (int)$idPob);

    if ($rows === []) {
        return 0;
    }

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
            throw new RuntimeException('INSERT smeny_plan selhal: ' . $stmt->error);
        }

        $saved++;
    }

    $stmt->close();

    return $saved;
}

function saveState(array $state): void
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
        throw new RuntimeException('UPSERT smeny_aktualizace selhal: ' . $stmt->error);
    }

    $stmt->close();
}

function printProgress(int $skipWeeks, int $idPob, string $startDay, int $blocks, ?string $error = null): void
{
    $line = sprintf(
        'skipWeeks=%d | id_pob=%d | startDay=%s | pocet_bloku=%d',
        $skipWeeks,
        $idPob,
        $startDay,
        $blocks
    );

    if ($error !== null && $error !== '') {
        $line .= ' | chyba=' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    }

    out($line);
}

function continueUrl(string $nextStartDay): string
{
    $base = strtok($_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? 'plnime_smeny_plan.php', '?');
    $separator = strpos($base, '?') === false ? '?' : '&';

    return $base . $separator . 'startDay=' . $nextStartDay;
}

function run(): void
{
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

    resetProgressLogIfFirstBatch();

    // TEST: SkipWeeks natvrdo -1 (jen 1 týden pro všechny pobočky)
    $skipWeeks = -1;
    out('Testovací běh: skipWeeks = ' . $skipWeeks . ' | jeden týden všech poboček.');

    foreach ($branches as $branch) {
        $idPob = (int)($branch['id'] ?? 0);
        if ($idPob <= 0) {
            continue;
        }

        $startedAt = nowSql();
        $startDay = '';
        $blocks = 0;
        $hours = 0;
        $errorText = null;
        $stav = 1;
        $posledniOk = null;

        try {
            $week = getWeek($token, $idPob, $skipWeeks);
            if ($week === null || !isset($week['startDay'])) {
                throw new RuntimeException('API nevratilo data tydne.');
            }

            $startDay = normalizeMonday((string)$week['startDay'])->format('Y-m-d');

            $rows = buildBlocks($week, $idPob);
            $blocks = saveBlocks($rows, $startDay, $idPob);
            $hours = countHours($rows);
            $posledniOk = nowSql();

            printProgress($skipWeeks, $idPob, $startDay, $blocks, null);
        } catch (Throwable $e) {
            $stav = 2;
            $errorText = mb_substr($e->getMessage(), 0, 1000, 'UTF-8');
            printProgress($skipWeeks, $idPob, $startDay, 0, $errorText);
        }

        $finishedAt = nowSql();

        saveState([
            'start_day' => $startDay,
            'skip_weeks' => $skipWeeks,
            'id_pob' => $idPob,
            'stav' => $stav,
            'datum_od' => MIN_DATE,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'posledni_ok' => $posledniOk,
            'pocet_bloku' => $blocks,
            'pocet_hodin' => $hours,
            'chyba_text' => $errorText,
        ]);
    }

    $totalDuration = microtime(true) - $totalStart;
    out('Konec testu | celkovy cas: ' . formatDuration($totalDuration));
}
        if ($currentStartDay > $targetStartDay) {
            break;
        }

        $lastProcessedStartDay = $currentStartDay;
        $skipWeeks = calculateSkipWeeksByStartDay($currentStartDay);
        $weekStartTime = microtime(true);
        $weekReturnedStartDay = null;

        out('==== startDay ' . $currentStartDay . ' | skipWeeks ' . $skipWeeks . ' ====');

        foreach ($branches as $branch) {
            $idPob = (int)($branch['id'] ?? 0);
            if ($idPob <= 0) {
                continue;
            }

            $startedAt = nowSql();
            $startDay = $currentStartDay;
            $blocks = 0;
            $hours = 0;
            $errorText = null;
            $stav = 1;
            $posledniOk = null;

            try {
                $week = getWeek($token, $idPob, $skipWeeks);
                if ($week === null || !isset($week['startDay'])) {
                    throw new RuntimeException('API nevratilo data tydne.');
                }

                $startDay = normalizeMonday((string)$week['startDay'])->format('Y-m-d');

                if ($weekReturnedStartDay === null || $startDay > $weekReturnedStartDay) {
                    $weekReturnedStartDay = $startDay;
                }

                $rows = buildBlocks($week, $idPob);
                $blocks = saveBlocks($rows, $startDay, $idPob);
                $hours = countHours($rows);
                $posledniOk = nowSql();

                printProgress($skipWeeks, $idPob, $startDay, $blocks, null);
            } catch (Throwable $e) {
                $stav = 2;
                $errorText = mb_substr($e->getMessage(), 0, 1000, 'UTF-8');
                printProgress($skipWeeks, $idPob, $startDay, 0, $errorText);
            }

            $finishedAt = nowSql();

            saveState([
                'start_day' => $startDay,
                'skip_weeks' => $skipWeeks,
                'id_pob' => $idPob,
                'stav' => $stav,
                'datum_od' => MIN_DATE,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'posledni_ok' => $posledniOk,
                'pocet_bloku' => $blocks,
                'pocet_hodin' => $hours,
                'chyba_text' => $errorText,
            ]);
        }

        $processedWeeks++;

        if ($weekReturnedStartDay === null) {
            $nextStartDay = nextWeekStartDay($currentStartDay);
        } else {
            $nextStartDay = nextWeekStartDay($weekReturnedStartDay);
        }

        if ($nextStartDay <= $currentStartDay) {
            out('stop | dalsi startDay se neposunul | aktualni=' . $currentStartDay . ' | dalsi=' . $nextStartDay);
            $hasMoreWeeks = false;
            break;
        }

        $currentStartDay = $nextStartDay;
        $hasMoreWeeks = ($currentStartDay <= $targetStartDay);

        $weekDuration = microtime(true) - $weekStartTime;
        $totalDuration = microtime(true) - $totalStart;

        out(
            'cas startDay ' . $lastProcessedStartDay . ': ' . formatDuration($weekDuration)
            . ' | celkem: ' . formatDuration($totalDuration)
        );
    }

    $totalDuration = microtime(true) - $totalStart;
    out('konec behu | celkovy cas: ' . formatDuration($totalDuration));

    if ($hasMoreWeeks && $lastProcessedStartDay !== null) {
        $url = continueUrl($currentStartDay);

        out('dalsi davka za 600 ms | startDay=' . $currentStartDay);
        echo '<script>setTimeout(function(){window.location.href=' . json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';},600);</script>';
        flush();
    }
}

run();

// inicializace/plnime_smeny_plan.php  * Verze: V8 * Aktualizace: 03.04.2026
// počet řádků 500
?>
