<?php
// admin_testy/smeny_week.php * Verze: V11 * Aktualizace: 16.3.2026
declare(strict_types=1);

/*
 * CO TENHLE SCRIPT DĚLÁ
 * =====================
 * 1) Testuje historii týdnů ve Směnách přes skipWeeks.
 * 2) Jedno spuštění scriptu udělá právě JEDEN CYKLUS.
 * 3) Jeden cyklus má pevně 5 skipWeeks.
 * 4) U každého z těch 5 skipWeeks projde všech 6 poboček.
 * 5) Výsledek cyklu:
 *    - zapíše do _kandidati/smeny_week.txt
 *    - vypíše na monitor
 *    - uloží si, kde skončil
 *    - ukončí aktuální request
 * 6) Po 0.5 s se browser zavolá znovu a pokračuje od dalšího skipWeeks.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';

const CB_SMENY_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const CB_OUT_FILE = __DIR__ . '/../_kandidati/smeny_week.txt';
const CB_STATE_FILE = __DIR__ . '/../_kandidati/smeny_week_state.txt';

$token = (string)($_SESSION['cb_token'] ?? '');
$skipFrom = -284;
$skipTo = 0;
$cycleSize = 5;
$graphqlTimeout = 10;
$refreshSeconds = '0.5';
$pobocky = [1, 2, 3, 4, 5, 6];

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($token === '') {
    $ok = file_put_contents(CB_OUT_FILE, "CHYBA: chybi session token cb_token\n", LOCK_EX);
    if ($ok === false) {
        echo "CHYBA: chybi session token cb_token + nelze zapsat do _kandidati/smeny_week.txt\n";
        exit;
    }

    echo "CHYBA: chybi session token cb_token\n";
    exit;
}

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

function cb_sw_count_occupied_hours(array $week): int
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

if (!file_exists(CB_STATE_FILE)) {
    $stateInit = [
        'next_skip' => $skipFrom,
    ];

    $okState = file_put_contents(CB_STATE_FILE, json_encode($stateInit, JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($okState === false) {
        echo "CHYBA: nelze vytvorit _kandidati/smeny_week_state.txt\n";
        exit;
    }

    $header =
        'TEST pobocek po tydnech' . "\n" .
        'cas startu: ' . date('Y-m-d H:i:s') . "\n" .
        'rozsah skipWeeks: ' . $skipFrom . ' az ' . $skipTo . "\n" .
        'pobocky: ' . implode(', ', $pobocky) . "\n" .
        'cyklus: ' . $cycleSize . "\n" .
        "----------------------------------------\n";

    $okHeader = file_put_contents(CB_OUT_FILE, $header, LOCK_EX);
    if ($okHeader === false) {
        echo "CHYBA: nelze vytvorit _kandidati/smeny_week.txt\n";
        exit;
    }
}

$stateRaw = trim((string)@file_get_contents(CB_STATE_FILE));
$state = json_decode($stateRaw, true);
$nextSkip = (int)($state['next_skip'] ?? $skipFrom);

if ($nextSkip > $skipTo) {
    echo "HOTOVO\n";
    exit;
}

$cycleStart = $nextSkip;
$cycleEnd = min($cycleStart + $cycleSize - 1, $skipTo);
$rowCount = (($cycleEnd - $cycleStart) + 1) * count($pobocky);
$cycleLog = [];
$timeStart = microtime(true);

for ($skipWeeks = $cycleStart; $skipWeeks <= $cycleEnd; $skipWeeks++) {
    foreach ($pobocky as $branchId) {
        $startDay = '';
        $occupiedHours = 0;
        $hasData = 'NE';

        try {
            $result = cb_smeny_graphql(
                CB_SMENY_GQL_URL,
                $query,
                [
                    'branchId' => $branchId,
                    'skipWeeks' => $skipWeeks,
                ],
                $token,
                $graphqlTimeout
            );

            if (isset($result['branchGetShiftWeek']) && is_array($result['branchGetShiftWeek'])) {
                $week = $result['branchGetShiftWeek'];
                $startDay = (string)($week['startDay'] ?? '');
                $occupiedHours = cb_sw_count_occupied_hours($week);
                $hasData = $occupiedHours > 0 ? 'ANO' : 'NE';
            }
        } catch (Throwable $e) {
            $hasData = 'NE';
            $occupiedHours = 0;
            $startDay = '';
        }

        $cycleLog[] =
            'skipWeeks=' . $skipWeeks .
            ' | id_pob=' . $branchId .
            ' | datum_od=' . $startDay .
            ' | ma_data=' . $hasData .
            ' | pocet_hodin=' . $occupiedHours;
    }
}

$durationMs = (int)round((microtime(true) - $timeStart) * 1000);

$okAppend = file_put_contents(CB_OUT_FILE, implode("\n", $cycleLog) . "\n", FILE_APPEND | LOCK_EX);
if ($okAppend === false) {
    echo "CHYBA: zapis do _kandidati/smeny_week.txt selhal\n";
    exit;
}

$nextSkip = $cycleEnd + 1;
$newState = [
    'next_skip' => $nextSkip,
];

$okState = file_put_contents(CB_STATE_FILE, json_encode($newState, JSON_UNESCAPED_UNICODE), LOCK_EX);
if ($okState === false) {
    echo "CHYBA: zapis do _kandidati/smeny_week_state.txt selhal\n";
    exit;
}

echo 'skipWeeks=' . $cycleStart .
    ' cyklus=' . $cycleSize .
    ' radku=' . $rowCount .
    ' cas: ' . $durationMs . " ms\n";

if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
}
flush();

if ($nextSkip <= $skipTo) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($requestUri !== '') {
        header('Refresh: ' . $refreshSeconds . '; url=' . $requestUri);
    }
}

exit;
