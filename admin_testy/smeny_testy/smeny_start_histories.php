<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../lib/app.php';
require_once __DIR__ . '/../../lib/smeny_graphql.php';

const CB_SMENY_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const CB_SMENY_MIN_DATE = '2020-10-26';
const CB_SMENY_PROBE_FROM = '2025-08-19';
const CB_START_FILE = __DIR__ . '/../../log/start_pobocek.txt';
const CB_LOG_FILE = __DIR__ . '/../../log/hledam_historii_smeny.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
@set_time_limit(0);

while (ob_get_level() > 0) {
    @ob_end_flush();
}
@ob_implicit_flush(true);

function cb_smh_write(string $file, string $text, bool $append = true): void
{
    @mkdir(dirname($file), 0775, true);
    if ($append) {
        @file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
        return;
    }

    @file_put_contents($file, $text, LOCK_EX);
}

function cb_smh_line(string $label, string $text): string
{
    return '[' . date('Y-m-d H:i:s') . '] ' . $label . ' ' . $text . "\n";
}

function cb_smh_log(string $label, string $text): void
{
    $line = cb_smh_line($label, $text);
    cb_smh_write(CB_LOG_FILE, $line, true);
    echo $line;
    flush();
}

function cb_smh_write_result(string $text, bool $append = true): void
{
    cb_smh_write(CB_START_FILE, $text, $append);
}

function cb_smh_current_monday(): DateTimeImmutable
{
    $tz = new DateTimeZone('Europe/Prague');
    return (new DateTimeImmutable('monday this week', $tz))->setTime(0, 0, 0);
}

function cb_smh_week_monday(string $date): DateTimeImmutable
{
    $tz = new DateTimeZone('Europe/Prague');
    return (new DateTimeImmutable($date, $tz))->modify('monday this week')->setTime(0, 0, 0);
}

function cb_smh_skip_weeks(DateTimeImmutable $weekMonday, DateTimeImmutable $currentMonday): int
{
    $weekSeconds = 7 * 24 * 60 * 60;
    $diff = $weekMonday->getTimestamp() - $currentMonday->getTimestamp();
    return (int)round($diff / $weekSeconds);
}

function cb_smh_branch_list(string $token): array
{
    $query = 'query{ branchFindAll{ id name } }';
    $data = cb_smeny_graphql(CB_SMENY_GQL_URL, $query, [], $token, 20);

    $rows = $data['branchFindAll'] ?? [];
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['name'] ?? ''));
        if ($id > 0) {
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : ('pobocka_' . $id),
            ];
        }
    }

    usort($out, static fn(array $a, array $b): int => ($a['id'] <=> $b['id']));
    return $out;
}

function cb_smh_count_occupied_hours(array $week): int
{
    $count = 0;
    $shiftDays = $week['shiftDays'] ?? [];
    if (!is_array($shiftDays)) {
        return 0;
    }

    foreach ($shiftDays as $day) {
        if (!is_array($day)) {
            continue;
        }

        $shiftRoles = $day['shiftRoles'] ?? [];
        if (!is_array($shiftRoles)) {
            continue;
        }

        foreach ($shiftRoles as $role) {
            if (!is_array($role)) {
                continue;
            }

            $shiftHours = $role['shiftHours'] ?? [];
            if (!is_array($shiftHours)) {
                continue;
            }

            foreach ($shiftHours as $hour) {
                if (!is_array($hour)) {
                    continue;
                }

                $employee = $hour['employee'] ?? null;
                if (is_array($employee) && (int)($employee['id'] ?? 0) > 0) {
                    $count++;
                }
            }
        }
    }

    return $count;
}

function cb_smh_week_probe(int $branchId, int $skipWeeks, string $token): array
{
    $query = <<<'GQL'
query($branchId:Int!, $skipWeeks:Int!){
  branchGetShiftWeek(branchId:$branchId, skipWeeks:$skipWeeks){
    startDay
    shiftDays{
      shiftRoles{
        shiftHours{
          employee{
            id
          }
        }
      }
    }
  }
}
GQL;

    try {
        $data = cb_smeny_graphql(
            CB_SMENY_GQL_URL,
            $query,
            [
                'branchId' => $branchId,
                'skipWeeks' => $skipWeeks,
            ],
            $token,
            20
        );
    } catch (Throwable $e) {
        return [
            'has_data' => false,
            'hours' => 0,
            'start_day' => '',
        ];
    }

    $week = $data['branchGetShiftWeek'] ?? null;
    if (!is_array($week)) {
        return [
            'has_data' => false,
            'hours' => 0,
            'start_day' => '',
        ];
    }

    $hours = cb_smh_count_occupied_hours($week);

    return [
        'has_data' => ($hours > 0),
        'hours' => $hours,
        'start_day' => trim((string)($week['startDay'] ?? '')),
    ];
}

$token = (string)($_SESSION['cb_token'] ?? '');
if ($token === '') {
    $msg = 'CHYBA: chybi session token cb_token.';
    cb_smh_write_result($msg . "\n", false);
    cb_smh_log('ERROR', $msg);
    echo $msg . "\n";
    exit;
}

$currentMonday = cb_smh_current_monday();
$probeFromMonday = cb_smh_week_monday(CB_SMENY_PROBE_FROM);
$minMonday = cb_smh_week_monday(CB_SMENY_MIN_DATE);

$branches = cb_smh_branch_list($token);
if ($branches === []) {
    $msg = 'CHYBA: nepodarilo se nacist seznam pobocek.';
    cb_smh_write_result($msg . "\n", false);
    cb_smh_log('ERROR', $msg);
    echo $msg . "\n";
    exit;
}

cb_smh_write_result(
    'START ' . date('Y-m-d H:i:s') . "\n" .
    'currentMonday=' . $currentMonday->format('Y-m-d') . "\n" .
    'probeFrom=' . $probeFromMonday->format('Y-m-d') . "\n" .
    'minDate=' . $minMonday->format('Y-m-d') . "\n" .
    'branches=' . count($branches) . "\n" .
    "----------------------------------------\n",
    false
);

@file_put_contents(CB_LOG_FILE, '', LOCK_EX);
cb_smh_log('START', 'pocet_pobocek=' . count($branches) . ' probeFrom=' . $probeFromMonday->format('Y-m-d') . ' minDate=' . $minMonday->format('Y-m-d'));

foreach ($branches as $branchIndex => $branch) {
    $branchId = (int)$branch['id'];
    $branchName = (string)$branch['name'];

    cb_smh_log('POBOCKA', 'id=' . $branchId . ' name=' . $branchName);

    $cursor = clone $probeFromMonday;
    $foundStart = null;
    $firstNoData = null;
    $lastData = null;
    $tested = 0;

    while ($cursor >= $minMonday) {
        $skipWeeks = cb_smh_skip_weeks($cursor, $currentMonday);
        $probe = cb_smh_week_probe($branchId, $skipWeeks, $token);
        $hasData = (bool)($probe['has_data'] ?? false);
        $tested++;

        cb_smh_log(
            'PROBE',
            'skipweeks=' . $skipWeeks .
            ' - Pob: ' . $branchId .
            ' pondělí=' . $cursor->format('Y-m-d') .
            ' / Data: ' . ($hasData ? 'ANO' : 'NE')
        );

        if ($hasData) {
            $lastData = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('-1 week');
            continue;
        }

        $firstNoData = $cursor->format('Y-m-d');
        $foundStart = $cursor->modify('+1 week')->format('Y-m-d');
        cb_smh_log('START', 'id=' . $branchId . ' start=' . $foundStart . ' prvni_bez_dat=' . $firstNoData);
        break;
    }

    if ($foundStart === null) {
        $foundStart = $minMonday->format('Y-m-d');
        cb_smh_log('LIMIT', 'id=' . $branchId . ' dosazeno_minDate=' . $foundStart . ' lastData=' . ($lastData ?? '---'));
    }

    cb_smh_write_result(
        'id_pob=' . $branchId .
        ' | nazev=' . $branchName .
        ' | start=' . $foundStart .
        ' | prvni_bez_dat=' . ($firstNoData ?? '---') .
        ' | posledni_data=' . ($lastData ?? '---') .
        ' | testu=' . $tested . "\n"
    );

    if ($branchIndex < (count($branches) - 1)) {
        usleep(500000);
    }
}

cb_smh_log('HOTOVO', 'zapsano_do=' . CB_START_FILE);
echo "HOTOVO\n";
