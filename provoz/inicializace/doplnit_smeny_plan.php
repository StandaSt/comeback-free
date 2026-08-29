<?php
// inicializace/doplnit_smeny_plan.php * Jednoucelove doplneni chybejicich tydnu smeny_plan
declare(strict_types=1);

/*
 * Spuštění:
 *   php _www/provoz/inicializace/doplnit_smeny_plan.php --db=local
 *   php www/provoz/inicializace/doplnit_smeny_plan.php --db=server
 *
 * Skript doplňuje pouze pobočka–týdny, pro které v smeny_plan není žádný řádek.
 * Existující týdny nepřepisuje a prázdná odpověď API nic z DB nemaže.
 */

$directRun = defined('CB_SMENY_PLAN_DOPLNIT_DIRECT') && CB_SMENY_PLAN_DOPLNIT_DIRECT === true;
if (!$directRun && PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Tento skript lze spustit pouze z příkazové řádky.\n");
    exit(1);
}

$options = $directRun ? [] : getopt('', ['db:']);
$environment = $directRun
    ? (string)($GLOBALS['CB_SMENY_PLAN_DOPLNIT_ENVIRONMENT'] ?? '')
    : (string)($options['db'] ?? '');
if (!in_array($environment, ['local', 'server'], true)) {
    $message = 'Použití: php provoz/inicializace/doplnit_smeny_plan.php --db=local|server';
    if ($directRun) {
        throw new RuntimeException($message);
    }
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!defined('CB_SMENY_PLAN_KONTROLA_AUTO_RUN')) {
    define('CB_SMENY_PLAN_KONTROLA_AUTO_RUN', false);
}
require_once __DIR__ . '/../../common/lib/smeny_plan_kontrola.php';

$PROSTREDI = $environment === 'local' ? 'LOCAL' : 'SERVER';
$GLOBALS['PROSTREDI'] = $PROSTREDI;
if (!defined('CB_SMENY_PLAN_LIBRARY_ONLY')) {
    define('CB_SMENY_PLAN_LIBRARY_ONLY', true);
}
require_once __DIR__ . '/plnime_smeny_plan.php';

$sessionSnapshot = $directRun ? cb_smeny_plan_session_snapshot() : [];
$dbLock = '';
$db = null;

try {
    set_time_limit(0);
    $token = cb_smeny_plan_kontrola_login();
    $_SESSION['cb_token'] = $token;

    $db = db();
    $dbLock = cb_smeny_plan_kontrola_lock_acquire($db, 10);
    $branches = getBranches($token);
    $currentMonday = currentWeekMonday();

    $existsStmt = $db->prepare('SELECT 1 FROM smeny_plan WHERE id_pob = ? AND start_day = ? LIMIT 1');
    if (!$existsStmt instanceof mysqli_stmt) {
        throw new RuntimeException('Nepodařilo se připravit kontrolu existujících týdnů.');
    }

    $counts = [
        'pobocky' => 0,
        'tydny_zkontrolovane' => 0,
        'tydny_existujici' => 0,
        'tydny_doplnene' => 0,
        'tydny_prazdne_api' => 0,
        'bloky_doplnene' => 0,
        'chyby' => 0,
    ];

    foreach ($branches as $branch) {
        $idPob = (int)($branch['id_pob'] ?? 0);
        $apiBranchId = (int)($branch['smeny_branch_id'] ?? 0);
        $branchName = trim((string)($branch['nazev'] ?? ''));
        $startSmeny = trim((string)($branch['start_smeny'] ?? ''));
        if ($idPob <= 0 || $apiBranchId <= 0 || $startSmeny === '') {
            $counts['chyby']++;
            continue;
        }

        $counts['pobocky']++;
        $week = normalizeMonday($startSmeny);
        while ($week <= $currentMonday) {
            $startDay = $week->format('Y-m-d');
            $counts['tydny_zkontrolovane']++;

            $existsStmt->bind_param('is', $idPob, $startDay);
            $existsStmt->execute();
            $existsResult = $existsStmt->get_result();
            $exists = $existsResult instanceof mysqli_result && $existsResult->fetch_assoc() !== null;
            if ($existsResult instanceof mysqli_result) {
                $existsResult->free();
            }

            if ($exists) {
                $counts['tydny_existujici']++;
                $week = $week->modify('+1 week');
                continue;
            }

            $skipWeeks = calculateSkipWeeksByStartDay($startDay);
            $result = processPlannedWeekUpdate($token, $skipWeeks, $idPob, $apiBranchId, $branchName);
            if ((string)($result['status'] ?? '') !== 'OK') {
                $counts['chyby']++;
            } elseif ((int)($result['blocks'] ?? 0) > 0) {
                $counts['tydny_doplnene']++;
                $counts['bloky_doplnene'] += (int)$result['blocks'];
            } else {
                $counts['tydny_prazdne_api']++;
            }

            if (($counts['tydny_zkontrolovane'] % 20) === 0) {
                usleep(CB_SMENY_PLAN_SLEEP_BETWEEN_WEEKS_US);
            }
            $week = $week->modify('+1 week');
        }
    }
    $existsStmt->close();

    $output = 'Doplnění naplánovaných směn dokončeno pro prostředí ' . $environment . '. '
        . 'Pobočky: ' . $counts['pobocky'] . ', '
        . 'doplněné týdny: ' . $counts['tydny_doplnene'] . ', '
        . 'doplněné bloky: ' . $counts['bloky_doplnene'] . ', '
        . 'prázdná odpověď API: ' . $counts['tydny_prazdne_api'] . ', '
        . 'chyby: ' . $counts['chyby'] . '.';

    if ($directRun) {
        $GLOBALS['CB_SMENY_PLAN_DOPLNIT_OUTPUT'] = $output;
        $GLOBALS['CB_SMENY_PLAN_DOPLNIT_COUNTS'] = $counts;
    } else {
        echo $output . PHP_EOL;
    }
} catch (Throwable $e) {
    if ($directRun) {
        throw $e;
    }
    fwrite(STDERR, 'Doplnění směn selhalo: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    if ($db instanceof mysqli) {
        try {
            db_api_smeny_flush($db, null, null);
        } catch (Throwable $e) {
        }
        cb_smeny_plan_kontrola_lock_release($db, $dbLock);
    }
    if ($directRun) {
        cb_smeny_plan_session_restore($sessionSnapshot);
    }
}
