<?php
// lib/denni_report_data.php * K10 pomocna priprava dat denniho reportu
declare(strict_types=1);

require_once __DIR__ . '/vypocet_col_rozdil.php';

function cb_denni_report_format_money(float $value): string
{
    $rounded = round($value, 2);
    $decimals = (abs($rounded - round($rounded)) < 0.001) ? 0 : 2;
    return number_format($rounded, $decimals, ',', ' ') . ' Kč';
}

function cb_denni_report_format_money_whole(float $value): string
{
    return number_format(round($value), 0, ',', ' ') . ' Kč';
}

function cb_denni_report_format_input_number(float $value): string
{
    $rounded = round($value, 2);
    $decimals = (abs($rounded - round($rounded)) < 0.001) ? 0 : 2;
    return number_format($rounded, $decimals, '.', '');
}

function cb_denni_report_format_percent(?float $value): string
{
    return $value === null ? '-- %' : number_format($value * 100, 2, ',', ' ') . ' %';
}

function cb_denni_report_format_time(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m) === 1) {
        return sprintf('%02d:%s', (int)$m[1], $m[2]);
    }

    return cb_dt_time_hm($raw);
}

function cb_denni_report_person_full_name(?string $jmeno, ?string $prijmeni = null): string
{
    return trim(trim((string)$jmeno) . ' ' . trim((string)$prijmeni));
}

function cb_denni_report_person_display_name(?string $jmeno, ?string $prijmeni = null): string
{
    return trim(trim((string)$prijmeni) . ' ' . trim((string)$jmeno));
}

function cb_denni_report_user_full_name_by_id(mysqli $conn, ?int $idUser): string
{
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

    return is_array($row) ? cb_denni_report_person_full_name($row['jmeno'] ?? '', $row['prijmeni'] ?? '') : '';
}

function cb_denni_report_user_name_parts_by_id(mysqli $conn, ?int $idUser): array
{
    if ($idUser === null || $idUser <= 0) {
        return ['jmeno' => '', 'prijmeni' => ''];
    }

    $stmt = $conn->prepare('SELECT jmeno, prijmeni FROM user WHERE id_user = ? LIMIT 1');
    if ($stmt === false) {
        return ['jmeno' => '', 'prijmeni' => ''];
    }
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!is_array($row)) {
        return ['jmeno' => '', 'prijmeni' => ''];
    }

    return [
        'jmeno' => trim((string)($row['jmeno'] ?? '')),
        'prijmeni' => trim((string)($row['prijmeni'] ?? '')),
    ];
}

function cb_denni_report_current_workday_date(): DateTimeImmutable
{
    return cb_dt_workday_start(null, 6);
}

function cb_denni_report_workday_options(DateTimeImmutable $currentWorkdayStart): array
{
    $options = [];
    for ($i = 0; $i <= 5; $i++) {
        $date = $currentWorkdayStart->modify('-' . $i . ' day');
        $value = $date->format('Y-m-d');
        $label = cb_dt_weekday_date_label_cs($date, true);
        if ($i === 0) {
            $label .= ' (dnes)';
        }
        $options[] = [
            'value' => $value,
            'label' => $label,
        ];
    }

    return $options;
}

function cb_denni_report_missing_reports_summary(mysqli $conn, string $date): array
{
    if ($date === '') {
        return [];
    }

    $sql = "
        SELECT p.id_pob, p.nazev
        FROM pobocka p
        LEFT JOIN reporty_is r
            ON r.id_pob = p.id_pob
           AND r.datum_reportu = ?
           AND r.platny = 1
        WHERE p.aktivni = 1
          AND p.id_pob > 0
          AND r.id_reportu IS NULL
        ORDER BY p.id_pob ASC
    ";

    $stmt = $conn->prepare($sql);
    $rows = [];
    if ($stmt !== false) {
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $idPob = (int)($row['id_pob'] ?? 0);
                $name = trim((string)($row['nazev'] ?? ''));
                if ($idPob >= 0) {
                    $rows[] = [
                        'id_pob' => $idPob,
                        'nazev' => $name !== '' ? $name : ('Pobočka ' . $idPob),
                    ];
                }
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function cb_denni_report_has_any_report(mysqli $conn, string $date): bool
{
    if ($date === '') {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM reporty_is
        WHERE datum_reportu = ?
          AND platny = 1
        LIMIT 1
    ');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasRow = ($result instanceof mysqli_result) ? ($result->fetch_assoc() !== null) : false;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return $hasRow;
}

function cb_denni_report_user_main_branch_id(mysqli $conn, int $idUser): int
{
    if ($idUser <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('
        SELECT id_pob
        FROM user_pobocka
        WHERE id_user = ?
          AND main = 1
        LIMIT 1
    ');
    if ($stmt === false) {
        return 0;
    }

    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? (int)($row['id_pob'] ?? 0) : 0;
}

function cb_denni_report_history_load(mysqli $conn, int $idPob, string $reportDate): ?array
{
    if ($idPob <= 0 || $reportDate === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT
            r.id_reportu,
            r.datum_reportu,
            r.id_pob,
            r.oteviral,
            r.zaviral,
            r.oteviral_text,
            r.zaviral_text,
            r.poznamka,
            pk.hotovost,
            pk.terminal,
            pk.stravenky,
            pk.rozdil,
            pk.vydaje_benzin,
            pk.vydaje_auta,
            pk.vydaje_suroviny,
            pk.vydaje_ostatni,
            pk.vydaje_phm_soukrome,
            ri.trzba,
            ri.wolt,
            ri.wolt_obj,
            ri.bolt,
            ri.bolt_obj,
            ri.damejidlo,
            ri.damejidlo_obj,
            ri.web,
            ri.web_obj,
            ri.wolt_cash,
            ri.wolt_cash_obj,
            ri.dj_cash,
            ri.dj_cash_obj,
            ri.col_pomer,
            ri.zrusene_obj_ks,
            ri.zrusene_obj_kc,
            ri.zpozdene_rozvozy_5_min,
            ri.make_time_prumer_sec,
            ri.objednavky_nezrusene_ks,
            ri.nase_rozvozy_ks,
            ri.woltdrive_pozde_5_min
        FROM reporty_is r
        LEFT JOIN reporty_is_pokladna pk
            ON pk.id_reportu = r.id_reportu
        LEFT JOIN reporty_is_restia ri
            ON ri.id_reportu = r.id_reportu
        WHERE r.id_pob = ?
          AND r.datum_reportu = ?
          AND r.platny = 1
        ORDER BY r.id_reportu DESC
        LIMIT 1
    ');
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param('is', $idPob, $reportDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $reportRow = ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!is_array($reportRow)) {
        return null;
    }

    $idReportu = (int)($reportRow['id_reportu'] ?? 0);
    if ($idReportu <= 0) {
        return null;
    }

    $stmtPeople = $conn->prepare('
        SELECT
            0 AS id_dr_osoby,
            id_user,
            slot AS id_slot,
            jmeno,
            prijmeni,
            smena_od,
            smena_do,
            pauza,
            odpracovano,
            rozvozu_manual,
            vlastni_vuz,
            vyplatit_phm,
            rozvozu_restia
        FROM reporty_is_osoby
        WHERE id_reportu = ?
        ORDER BY slot ASC, COALESCE(smena_od, "00:00:00") ASC, COALESCE(smena_do, "00:00:00") ASC, prijmeni ASC, jmeno ASC
    ');
    $peopleRows = [];
    if ($stmtPeople !== false) {
        $stmtPeople->bind_param('i', $idReportu);
        $stmtPeople->execute();
        $peopleResult = $stmtPeople->get_result();
        if ($peopleResult instanceof mysqli_result) {
            while ($row = $peopleResult->fetch_assoc()) {
                $peopleRows[] = $row;
            }
            $peopleResult->free();
        }
        $stmtPeople->close();
    }

    return [
        'report' => $reportRow,
        'people_rows' => $peopleRows,
    ];
}

function cb_denni_report_previous_note_from_history(mysqli $conn, int $idPob, DateTimeImmutable $reportDateDt): string
{
    if ($idPob <= 0) {
        return '';
    }

    $previousReportDate = $reportDateDt->modify('-1 day')->format('Y-m-d');
    $stmt = $conn->prepare('
        SELECT poznamka
        FROM reporty_is
        WHERE id_pob = ?
          AND datum_reportu = ?
          AND platny = 1
        ORDER BY id_reportu DESC
        LIMIT 1
    ');
    if ($stmt === false) {
        return '';
    }

    $stmt->bind_param('is', $idPob, $previousReportDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return trim((string)($row['poznamka'] ?? ''));
}

function cb_denni_report_ensure_user_option(array $options, int $selectedId, string $selectedName): array
{
    if ($selectedId <= 0 || trim($selectedName) === '') {
        return $options;
    }

    foreach ($options as $option) {
        if ((int)($option['id_user'] ?? 0) === $selectedId) {
            return $options;
        }
    }

    array_unshift($options, [
        'id_user' => $selectedId,
        'name' => $selectedName,
        'restia_name' => $selectedName,
    ]);

    return $options;
}

function cb_denni_report_branch_slot_user_options(mysqli $conn, int $idPob, int $idSlot): array
{
    if ($idPob <= 0 || $idSlot <= 0) {
        return [];
    }

    $sql = "
        SELECT DISTINCT
            u.id_user,
            TRIM(CONCAT_WS(' ', u.jmeno, u.prijmeni)) AS full_name,
            TRIM(CONCAT_WS(' ', u.prijmeni, u.jmeno)) AS display_name
        FROM user u
        INNER JOIN user_pobocka up ON up.id_user = u.id_user
        INNER JOIN user_slot us ON us.id_user = u.id_user
        WHERE u.aktivni = 1
          AND up.id_pob = ?
          AND us.id_slot = ?
        HAVING full_name <> ''
        ORDER BY display_name ASC
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
                $displayName = trim((string)($row['display_name'] ?? ''));
                if ($idUser > 0 && $name !== '') {
                    $users[$idUser] = [
                        'id_user' => $idUser,
                        'name' => $displayName !== '' ? $displayName : $name,
                        'restia_name' => $name,
                    ];
                }
            }
            $result->free();
        }
        $stmt->close();
    }

    return array_values($users);
}

function cb_denni_report_shift_plan_people_rows(mysqli $conn, int $idPob, string $date): array
{
    if ($idPob <= 0 || $date === '') {
        return [];
    }

    $sqlShiftPlan = "
        SELECT
            0 AS id_dr_osoby,
            sp.id_user,
            sp.id_slot,
            u.jmeno,
            u.prijmeni,
            sp.cas_od AS smena_od,
            sp.cas_do AS smena_do,
            0 AS pauza,
            TIMESTAMPDIFF(MINUTE, CONCAT(sp.datum, ' ', sp.cas_od), DATE_ADD(CONCAT(sp.datum, ' ', sp.cas_do), INTERVAL CASE WHEN sp.cas_do < sp.cas_od THEN 1 ELSE 0 END DAY)) / 60 AS odpracovano,
            0 AS rozvozu_manual,
            0 AS vlastni_vuz,
            0 AS vyplatit_phm,
            0 AS rozvozu_restia
        FROM smeny_plan sp
        INNER JOIN user u ON u.id_user = sp.id_user
        WHERE sp.id_pob = ?
          AND sp.datum = ?
          AND sp.id_slot IN (1, 2)
        ORDER BY sp.id_slot ASC, sp.cas_od ASC, u.jmeno ASC, u.prijmeni ASC
    ";

    $stmtShiftPlan = $conn->prepare($sqlShiftPlan);
    $rows = [];
    if ($stmtShiftPlan !== false) {
        $stmtShiftPlan->bind_param('is', $idPob, $date);
        $stmtShiftPlan->execute();
        $resultShiftPlan = $stmtShiftPlan->get_result();
        if ($resultShiftPlan instanceof mysqli_result) {
            while ($row = $resultShiftPlan->fetch_assoc()) {
                $rows[] = $row;
            }
            $resultShiftPlan->free();
        }
        $stmtShiftPlan->close();
    }

    return $rows;
}

function cb_denni_report_planned_instor_defaults(mysqli $conn, int $idPob, string $date): array
{
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
}

function cb_denni_report_cash_data(?array $row): array
{
    $value = static function (?array $row, string $key): string {
        if (!is_array($row) || !array_key_exists($key, $row) || $row[$key] === null) {
            return '';
        }

        return cb_denni_report_format_input_number((float)$row[$key]);
    };

    $valueOrZero = static function (?array $row, string $key) use ($value): string {
        $val = $value($row, $key);
        return $val !== '' ? $val : '0';
    };

    return [
        'hotovost' => $value($row, 'hotovost'),
        'terminal' => $value($row, 'terminal'),
        'stravenky' => $value($row, 'stravenky'),
        'vydaje_benzin' => $valueOrZero($row, 'vydaje_benzin'),
        'vydaje_auta' => $valueOrZero($row, 'vydaje_auta'),
        'vydaje_suroviny' => $valueOrZero($row, 'vydaje_suroviny'),
        'vydaje_ostatni' => $valueOrZero($row, 'vydaje_ostatni'),
        'vydaje_phm_soukrome' => $valueOrZero($row, 'vydaje_phm_soukrome'),
    ];
}

function cb_denni_report_previous_note(mysqli $conn, int $idPob, DateTimeImmutable $reportDateDt): string
{
    if ($idPob <= 0) {
        return '';
    }

    $previousReportDate = $reportDateDt->modify('-1 day')->format('Y-m-d');
    $previousRow = cb_db_dr_pracovni_find($conn, $idPob, $previousReportDate);

    return trim((string)($previousRow['poznamka'] ?? ''));
}

function cb_denni_report_restia_summary_default(): array
{
    return [
        'trzba' => 0.0,
        'wolt' => 0.0,
        'wolt_count' => 0,
        'bolt' => 0.0,
        'bolt_count' => 0,
        'dj' => 0.0,
        'dj_count' => 0,
        'web' => 0.0,
        'web_count' => 0,
        'wolt_cash' => 0.0,
        'wolt_cash_count' => 0,
        'dj_cash' => 0.0,
        'dj_cash_count' => 0,
        'other' => 0.0,
        'other_count' => 0,
        'control_amount' => 0.0,
        'control_count' => 0,
        'cancel_count' => 0,
        'cancel_value' => 0.0,
        'delay_count' => 0,
        'make_time_avg_sec' => null,
        'docs_count' => 0,
        'orders_total' => 0,
        'own_deliveries' => 0,
        'woltdrive_late' => 0,
    ];
}

function cb_denni_report_restia_summary(mysqli $conn, int $idPob, array $workdayRange): array
{
    $restiaSummary = cb_denni_report_restia_summary_default();

    if ($idPob > 0) {
        $notCanceled = "COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted')";
        $woltCondition = $notCanceled . " AND cp.kod = 'wolt' AND COALESCE(p.nazev, '') <> 'cash'";
        $boltCondition = $notCanceled . " AND cp.kod = 'bolt'";
        $djCondition = $notCanceled . " AND cp.kod IN ('foodora', 'damejidlo') AND COALESCE(p.nazev, '') <> 'cash'";
        $webCondition = $notCanceled . " AND cp.kod = 'generic' AND COALESCE(p.nazev, '') = 'online'";
        $woltCashCondition = $notCanceled . " AND cp.kod = 'generic' AND COALESCE(d.nazev, '') = 'delivery' AND COALESCE(p.nazev, '') = 'cash'";
        $djCashCondition = $notCanceled . " AND cp.kod IN ('foodora', 'damejidlo') AND COALESCE(p.nazev, '') = 'cash'";
        $otherCondition = $notCanceled . " AND NOT (("
            . $woltCondition . ") OR ("
            . $boltCondition . ") OR ("
            . $djCondition . ") OR ("
            . $webCondition . ") OR ("
            . $woltCashCondition . ") OR ("
            . $djCashCondition . "))";

        $summarySql = "
            SELECT
                SUM(CASE WHEN COALESCE(s.nazev, '') IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN 0 ELSE COALESCE(c.cena_celk, 0) END) AS trzba,
                SUM(CASE WHEN " . $woltCondition . " THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS wolt,
                COUNT(DISTINCT CASE WHEN " . $woltCondition . " THEN o.id_obj ELSE NULL END) AS wolt_count,
                SUM(CASE WHEN " . $boltCondition . " THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS bolt,
                COUNT(DISTINCT CASE WHEN " . $boltCondition . " THEN o.id_obj ELSE NULL END) AS bolt_count,
                SUM(CASE WHEN " . $djCondition . " THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS damejidlo,
                COUNT(DISTINCT CASE WHEN " . $djCondition . " THEN o.id_obj ELSE NULL END) AS damejidlo_count,
                SUM(CASE WHEN " . $webCondition . " THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS web,
                COUNT(DISTINCT CASE WHEN " . $webCondition . " THEN o.id_obj ELSE NULL END) AS web_count,
                SUM(CASE WHEN " . $woltCashCondition . " THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS wolt_cash,
                COUNT(DISTINCT CASE WHEN " . $woltCashCondition . " THEN o.id_obj ELSE NULL END) AS wolt_cash_count,
                SUM(CASE WHEN " . $djCashCondition . " THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS dj_cash,
                COUNT(DISTINCT CASE WHEN " . $djCashCondition . " THEN o.id_obj ELSE NULL END) AS dj_cash_count,
                SUM(CASE WHEN " . $otherCondition . " THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS other,
                COUNT(DISTINCT CASE WHEN " . $otherCondition . " THEN o.id_obj ELSE NULL END) AS other_count,
                SUM(CASE WHEN COALESCE(s.nazev, '') IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN 1 ELSE 0 END) AS cancel_count,
                SUM(CASE WHEN COALESCE(s.nazev, '') IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS cancel_value,
                AVG(CASE WHEN " . $notCanceled . " AND ca.cas_vytvor IS NOT NULL AND ca.cas_pripr_v IS NOT NULL THEN TIMESTAMPDIFF(SECOND, ca.cas_vytvor, ca.cas_pripr_v) END) AS make_time_avg_sec,
                COUNT(DISTINCT CASE WHEN " . $notCanceled . " THEN o.id_obj ELSE NULL END) AS orders_total,
                COUNT(DISTINCT CASE WHEN " . $notCanceled . " AND ok.provider = 'delivery' THEN o.id_obj ELSE NULL END) AS own_deliveries,
                COUNT(DISTINCT CASE WHEN " . $notCanceled . " AND ok.provider = 'delivery' AND ca.cas_slib IS NOT NULL AND ca.cas_doruc IS NOT NULL AND TIMESTAMPDIFF(MINUTE, ca.cas_slib, ca.cas_doruc) > 5 THEN o.id_obj ELSE NULL END) AS delay_count,
                COUNT(DISTINCT CASE WHEN " . $notCanceled . " AND ok.provider = 'external-delivery' AND ca.cas_slib IS NOT NULL AND ca.cas_doruc IS NOT NULL AND TIMESTAMPDIFF(MINUTE, ca.cas_slib, ca.cas_doruc) > 5 THEN o.id_obj ELSE NULL END) AS woltdrive_late
            FROM objednavky_restia o
            LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
            LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
            LEFT JOIN cis_obj_platby p ON p.id_platba = o.id_platba
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
            $stmtSummary->bind_param('iss', $idPob, $fromDb, $toDb);
            $stmtSummary->execute();
            $summaryResult = $stmtSummary->get_result();
            if ($summaryResult instanceof mysqli_result) {
                $summaryRow = $summaryResult->fetch_assoc() ?: [];
                $wolt = (float)($summaryRow['wolt'] ?? 0);
                $bolt = (float)($summaryRow['bolt'] ?? 0);
                $dj = (float)($summaryRow['damejidlo'] ?? 0);
                $web = (float)($summaryRow['web'] ?? 0);
                $woltCash = (float)($summaryRow['wolt_cash'] ?? 0);
                $djCash = (float)($summaryRow['dj_cash'] ?? 0);
                $other = (float)($summaryRow['other'] ?? 0);
                $woltCount = (int)($summaryRow['wolt_count'] ?? 0);
                $boltCount = (int)($summaryRow['bolt_count'] ?? 0);
                $djCount = (int)($summaryRow['damejidlo_count'] ?? 0);
                $webCount = (int)($summaryRow['web_count'] ?? 0);
                $woltCashCount = (int)($summaryRow['wolt_cash_count'] ?? 0);
                $djCashCount = (int)($summaryRow['dj_cash_count'] ?? 0);
                $otherCount = (int)($summaryRow['other_count'] ?? 0);
                $restiaSummary = [
                    'trzba' => (float)($summaryRow['trzba'] ?? 0),
                    'wolt' => $wolt,
                    'wolt_count' => $woltCount,
                    'bolt' => $bolt,
                    'bolt_count' => $boltCount,
                    'dj' => $dj,
                    'dj_count' => $djCount,
                    'web' => $web,
                    'web_count' => $webCount,
                    'wolt_cash' => $woltCash,
                    'wolt_cash_count' => $woltCashCount,
                    'dj_cash' => $djCash,
                    'dj_cash_count' => $djCashCount,
                    'other' => $other,
                    'other_count' => $otherCount,
                    'control_amount' => $wolt + $bolt + $dj + $web + $woltCash + $djCash + $other,
                    'control_count' => $woltCount + $boltCount + $djCount + $webCount + $woltCashCount + $djCashCount + $otherCount,
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
    
    
    return $restiaSummary;
}

function cb_denni_report_kuryr_delivery_data(mysqli $conn, int $idPob, array $workdayRange, array $kuryrRows): array
{
    $kuryrDeliveryCounts = [];

    if ($idPob > 0) {
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
            $stmtDeliveries->bind_param('iss', $idPob, $fromDb, $toDb);
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
        $kuryrName = trim((string)($kuryrRow['restia_name'] ?? $kuryrRow['name'] ?? ''));
        $deliveryRestia = (int)($kuryrDeliveryCounts[$kuryrName] ?? 0);
        $deliveryManual = (int)($kuryrRow['delivery_manual'] ?? 0);
        $kuryrRow['delivery_restia'] = $deliveryRestia;
        $kuryrRow['delivery_total'] = $deliveryRestia + $deliveryManual;
    }
    unset($kuryrRow);

    $countsJson = (string)json_encode($kuryrDeliveryCounts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    if ($countsJson === '') {
        $countsJson = '{}';
    }

    return ['kuryr_rows' => $kuryrRows, 'counts_json' => $countsJson];
}

function cb_denni_report_control_values(mysqli $conn, string $datumReportu, array $restiaSummary, ?array $draftRow, array $draftPersonRows): array
{
    $makeTimeLabel = cb_report_make_time_label(is_int($restiaSummary['make_time_avg_sec']) ? (int)$restiaSummary['make_time_avg_sec'] : null);
    $values = cb_vypocet_col_rozdil(
        $conn,
        $datumReportu,
        $restiaSummary,
        cb_vcr_cash_from_draft($draftRow),
        cb_vcr_people_from_rows($draftPersonRows)
    );
    $reportDifference = $values['rozdil'];
    $reportCol = $values['col_pomer'];
    $reportColBezDph = isset($values['col_bez_dph_pomer']) && is_numeric($values['col_bez_dph_pomer'])
        ? (float)$values['col_bez_dph_pomer']
        : null;

    return [
        'make_time_label' => $makeTimeLabel,
        'difference_label' => $reportDifference === null ? '-- Kč' : cb_denni_report_format_money_whole((float)$reportDifference),
        'difference_value' => $reportDifference === null ? '' : number_format((float)$reportDifference, 2, '.', ''),
        'col_label' => cb_denni_report_format_percent($reportCol),
        'col_value' => $reportCol === null ? '' : number_format((float)$reportCol, 6, '.', ''),
        'col_bez_dph_label' => 'COL bez DPH: ' . cb_denni_report_format_percent($reportColBezDph),
    ];
}

function cb_denni_report_person_rows(array $draftPersonRows): array
{
    $instorRows = [];
    $kuryrRows = [];

    foreach ($draftPersonRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $personRow = [
            'id_dr_osoby' => (int)($row['id_dr_osoby'] ?? 0),
            'id_user' => (int)($row['id_user'] ?? 0),
            'name' => cb_denni_report_person_display_name($row['jmeno'] ?? '', $row['prijmeni'] ?? ''),
            'restia_name' => cb_denni_report_person_full_name($row['jmeno'] ?? '', $row['prijmeni'] ?? ''),
            'start' => cb_denni_report_format_time((string)($row['smena_od'] ?? '')),
            'end' => cb_denni_report_format_time((string)($row['smena_do'] ?? '')),
            'break' => $row['pauza'] === null ? '' : cb_denni_report_format_input_number((float)$row['pauza']),
            'hours' => $row['odpracovano'] === null ? '0' : cb_denni_report_format_input_number((float)$row['odpracovano']),
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

    return ['instor' => $instorRows, 'kuryr' => $kuryrRows];
}

function cb_denni_report_used_user_ids(array $rows): array
{
    return array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id_user'] ?? 0), $rows)));
}

function cb_denni_report_docs_count_from_person_rows(array $rows): int
{
    $count = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((int)($row['id_slot'] ?? 0) === 2 && (float)($row['vyplatit_phm'] ?? 0) > 0) {
            $count++;
        }
    }

    return $count;
}

function cb_denni_report_prepare_data(mysqli $conn, string $renderMode = ''): array
{
    $renderMode = trim($renderMode);
    $isMaxRender = ($renderMode === 'max');
    
    $tz = new DateTimeZone('Europe/Prague');
    $currentWorkdayDt = cb_denni_report_current_workday_date();
    $workdayOptions = cb_denni_report_workday_options($currentWorkdayDt);
    $miniMissingReportDays = [];
    $todayDate = $currentWorkdayDt->format('Y-m-d');
    $showTodayMiniMissingReport = cb_denni_report_has_any_report($conn, $todayDate);
    for ($i = 0; $i <= 4; $i++) {
        if ($i === 0 && !$showTodayMiniMissingReport) {
            continue;
        }
        $miniMissingReportDays[] = $currentWorkdayDt->modify('-' . $i . ' day');
    }
    $miniMissingReports = [];
    foreach ($miniMissingReportDays as $dayDt) {
        $dayDate = $dayDt->format('Y-m-d');
        $missingBranches = cb_denni_report_missing_reports_summary($conn, $dayDate);
        $miniMissingReports[] = [
            'date' => $dayDate,
            'label' => cb_dt_weekday_date_label_cs($dayDt, true),
            'branches' => $missingBranches,
            'branches_text' => $missingBranches !== []
                ? implode(', ', array_map(static fn(array $row): string => (string)$row['nazev'], $missingBranches))
                : 'OK',
        ];
    }
    if (!$isMaxRender) {
        return [
            'renderMode' => $renderMode,
            'isMaxRender' => $isMaxRender,
            'tz' => $tz,
            'currentWorkdayDt' => $currentWorkdayDt,
            'workdayOptions' => $workdayOptions,
            'miniMissingReports' => $miniMissingReports,
        ];
    }
    $allowedWorkdayValues = array_column($workdayOptions, 'value');
    $requestedReportDate = trim((string)($_POST['datum_reportu'] ?? $_GET['datum_reportu'] ?? ''));
    if (!in_array($requestedReportDate, $allowedWorkdayValues, true)) {
        $requestedReportDate = $currentWorkdayDt->format('Y-m-d');
    }
    $reportDate = $requestedReportDate;
    $reportDateDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $reportDate . ' 06:00:00', $tz);
    if (!($reportDateDt instanceof DateTimeImmutable)) {
        $reportDateDt = $currentWorkdayDt;
        $reportDate = $reportDateDt->format('Y-m-d');
    }
    $isCurrentWorkday = ($reportDate === $currentWorkdayDt->format('Y-m-d'));
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
    $hasRole5 = isset($currentUserRoleIds[5]);
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
    $mainBranchId = cb_denni_report_user_main_branch_id($conn, $currentUserId);
    $requestedBranchId = (int)($_POST['zr_id_pob'] ?? $_GET['zr_id_pob'] ?? 0);
    $reportBranchId = ($requestedBranchId > 0 && in_array($requestedBranchId, $allowedBranchIds, true)) ? $requestedBranchId : 0;
    $singleAllowedBranchName = '';
    if ($reportBranchId <= 0 && count($allowedBranches) === 1) {
        $reportBranchId = (int)array_key_first($allowedBranches);
    }
    if ($reportBranchId <= 0 && $mainBranchId > 0 && in_array($mainBranchId, $allowedBranchIds, true)) {
        $reportBranchId = $mainBranchId;
    }
    if (count($allowedBranches) === 1 && $reportBranchId > 0 && isset($allowedBranches[$reportBranchId])) {
        $singleAllowedBranchName = (string)$allowedBranches[$reportBranchId];
    }
    
    if ($reportBranchId <= 0 && $canSaveReport && $currentUserId > 0 && $isCurrentWorkday) {
        $nowLocal = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');
        $dateFrom = $currentWorkdayDt->modify('-1 day')->format('Y-m-d');
        $dateTo = $currentWorkdayDt->format('Y-m-d');
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
    
    $historyData = null;
    $historyReportId = 0;
    $historyReportExists = false;
    if ($reportBranchId > 0) {
        $historyData = cb_denni_report_history_load($conn, $reportBranchId, $reportDate);
        if (is_array($historyData)) {
            $historyReportId = (int)($historyData['report']['id_reportu'] ?? 0);
            $historyReportExists = ($historyReportId > 0);
        }
    }
    $preferFinalReportData = $historyReportExists && is_array($historyData);
    $requestedFinalEdit = ((int)($_POST['zr_edit_final'] ?? $_GET['zr_edit_final'] ?? 0)) === 1;
    $canUnlockFinalReport = ($historyReportExists && $hasRole5 && $mainBranchId > 0 && $mainBranchId === $reportBranchId);
    $isEditingFinalReport = $canUnlockFinalReport && $requestedFinalEdit;
    $canEditHistory = (!$isCurrentWorkday && $canUnlockFinalReport);

    if ($historyReportExists) {
        $canEditReport = $isEditingFinalReport;
        $usesDraftPersistence = false;
        $isReadOnlyForm = !$isEditingFinalReport;
        $formMode = $isEditingFinalReport ? 'final_edit' : 'final_readonly';
    } else {
        $canEditReport = $isCurrentWorkday ? $canSaveReport : false;
        $usesDraftPersistence = $isCurrentWorkday && $canEditReport;
        $isReadOnlyForm = !$canEditReport;
        $formMode = $isCurrentWorkday ? 'workday' : 'history_readonly';
    }
    $missingHistoryReport = (!$isCurrentWorkday && !$historyReportExists && $reportBranchId > 0);
    $readonlyInfoText = '';
    if ($missingHistoryReport) {
        $readonlyInfoText = '';
    } elseif ($isReadOnlyForm) {
        $readonlyInfoText = 'Denní report mohou upravovat pouze oprávnění uživatelé. Zobrazená data jsou jen pro kontrolu.';
    }

    $instorOptions = cb_denni_report_branch_slot_user_options($conn, $reportBranchId, 1);
    $plannedInstorDefaults = cb_denni_report_planned_instor_defaults($conn, $reportBranchId, $reportDate);
    $kuryrOptions = cb_denni_report_branch_slot_user_options($conn, $reportBranchId, 2);
    $reportBranchName = $reportBranchId > 0 ? trim((string)($allowedBranches[$reportBranchId] ?? '')) : '';
    if ($reportBranchName === '' && $singleAllowedBranchName !== '') {
        $reportBranchName = trim($singleAllowedBranchName);
    }
    $missingHistoryReportText = $missingHistoryReport
        ? 'Pobočka ' . ($reportBranchName !== '' ? $reportBranchName : ('ID ' . $reportBranchId)) . ' nemá dne ' . $reportDateDisplay . ' zadaný report.'
        : '';
    
    $draftRow = null;
    $idDr = 0;
    $instorRows = [];
    $kuryrRows = [];
    $reportSaveAtTs = 0;
    
    if ($reportBranchId > 0 && $isCurrentWorkday) {
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
    
                $reportSaveAtTs = cb_report_save_at_ts($reportDateDt, (string)($endRow['end_time'] ?? ''), $reportSaveMinutes, 6);
            }
        }
    }
    $reportRefreshAtTs = cb_report_refresh_at_ts($reportSaveAtTs, 300);
    
    if ($reportBranchId > 0 && $usesDraftPersistence) {
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
    
    if ($usesDraftPersistence && $idDr > 0 && cb_db_dr_pracovni_osoby_list($conn, $idDr) === []) {
        foreach (cb_denni_report_shift_plan_people_rows($conn, $reportBranchId, $reportDate) as $row) {
            cb_db_dr_pracovni_osoby_insert(
                $conn,
                $idDr,
                (int)($row['id_user'] ?? 0),
                (int)($row['id_slot'] ?? 0),
                (string)($row['smena_od'] ?? ''),
                (string)($row['smena_do'] ?? ''),
                0.0,
                (float)($row['odpracovano'] ?? 0)
            );
        }
    }
    
    if ($preferFinalReportData) {
        $historyReport = (array)($historyData['report'] ?? []);
        $draftRow = array_merge(
            is_array($draftRow) ? $draftRow : [],
            [
                'datum_reportu' => $reportDate,
                'id_pob' => $reportBranchId,
                'oteviral' => (int)($historyReport['oteviral'] ?? 0),
                'zaviral' => (int)($historyReport['zaviral'] ?? 0),
                'hotovost' => $historyReport['hotovost'] ?? null,
                'terminal' => $historyReport['terminal'] ?? null,
                'stravenky' => $historyReport['stravenky'] ?? null,
                'vydaje_benzin' => $historyReport['vydaje_benzin'] ?? 0,
                'vydaje_auta' => $historyReport['vydaje_auta'] ?? 0,
                'vydaje_suroviny' => $historyReport['vydaje_suroviny'] ?? 0,
                'vydaje_ostatni' => $historyReport['vydaje_ostatni'] ?? 0,
                'vydaje_phm_soukrome' => $historyReport['vydaje_phm_soukrome'] ?? 0,
                'poznamka' => $historyReport['poznamka'] ?? '',
                'oteviral_text' => $historyReport['oteviral_text'] ?? '',
                'zaviral_text' => $historyReport['zaviral_text'] ?? '',
            ]
        );
        $draftPersonRows = is_array($historyData['people_rows'] ?? null) ? $historyData['people_rows'] : [];
    } elseif ($usesDraftPersistence) {
        $draftPersonRows = $idDr > 0 ? cb_db_dr_pracovni_osoby_list($conn, $idDr) : [];
    } elseif ($isCurrentWorkday) {
        $draftPersonRows = cb_denni_report_shift_plan_people_rows($conn, $reportBranchId, $reportDate);
    } elseif (is_array($historyData)) {
        $historyReport = $historyData['report'];
        $draftRow = [
            'datum_reportu' => $reportDate,
            'id_pob' => $reportBranchId,
            'oteviral' => (int)($historyReport['oteviral'] ?? 0),
            'zaviral' => (int)($historyReport['zaviral'] ?? 0),
            'hotovost' => $historyReport['hotovost'] ?? null,
            'terminal' => $historyReport['terminal'] ?? null,
            'stravenky' => $historyReport['stravenky'] ?? null,
            'vydaje_benzin' => $historyReport['vydaje_benzin'] ?? 0,
            'vydaje_auta' => $historyReport['vydaje_auta'] ?? 0,
            'vydaje_suroviny' => $historyReport['vydaje_suroviny'] ?? 0,
            'vydaje_ostatni' => $historyReport['vydaje_ostatni'] ?? 0,
            'vydaje_phm_soukrome' => $historyReport['vydaje_phm_soukrome'] ?? 0,
            'poznamka' => $historyReport['poznamka'] ?? '',
            'oteviral_text' => $historyReport['oteviral_text'] ?? '',
            'zaviral_text' => $historyReport['zaviral_text'] ?? '',
        ];
        $draftPersonRows = is_array($historyData['people_rows'] ?? null) ? $historyData['people_rows'] : [];
    } else {
        $draftPersonRows = [];
    }
    $reportPersonRows = cb_denni_report_person_rows($draftPersonRows);
    $instorRows = $reportPersonRows['instor'];
    $kuryrRows = $reportPersonRows['kuryr'];
    
    $usedInstorIds = cb_denni_report_used_user_ids($instorRows);
    $usedKuryrIds = cb_denni_report_used_user_ids($kuryrRows);
    
    $openingId = isset($draftRow['oteviral']) ? (int)$draftRow['oteviral'] : 0;
    $closingId = isset($draftRow['zaviral']) ? (int)$draftRow['zaviral'] : 0;
    $openingName = cb_denni_report_user_full_name_by_id($conn, $openingId > 0 ? $openingId : null);
    $closingName = cb_denni_report_user_full_name_by_id($conn, $closingId > 0 ? $closingId : null);
    if ($openingName === '' && $historyReportExists) {
        $openingName = trim((string)($draftRow['oteviral_text'] ?? ''));
    }
    if ($closingName === '' && $historyReportExists) {
        $closingName = trim((string)($draftRow['zaviral_text'] ?? ''));
    }
    if ($openingName === '') {
        $openingName = (string)($plannedInstorDefaults['opening'] ?? '');
        $openingId = (int)($plannedInstorDefaults['opening_id'] ?? 0);
    }
    if ($closingName === '') {
        $closingName = (string)($plannedInstorDefaults['closing'] ?? '');
        $closingId = (int)($plannedInstorDefaults['closing_id'] ?? 0);
    }
    $instorOptions = cb_denni_report_ensure_user_option($instorOptions, $openingId, $openingName);
    $instorOptions = cb_denni_report_ensure_user_option($instorOptions, $closingId, $closingName);
    
    $cashData = cb_denni_report_cash_data($draftRow);
    
    $draftNote = trim((string)($draftRow['poznamka'] ?? ''));
    $previousNote = $isCurrentWorkday
        ? cb_denni_report_previous_note($conn, $reportBranchId, $reportDateDt)
        : cb_denni_report_previous_note_from_history($conn, $reportBranchId, $reportDateDt);
    
    if ($historyReportExists) {
        $historyReport = is_array($historyData) ? (array)$historyData['report'] : [];
        $restiaSummary = cb_denni_report_restia_summary_default();
        $restiaSummary['trzba'] = (float)($historyReport['trzba'] ?? 0);
        $restiaSummary['wolt'] = (float)($historyReport['wolt'] ?? 0);
        $restiaSummary['bolt'] = (float)($historyReport['bolt'] ?? 0);
        $restiaSummary['dj'] = (float)($historyReport['damejidlo'] ?? 0);
        $restiaSummary['web'] = (float)($historyReport['web'] ?? 0);
        $restiaSummary['wolt_cash'] = (float)($historyReport['wolt_cash'] ?? 0);
        $restiaSummary['dj_cash'] = (float)($historyReport['dj_cash'] ?? 0);
        $restiaSummary['wolt_count'] = (int)($historyReport['wolt_obj'] ?? 0);
        $restiaSummary['bolt_count'] = (int)($historyReport['bolt_obj'] ?? 0);
        $restiaSummary['dj_count'] = (int)($historyReport['damejidlo_obj'] ?? 0);
        $restiaSummary['web_count'] = (int)($historyReport['web_obj'] ?? 0);
        $restiaSummary['wolt_cash_count'] = (int)($historyReport['wolt_cash_obj'] ?? 0);
        $restiaSummary['dj_cash_count'] = (int)($historyReport['dj_cash_obj'] ?? 0);
        $restiaSummary['other'] = 0.0;
        $restiaSummary['other_count'] = 0;
        $restiaSummary['control_amount'] = (float)($historyReport['trzba'] ?? 0);
        $restiaSummary['control_count'] = (int)($historyReport['objednavky_nezrusene_ks'] ?? 0);
        $restiaSummary['cancel_count'] = (int)($historyReport['zrusene_obj_ks'] ?? 0);
        $restiaSummary['cancel_value'] = (float)($historyReport['zrusene_obj_kc'] ?? 0);
        $restiaSummary['delay_count'] = (int)($historyReport['zpozdene_rozvozy_5_min'] ?? 0);
        $restiaSummary['make_time_avg_sec'] = isset($historyReport['make_time_prumer_sec']) ? (int)$historyReport['make_time_prumer_sec'] : null;
        $restiaSummary['docs_count'] = cb_denni_report_docs_count_from_person_rows($draftPersonRows);
        $restiaSummary['orders_total'] = (int)($historyReport['objednavky_nezrusene_ks'] ?? 0);
        $restiaSummary['own_deliveries'] = (int)($historyReport['nase_rozvozy_ks'] ?? 0);
        $restiaSummary['woltdrive_late'] = (int)($historyReport['woltdrive_pozde_5_min'] ?? 0);

        $kuryrDeliveryCountsJson = '{}';
        $controlValues = cb_denni_report_control_values($conn, $reportDate, $restiaSummary, $draftRow, $draftPersonRows);
        if (array_key_exists('rozdil', $historyReport)) {
            $savedRozdil = $historyReport['rozdil'];
            $controlValues['difference_label'] = $savedRozdil === null ? '-- KÄŤ' : cb_denni_report_format_money_whole((float)$savedRozdil);
            $controlValues['difference_value'] = $savedRozdil === null ? '' : number_format((float)$savedRozdil, 2, '.', '');
        }
        if (array_key_exists('col_pomer', $historyReport)) {
            $savedCol = $historyReport['col_pomer'];
            $savedColFloat = $savedCol === null ? null : (float)$savedCol;
            $controlValues['col_label'] = cb_denni_report_format_percent($savedColFloat);
            $controlValues['col_value'] = $savedColFloat === null ? '' : number_format($savedColFloat, 6, '.', '');
        }
        $kuryrDeliveryData = [
            'kuryr_rows' => $kuryrRows,
            'counts_json' => $kuryrDeliveryCountsJson,
        ];
    } elseif ($isCurrentWorkday) {
        $restiaSummary = cb_denni_report_restia_summary($conn, $reportBranchId, $workdayRange);
        $restiaSummary['docs_count'] = cb_denni_report_docs_count_from_person_rows($draftPersonRows);
        
        $kuryrDeliveryData = cb_denni_report_kuryr_delivery_data($conn, $reportBranchId, $workdayRange, $kuryrRows);
        $kuryrRows = $kuryrDeliveryData['kuryr_rows'];
        $kuryrDeliveryCountsJson = $kuryrDeliveryData['counts_json'];
        
        $controlValues = cb_denni_report_control_values($conn, $reportDate, $restiaSummary, $draftRow, $draftPersonRows);
    } else {
        $historyReport = is_array($historyData) ? (array)$historyData['report'] : [];
        $restiaSummary = cb_denni_report_restia_summary($conn, $reportBranchId, $workdayRange);
        $restiaSummary['docs_count'] = cb_denni_report_docs_count_from_person_rows($draftPersonRows);

        $kuryrDeliveryCountsJson = '{}';
        $controlValues = cb_denni_report_control_values($conn, $reportDate, $restiaSummary, $draftRow, $draftPersonRows);
        if (array_key_exists('rozdil', $historyReport)) {
            $savedRozdil = $historyReport['rozdil'];
            $controlValues['difference_label'] = $savedRozdil === null ? '-- Kč' : cb_denni_report_format_money_whole((float)$savedRozdil);
            $controlValues['difference_value'] = $savedRozdil === null ? '' : number_format((float)$savedRozdil, 2, '.', '');
        }
        if (array_key_exists('col_pomer', $historyReport)) {
            $savedCol = $historyReport['col_pomer'];
            $savedColFloat = $savedCol === null ? null : (float)$savedCol;
            $controlValues['col_label'] = cb_denni_report_format_percent($savedColFloat);
            $controlValues['col_value'] = $savedColFloat === null ? '' : number_format($savedColFloat, 6, '.', '');
        }
        $kuryrDeliveryData = [
            'kuryr_rows' => $kuryrRows,
            'counts_json' => $kuryrDeliveryCountsJson,
        ];
    }
    $makeTimeLabel = $controlValues['make_time_label'];
    $reportDifferenceLabel = $controlValues['difference_label'];
    $reportDifferenceValue = $controlValues['difference_value'];
    $reportColLabel = $controlValues['col_label'];
    $reportColValue = $controlValues['col_value'];
    $reportColBezDphLabel = (string)($controlValues['col_bez_dph_label'] ?? 'COL bez DPH: -- %');
    
    
    
    return [
        'renderMode' => $renderMode,
        'isMaxRender' => $isMaxRender,
        'tz' => $tz,
        'currentWorkdayDt' => $currentWorkdayDt,
        'workdayOptions' => $workdayOptions,
        'miniMissingReports' => $miniMissingReports,
        'isCurrentWorkday' => $isCurrentWorkday,
        'reportDateDt' => $reportDateDt,
        'reportDate' => $reportDate,
        'reportDateDisplay' => $reportDateDisplay,
        'workdayRange' => $workdayRange,
        'reportSaveMinutes' => $reportSaveMinutes,
        'lastRestiaUpdateLabel' => $lastRestiaUpdateLabel,
        'reportEndColumns' => $reportEndColumns,
        'currentUser' => $currentUser,
        'currentUserId' => $currentUserId,
        'currentUserRoleId' => $currentUserRoleId,
        'currentUserRoleIds' => $currentUserRoleIds,
        'mainBranchId' => $mainBranchId,
        'canSaveReport' => $canSaveReport,
        'hasRole5' => $hasRole5,
        'allowedBranches' => $allowedBranches,
        'allowedBranchIds' => $allowedBranchIds,
        'requestedBranchId' => $requestedBranchId,
        'reportBranchId' => $reportBranchId,
        'singleAllowedBranchName' => $singleAllowedBranchName,
        'reportBranchName' => $reportBranchName,
        'historyData' => $historyData,
        'historyReportId' => $historyReportId,
        'historyReportExists' => $historyReportExists,
        'canUnlockFinalReport' => $canUnlockFinalReport,
        'requestedFinalEdit' => $requestedFinalEdit,
        'isEditingFinalReport' => $isEditingFinalReport,
        'canEditHistory' => $canEditHistory,
        'canEditReport' => $canEditReport,
        'usesDraftPersistence' => $usesDraftPersistence,
        'isReadOnlyForm' => $isReadOnlyForm,
        'formMode' => $formMode,
        'missingHistoryReport' => $missingHistoryReport,
        'missingHistoryReportText' => $missingHistoryReportText,
        'readonlyInfoText' => $readonlyInfoText,
        'instorOptions' => $instorOptions,
        'plannedInstorDefaults' => $plannedInstorDefaults,
        'kuryrOptions' => $kuryrOptions,
        'draftRow' => $draftRow,
        'idDr' => $idDr,
        'instorRows' => $instorRows,
        'kuryrRows' => $kuryrRows,
        'reportSaveAtTs' => $reportSaveAtTs,
        'reportRefreshAtTs' => $reportRefreshAtTs,
        'draftPersonRows' => $draftPersonRows,
        'reportPersonRows' => $reportPersonRows,
        'usedInstorIds' => $usedInstorIds,
        'usedKuryrIds' => $usedKuryrIds,
        'openingId' => $openingId,
        'closingId' => $closingId,
        'openingName' => $openingName,
        'closingName' => $closingName,
        'cashData' => $cashData,
        'draftNote' => $draftNote,
        'previousNote' => $previousNote,
        'restiaSummary' => $restiaSummary,
        'kuryrDeliveryData' => $kuryrDeliveryData,
        'kuryrDeliveryCountsJson' => $kuryrDeliveryCountsJson,
        'controlValues' => $controlValues,
        'makeTimeLabel' => $makeTimeLabel,
        'reportDifferenceLabel' => $reportDifferenceLabel,
        'reportDifferenceValue' => $reportDifferenceValue,
        'reportColLabel' => $reportColLabel,
        'reportColValue' => $reportColValue,
        'reportColBezDphLabel' => $reportColBezDphLabel,
    ];
}

// lib/denni_report_data.php * Konec souboru
