<?php
// Ukládá finální denní report včetně důvodů storen a vrací klientovi konkrétní bezpečnou odpověď.
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
$action = trim((string)($_POST['dr_action'] ?? ''));
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

if (!cb_denni_report_ma_pravo(CB_DENNI_REPORT_ZOBRAZIT_PRAVO)) {
    $sendJson(403, ['ok' => false, 'err' => 'Nemate pravo zobrazit denni report']);
}
$canCloseReport = cb_denni_report_ma_pravo(CB_DENNI_REPORT_UZAVRIT_PRAVO);
$canEditSavedReport = cb_denni_report_ma_pravo(CB_DENNI_REPORT_EDITOVAT_PRAVO);

$stmtAllowed = $conn->prepare('SELECT 1 FROM hr_person hp WHERE hp.id_person = ? AND (hp.pristup_vsechny_pobocky = 1 OR EXISTS (SELECT 1 FROM hr_pracoviste prac WHERE prac.id_person = hp.id_person AND prac.id_pob = ? AND prac.platny = 1)) LIMIT 1');
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
$stmtMainBranch = $conn->prepare('SELECT 1 FROM hr_pracoviste WHERE id_person = ? AND id_pob = ? AND hlavni = 1 AND platny = 1 LIMIT 1');
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
$canFinalizeCurrentNew = $isCurrentWorkday && !$currentFinalExists && $canCloseReport;
$canFinalizeCurrentEdit = $isCurrentWorkday && $currentFinalExists && $requestedFinalEdit && $canEditSavedReport && $isMainBranch;
$canFinalizeHistoryNew = !$isCurrentWorkday && !$historyReportExists && $canCloseReport;
$canFinalizeHistoryEdit = !$isCurrentWorkday && $historyReportExists && $requestedFinalEdit && $canEditSavedReport && $isMainBranch;

$rozdilFormRaw = trim((string)($_POST['rozdil'] ?? ''));
$colPomerFormRaw = trim((string)($_POST['col_pomer'] ?? ''));
$rozdilForm = $rozdilFormRaw === '' ? null : cb_vcr_float($rozdilFormRaw);
$colPomerForm = $colPomerFormRaw === '' ? null : cb_vcr_float($colPomerFormRaw);
$historyRestiaSummary = static function (array $historyReport): array {
    $restiaSummary = cb_denni_report_restia_summary_default();
    $restiaSummary['trzba'] = (float)($historyReport['trzba'] ?? 0);
    $restiaSummary['wolt'] = (float)($historyReport['wolt'] ?? 0);
    $restiaSummary['wolt_count'] = (int)($historyReport['wolt_obj'] ?? 0);
    $restiaSummary['bolt'] = (float)($historyReport['bolt'] ?? 0);
    $restiaSummary['bolt_count'] = (int)($historyReport['bolt_obj'] ?? 0);
    $restiaSummary['dj'] = (float)($historyReport['damejidlo'] ?? 0);
    $restiaSummary['dj_count'] = (int)($historyReport['damejidlo_obj'] ?? 0);
    $restiaSummary['web'] = (float)($historyReport['web'] ?? 0);
    $restiaSummary['web_count'] = (int)($historyReport['web_obj'] ?? 0);
    $restiaSummary['wolt_cash'] = (float)($historyReport['wolt_cash'] ?? 0);
    $restiaSummary['wolt_cash_count'] = (int)($historyReport['wolt_cash_obj'] ?? 0);
    $restiaSummary['dj_cash'] = (float)($historyReport['dj_cash'] ?? 0);
    $restiaSummary['dj_cash_count'] = (int)($historyReport['dj_cash_obj'] ?? 0);
    $restiaSummary['cancel_count'] = (int)($historyReport['zrusene_obj_ks'] ?? 0);
    $restiaSummary['cancel_value'] = (float)($historyReport['zrusene_obj_kc'] ?? 0);
    $restiaSummary['delay_count'] = (int)($historyReport['zpozdene_rozvozy_5_min'] ?? 0);
    $restiaSummary['make_time_avg_sec'] = isset($historyReport['make_time_prumer_sec']) ? (int)$historyReport['make_time_prumer_sec'] : null;
    $restiaSummary['orders_total'] = (int)($historyReport['objednavky_nezrusene_ks'] ?? 0);
    $restiaSummary['own_deliveries'] = (int)($historyReport['nase_rozvozy_ks'] ?? 0);
    $restiaSummary['woltdrive_count'] = (int)($historyReport['woltdrive_ks'] ?? 0);
    $restiaSummary['woltdrive_late'] = (int)($historyReport['woltdrive_pozde_5_min'] ?? 0);
    $restiaSummary['woltdrive_our_fault'] = (int)($historyReport['woltdrive_pozde_nase_vina'] ?? 0);
    $restiaSummary['own_delivery_late_ratio'] = isset($historyReport['nase_rozvozy_pozde_pomer']) ? (float)$historyReport['nase_rozvozy_pozde_pomer'] : null;
    $restiaSummary['woltdrive_late_count'] = (int)($historyReport['woltdrive_zpozdene_ks'] ?? 0);
    $restiaSummary['delivered_on_time_ratio'] = isset($historyReport['doruceno_vcas_pomer']) ? (float)$historyReport['doruceno_vcas_pomer'] : null;
    $restiaSummary['woltdrive_late_ratio'] = isset($historyReport['woltdrive_zpozdene_pomer']) ? (float)$historyReport['woltdrive_zpozdene_pomer'] : null;

    return $restiaSummary;
};

// Důvody storen ověříme proti stejné pobočce a provoznímu dni, aby nešlo podvrhnout cizí objednávku.
$saveStornoNotes = static function (array $notes) use ($conn, $idPob, $datum): void {
    if ($notes === []) {
        return;
    }
    if (count($notes) > 100) {
        throw new CbUserVisibleException('Report obsahuje příliš mnoho důvodů stornovaných objednávek.');
    }

    $workdayRange = cb_dt_workday_range_utc($datum);
    $fromDb = (string)$workdayRange['from_db'];
    $toDb = (string)$workdayRange['to_db'];
    $stmtOrder = $conn->prepare("
        SELECT os.id_obj AS saved_id
        FROM objednavky_restia o
        INNER JOIN obj_casy ca ON ca.id_obj = o.id_obj
        INNER JOIN cis_obj_stav s ON s.id_stav = o.id_stav
        LEFT JOIN obj_storno os ON os.id_obj = o.id_obj
        WHERE o.id_obj = ?
          AND o.id_pob = ?
          AND ca.report >= DATE(?)
          AND ca.report < DATE(?)
          AND s.nazev IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted')
        LIMIT 1
    ");
    $stmtSave = $conn->prepare('
        INSERT INTO obj_storno (id_obj, poznamka)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE poznamka = VALUES(poznamka)
    ');
    if ($stmtOrder === false || $stmtSave === false) {
        if ($stmtOrder instanceof mysqli_stmt) {
            $stmtOrder->close();
        }
        if ($stmtSave instanceof mysqli_stmt) {
            $stmtSave->close();
        }
        throw new RuntimeException('Nelze připravit uložení důvodů storen.');
    }

    try {
        foreach ($notes as $rawIdObj => $rawValue) {
            $idObj = (int)$rawIdObj;
            if ($idObj <= 0 || is_array($rawValue)) {
                throw new CbUserVisibleException('Důvod storna obsahuje neplatnou objednávku.');
            }
            $value = trim((string)$rawValue);
            $value = function_exists('mb_substr')
                ? mb_substr($value, 0, 255, 'UTF-8')
                : substr($value, 0, 255);

            $stmtOrder->bind_param('iiss', $idObj, $idPob, $fromDb, $toDb);
            $stmtOrder->execute();
            $orderResult = $stmtOrder->get_result();
            $orderRow = $orderResult instanceof mysqli_result ? ($orderResult->fetch_assoc() ?: null) : null;
            if ($orderResult instanceof mysqli_result) {
                $orderResult->free();
            }
            if (!is_array($orderRow)) {
                throw new CbUserVisibleException('Stornovaná objednávka nepatří k tomuto reportu.');
            }

            // Prázdný nový důvod nemusí vytvářet zbytečný řádek; prázdná hodnota ale umí smazat starý text.
            if ($value === '' && $orderRow['saved_id'] === null) {
                continue;
            }
            $stmtSave->bind_param('is', $idObj, $value);
            $stmtSave->execute();
        }
    } finally {
        $stmtOrder->close();
        $stmtSave->close();
    }
};

try {
    if ($action === 'prepocet_col_rozdil') {
        if ($isCurrentWorkday) {
            if (!$canFinalizeCurrentEdit) {
                $sendJson(403, ['ok' => false, 'err' => 'Nemate pravo prepocitat report']);
            }
            $workdayRange = cb_dt_workday_range_utc($datum);
            $restiaSummary = cb_denni_report_restia_summary($conn, $idPob, $workdayRange);
        } else {
            if (!$canFinalizeHistoryNew && !$canFinalizeHistoryEdit) {
                $sendJson(403, ['ok' => false, 'err' => 'Nemate pravo prepocitat report']);
            }
            if ($historyReportExists) {
                $historyReport = is_array($historyData) ? (array)($historyData['report'] ?? []) : [];
                $restiaSummary = $historyRestiaSummary($historyReport);
            } else {
                $workdayRange = cb_dt_workday_range_utc($datum);
                $restiaSummary = cb_denni_report_restia_summary($conn, $idPob, $workdayRange);
            }
        }

        $values = cb_vypocet_col_rozdil(
            $conn,
            $datum,
            $restiaSummary,
            cb_vcr_cash_from_post($_POST),
            cb_vcr_people_from_post($_POST)
        );
        $rozdil = $values['rozdil'];
        $colPomer = $values['col_pomer'];
        $sendJson(200, [
            'ok' => true,
            'rozdil' => $rozdil === null ? null : round((float)$rozdil, 2),
            'rozdil_label' => $rozdil === null ? '-- Kč' : cb_format('p', $rozdil),
            'col_pomer' => $colPomer === null ? null : round((float)$colPomer, 6),
            'col_label' => $colPomer === null ? '-- %' : cb_format('pr', $colPomer),
        ]);
    }

    if ($isCurrentWorkday) {
        if (!$canFinalizeCurrentNew && !$canFinalizeCurrentEdit) {
            $sendJson(403, ['ok' => false, 'err' => 'Nemate pravo ulozit report']);
        }
        $workdayRange = cb_dt_workday_range_utc($datum);
        $restiaSummary = cb_denni_report_restia_summary($conn, $idPob, $workdayRange);
        $saveStornoNotes(is_array($_POST['storno_poznamka'] ?? null) ? $_POST['storno_poznamka'] : []);
        $idReportu = cb_db_zapis_denni_report_from_form($conn, $idPob, $datum, $currentUserId, $restiaSummary, $_POST, $rozdilForm, $colPomerForm, $canFinalizeCurrentEdit);
        $sendJson(200, ['ok' => true, 'id_reportu' => $idReportu]);
    }

    if (!$canFinalizeHistoryNew && !$canFinalizeHistoryEdit) {
        $sendJson(403, ['ok' => false, 'err' => 'Nemate pravo ulozit historicky report']);
    }

    if ($historyReportExists) {
        $historyReport = is_array($historyData) ? (array)($historyData['report'] ?? []) : [];
        $restiaSummary = $historyRestiaSummary($historyReport);
    } else {
        $workdayRange = cb_dt_workday_range_utc($datum);
        $restiaSummary = cb_denni_report_restia_summary($conn, $idPob, $workdayRange);
    }
    $saveStornoNotes(is_array($_POST['storno_poznamka'] ?? null) ? $_POST['storno_poznamka'] : []);
    $idReportu = cb_db_zapis_denni_report_from_form($conn, $idPob, $datum, $currentUserId, $restiaSummary, $_POST, $rozdilForm, $colPomerForm, $historyReportExists);
    $sendJson(200, ['ok' => true, 'id_reportu' => $idReportu]);
} catch (Throwable $e) {
    $message = trim($e->getMessage());
    if (
        $message === cb_db_zapis_denni_report_already_saved_message()
        || cb_db_zapis_denni_report_is_duplicate_error($e, 'uq_reporty_is_pob_datum')
    ) {
        $sendJson(409, ['ok' => false, 'err' => cb_db_zapis_denni_report_already_saved_message()]);
    }

    if ($e instanceof CbUserVisibleException && $message !== '') {
        $sendJson(500, ['ok' => false, 'err' => $message]);
    }

    $errorMessage = cb_chyba_uzivatel($e, [
        'module' => 'PROVOZ',
        'action' => 'Uložení denního reportu',
    ]);
    $sendJson(500, ['ok' => false, 'err' => $errorMessage]);
}
