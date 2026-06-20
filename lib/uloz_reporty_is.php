<?php
// lib/uloz_reporty_is.php * K10 finalni ulozeni reportu do reporty_is*
declare(strict_types=1);

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !isset($_SERVER['HTTP_X_COMEBACK_REPORTY_IS'])
) {
    return;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db/db_zapis_denni_report.php';
require_once __DIR__ . '/format_datum_cas.php';
require_once __DIR__ . '/vypocty_report.php';
require_once __DIR__ . '/vypocet_col_rozdil.php';
require_once __DIR__ . '/denni_report_data.php';

$sendJson = static function (int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

$currentUser = $_SESSION['cb_user'] ?? [];
$currentUserId = is_array($currentUser) ? (int)($currentUser['id_user'] ?? 0) : 0;
if ($currentUserId <= 0 || empty($_SESSION['login_ok'])) {
    $sendJson(401, ['ok' => false, 'err' => 'Nutne prihlaseni']);
}

$idPob = (int)($_POST['id_pob'] ?? 0);
$datum = trim((string)($_POST['datum_reportu'] ?? ''));
if ($idPob <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
    $sendJson(422, ['ok' => false, 'err' => 'Neplatny pozadavek']);
}

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$currentWorkday = cb_denni_report_current_workday_date()->format('Y-m-d');
$isCurrentWorkday = ($datum === $currentWorkday);
$requestedFinalEdit = ((int)($_POST['zr_edit_final'] ?? 0)) === 1;

$roleIds = [];
$roleId = is_array($currentUser) ? (int)($currentUser['id_role'] ?? 0) : 0;
if ($roleId > 0) {
    $roleIds[$roleId] = true;
}
$stmtRoles = $conn->prepare('SELECT id_role FROM user_role WHERE id_user = ?');
if ($stmtRoles !== false) {
    $stmtRoles->bind_param('i', $currentUserId);
    $stmtRoles->execute();
    $rolesResult = $stmtRoles->get_result();
    if ($rolesResult instanceof mysqli_result) {
        while ($row = $rolesResult->fetch_assoc()) {
            $idRole = (int)($row['id_role'] ?? 0);
            if ($idRole > 0) {
                $roleIds[$idRole] = true;
            }
        }
        $rolesResult->free();
    }
    $stmtRoles->close();
}

$stmtAllowed = $conn->prepare('SELECT 1 FROM user_pobocka WHERE id_user = ? AND id_pob = ? LIMIT 1');
if ($stmtAllowed === false) {
    $sendJson(500, ['ok' => false, 'err' => 'Nelze overit pobocku']);
}
$stmtAllowed->bind_param('ii', $currentUserId, $idPob);
$stmtAllowed->execute();
$allowedResult = $stmtAllowed->get_result();
$isAllowed = $allowedResult instanceof mysqli_result && $allowedResult->num_rows > 0;
if ($allowedResult instanceof mysqli_result) {
    $allowedResult->free();
}
$stmtAllowed->close();
if (!$isAllowed) {
    $sendJson(403, ['ok' => false, 'err' => 'Pobocka neni povolena']);
}

$isMainBranch = false;
$stmtMainBranch = $conn->prepare('SELECT 1 FROM user_pobocka WHERE id_user = ? AND id_pob = ? AND main = 1 LIMIT 1');
if ($stmtMainBranch !== false) {
    $stmtMainBranch->bind_param('ii', $currentUserId, $idPob);
    $stmtMainBranch->execute();
    $mainResult = $stmtMainBranch->get_result();
    $isMainBranch = $mainResult instanceof mysqli_result && $mainResult->num_rows > 0;
    if ($mainResult instanceof mysqli_result) {
        $mainResult->free();
    }
    $stmtMainBranch->close();
}

$historyData = (!$isCurrentWorkday) ? cb_denni_report_history_load($conn, $idPob, $datum) : null;
$historyReportExists = is_array($historyData) && (int)(($historyData['report']['id_reportu'] ?? 0)) > 0;
$activeCurrentReportId = $isCurrentWorkday ? cb_db_reporty_is_find_active_id($conn, $idPob, $datum) : 0;
$currentFinalExists = $activeCurrentReportId > 0;
$canFinalizeCurrentNew = $isCurrentWorkday && !$currentFinalExists && (isset($roleIds[5]) || isset($roleIds[7]));
$canFinalizeCurrentEdit = $isCurrentWorkday && $currentFinalExists && $requestedFinalEdit && isset($roleIds[5]) && $isMainBranch;
$canFinalizeHistory = !$isCurrentWorkday && $requestedFinalEdit && isset($roleIds[5]) && $isMainBranch && $historyReportExists;

$rozdilFormRaw = trim((string)($_POST['rozdil'] ?? ''));
$colPomerFormRaw = trim((string)($_POST['col_pomer'] ?? ''));
$rozdilForm = $rozdilFormRaw === '' ? null : cb_vcr_float($rozdilFormRaw);
$colPomerForm = $colPomerFormRaw === '' ? null : cb_vcr_float($colPomerFormRaw);

try {
    if ($isCurrentWorkday) {
        if (!$canFinalizeCurrentNew && !$canFinalizeCurrentEdit) {
            $sendJson(403, ['ok' => false, 'err' => 'Nemate pravo ulozit report']);
        }
        $workdayRange = cb_dt_workday_range_utc($datum);
        $restiaSummary = cb_denni_report_restia_summary($conn, $idPob, $workdayRange);
        $idReportu = cb_db_zapis_denni_report_from_form($conn, $idPob, $datum, $currentUserId, $restiaSummary, $_POST, $rozdilForm, $colPomerForm, $canFinalizeCurrentEdit);
        $sendJson(200, ['ok' => true, 'id_reportu' => $idReportu]);
    }

    if (!$canFinalizeHistory) {
        $sendJson(403, ['ok' => false, 'err' => 'Nemate pravo upravit historicky report']);
    }

    $historyReport = is_array($historyData) ? (array)($historyData['report'] ?? []) : [];
    $restiaSummary = cb_denni_report_restia_summary_default();
    $restiaSummary['trzba'] = (float)($historyReport['trzba'] ?? 0);
    $restiaSummary['wolt'] = (float)($historyReport['wolt'] ?? 0);
    $restiaSummary['bolt'] = (float)($historyReport['bolt'] ?? 0);
    $restiaSummary['dj'] = (float)($historyReport['damejidlo'] ?? 0);
    $restiaSummary['web'] = (float)($historyReport['web'] ?? 0);
    $restiaSummary['wolt_cash'] = (float)($historyReport['wolt_cash'] ?? 0);
    $restiaSummary['dj_cash'] = (float)($historyReport['dj_cash'] ?? 0);
    $restiaSummary['cancel_count'] = (int)($historyReport['zrusene_obj_ks'] ?? 0);
    $restiaSummary['cancel_value'] = (float)($historyReport['zrusene_obj_kc'] ?? 0);
    $restiaSummary['delay_count'] = (int)($historyReport['zpozdene_rozvozy_5_min'] ?? 0);
    $restiaSummary['make_time_avg_sec'] = isset($historyReport['make_time_prumer_sec']) ? (int)$historyReport['make_time_prumer_sec'] : null;
    $restiaSummary['orders_total'] = (int)($historyReport['objednavky_nezrusene_ks'] ?? 0);
    $restiaSummary['own_deliveries'] = (int)($historyReport['nase_rozvozy_ks'] ?? 0);
    $restiaSummary['woltdrive_late'] = (int)($historyReport['woltdrive_pozde_5_min'] ?? 0);
    $idReportu = cb_db_zapis_denni_report_from_form($conn, $idPob, $datum, $currentUserId, $restiaSummary, $_POST, $rozdilForm, $colPomerForm, true);
    $sendJson(200, ['ok' => true, 'id_reportu' => $idReportu]);
} catch (Throwable $e) {
    $message = trim($e->getMessage());
    $sendJson(500, ['ok' => false, 'err' => $message !== '' ? $message : 'Ulozeni finalniho reportu selhalo']);
}
