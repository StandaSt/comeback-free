<?php
// K10
// karty/zadani_reportu.php * Verze: V3 * Aktualizace: 12.05.2026
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../db/db_dr_pracovni.php';
require_once __DIR__ . '/../db/db_dr_pracovni_osoby.php';

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$renderMode = isset($cbDashboardRenderMode) ? trim((string)$cbDashboardRenderMode) : '';
$isMaxRender = ($renderMode === 'max');

$tz = new DateTimeZone('Europe/Prague');
$reportDateDt = cb_dt_workday_start(null, 6);
$reportDate = $reportDateDt->format('Y-m-d');
$reportDateDisplay = cb_dt_weekday_date_label_cs($reportDateDt, true);
$workdayRange = cb_dt_workday_range_utc($reportDate);
$reportSaveMinutes = 5;
$reportSaveMinutesResult = $conn->query('SELECT report_save FROM set_system WHERE id_set = 1 LIMIT 1');
if ($reportSaveMinutesResult instanceof mysqli_result) {
    $reportSaveMinutesRow = $reportSaveMinutesResult->fetch_assoc() ?: [];
    $reportSaveMinutesResult->free();
    $reportSaveMinutesValue = (int)($reportSaveMinutesRow['report_save'] ?? 5);
    if (in_array($reportSaveMinutesValue, [5, 10, 15, 30, 60], true)) {
        $reportSaveMinutes = $reportSaveMinutesValue;
    }
}
$lastRestiaUpdateLabel = '--:--:--';
$lastRestiaUpdateResult = $conn->query('SELECT MAX(konec) AS posledni_konec FROM online_restia WHERE konec IS NOT NULL');
if ($lastRestiaUpdateResult instanceof mysqli_result) {
    $lastRestiaUpdateRow = $lastRestiaUpdateResult->fetch_assoc() ?: [];
    $lastRestiaUpdateResult->free();
    $lastRestiaUpdateRaw = trim((string)($lastRestiaUpdateRow['posledni_konec'] ?? ''));
    if ($lastRestiaUpdateRaw !== '') {
        $lastRestiaUpdateDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastRestiaUpdateRaw, $tz);
        if ($lastRestiaUpdateDt instanceof DateTimeImmutable) {
            $lastRestiaUpdateLabel = $lastRestiaUpdateDt->format('G:i:s');
        }
    }
}
$reportEndColumns = [
    1 => 'end_po',
    2 => 'end_ut',
    3 => 'end_st',
    4 => 'end_ct',
    5 => 'end_pa',
    6 => 'end_so',
    7 => 'end_ne',
];
$currentUser = $_SESSION['cb_user'] ?? [];
$currentUserId = is_array($currentUser) ? (int)($currentUser['id_user'] ?? 0) : 0;
$currentUserRoleId = is_array($currentUser) ? (int)($currentUser['id_role'] ?? 0) : 0;
$currentUserRoleIds = $currentUserRoleId > 0 ? [$currentUserRoleId => true] : [];
if ($currentUserId > 0) {
    $stmtUserRoles = $conn->prepare('SELECT id_role FROM user_role WHERE id_user = ?');
    if ($stmtUserRoles !== false) {
        $stmtUserRoles->bind_param('i', $currentUserId);
        $stmtUserRoles->execute();
        $userRolesResult = $stmtUserRoles->get_result();
        if ($userRolesResult instanceof mysqli_result) {
            while ($row = $userRolesResult->fetch_assoc()) {
                $idRole = (int)($row['id_role'] ?? 0);
                if ($idRole > 0) {
                    $currentUserRoleIds[$idRole] = true;
                }
            }
            $userRolesResult->free();
        }
        $stmtUserRoles->close();
    }
}
$canSaveReport = isset($currentUserRoleIds[5]) || isset($currentUserRoleIds[7]);
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
    return number_format($rounded, $decimals, ',', ' ') . ' Kč';
};

$formatMoneyWhole = static function (float $value): string {
    return number_format(round($value), 0, ',', ' ') . ' Kč';
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

$parseBranchEndTime = static function (?string $value): array {
    $raw = trim((string)$value);
    if ($raw === '') {
        return ['hour' => 0, 'minute' => 0];
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m) === 1) {
        return [
            'hour' => max(0, min(23, (int)$m[1])),
            'minute' => max(0, min(59, (int)$m[2])),
        ];
    }
    if (preg_match('/^\d{1,2}$/', $raw) === 1) {
        return [
            'hour' => max(0, min(23, (int)$raw)),
            'minute' => 0,
        ];
    }

    return ['hour' => 0, 'minute' => 0];
};

$personFullName = static function (?string $jmeno, ?string $prijmeni = null): string {
    return trim(trim((string)$jmeno) . ' ' . trim((string)$prijmeni));
};

$buildBranchSlotUserOptions = static function (mysqli $conn, int $idPob, int $idSlot): array {
    if ($idPob <= 0 || $idSlot <= 0) {
        return [];
    }

    $sql = "
        SELECT DISTINCT u.id_user, TRIM(CONCAT_WS(' ', u.jmeno, u.prijmeni)) AS full_name
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
    $users = [];
    if ($stmt !== false) {
        $stmt->bind_param('ii', $idPob, $idSlot);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $idUser = (int)($row['id_user'] ?? 0);
                $name = trim((string)($row['full_name'] ?? ''));
                if ($idUser > 0 && $name !== '') {
                    $users[$idUser] = ['id_user' => $idUser, 'name' => $name];
                }
            }
            $result->free();
        }
        $stmt->close();
    }

    return array_values($users);
};

$buildPlannedInstorDefaults = static function (mysqli $conn, int $idPob, string $date): array {
    if ($idPob <= 0 || $date === '') {
        return ['opening' => '', 'closing' => ''];
    }

    $sql = "
        SELECT
            sp.id_user,
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
    $openingId = null;
    $openingStart = '';
    $closingName = '';
    $closingId = null;
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
                    $openingId = (int)($row['id_user'] ?? 0);
                    $openingStart = $startDt;
                }
                if ($closingName === '' || ($endDt !== '' && strcmp($endDt, $closingEnd) > 0)) {
                    $closingName = $name;
                    $closingId = (int)($row['id_user'] ?? 0);
                    $closingEnd = $endDt;
                }
            }
            $result->free();
        }
        $stmt->close();
    }

    return ['opening' => $openingName, 'opening_id' => $openingId, 'closing' => $closingName, 'closing_id' => $closingId];
};

$userFullNameById = static function (mysqli $conn, ?int $idUser) use ($personFullName): string {
    if ($idUser === null || $idUser <= 0) {
        return '';
    }

    $stmt = $conn->prepare('SELECT jmeno, prijmeni FROM user WHERE id_user = ? LIMIT 1');
    if ($stmt === false) {
        return '';
    }
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? $personFullName($row['jmeno'] ?? '', $row['prijmeni'] ?? '') : '';
};

$renderUserSelectOptions = static function (array $options, int $selectedId, string $placeholder, array $excludeIds = []): string {
    $html = '<option value="">' . h($placeholder) . '</option>';
    $exclude = array_fill_keys(array_map('intval', $excludeIds), true);
    foreach ($options as $option) {
        $idUser = (int)($option['id_user'] ?? 0);
        $name = trim((string)($option['name'] ?? ''));
        if ($idUser <= 0 || $name === '' || isset($exclude[$idUser])) {
            continue;
        }
        $html .= '<option value="' . h((string)$idUser) . '"' . ($idUser === $selectedId ? ' selected' : '') . '>' . h($name) . '</option>';
    }
    return $html;
};

$renderTimeInput = static function (string $name, string $selected, string $dataAttr, string $extraAttr = ''): string {
    $attrName = trim($name) !== '' ? ' name="' . h($name) . '"' : '';
    $attrExtra = trim($extraAttr) !== '' ? ' ' . trim($extraAttr) : '';

    return '<input class="zr_time_input" type="text" inputmode="numeric"' . $attrName . ' value="' . h($selected) . '" style="width:100%;text-align:center;" ' . $dataAttr . $attrExtra . '>';
};

$instorOptions = $buildBranchSlotUserOptions($conn, $reportBranchId, 1);
$plannedInstorDefaults = $buildPlannedInstorDefaults($conn, $reportBranchId, $reportDate);
$kuryrOptions = $buildBranchSlotUserOptions($conn, $reportBranchId, 2);

$draftRow = null;
$idDr = 0;
$instorRows = [];
$kuryrRows = [];
$reportSaveAtTs = 0;

if ($reportBranchId > 0) {
    $endColumn = $reportEndColumns[(int)$reportDateDt->format('N')] ?? '';
    if ($endColumn !== '') {
        $stmtEnd = $conn->prepare('SELECT `' . $endColumn . '` AS end_time FROM pobocka WHERE id_pob = ? LIMIT 1');
        if ($stmtEnd !== false) {
            $stmtEnd->bind_param('i', $reportBranchId);
            $stmtEnd->execute();
            $endResult = $stmtEnd->get_result();
            $endRow = $endResult instanceof mysqli_result ? ($endResult->fetch_assoc() ?: []) : [];
            if ($endResult instanceof mysqli_result) {
                $endResult->free();
            }
            $stmtEnd->close();

            $endTime = $parseBranchEndTime((string)($endRow['end_time'] ?? ''));
            $endTimeMinutes = ((int)$endTime['hour'] * 60) + (int)$endTime['minute'];
            $workdayStartMinutes = 6 * 60;
            $endBaseDate = $endTimeMinutes < $workdayStartMinutes ? $reportDateDt->modify('+1 day') : $reportDateDt;
            $reportSaveAtTs = $endBaseDate
                ->setTime((int)$endTime['hour'], (int)$endTime['minute'], 0)
                ->modify('-' . $reportSaveMinutes . ' minutes')
                ->getTimestamp();
        }
    }
}
$reportRefreshAtTs = $reportSaveAtTs > 300 ? $reportSaveAtTs - 300 : 0;

if ($reportBranchId > 0) {
    $idDr = cb_db_dr_pracovni_ensure(
        $conn,
        $reportBranchId,
        $reportDate,
        $currentUserId,
        isset($plannedInstorDefaults['opening_id']) ? (int)$plannedInstorDefaults['opening_id'] : null,
        isset($plannedInstorDefaults['closing_id']) ? (int)$plannedInstorDefaults['closing_id'] : null
    );
    $draftRow = cb_db_dr_pracovni_find($conn, $reportBranchId, $reportDate);
    if (!is_array($draftRow) && $idDr > 0) {
        $draftRow = ['id_dr' => $idDr, 'id_pob' => $reportBranchId, 'datum_reportu' => $reportDate];
    }
}

if ($idDr > 0 && cb_db_dr_pracovni_osoby_list($conn, $idDr) === []) {
    $sqlShiftPlan = "
        SELECT
            sp.id_user,
            sp.id_slot,
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
                cb_db_dr_pracovni_osoby_insert(
                    $conn,
                    $idDr,
                    (int)($row['id_user'] ?? 0),
                    (int)($row['id_slot'] ?? 0),
                    (string)($row['cas_od'] ?? ''),
                    (string)($row['cas_do'] ?? ''),
                    0.0,
                    (float)($row['odpracovano'] ?? 0)
                );
            }
            $resultShiftPlan->free();
        }
        $stmtShiftPlan->close();
    }
}

$draftPersonRows = $idDr > 0 ? cb_db_dr_pracovni_osoby_list($conn, $idDr) : [];
foreach ($draftPersonRows as $row) {
    $personRow = [
        'id_dr_osoby' => (int)($row['id_dr_osoby'] ?? 0),
        'id_user' => (int)($row['id_user'] ?? 0),
        'name' => $personFullName($row['jmeno'] ?? '', $row['prijmeni'] ?? ''),
        'start' => $formatTime((string)($row['smena_od'] ?? '')),
        'end' => $formatTime((string)($row['smena_do'] ?? '')),
        'break' => $row['pauza'] === null ? '' : $formatInputNumber((float)$row['pauza']),
        'hours' => $row['odpracovano'] === null ? '0' : $formatInputNumber((float)$row['odpracovano']),
        'delivery_restia' => 0,
        'delivery_manual' => (int)($row['rozvozu_manual'] ?? 0),
        'delivery_total' => 0,
        'car' => (int)($row['vlastni_vuz'] ?? 0),
        'phm' => (float)($row['vyplatit_phm'] ?? 0),
    ];
    if ((int)($row['id_slot'] ?? 0) === 2) {
        $kuryrRows[] = $personRow;
    } elseif ((int)($row['id_slot'] ?? 0) === 1) {
        $instorRows[] = $personRow;
    }
}

$usedInstorIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id_user'] ?? 0), $instorRows)));
$usedKuryrIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id_user'] ?? 0), $kuryrRows)));

$openingId = isset($draftRow['oteviral']) ? (int)$draftRow['oteviral'] : 0;
$closingId = isset($draftRow['zaviral']) ? (int)$draftRow['zaviral'] : 0;
$openingName = $userFullNameById($conn, $openingId > 0 ? $openingId : null);
$closingName = $userFullNameById($conn, $closingId > 0 ? $closingId : null);
if ($openingName === '') {
    $openingName = (string)($plannedInstorDefaults['opening'] ?? '');
    $openingId = (int)($plannedInstorDefaults['opening_id'] ?? 0);
}
if ($closingName === '') {
    $closingName = (string)($plannedInstorDefaults['closing'] ?? '');
    $closingId = (int)($plannedInstorDefaults['closing_id'] ?? 0);
}

$restiaSummary = [
    'trzba' => 0.0,
    'wolt' => 0.0,
    'bolt' => 0.0,
    'dj' => 0.0,
    'web' => 0.0,
    'wolt_cash' => 0.0,
    'dj_cash' => 0.0,
    'cancel_count' => 0,
    'cancel_value' => 0.0,
    'delay_count' => 0,
    'make_time_avg_sec' => null,
    'docs_count' => 0,
    'orders_total' => 0,
    'own_deliveries' => 0,
    'woltdrive_late' => 0,
];

$cashInputValue = static function (?array $row, string $key) use ($formatInputNumber): string {
    if (!is_array($row) || !array_key_exists($key, $row) || $row[$key] === null) {
        return '';
    }

    return $formatInputNumber((float)$row[$key]);
};

$cashInputValueOrZero = static function (?array $row, string $key) use ($cashInputValue): string {
    $value = $cashInputValue($row, $key);
    return $value !== '' ? $value : '0';
};

$cashData = [
    'hotovost' => $cashInputValue($draftRow, 'hotovost'),
    'terminal' => $cashInputValue($draftRow, 'terminal'),
    'stravenky' => $cashInputValue($draftRow, 'stravenky'),
    'vydaje_benzin' => $cashInputValueOrZero($draftRow, 'vydaje_benzin'),
    'vydaje_auta' => $cashInputValueOrZero($draftRow, 'vydaje_auta'),
    'vydaje_suroviny' => $cashInputValueOrZero($draftRow, 'vydaje_suroviny'),
    'vydaje_ostatni' => $cashInputValueOrZero($draftRow, 'vydaje_ostatni'),
    'vydaje_phm_soukrome' => $cashInputValueOrZero($draftRow, 'vydaje_phm_soukrome'),
];

$draftNote = trim((string)($draftRow['poznamka'] ?? ''));
$previousNote = '';
if ($reportBranchId > 0) {
    $previousReportDate = $reportDateDt->modify('-1 day')->format('Y-m-d');
    $previousRow = cb_db_dr_pracovni_find($conn, $reportBranchId, $previousReportDate);
    $previousNote = trim((string)($previousRow['poznamka'] ?? ''));
}

if ($reportBranchId > 0) {
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
    $idDrOsoby = (int)($row['id_dr_osoby'] ?? 0);
    $idUser = (int)($row['id_user'] ?? 0);
    $name = trim((string)($row['name'] ?? ''));
    $start = trim((string)($row['start'] ?? ''));
    $end = trim((string)($row['end'] ?? ''));
    $break = trim((string)($row['break'] ?? '0'));
    $hours = trim((string)($row['hours'] ?? '0'));

    return ''
        . '<tr data-zr-person-row="instor" data-zr-id-user="' . h((string)$idUser) . '" data-zr-id-dr-osoby="' . h((string)$idDrOsoby) . '">'
        . '<td style="width:220px;"><button type="button" class="zr_row_remove" data-zr-remove-row title="Odebrat" aria-label="Odebrat">×</button><strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="instor_jmeno[]" value="' . h($name) . '">'
        . '<input type="hidden" name="instor_id_user[]" value="' . h((string)$idUser) . '">'
        . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_zacatek[]', $start, 'data-zr-start') . '</td>'
        . '<td style="width:58px;">' . $renderTimeInput('instor_konec[]', $end, 'data-zr-end') . '</td>'
        . '<td class="zr_person_cell_break" style="width:44px;"><input type="text" inputmode="decimal" name="instor_pauza_hod[]" value="' . h($break) . '" style="width:100%;text-align:center;" data-zr-break></td>'
        . '<td style="width:70px;"><strong class="zr_saved_value" data-zr-hours>' . h($hours) . ' hod.</strong><input type="hidden" name="instor_hodiny[]" value="' . h($hours) . '" data-zr-hours-hidden></td>'
        . '<td></td>'
        . '</tr>';
};

$renderKuryrSavedRow = static function (array $row, callable $formatMoney, callable $renderTimeInput): string {
    $idDrOsoby = (int)($row['id_dr_osoby'] ?? 0);
    $idUser = (int)($row['id_user'] ?? 0);
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
        . '<tr data-zr-person-row="kuryr" data-zr-id-user="' . h((string)$idUser) . '" data-zr-id-dr-osoby="' . h((string)$idDrOsoby) . '">'
        . '<td style="width:220px;"><button type="button" class="zr_row_remove" data-zr-remove-row title="Odebrat" aria-label="Odebrat">×</button><strong class="zr_saved_value">' . h($name) . '</strong>'
        . '<input type="hidden" name="kuryr_jmeno[]" value="' . h($name) . '">'
        . '<input type="hidden" name="kuryr_id_user[]" value="' . h((string)$idUser) . '">'
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
<?php if (!$canSaveReport): ?>
  <div class="zr_readonly_info">
    Denní report mohou upravovat pouze oprávnění uživatelé. Zobrazená data jsou jen pro kontrolu.
  </div>
<?php endif; ?>
<form class="zr_form gap_14" autocomplete="off" method="post" action="<?= h(cb_url('/')) ?>" data-zr-form data-cb-max-form="1" data-cb-loader-text="Načítám report pobočky" style="position:relative;">
  <input type="hidden" name="dr_id" value="<?= h((string)$idDr) ?>" data-zr-dr-id>
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
                    <?= $renderUserSelectOptions($instorOptions, $openingId, 'Vyber jméno') ?>
                  </select>
                </td>
              </tr>
              <tr>
                <th class="zr_intro_label zr_req_label txt_l" data-zr-required-label="zaviral">Zavíral</th>
                <td>
                  <select class="zr_intro_select" name="zaviral" data-zr-field="zaviral" data-zr-required="zaviral">
                    <?= $renderUserSelectOptions($instorOptions, $closingId, 'Vyber jméno') ?>
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
                <th class="zr_req_label txt_l" data-zr-required-label="pokladna_terminal">Terminal</th>
                <th class="zr_req_label txt_l" data-zr-required-label="pokladna_stravenky">Stravenky</th>
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
              <?= $renderUserSelectOptions($instorOptions, 0, 'Vyber zaměstnance', $usedInstorIds) ?>
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
              <?= $renderUserSelectOptions($kuryrOptions, 0, 'Vyber kurýra', $usedKuryrIds) ?>
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
        <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_note_section">
          <?php if ($previousNote !== ''): ?>
            <div style="margin-bottom:8px;">
              <div class="txt_seda text_12">Včerejší vzkaz</div>
              <div class="text_14"><?= h($previousNote) ?></div>
            </div>
          <?php endif; ?>
          <label class="txt_seda text_12" for="zr_note">Vzkaz pro další směnu nebo managera</label>
          <input
            id="zr_note"
            type="text"
            name="poznamka"
            value="<?= h($draftNote) ?>"
            data-zr-note
            style="width:100%;margin-top:4px;"
          >
        </section>
        <?php if ($canSaveReport && $reportBranchId > 0): ?>
          <button
            type="button"
            class="zr_submit"
            disabled
            data-zr-submit
            data-zr-submit-locked-text="Report bude možné uložit za"
            data-zr-submit-ready-text="Report je zkontrolovaný, uložit"
            data-zr-submit-missing-text="Vyplň všechna povinná data reportu"
            data-zr-submit-at="<?= h((string)$reportSaveAtTs) ?>"
            style="background:#d9dee8;border-color:#c1c9d6;color:#5f6b7a;cursor:not-allowed;opacity:1;"
          >Report bude možné uložit za 0:00:00</button>
        <?php endif; ?>
      </div>
    </div>

    <aside class="zr_side gap_14">
      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_restia_section">
        <h4 class="card_section_title txt_seda">Aktuální data z Restie</h4>
        <div class="zr_restia_update">
          <span>Aktualizace v <?= h($lastRestiaUpdateLabel) ?></span>
          <button
            type="button"
            class="zr_restia_refresh_btn"
            disabled
            data-zr-restia-refresh
            data-zr-restia-refresh-at="<?= h((string)$reportRefreshAtTs) ?>"
            title="Aktualizovat Restii"
            aria-label="Aktualizovat Restii"
          >↻</button>
        </div>
        <div class="zr_restia_total gap_4">
          <span class="zr_metric_label">Tržba</span>
          <strong class="zr_metric_value" data-zr-restia-trzba><?= h($formatMoneyWhole((float)$restiaSummary['trzba'])) ?></strong>
        </div>
        <table class="zr_table zr_restia_table">
          <tbody>
            <tr><td class="zr_restia_key">Wolt</td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt><?= h($formatMoneyWhole((float)$restiaSummary['wolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Bolt</td><td class="zr_restia_value txt_r"><strong data-zr-restia-bolt><?= h($formatMoneyWhole((float)$restiaSummary['bolt'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Foodora</td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj><?= h($formatMoneyWhole((float)$restiaSummary['dj'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Web</td><td class="zr_restia_value txt_r"><strong data-zr-restia-web><?= h($formatMoneyWhole((float)$restiaSummary['web'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Wolt drive cash</td><td class="zr_restia_value txt_r"><strong data-zr-restia-wolt-cash><?= h($formatMoneyWhole((float)$restiaSummary['wolt_cash'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">DJ cash</td><td class="zr_restia_value txt_r"><strong data-zr-restia-dj-cash><?= h($formatMoneyWhole((float)$restiaSummary['dj_cash'])) ?></strong></td></tr>
          </tbody>
        </table>
      </section>

      <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 zr_section zr_ops_section">
        <h4 class="card_section_title txt_seda">Operativa a kontrola</h4>
        <table class="zr_table zr_restia_table">
          <tbody>
            <tr><td class="zr_restia_key">Zrušené obj. ks</td><td class="zr_restia_value txt_r"><strong data-zr-cancel-count><?= h((string)$restiaSummary['cancel_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Zrušené obj. Kč</td><td class="zr_restia_value txt_r"><strong data-zr-cancel-value><?= h($formatMoneyWhole((float)$restiaSummary['cancel_value'])) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Zpožděné rozvozy +5 min</td><td class="zr_restia_value txt_r"><strong data-zr-delay-count><?= h((string)$restiaSummary['delay_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Průměrný make time</td><td class="zr_restia_value txt_r"><strong data-zr-make-time><?= h($makeTimeLabel) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Výdajové doklady</td><td class="zr_restia_value txt_r"><strong data-zr-docs-count><?= h((string)$restiaSummary['docs_count']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Nezrušené celkem</td><td class="zr_restia_value txt_r"><strong data-zr-orders-total><?= h((string)$restiaSummary['orders_total']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Naše rozvozy</td><td class="zr_restia_value txt_r"><strong data-zr-own-deliveries><?= h((string)$restiaSummary['own_deliveries']) ?></strong></td></tr>
            <tr><td class="zr_restia_key">Pozdě WoltDrive 5+</td><td class="zr_restia_value txt_r"><strong data-zr-woltdrive-late><?= h((string)$restiaSummary['woltdrive_late']) ?></strong></td></tr>
          </tbody>
        </table>
      </section>
    </aside>
  </div>
  <?php if (!$canSaveReport): ?>
    <div class="zr_readonly_overlay" aria-hidden="true"></div>
  <?php endif; ?>
</form>
<?php
$card_max_html = (string)ob_get_clean();
/* karty/zadani_reportu.php * Verze: V3 * Aktualizace: 12.05.2026 */
