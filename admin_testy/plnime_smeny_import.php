<?php
// admin_testy/plnime_smeny_import.php * Verze: V2 * Aktualizace: 16.3.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/smeny_graphql.php';

const CB_PSI_GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';
const CB_PSI_STATE_FILE = __DIR__ . '/../_kandidati/plnime_smeny_import_state.txt';
const CB_PSI_OUT_FILE = __DIR__ . '/../_kandidati/smeny_import.txt';

function cb_psi_count_occupied_hours(array $week): int
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

function cb_psi_start_day_to_date(string $startDay): string
{
    $dt = new DateTimeImmutable($startDay);
    return $dt->format('Y-m-d');
}

function cb_psi_load_state(int $skipFrom): int
{
    if (!file_exists(CB_PSI_STATE_FILE)) {
        $stateInit = [
            'next_skip' => $skipFrom,
        ];

        $ok = file_put_contents(CB_PSI_STATE_FILE, json_encode($stateInit, JSON_UNESCAPED_UNICODE), LOCK_EX);
        if ($ok === false) {
            throw new RuntimeException('Nelze vytvořit _kandidati/plnime_smeny_import_state.txt');
        }

        return $skipFrom;
    }

    $stateRaw = trim((string)@file_get_contents(CB_PSI_STATE_FILE));
    if ($stateRaw === '') {
        return $skipFrom;
    }

    $state = json_decode($stateRaw, true);
    if (!is_array($state)) {
        return $skipFrom;
    }

    return (int)($state['next_skip'] ?? $skipFrom);
}

function cb_psi_save_state(int $nextSkip): void
{
    $state = [
        'next_skip' => $nextSkip,
    ];

    $ok = file_put_contents(CB_PSI_STATE_FILE, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($ok === false) {
        throw new RuntimeException('Nelze zapsat _kandidati/plnime_smeny_import_state.txt');
    }
}

$token = (string)($_SESSION['cb_token'] ?? '');
$skipFrom = -284;
$skipTo = 0;
$cycleSize = 4;
$graphqlTimeout = 3;
$reloadMs = 500;
$pobocky = [1, 2, 3, 4, 5, 6];
$monitorLog = [];

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($token === '') {
    echo '<pre>CHYBA: chybí session token cb_token</pre>';
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

try {
    $nextSkip = cb_psi_load_state($skipFrom);

    if ($nextSkip > $skipTo) {
        echo '<pre>HOTOVO</pre>';
        exit;
    }

    $cycleStart = $nextSkip;
    $cycleEnd = min($cycleStart + $cycleSize - 1, $skipTo);
    $rowCount = (($cycleEnd - $cycleStart) + 1) * count($pobocky);
    $timeStart = microtime(true);
    $writeLines = [];
    $insertedAno = 0;

    for ($skipWeeks = $cycleStart; $skipWeeks <= $cycleEnd; $skipWeeks++) {
        foreach ($pobocky as $branchId) {
            $startDay = '';
            $occupiedHours = 0;
            $hasData = 'NE';

            try {
                $result = cb_smeny_graphql(
                    CB_PSI_GQL_URL,
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
                    $occupiedHours = cb_psi_count_occupied_hours($week);
                    $hasData = $occupiedHours > 0 ? 'ANO' : 'NE';
                }
            } catch (Throwable $e) {
                $hasData = 'NE';
                $occupiedHours = 0;
                $startDay = '';
            }

            if ($hasData === 'ANO' && $startDay !== '') {
                $startDayDate = cb_psi_start_day_to_date($startDay);

                $writeLines[] =
                    'start_day=' . $startDayDate .
                    ' | id_pob=' . $branchId .
                    ' | pocet_hodin=' . $occupiedHours;

                $insertedAno++;
            }

            $monitorLog[] =
                'skipWeeks=' . $skipWeeks .
                ' | id_pob=' . $branchId .
                ' | datum_od=' . $startDay .
                ' | ma_data=' . $hasData .
                ' | pocet_hodin=' . $occupiedHours;
        }
    }

    if (!file_exists(CB_PSI_OUT_FILE)) {
        $headerText =
            'PLNENI smeny_import' . "\n" .
            'cas startu: ' . date('Y-m-d H:i:s') . "\n" .
            'rozsah skipWeeks: ' . $skipFrom . ' az ' . $skipTo . "\n" .
            'cyklus: ' . $cycleSize . "\n" .
            "----------------------------------------\n";

        $okHeader = file_put_contents(CB_PSI_OUT_FILE, $headerText, LOCK_EX);
        if ($okHeader === false) {
            throw new RuntimeException('Nelze vytvořit _kandidati/smeny_import.txt');
        }
    }

    if (!empty($writeLines)) {
        $okAppend = file_put_contents(CB_PSI_OUT_FILE, implode("\n", $writeLines) . "\n", FILE_APPEND | LOCK_EX);
        if ($okAppend === false) {
            throw new RuntimeException('Nelze zapsat do _kandidati/smeny_import.txt');
        }
    }

    $nextSkip = $cycleEnd + 1;
    cb_psi_save_state($nextSkip);

    $durationMs = (int)round((microtime(true) - $timeStart) * 1000);

    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Plníme smeny_import</title>';
    if ($nextSkip <= $skipTo) {
        echo '<script>setTimeout(function(){ window.location.reload(); }, ' . (string)$reloadMs . ');</script>';
    }
    echo '</head><body><pre>';

    echo 'skipWeeks=' . $cycleStart .
        ' cyklus=' . $cycleSize .
        ' radku=' . $rowCount .
        ' zapsano_ano=' . $insertedAno .
        ' cas: ' . $durationMs . " ms\n\n";

    foreach ($monitorLog as $line) {
        echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
    }

    if ($nextSkip <= $skipTo) {
        echo "\nDalší cyklus za 0.5 s...\n";
    } else {
        echo "\nHOTOVO\n";
    }

    echo '</pre></body></html>';
    exit;

} catch (Throwable $e) {
    echo '<!doctype html><html lang="cs"><head><meta charset="utf-8"><title>Plníme smeny_import</title></head><body><pre>';
    echo 'CHYBA' . "\n";
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n";
    echo '</pre></body></html>';
    exit;
}
