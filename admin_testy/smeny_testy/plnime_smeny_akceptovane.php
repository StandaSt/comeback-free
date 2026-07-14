<?php
// admin_testy/plnime_smeny_akceptovane.php * Verze: V5 * Aktualizace: 16.3.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';

const CB_PSA_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const CB_PSA_BATCH_SIZE = 5;
const CB_PSA_GRAPHQL_TIMEOUT = 30;
const CB_PSA_RELOAD_MS = 500;

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

while (ob_get_level() > 0) {
    @ob_end_flush();
}
@ob_implicit_flush(true);

function cb_psa_out(string $text = ''): void
{
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . "\n";
    flush();
}

function cb_psa_day_offset(string $day): int
{
    if ($day === 'monday') {
        return 0;
    }
    if ($day === 'tuesday') {
        return 1;
    }
    if ($day === 'wednesday') {
        return 2;
    }
    if ($day === 'thursday') {
        return 3;
    }
    if ($day === 'friday') {
        return 4;
    }
    if ($day === 'saturday') {
        return 5;
    }
    if ($day === 'sunday') {
        return 6;
    }

    throw new RuntimeException('Neznámý den ze Směn: ' . $day);
}

function cb_psa_datum(string $startDay, string $day): string
{
    $dt = new DateTimeImmutable($startDay);
    $offset = cb_psa_day_offset($day);

    if ($offset > 0) {
        $dt = $dt->modify('+' . $offset . ' day');
    }

    return $dt->format('Y-m-d');
}

function cb_psa_skip_weeks_from_start_day(string $startDay): int
{
    $tz = new DateTimeZone('Europe/Prague');

    $target = new DateTimeImmutable($startDay, $tz);
    $target = $target->setTime(0, 0, 0);

    $today = new DateTimeImmutable('now', $tz);
    $currentMonday = $today->modify('monday this week')->setTime(0, 0, 0);

    $targetTs = $target->getTimestamp();
    $currentTs = $currentMonday->getTimestamp();

    $diffSeconds = $targetTs - $currentTs;
    $weekSeconds = 7 * 24 * 60 * 60;
    $weekDiff = (int)round($diffSeconds / $weekSeconds);

    return $weekDiff - 1;
}

function cb_psa_fetch_batch(): array
{
    $rows = [];

    $sql = 'SELECT id_smeny_import, id_pob, start_day
            FROM smeny_import
            WHERE import = 0
            ORDER BY id_smeny_import ASC
            LIMIT ' . (string)CB_PSA_BATCH_SIZE;

    $result = db()->query($sql);
    if ($result === false) {
        throw new RuntimeException('Nepodařilo se načíst smeny_import: ' . db()->error);
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id_smeny_import' => (int)($row['id_smeny_import'] ?? 0),
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'start_day' => (string)($row['start_day'] ?? ''),
        ];
    }

    $result->close();
    return $rows;
}

function cb_psa_remaining_count(): int
{
    $result = db()->query('SELECT COUNT(*) AS cnt FROM smeny_import WHERE import = 0');
    if ($result === false) {
        throw new RuntimeException('Nepodařilo se spočítat zbývající řádky: ' . db()->error);
    }

    $row = $result->fetch_assoc();
    $result->close();

    return (int)($row['cnt'] ?? 0);
}

function cb_psa_build_insert_sql(array $rows): string
{
    $values = [];

    foreach ($rows as $row) {
        $values[] = '(' .
            (int)$row['smeny_week_id'] . ', ' .
            (int)$row['smeny_day_id'] . ', ' .
            (int)$row['smeny_role_id'] . ', ' .
            (int)$row['shift_hour_id'] . ", '" .
            db()->real_escape_string((string)$row['datum']) . "', '" .
            db()->real_escape_string((string)$row['den']) . "', " .
            (int)$row['id_pob'] . ', ' .
            (int)$row['id_slot'] . ', ' .
            (int)$row['start_hour'] . ', ' .
            (int)$row['confirmed'] . ', ' .
            (int)$row['is_first'] . ', ' .
            (int)$row['id_user'] .
        ')';
    }

    return 'INSERT INTO smeny_akceptovane (
        smeny_week_id,
        smeny_day_id,
        smeny_role_id,
        shift_hour_id,
        datum,
        den,
        id_pob,
        id_slot,
        start_hour,
        confirmed,
        is_first,
        id_user
    ) VALUES ' . implode(",\n", $values);
}

echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Plníme smeny_akceptovane</title></head><body><pre>';

try {
    $token = (string)($_SESSION['cb_token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('Chybí session token cb_token.');
    }

    $cycleNo = isset($_GET['c']) ? max(1, (int)$_GET['c']) : 1;

    $batch = cb_psa_fetch_batch();
    if (count($batch) === 0) {
        cb_psa_out('HOTOVO');
        echo '</pre></body></html>';
        exit;
    }

    $query = <<<'GQL'
query($skipWeeks:Int!, $branchId:Int!){
  branchGetShiftWeek(skipWeeks:$skipWeeks, branchId:$branchId){
    id
    startDay
    shiftDays{
      id
      day
      shiftRoles{
        id
        type{
          id
        }
        shiftHours{
          id
          startHour
          confirmed
          isFirst
          employee{
            id
          }
        }
      }
    }
  }
}
GQL;

    $timeStart = microtime(true);
    $processed = 0;
    $insertedHours = 0;

    foreach ($batch as $item) {
        $idSmenyImport = (int)$item['id_smeny_import'];
        $idPob = (int)$item['id_pob'];
        $startDayDb = (string)$item['start_day'];
        $skipWeeks = cb_psa_skip_weeks_from_start_day($startDayDb);

        cb_psa_out(
            'Načítám | id_smeny_import=' . (string)$idSmenyImport .
            ' | id_pob=' . (string)$idPob .
            ' | start_day=' . $startDayDb .
            ' | skipWeeks=' . (string)$skipWeeks
        );

        db()->begin_transaction();

        try {
            $weekData = cb_smeny_graphql(
                CB_PSA_GQL_URL,
                $query,
                [
                    'skipWeeks' => $skipWeeks,
                    'branchId' => $idPob,
                ],
                $token,
                CB_PSA_GRAPHQL_TIMEOUT
            );

            $week = $weekData['branchGetShiftWeek'] ?? null;
            if (!is_array($week)) {
                throw new RuntimeException('branchGetShiftWeek nevrátil data.');
            }

            $startDayApi = (string)($week['startDay'] ?? '');
            if ($startDayApi === '') {
                throw new RuntimeException('Ve výsledku chybí startDay.');
            }

            $smenyWeekId = (int)($week['id'] ?? 0);
            if ($smenyWeekId <= 0) {
                throw new RuntimeException('Ve výsledku chybí id týdne.');
            }

            $insertRows = [];

            foreach (($week['shiftDays'] ?? []) as $shiftDay) {
                if (!is_array($shiftDay)) {
                    continue;
                }

                $smenyDayId = (int)($shiftDay['id'] ?? 0);
                $den = (string)($shiftDay['day'] ?? '');
                $datum = cb_psa_datum($startDayApi, $den);

                foreach (($shiftDay['shiftRoles'] ?? []) as $shiftRole) {
                    if (!is_array($shiftRole)) {
                        continue;
                    }

                    $smenyRoleId = (int)($shiftRole['id'] ?? 0);
                    $idSlot = (int)($shiftRole['type']['id'] ?? 0);

                    foreach (($shiftRole['shiftHours'] ?? []) as $shiftHour) {
                        if (!is_array($shiftHour)) {
                            continue;
                        }

                        if (!isset($shiftHour['employee']['id'])) {
                            continue;
                        }

                        $insertRows[] = [
                            'smeny_week_id' => $smenyWeekId,
                            'smeny_day_id' => $smenyDayId,
                            'smeny_role_id' => $smenyRoleId,
                            'shift_hour_id' => (int)($shiftHour['id'] ?? 0),
                            'datum' => $datum,
                            'den' => $den,
                            'id_pob' => $idPob,
                            'id_slot' => $idSlot,
                            'start_hour' => (int)($shiftHour['startHour'] ?? 0),
                            'confirmed' => !empty($shiftHour['confirmed']) ? 1 : 0,
                            'is_first' => !empty($shiftHour['isFirst']) ? 1 : 0,
                            'id_user' => (int)$shiftHour['employee']['id'],
                        ];
                    }
                }
            }

            if (count($insertRows) > 0) {
                $sqlInsert = cb_psa_build_insert_sql($insertRows);

                if (db()->query($sqlInsert) !== true) {
                    throw new RuntimeException('Hromadný INSERT selhal: ' . db()->error);
                }

                $zapsanoTentoRadek = count($insertRows);
                $insertedHours += $zapsanoTentoRadek;
            } else {
                $zapsanoTentoRadek = 0;
            }

            $sqlDone = 'UPDATE smeny_import
                        SET import = 1
                        WHERE id_smeny_import = ' . (string)$idSmenyImport . '
                        LIMIT 1';

            if (db()->query($sqlDone) !== true) {
                throw new RuntimeException('Nepodařilo se změnit import=1: ' . db()->error);
            }

            db()->commit();
            $processed++;

            cb_psa_out('OK | zapsano_hodin=' . (string)$zapsanoTentoRadek);

        } catch (Throwable $e) {
            db()->rollback();
            cb_psa_out('CHYBA | ' . $e->getMessage());
        }

        cb_psa_out('');
    }

    $durationMs = (int)round((microtime(true) - $timeStart) * 1000);
    $remaining = cb_psa_remaining_count();

    cb_psa_out(
        'Cyklus ' . (string)$cycleNo .
        ' skončil | zpracováno ' . (string)$processed .
        ' řádků | zapsáno ' . (string)$insertedHours .
        ' hodin | čas ' . (string)$durationMs . ' ms'
    );
    cb_psa_out('');
    cb_psa_out('');

    if ($remaining > 0) {
        $nextCycle = $cycleNo + 1;
        $self = (string)($_SERVER['PHP_SELF'] ?? 'plnime_smeny_akceptovane.php');
        echo '</pre><script>setTimeout(function(){ window.location.href=' . json_encode($self . '?c=' . $nextCycle) . '; }, ' . (string)CB_PSA_RELOAD_MS . ');</script></body></html>';
        exit;
    }

    cb_psa_out('HOTOVO');
    echo '</pre></body></html>';
    exit;

} catch (Throwable $e) {
    cb_psa_out('CHYBA');
    cb_psa_out($e->getMessage());
    echo '</pre></body></html>';
    exit;
}
