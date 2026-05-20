<?php
// K10
// karty/zadani_reportu.php * Verze: V3 * Aktualizace: 12.05.2026
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$renderMode = isset($cbDashboardRenderMode) ? trim((string)$cbDashboardRenderMode) : '';
$isMaxRender = ($renderMode === 'max');

if ($renderMode === 'max' && function_exists('cb_restia_online_kontrola')) {
    cb_restia_online_kontrola(true);
}

$tz = new DateTimeZone('Europe/Prague');
$reportDateDt = cb_dt_workday_start(null, 6);
$reportDate = $reportDateDt->format('Y-m-d');
$reportDateDisplay = cb_dt_weekday_date_label_cs($reportDateDt, true);
$workdayRange = cb_dt_workday_range_utc($reportDate);
$currentUser = $_SESSION['cb_user'] ?? [];
$currentUserId = is_array($currentUser) ? (int)($currentUser['id_user'] ?? 0) : 0;
$currentUserRoleId = is_array($currentUser) ? (int)($currentUser['id_role'] ?? 0) : 0;
$canSaveReport = in_array($currentUserRoleId, [5, 7], true);
$allowedBranches = [];
if ($currentUserId > 0) {
    $stmtAllowedBranches = $conn->prepare("
        SELECT p.id_pob, p.nazev
        FROM user_pobocka up
        INNER JOIN pobocka p ON p.id_pob = up.id_pob
        WHERE up.id_user = ?
          AND p.aktivni = 1
        ORDER BY p.id_pob ASC
    ");
    if ($stmtAllowedBranches !== false) {
        $stmtAllowedBranches->bind_param('i', $currentUserId);
        $stmtAllowedBranches->execute();
        $allowedBranchesResult = $stmtAllowedBranches->get_result();
        if ($allowedBranchesResult instanceof mysqli_result) {
            while ($row = $allowedBranchesResult->fetch_assoc()) {
                $idPob = (int)($row['id_pob'] ?? 0);
                $name = trim((string)($row['nazev'] ?? ''));
                if ($idPob > 0) {
                    $allowedBranches[$idPob] = $name !== '' ? $name : ('Pobočka ' . $idPob);
                }
            }
            $allowedBranchesResult->free();
        }
        $stmtAllowedBranches->close();
    }
}
$allowedBranchIds = array_map('intval', array_keys($allowedBranches));
$requestedBranchId = (int)($_POST['zr_id_pob'] ?? $_GET['zr_id_pob'] ?? 0);
$reportBranchId = ($requestedBranchId > 0 && in_array($requestedBranchId, $allowedBranchIds, true)) ? $requestedBranchId : 0;
$singleAllowedBranchName = '';
if ($reportBranchId <= 0 && count($allowedBranches) === 1) {
    $reportBranchId = (int)array_key_first($allowedBranches);
}
if (count($allowedBranches) === 1 && $reportBranchId > 0 && isset($allowedBranches[$reportBranchId])) {
    $singleAllowedBranchName = (string)$allowedBranches[$reportBranchId];
}

if ($reportBranchId <= 0 && $canSaveReport && $currentUserId > 0) {
    $nowLocal = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
    $dateFrom = $reportDateDt->modify('-1 day')->format('Y-m-d');
    $dateTo = $reportDateDt->format('Y-m-d');
    $stmtCurrentShift = $conn->prepare("
        SELECT sp.id_pob
        FROM smeny_plan sp
        WHERE sp.id_user = ?
          AND sp.datum BETWEEN ? AND ?
          AND ? >= CONCAT(sp.datum, ' ', sp.cas_od)
          AND ? < DATE_ADD(CONCAT(sp.datum, ' ', sp.cas_do), INTERVAL CASE WHEN sp.cas_do <= sp.cas_od THEN 1 ELSE 0 END DAY)
        ORDER BY sp.id_slot = 1 DESC, sp.cas_od ASC
        LIMIT 1
    ");
    if ($stmtCurrentShift !== false) {
        $stmtCurrentShift->bind_param('issss', $currentUserId, $dateFrom, $dateTo, $nowLocal, $nowLocal);
        $stmtCurrentShift->execute();
        $currentShiftResult = $stmtCurrentShift->get_result();
        if ($currentShiftResult instanceof mysqli_result) {
            $currentShiftRow = $currentShiftResult->fetch_assoc() ?: [];
            $currentShiftBranchId = (int)($currentShiftRow['id_pob'] ?? 0);
            if ($currentShiftBranchId > 0 && in_array($currentShiftBranchId, $allowedBranchIds, true)) {
                $reportBranchId = $currentShiftBranchId;
            }
            $currentShiftResult->free();
        }
        $stmtCurrentShift->close();
    }
}

$formatMoney = static function (float $value): string {
    $rounded = round($value, 2);
    $decimals = (abs($rounded - round($rounded)) < 0.001) ? 0 : 2;
    return number_format($rounded, $decimals, ',', ' ') . ' Kc';
};

$formatInputNumber = static function (float $value): string {
    $rounded = round($value, 2);
    $decimals = (abs($rounded - round($rounded)) < 0.001) ? 0 : 2;
    return number_format($rounded, $decimals, '.', '');
};

$formatTime = static function (?string $value): string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m) === 1) {
        return sprintf('%02d:%s', (int)$m[1], $m[2]);
    }

    return cb_dt_time_hm($raw);
};

$personFullName = static function (?string $jmeno, ?string $prijmeni = null): string {
    return trim(trim((string)$jmeno) . ' ' . trim((string)$prijmeni));
};

$buildBranchSlotNameOptions = static function (mysqli $conn, int $idPob, int $idSlot): array {
    if ($idPob <= 0 || $idSlot <= 0) {
        return [];
    }

    $sql = "
        SELECT DISTINCT TRIM(CONCAT_WS(' ', u.jmeno, u.prijmeni)) AS full_name
        FROM user u
        INNER JOIN user_pobocka up ON up.id_user = u.id_user
        INNER JOIN user_slot us ON us.id_user = u.id_user
        WHERE u.aktivni = 1
          AND up.id_pob = ?
          AND us.id_slot = ?
        HAVING full_name <> ''
        ORDER BY full_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $names = [];
    if ($stmt !== false) {
        $stmt->bind_param('ii', $idPob, $idSlot);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $name = trim((string)($row['full_name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $result->free();
        }
        $stmt->close();
    }

    return array_values(array_unique($names));
};

$buildPlannedInstorDefaults = static function (mysqli $conn, int $idPob, string $date): array {
    if ($idPob <= 0 || $date === '') {
        return ['opening' => '', 'closing' => ''];
    }

    $sql = "
        SELECT
            TRIM(CONCAT_WS(' ', u.jmeno, u.prijmeni)) AS full_name,
            CONCAT(sp.datum, ' ', sp.cas_od) AS start_dt,
            DATE_ADD(CONCAT(sp.datum, ' ', sp.cas_do), INTERVAL CASE WHEN sp.cas_do <= sp.cas_od THEN 1 ELSE 0 END DAY) AS end_dt
        FROM smeny_plan sp
        INNER JOIN user u ON u.id_user = sp.id_user
        WHERE sp.id_pob = ?
          AND sp.id_slot = 1
          AND sp.datum = ?
        HAVING full_name <> ''
        ORDER BY start_dt ASC, end_dt DESC, full_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $openingName = '';
    $openingStart = '';
    $closingName = '';
    $closingEnd = '';

    if ($stmt !== false) {
        $stmt->bind_param('is', $idPob, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $name = trim((string)($row['full_name'] ?? ''));
                $startDt = trim((string)($row['start_dt'] ?? ''));
                $endDt = trim((string)($row['end_dt'] ?? ''));
                if ($name === '') {
                    continue;
                }
                if ($openingName === '' || ($startDt !== '' && strcmp($startDt, $openingStart) < 0)) {
                    $openingName = $name;
                    $openingStart = $startDt;
                }
                if ($closingName === '' || ($endDt !== '' && strcmp($endDt, $closingEnd) > 0)) {
                    $closingName = $name;
                    $closingEnd = $endDt;
                }
            }
            $result->free();
        }
        $stmt->close();
    }

    return ['opening' => $openingName, 'closing' => $closingName];
};

$renderNameSelectOptions = static function (array $options, string $selected, string $placeholder): string {
    $html = '<option value="">' . h($placeholder) . '</option>';
    foreach ($options as $option) {
        $value = trim((string)$option);
        if ($value === '') {
            continue;
        }
        $html .= '<option value="' . h($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . h($value) . '</option>';
    }
    return $html;
};

$renderTimeInput = static function (string $name, string $selected, string $dataAttr, string $extraAttr = ''): string {
    $attrName = trim($name) !== '' ? ' name="' . h($name) . '"' : '';
    $attrExtra = trim($extraAttr) !== '' ? ' ' . trim($extraAttr) : '';

    return '<input class="zr_time_input" type="text" inputmode="numeric"' . $attrName . ' value="' . h($selected) . '" style="width:100%;text-align:center;" ' . $dataAttr . $attrExtra . '>';
};

$instorOptions = $buildBranchSlotNameOptions($conn, $reportBranchId, 1);
$plannedInstorDefaults = $buildPlannedInstorDefaults($conn, $reportBranchId, $reportDate);
$kuryrOptions = $buildBranchSlotNameOptions($conn, $reportBranchId, 2);

$reportRow = null;
$instorRows = [];
$kuryrRows = [];

if ($reportBranchId > 0) {
    $sqlReport = "
        SELECT
            r.id_reportu,
            r.oteviral_text,
            r.zaviral_text,
            rr.trzba,
            rr.wolt,
            rr.bolt,
            rr.damejidlo,
            rr.web,
            rr.wolt_cash,
            rr.dj_cash,
            rr.zrusene_obj_ks,
            rr.zrusene_obj_kc,
            rr.zpozdene_rozvozy_5_min,
            rr.make_time_prumer_sec,
            rr.objednavky_nezrusene_ks,
            rr.nase_rozvozy_ks,
            rr.woltdrive_pozde_5_min,
            rp.hotovost,
            rp.terminal,
            rp.stravenky,
            rp.vydaje_benzin,
            rp.vydaje_auta,
            rp.vydaje_suroviny,
            rp.vydaje_ostatni,
            rp.vydaje_phm_soukrome,
            rp.vydaje_doklady_ks
        FROM reporty r
        LEFT JOIN reporty_restia rr ON rr.id_reportu = r.id_reportu
        LEFT JOIN reporty_pokladna rp ON rp.id_reportu = r.id_reportu
        WHERE r.id_pob = ?
          AND r.datum_reportu = ?
          AND r.platny = 1
        ORDER BY r.id_reportu DESC
        LIMIT 1
    ";
    $stmtReport = $conn->prepare($sqlReport);
    if ($stmtReport !== false) {
        $stmtReport->bind_param('is', $reportBranchId, $reportDate);
        $stmtReport->execute();
        $resultReport = $stmtReport->get_result();
        if ($resultReport instanceof mysqli_result) {
            $reportRow = $resultReport->fetch_assoc() ?: null;
            $resultReport->free();
        }
        $stmtReport->close();
    }

    if (is_array($reportRow) && isset($reportRow['id_reportu'])) {
        $reportId = (int)$reportRow['id_reportu'];
        $stmtPeople = $conn->prepare("
            SELECT slot, jmeno, prijmeni, smena_od, smena_do, pauza, odpracovano, rozvozu_restia, rozvozu_manual, rozvozu_celkem, vlastni_vuz, vyplatit_phm
            FROM reporty_osoby
            WHERE id_reportu = ?
            ORDER BY slot ASC, smena_od ASC, jmeno ASC, prijmeni ASC
        ");
        if ($stmtPeople !== false) {
            $stmtPeople->bind_param('i', $reportId);
            $stmtPeople->execute();
            $resultPeople = $stmtPeople->get_result();
            if ($resultPeople instanceof mysqli_result) {
                while ($row = $resultPeople->fetch_assoc()) {
                    $personRow = [
                        'name' => $personFullName($row['jmeno'] ?? '', $row['prijmeni'] ?? ''),
                        'start' => $formatTime((string)($row['smena_od'] ?? '')),
                        'end' => $formatTime((string)($row['smena_do'] ?? '')),
                        'break' => $formatInputNumber((float)($row['pauza'] ?? 0)),
                        'hours' => $formatInputNumber((float)($row['odpracovano'] ?? 0)),
                        'delivery_restia' => (int)($row['rozvozu_restia'] ?? 0),
                        'delivery_manual' => (int)($row['rozvozu_manual'] ?? 0),
                        'delivery_total' => (int)($row['rozvozu_celkem'] ?? 0),
                        'car' => (int)($row['vlastni_vuz'] ?? 0),
                        'phm' => (float)($row['vyplatit_phm'] ?? 0),
                    ];
                    if (($row['slot'] ?? '') === 'kuryr') {
                        $kuryrRows[] = $personRow;
                    } else {
                        $instorRows[] = $personRow;
                    }
                }
                $resultPeople->free();
            }
            $stmtPeople->close();
        }
    }
}

if ($instorRows === [] && $kuryrRows === [] && $reportBranchId > 0) {
    $sqlShiftReport = "
        SELECT
            CASE WHEN id_slot = 1 THEN 'instor' WHEN id_slot = 2 THEN 'kuryr' ELSE '' END AS slot_name,
            jmeno,
            prijmeni,
            cas_od,
            cas_do,
            COALESCE(TIME_TO_SEC(pauza) / 3600, 0) AS pauza_hod,
            COALESCE(odpracovano, 0) AS odpracovano
        FROM smeny_report
        WHERE id_pob = ?
          AND datum = ?
          AND id_slot IN (1, 2)
        ORDER BY id_slot ASC, cas_od ASC, jmeno ASC, prijmeni ASC
    ";
    $stmtShiftReport = $conn->prepare($sqlShiftReport);
    if ($stmtShiftReport !== false) {
        $stmtShiftReport->bind_param('is', $reportBranchId, $reportDate);
        $stmtShiftReport->execute();
        $resultShiftReport = $stmtShiftReport->get_result();
        if ($resultShiftReport instanceof mysqli_result) {
            while ($row = $resultShiftReport->fetch_assoc()) {
                $personRow = [
                    'name' => $personFullName($row['jmeno'] ?? '', $row['prijmeni'] ?? ''),
                    'start' => $formatTime((string)($row['cas_od'] ?? '')),
                    'end' => $formatTime((string)($row['cas_do'] ?? '')),
                    'break' => $formatInputNumber((float)($row['pauza_hod'] ?? 0)),
                    'hours' => $formatInputNumber((float)($row['odpracovano'] ?? 0)),
                    'delivery_restia' => 0,
                    'delivery_manual' => 0,
                    'delivery_total' => 0,
                    'car' => 0,
                    'phm' => 0.0,
                ];
                if (($row['slot_name'] ?? '') === 'kuryr') {
                    $kuryrRows[] = $personRow;
                } else {
                    $instorRows[] = $personRow;
                }
            }
            $resultShiftReport->free();
        }
        $stmtShiftReport->close();
    }
}

if ($instorRows === [] && $kuryrRows === [] && $reportBranchId > 0) {
    $sqlShiftPlan = "
        SELECT
            CASE WHEN sp.id_slot = 1 THEN 'instor' WHEN sp.id_slot = 2 THEN 'kuryr' ELSE '' END AS slot_name,
            u.jmeno,
            u.prijmeni,
            sp.cas_od,
            sp.cas_do,
            TIMESTAMPDIFF(MINUTE, CONCAT(sp.datum, ' ', sp.cas_od), DATE_ADD(CONCAT(sp.datum, ' ', sp.cas_do), INTERVAL CASE WHEN sp.cas_do < sp.cas_od THEN 1 ELSE 0 END DAY)) / 60 AS odpracovano
        FROM smeny_plan sp
        INNER JOIN user u ON u.id_user = sp.id_user
        WHERE sp.id_pob = ?
          AND sp.datum = ?
          AND sp.id_slot IN (1, 2)
        ORDER BY sp.id_slot ASC, sp.cas_od ASC, u.jmeno ASC, u.prijmeni ASC
    ";
    $stmtShiftPlan = $conn->prepare($sqlShiftPlan);
    if ($stmtShiftPlan !== false) {
        $stmtShiftPlan->bind_param('is', $reportBranchId, $reportDate);
        $stmtShiftPlan->execute();
        $resultShiftPlan = $stmtShiftPlan->get_result();
        if ($resultShiftPlan instanceof mysqli_result) {
            while ($row = $resultShiftPlan->fetch_assoc()) {
                $personRow = [
                    'name' => $personFullName($row['jmeno'] ?? '', $row['prijmeni'] ?? ''),
                    'start' => $formatTime((string)($row['cas_od'] ?? '')),
                    'end' => $formatTime((string)($row['cas_do'] ?? '')),
                    'break' => '0',
                    'hours' => $formatInputNumber((float)($row['odpracovano'] ?? 0)),
                    'delivery_restia' => 0,
                    'delivery_manual' => 0,
                    'delivery_total' => 0,
                    'car' => 0,
                    'phm' => 0.0,
                ];
                if (($row['slot_name'] ?? '') === 'kuryr') {
                    $kuryrRows[] = $personRow;
                } else {
                    $instorRows[] = $personRow;
                }
            }
            $resultShiftPlan->free();
        }
        $stmtShiftPlan->close();
    }
}

$openingName = trim((string)($reportRow['oteviral_text'] ?? ''));
$closingName = trim((string)($reportRow['zaviral_text'] ?? ''));
if ($openingName === '') {
    $openingName = (string)($plannedInstorDefaults['opening'] ?? '');
}
if ($closingName === '') {
    $closingName = (string)($plannedInstorDefaults['closing'] ?? '');
}

$restiaSummary = [
    'trzba' => (float)($reportRow['trzba'] ?? 0),
    'wolt' => (float)($reportRow['wolt'] ?? 0),
    'bolt' => (float)($reportRow['bolt'] ?? 0),
    'dj' => (float)($reportRow['damejidlo'] ?? 0),
    'web' => (float)($reportRow['web'] ?? 0),
    'wolt_cash' => (float)($reportRow['wolt_cash'] ?? 0),
    'dj_cash' => (float)($reportRow['dj_cash'] ?? 0),
    'cancel_count' => (int)($reportRow['zrusene_obj_ks'] ?? 0),
    'cancel_value' => (float)($reportRow['zrusene_obj_kc'] ?? 0),
    'delay_count' => (int)($reportRow['zpozdene_rozvozy_5_min'] ?? 0),
    'make_time_avg_sec' => isset($reportRow['make_time_prumer_sec']) ? (int)$reportRow['make_time_prumer_sec'] : null,
    'docs_count' => (int)($reportRow['vydaje_doklady_ks'] ?? 0),
    'orders_total' => (int)($reportRow['objednavky_nezrusene_ks'] ?? 0),
    'own_deliveries' => (int)($reportRow['nase_rozvozy_ks'] ?? 0),
    'woltdrive_late' => (int)($reportRow['woltdrive_pozde_5_min'] ?? 0),
];

$cashInputValue = static function (?array $row, string $key) use ($formatInputNumber): string {
    if (!is_array($row) || !array_key_exists($key, $row) || $row[$key] === null) {
        return '';
    }

    return $formatInputNumber((float)$row[$key]);
};

$cashData = [
    'hotovost' => $cashInputValue($reportRow, 'hotovost'),
    'terminal' => $cashInputValue($reportRow, 'terminal'),
    'stravenky' => $cashInputValue($reportRow, 'stravenky'),
    'vydaje_benzin' => $cashInputValue($reportRow, 'vydaje_benzin'),
    'vydaje_auta' => $cashInputValue($reportRow, 'vydaje_auta'),
    'vydaje_suroviny' => $cashInputValue($reportRow, 'vydaje_suroviny'),
    'vydaje_ostatni' => $cashInputValue($reportRow, 'vydaje_ostatni'),
    'vydaje_phm_soukrome' => $cashInputValue($reportRow, 'vydaje_phm_soukrome'),
];

if (!is_array($reportRow) && $reportBranchId > 0) {
    $sqlIds = "
        SELECT
            MAX(CASE WHEN p.nazev = 'cash' THEN p.id_platba ELSE NULL END) AS cash_id,
            MAX(CASE WHEN p.nazev = 'online' THEN p.id_platba ELSE NULL END) AS online_id
        FROM cis_obj_platby p
    ";
    $idsRow = [];
    $idsResult = $conn->query($sqlIds);
    if ($idsResult instanceof mysqli_result) {
        $idsRow = $idsResult->fetch_assoc() ?: [];
        $idsResult->free();
    }
    $cashPaymentId = (int)($idsRow['cash_id'] ?? 0);
    $onlinePaymentId = (int)($idsRow['online_id'] ?? 0);

    $summarySql = "
        SELECT
            SUM(CASE WHEN COALESCE(s.nazev, '') IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN 0 ELSE COALESCE(c.cena_celk, 0) END) AS trzba,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'wolt' AND o.id_platba <> ? THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS wolt,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'bolt' THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS bolt,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod IN ('foodora', 'damejidlo') AND o.id_platba <> ? THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS damejidlo,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'generic' AND o.id_platba = ? THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS web,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'wolt' AND o.id_platba = ? THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS wolt_cash,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod IN ('foodora', 'damejidlo') AND o.id_platba = ? THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS dj_cash,
            SUM(CASE WHEN COALESCE(s.nazev, '') IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN 1 ELSE 0 END) AS cancel_count,
            SUM(CASE WHEN COALESCE(s.nazev, '') IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS cancel_value,
            AVG(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND ca.cas_vytvor IS NOT NULL AND ca.cas_pripr_v IS NOT NULL THEN TIMESTAMPDIFF(SECOND, ca.cas_vytvor, ca.cas_pripr_v) END) AS make_time_avg_sec,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN 1 ELSE 0 END) AS orders_total,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND ok.provider = 'delivery' THEN 1 ELSE 0 END) AS own_deliveries,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND ok.provider = 'delivery' AND ca.cas_slib IS NOT NULL AND ca.cas_doruc IS NOT NULL AND TIMESTAMPDIFF(MINUTE, ca.cas_slib, ca.cas_doruc) > 5 THEN 1 ELSE 0 END) AS delay_count,
            SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND ok.provider = 'external-delivery' AND ca.cas_slib IS NOT NULL AND ca.cas_doruc IS NOT NULL AND TIMESTAMPDIFF(MINUTE, ca.cas_slib, ca.cas_doruc) > 5 THEN 1 ELSE 0 END) AS woltdrive_late
        FROM objednavky_restia o
        LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
        LEFT JOIN cis_obj_stav s ON s.id_stav = o.id_stav
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
        LEFT JOIN (
            SELECT id_obj, MIN(provider) AS provider
            FROM obj_kuryr
            GROUP BY id_obj
        ) ok ON ok.id_obj = o.id_obj
        WHERE o.id_pob = ?
          AND o.restia_created_at IS NOT NULL
          AND o.restia_created_at >= ?
          AND o.restia_created_at < ?
    ";
    $stmtSummary = $conn->prepare($summarySql);
    if ($stmtSummary !== false) {
        $fromDb = (string)$workdayRange['from_db'];
        $toDb = (string)$workdayRange['to_db'];
        $stmtSummary->bind_param(
            'iiiiiiss',
            $cashPaymentId,
            $cashPaymentId,
            $onlinePaymentId,
            $cashPaymentId,
            $cashPaymentId,
            $reportBranchId,
            $fromDb,
            $toDb
        );
        $stmtSummary->execute();
        $summaryResult = $stmtSummary->get_result();
        if ($summaryResult instanceof mysqli_result) {
            $summaryRow = $summaryResult->fetch_assoc() ?: [];
            $restiaSummary = [
                'trzba' => (float)($summaryRow['trzba'] ?? 0),
                'wolt' => (float)($summaryRow['wolt'] ?? 0),
                'bolt' => (float)($summaryRow['bolt'] ?? 0),
                'dj' => (float)($summaryRow['damejidlo'] ?? 0),
                'web' => (float)($summaryRow['web'] ?? 0),
                'wolt_cash' => (float)($summaryRow['wolt_cash'] ?? 0),
                'dj_cash' => (float)($summaryRow['dj_cash'] ?? 0),
                'cancel_count' => (int)($summaryRow['cancel_count'] ?? 0),
                'cancel_value' => (float)($summaryRow['cancel_value'] ?? 0),
                'delay_count' => (int)($summaryRow['delay_count'] ?? 0),
                'make_time_avg_sec' => isset($summaryRow['make_time_avg_sec']) ? (int)round((float)$summaryRow['make_time_avg_sec']) : null,
                'docs_count' => 0,
                'orders_total' => (int)($summaryRow['orders_total'] ?? 0),
                'own_deliveries' => (int)($summaryRow['own_deliveries'] ?? 0),
                'woltdrive_late' => (int)($summaryRow['woltdrive_late'] ?? 0),
            ];
            $summaryResult->free();
        }
        $stmtSummary->close();
    }
}

$kuryrDeliveryCounts = [];
if ($reportBranchId > 0) {
    $deliverySql = "
        SELECT
            TRIM(ok.jmeno) AS kuryr_name,
            COUNT(DISTINCT o.id_obj) AS rozvozu
        FROM objednavky_restia o
        INNER JOIN obj_kuryr ok ON ok.id_obj = o.id_obj
        LEFT JOIN cis_obj_stav s ON s.id_stav = o.id_stav
        WHERE o.id_pob = ?
          AND o.restia_created_at IS NOT NULL
          AND o.restia_created_at >= ?
          AND o.restia_created_at < ?
          AND ok.provider = 'delivery'
          AND COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted')
        GROUP BY kuryr_name
        HAVING kuryr_name <> ''
    ";
    $stmtDeliveries = $conn->prepare($deliverySql);
    if ($stmtDeliveries !== false) {
        $fromDb = (string)$workdayRange['from_db'];
        $toDb = (string)$workdayRange['to_db'];
        $stmtDeliveries->bind_param('iss', $reportBranchId, $fromDb, $toDb);
        $stmtDeliveries->execute();
        $deliveriesResult = $stmtDeliveries->get_result();
        if ($deliveriesResult instanceof mysqli_result) {
            while ($row = $deliveriesResult->fetch_assoc()) {
                $courierName = trim((string)($row['kuryr_name'] ?? ''));
                if ($courierName !== '') {
                    $kuryrDeliveryCounts[$courierName] = (int)($row['rozvozu'] ?? 0);
                }
            }
            $deliveriesResult->free();
        }
        $stmtDeliveries->close();
    }
}

foreach ($kuryrRows as &$kuryrRow) {
    $kuryrName = trim((string)($kuryrRow['name'] ?? ''));
    $deliveryRestia = (int)($kuryrDeliveryCounts[$kuryrName] ?? 0);
    $deliveryManual = (int)($kuryrRow['delivery_manual'] ?? 0);
    $kuryrRow['delivery_restia'] = $deliveryRestia;
    $kuryrRow['delivery_total'] = $deliveryRestia + $deliveryManual;
}
unset($kuryrRow);
$kuryrDeliveryCountsJson = (string)json_encode($kuryrDeliveryCounts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($kuryrDeliveryCountsJson === '') {
    $kuryrDeliveryCountsJson = '{}';
}

$makeTimeLabel = '0 min 00 s';
if (is_int($restiaSummary['make_time_avg_sec']) && $restiaSummary['make_time_avg_sec'] > 0) {
    $minutes = intdiv((int)$restiaSummary['make_time_avg_sec'], 60);
    $seconds = (int)$restiaSummary['make_time_avg_sec'] % 60;
    $makeTimeLabel = $minutes . ' min ' . sprintf('%02d', $seconds) . ' s';
}

$renderInstorSavedRow = static function (array $row, callable $renderTimeInput): string {
    $name = trim((string)($row['name'] ?? ''));
    $start = trim((string)($row['start'] ?? ''));
    $end = trim((string)($row['end'] ?? ''));
    $break = trim((string)($row['break'] ?? '0'));
    $hours = trim((string)($row['hours'] ?? '0'));

    return ''
        . '<tr data-zr-person-row="instor">'
        . '<td style="width:220px;"><button type="button" class="zr_row_remove" data-zr-remove-row title="Odebrat" aria-label="Odebrat">×</button><strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="instor_jmeno[]" value="' . h($name) . '">'
        . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_zacatek[]', $start, 'data-zr-start') . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_konec[]', $end, 'data-zr-end') . '</td>'
        . '<td class="zr_person_cell_break" style="width:44px;"><input type="text" inputmode="decimal" name="instor_pauza_hod[]" value="' . h($break) . '" style="width:100%;text-align:center;" data-zr-break></td>'
        . '<td style="width:70px;"><strong class="zr_saved_value" data-zr-hours>' . h($hours) . ' hod.</strong><input type="hidden" name="instor_hodiny[]" value="' . h($hours) . '" data-zr-hours-hidden></td>'
        . '<td></td>'
        . '</tr>';
};

$renderKuryrSavedRow = static function (array $row, callable $formatMoney, callable $renderTimeInput): string {
    $name = trim((string)($row['name'] ?? ''));
    $start = trim((string)($row['start'] ?? ''));
    $end = trim((string)($row['end'] ?? ''));
    $break = trim((string)($row['break'] ?? '0'));
    $hours = trim((string)($row['hours'] ?? '0'));
    $deliveryRestia = (int)($row['delivery_restia'] ?? 0);
    $deliveryManual = (int)($row['delivery_manual'] ?? 0);
    $deliveryTotal = (int)($row['delivery_total'] ?? ($deliveryRestia + $deliveryManual));
    $car = (int)($row['car'] ?? 0);
    $phm = (float)($row['phm'] ?? 0);
    return ''
        . '<tr data-zr-person-row="kuryr">'
        . '<td style="width:220px;"><button type="button" class="zr_row_remove" data-zr-remove-row title="Odebrat" aria-label="Odebrat">×</button><strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="kuryr_jmeno[]" value="' . h($name) . '">'
        . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('kuryr_zacatek[]', $start, 'data-zr-start') . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('kuryr_konec[]', $end, 'data-zr-end') . '</td>'
        . '<td class="zr_person_cell_break" style="width:44px;"><input type="text" inputmode="decimal" name="kuryr_pauza_hod[]" value="' . h($break) . '" style="width:100%;text-align:center;" data-zr-break></td>'
        . '<td style="width:70px;"><strong class="zr_saved_value" data-zr-hours>' . h($hours) . ' hod.</strong><input type="hidden" name="kuryr_hodiny[]" value="' . h($hours) . '" data-zr-hours-hidden></td>'
        . '<td class="txt_c" style="width:48px;"><strong class="zr_saved_value">' . h((string)$deliveryRestia) . '</strong><input type="hidden" name="kuryr_pocet_rozvozu_restia[]" value="' . h((string)$deliveryRestia) . '"></td>'
        . '<td class="txt_c" style="width:48px;"><strong class="zr_saved_value">' . h((string)$deliveryManual) . '</strong><input type="hidden" name="kuryr_pocet_rozvozu_manual[]" value="' . h((string)$deliveryManual) . '">'
        . '<input type="hidden" name="kuryr_pocet_rozvozu[]" value="' . h((string)$deliveryTotal) . '">'
        . '</td>'
        . '<td class="txt_c" style="width:34px;"><strong class="zr_saved_value">' . h($car === 1 ? 'Ano' : 'Ne') . '</strong><input type="hidden" name="kuryr_vlastni_vuz[]" value="' . h((string)$car) . '"></td>'
        . '<td><strong class="zr_saved_value">' . h($formatMoney($phm)) . '</strong><input type="hidden" name="kuryr_vyplatit_phm[]" value="' . h(number_format($phm, 2, '.', '')) . '"></td>'
        . '</tr>';
};

ob_start();
?>
<p class="card_text txt_seda odstup_vnejsi_0">
  Denni report za pobočku je možné zadat<br>po ukončení směny.
</p>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
?>
<form class="zr_form gap_14" autocomplete="off" method="post" action="<?= h(cb_url('/')) ?>" data-zr-form data-cb-max-form="1" data-cb-loader-text="Načítám report pobočky">
  <div class="zr_layout gap_14">
    <div class="zr_main gap_14">
      <section class="zr_top gap_14">
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_intro_section">
          <table class="zr_table">
            <tbody>
              <tr>
                <th class="zr_intro_label txt_l">Pobočka</th>
                <td>
                  <?php if ($singleAllowedBranchName !== ''): ?>
                    <span class="text_tucny text_14"><?= h($singleAllowedBranchName) ?></span>
                    <input type="hidden" name="zr_id_pob" value="<?= h((string)$reportBranchId) ?>">
                  <?php else: ?>
                    <select class="zr_intro_select" name="zr_id_pob" onchange="if(this.form){this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit();}">
                      <option value=""><?= h('Vyber pobočku') ?></option>
                      <?php foreach ($allowedBranches as $branchId => $allowedBranchName): ?>
                        <option value="<?= h((string)$branchId) ?>"<?= (int)$branchId === $reportBranchId ? ' selected' : '' ?>><?= h((string)$allowedBranchName) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                  <input type="hidden" name="id_pob" value="<?= h((string)$reportBranchId) ?>">
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="datum">Datum</th>
                <td>
                  <input class="zr_date_display" type="text" value="<?= h($reportDateDisplay) ?>" readonly data-zr-date-display>
                  <input type="hidden" name="datum_reportu" value="<?= h($reportDate) ?>" data-zr-date data-zr-required="datum">
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="oteviral">Otevíral</th>
                <td>
                  <select class="zr_intro_select" name="oteviral" data-zr-field="oteviral" data-zr-required="oteviral">
                    <?= $renderNameSelectOptions($instorOptions, $openingName, 'Vyber jméno') ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="zaviral">Zavíral</th>
                <td>
                  <select class="zr_intro_select" name="zaviral" data-zr-field="zaviral" data-zr-required="zaviral">
                    <?= $renderNameSelectOptions($instorOptions, $closingName, 'Vyber jméno') ?>
                  </select>
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_cash_section">
          <h4 class="card_section_title txt_seda">Pokladna a výdaje</h4>
          <table class="zr_table zr_cash_table">
            <tbody>
              <tr>
                <th class="zr_req_label txt_l" data-zr-required-label="pokladna_hotovost">Hotovost</th>
                <th class="txt_l">Terminal</th>
                <th class="txt_l">Stravenky</th>
                <th class="txt_l">Benzín</th>
              </tr>
              <tr>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_hotovost" value="<?= h($cashData['hotovost']) ?>" data-zr-field="pokladna_hotovost" data-zr-money="int" data-zr-required="pokladna_hotovost"></td>
                <td><input class="zr_money_input" type="text" inputmode="decimal" name="pokladna_terminal" value="<?= h($cashData['terminal']) ?>" data-zr-field="pokladna_terminal" data-zr-money="decimal" data-zr-required="pokladna_terminal"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="pokladna_stravenky" value="<?= h($cashData['stravenky']) ?>" data-zr-field="pokladna_stravenky" data-zr-money="int" data-zr-required="pokladna_stravenky"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_benzin" value="<?= h($cashData['vydaje_benzin']) ?>" data-zr-field="vydaje_benzin" data-zr-money="int" data-zr-required="vydaje_benzin"></td>
              </tr>
              <tr>
                <th class="txt_l">Auta</th>
                <th class="txt_l">Suroviny</th>
                <th class="txt_l">Ostatni</th>
                <th class="txt_l">PHM-soukr.</th>
              </tr>
              <tr>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_auta" value="<?= h($cashData['vydaje_auta']) ?>" data-zr-field="vydaje_auta" data-zr-money="int" data-zr-required="vydaje_auta"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_suroviny" value="<?= h($cashData['vydaje_suroviny']) ?>" data-zr-field="vydaje_suroviny" data-zr-money="int" data-zr-required="vydaje_suroviny"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_ostatni" value="<?= h($cashData['vydaje_ostatni']) ?>" data-zr-field="vydaje_ostatni" data-zr-money="int" data-zr-required="vydaje_ostatni"></td>
                <td><input class="zr_money_input" type="text" inputmode="numeric" name="vydaje_phm_soukrome" value="<?= h($cashData['vydaje_phm_soukrome']) ?>" data-zr-field="vydaje_phm_soukrome" data-zr-money="int" data-zr-required="vydaje_phm_soukrome"></td>
              </tr>
            </tbody>
          </table>
        </section>
      </section>

      <div class="zr_left gap_14">
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_instor_section">
          <h4 class="card_section_title txt_seda">Instor</h4>
          <div style="width:220px;margin-bottom:6px;">
            <select data-zr-add-person="instor">
              <?= $renderNameSelectOptions($instorOptions, '', 'Vyber zaměstnance') ?>
            </select>
          </div>
          <table class="zr_table zr_person_table" style="width:100%;" data-zr-people-list="instor">
            <thead>
              <tr>
                <th class="zr_req_label txt_l" style="width:220px;white-space:nowrap;" data-zr-required-label="instor_jmeno">Instor</th>
                <th class="zr_req_label txt_l" style="width:58px;white-space:nowrap;" data-zr-required-label="instor_zacatek">Směna od</th>
                <th class="zr_req_label txt_l" style="width:58px;white-space:nowrap;" data-zr-required-label="instor_konec">Směna do</th>
                <th class="txt_l" style="width:44px;white-space:nowrap;">Pauza</th>
                <th class="txt_l" style="width:70px;white-space:nowrap;">Odprac.</th>
                <th class="txt_l" style="white-space:nowrap;"></th>
              </tr>
            </thead>
            <tbody data-zr-saved-list="instor">
              <?php foreach ($instorRows as $row): ?>
                <?= $renderInstorSavedRow($row, $renderTimeInput) ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>

        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_kuryr_section">
          <h4 class="card_section_title txt_seda">Kurýr</h4>
          <div style="width:220px;margin-bottom:6px;">
            <select data-zr-add-person="kuryr">
              <?= $renderNameSelectOptions($kuryrOptions, '', 'Vyber kurýra') ?>
            </select>
          </div>
          <table class="zr_table zr_person_table" style="width:100%;" data-zr-people-list="kuryr" data-zr-delivery-counts="<?= h($kuryrDeliveryCountsJson) ?>">
            <thead>
              <tr>
                <th class="txt_l" style="width:220px;white-space:nowrap;">Kurýr</th>
                <th class="txt_l" style="width:58px;white-space:nowrap;">Směna od</th>
                <th class="txt_l" style="width:58px;white-space:nowrap;">Směna do</th>
                <th class="txt_l" style="width:44px;white-space:nowrap;">Pauza</th>
                <th class="txt_l" style="width:70px;white-space:nowrap;">Odprac.</th>
                <th class="txt_l" style="width:48px;white-space:nowrap;">Rozvozů</th>
                <th class="txt_l" style="width:48px;white-space:nowrap;">Ručně</th>
                <th class="txt_l" style="width:34px;white-space:nowrap;">Vůz</th>
                <th class="txt_l" style="white-space:nowrap;">PHM</th>
              </tr>
            </thead>
            <tbody data-zr-saved-list="kuryr">
              <?php foreach ($kuryrRows as $row): ?>
                <?= $renderKuryrSavedRow($row, $formatMoney, $renderTimeInput) ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      </div>
    </div>

    <aside class="zr_side gap_14">
      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_restia_section">
        <h4 class="card_section_title txt_seda">Automaticky z Restie</h4>
        <div class="zr_restia_total gap_4">
          <span class="zr_metric_label">Trzba</span>
          <strong class="zr_metric_value" data-zr-restia-trzba><?= h($formatMoney((float)$restiaSummary['trzba'])) ?></strong>
        </div>
        <table class="zr_table zr_restia_table">
          <tbody>
            <tr><td class="zr_restia_key">Wolt</td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt><?= h($formatMoney((float)$restiaSummary['wolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Bolt</td><td class="zr_restia_value txt_r"><strong data-zr-restia-bolt><?= h($formatMoney((float)$restiaSummary['bolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Dáme jídlo</td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj><?= h($formatMoney((float)$restiaSummary['dj'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Web</td><td class="zr_restia_value txt_r"><strong data-zr-restia-web><?= h($formatMoney((float)$restiaSummary['web'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Wolt drive cash</td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt-cash><?= h($formatMoney((float)$restiaSummary['wolt_cash'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">DJ cash</td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj-cash><?= h($formatMoney((float)$restiaSummary['dj_cash'])) ?></strong></td></tr>
          </tbody>
        </table>
      </section>

      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_ops_section">
        <h4 class="card_section_title txt_seda">Operativa a kontrola</h4>
        <table class="zr_table zr_restia_table">
          <tbody>
            <tr><td class="zr_restia_key">Zrušené obj. ks</td><td class="zr_restia_value txt_r"><strong data-zr-cancel-count><?= h((string)$restiaSummary['cancel_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Zrušené obj. Kč</td><td class="zr_restia_value txt_r"><strong data-zr-cancel-value><?= h($formatMoney((float)$restiaSummary['cancel_value'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Zpožděné rozvozy +5 min</td><td class="zr_restia_value txt_r"><strong data-zr-delay-count><?= h((string)$restiaSummary['delay_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Průměrný make time</td><td class="zr_restia_value txt_r"><strong data-zr-make-time><?= h($makeTimeLabel) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Výdajové doklady</td><td class="zr_restia_value txt_r"><strong data-zr-docs-count><?= h((string)$restiaSummary['docs_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Nezrušené celkem</td><td class="zr_restia_value txt_r"><strong data-zr-orders-total><?= h((string)$restiaSummary['orders_total']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Naše rozvozy</td><td class="zr_restia_value txt_r"><strong data-zr-own-deliveries><?= h((string)$restiaSummary['own_deliveries']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Pozdě WoltDrive 5+</td><td class="zr_restia_value txt_r"><strong data-zr-woltdrive-late><?= h((string)$restiaSummary['woltdrive_late']) ?></strong></td></tr>
          </tbody>
        </table>
        <?php if ($canSaveReport): ?>
          <div class="card_actions gap_8 displ_flex jc_konec odstup_horni_10">
            <button type="button" class="zr_submit text_18 is-hidden" data-zr-submit>Uložit</button>
          </div>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</form>
<?php
$card_max_html = (string)ob_get_clean();
/* karty/zadani_reportu.php * Verze: V3 * Aktualizace: 12.05.2026 */
