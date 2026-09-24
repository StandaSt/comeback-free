<?php
declare(strict_types=1);

require_once __DIR__ . '/pobocka_provoz.php';

require_once __DIR__ . '/denni_report_data.php';
require_once __DIR__ . '/../../common/db/db_cis_slot.php';

/*
 * Read-only souhrn uzavrenych dennich reportu pro jednu pobocku.
 * Pobocku prebera z otevreneho Denniho reportu, obdobi je pri prvnim vstupu
 * predchozi kalendarni tyden a po zmene GN presny globalni interval.
 */

const CB_KONTROLA_REPORTU_PRAVO = 212;

function cb_kontrola_reportu_ma_pravo(): bool
{
    try {
        return function_exists('cb_pravo_ma') && cb_pravo_ma(CB_KONTROLA_REPORTU_PRAVO);
    } catch (Throwable $e) {
        return false;
    }
}

function cb_kontrola_reportu_user_id(): int
{
    $user = $_SESSION['cb_user'] ?? [];

    return is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
}

function cb_kontrola_reportu_valid_date(string $value): string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Prague'));

    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
}

/**
 * @return array{from:string,to:string,display_from:string,display_to:string,label:string,source:string}
 */
function cb_kontrola_reportu_previous_week(): array
{
    $tz = new DateTimeZone('Europe/Prague');
    $today = new DateTimeImmutable('today', $tz);
    $thisMonday = $today->modify('monday this week');
    $from = $thisMonday->modify('-7 days');
    $to = $from->modify('+6 days');

    return [
        'from' => $from->format('Y-m-d'),
        'to' => $to->format('Y-m-d'),
        'display_from' => $from->format('Y-m-d'),
        'display_to' => $to->format('Y-m-d'),
        'label' => $from->format('j. n. Y') . ' - ' . $to->format('j. n. Y'),
        'source' => 'previous_week',
    ];
}

function cb_kontrola_reportu_workday_date(DateTimeImmutable $dateTime): string
{
    if ((int)$dateTime->format('G') < 6) {
        $dateTime = $dateTime->modify('-1 day');
    }

    return $dateTime->format('Y-m-d');
}

/**
 * GN uklada interval pracovnich dnu s hranici v 06:00. Konec je vyhradni,
 * proto se pro urceni posledniho reportu posune o jednu sekundu zpet.
 *
 * @return array{from:string,to:string,display_from:string,display_to:string,label:string,source:string}
 */
function cb_kontrola_reportu_global_period(): array
{
    $fallback = cb_kontrola_reportu_previous_week();
    $fromRaw = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
    $toRaw = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));
    if ($fromRaw === '' || $toRaw === '') {
        return $fallback;
    }

    try {
        $tz = new DateTimeZone('Europe/Prague');
        $fromDateTime = new DateTimeImmutable($fromRaw, $tz);
        $toDateTime = new DateTimeImmutable($toRaw, $tz);
        if ($fromDateTime >= $toDateTime) {
            return $fallback;
        }

        $from = cb_kontrola_reportu_workday_date($fromDateTime);
        $to = cb_kontrola_reportu_workday_date($toDateTime->modify('-1 second'));
        $displayTo = $toDateTime->format('Y-m-d');
        $periodLabel = $fromDateTime->format('j. n. Y H:i') . ' - ' . $toDateTime->format('j. n. Y H:i');

        $currentWorkday = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            cb_kontrola_reportu_workday_date(new DateTimeImmutable('now', $tz)),
            $tz
        );
        if ($currentWorkday instanceof DateTimeImmutable) {
            $lastClosedWorkday = $currentWorkday->modify('-1 day')->format('Y-m-d');
            if ($to > $lastClosedWorkday) {
                $to = $lastClosedWorkday;
                $displayTo = $to;
                $periodLabel = $fromDateTime->format('j. n. Y H:i') . ' - '
                    . $currentWorkday->modify('-1 day')->format('j. n. Y');
            }
        }
        if ($from > $to) {
            return $fallback;
        }

        return [
            'from' => $from,
            'to' => $to,
            'display_from' => $fromDateTime->format('Y-m-d'),
            'display_to' => $displayTo,
            'label' => $periodLabel,
            'source' => 'global',
        ];
    } catch (Throwable $e) {
        return $fallback;
    }
}

/**
 * @return array{id:int,name:string}|null
 */
function cb_kontrola_reportu_branch(mysqli $conn, int $userId, int $requestedBranchId): ?array
{
    if ($userId <= 0 || $requestedBranchId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT p.id_pob, p.nazev
        FROM user_pobocka up
        INNER JOIN pobocka p ON p.id_pob = up.id_pob
        WHERE up.id_user = ? AND up.id_pob = ? AND p.aktivni = 1
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze ověřit pobočku pro Kontrolu reportů.');
    }
    $stmt->bind_param('ii', $userId, $requestedBranchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    return ['id' => (int)$row['id_pob'], 'name' => trim((string)$row['nazev'])];
}

/**
 * @return array<string,int|float|null>
 */
function cb_kontrola_reportu_totals(mysqli $conn, int $branchId, string $from, string $to): array
{
    $stmt = $conn->prepare('
        SELECT
            COUNT(r.id_reportu) AS reports_count,
            COALESCE(SUM(ri.trzba), 0) AS trzba,
            COALESCE(SUM(ri.wolt), 0) AS wolt,
            COALESCE(SUM(ri.bolt), 0) AS bolt,
            COALESCE(SUM(ri.damejidlo), 0) AS damejidlo,
            COALESCE(SUM(ri.web), 0) AS web,
            COALESCE(SUM(ri.wolt_cash), 0) AS wolt_cash,
            COALESCE(SUM(ri.dj_cash), 0) AS dj_cash,
            COALESCE(SUM(pk.hotovost), 0) AS hotovost,
            COALESCE(SUM(pk.terminal), 0) AS terminal,
            COALESCE(SUM(pk.stravenky), 0) AS stravenky,
            COALESCE(SUM(pk.rozdil), 0) AS rozdil,
            COALESCE(SUM(pk.vydaje_benzin), 0) AS vydaje_benzin,
            COALESCE(SUM(pk.vydaje_auta), 0) AS vydaje_auta,
            COALESCE(SUM(pk.vydaje_suroviny), 0) AS vydaje_suroviny,
            COALESCE(SUM(pk.vydaje_ostatni), 0) AS vydaje_ostatni,
            COALESCE(SUM(pk.vydaje_phm_soukrome), 0) AS vydaje_phm_soukrome,
            COALESCE(SUM(pk.vydaje_doklady_ks), 0) AS vydaje_doklady_ks,
            COALESCE(SUM(ri.zrusene_obj_ks), 0) AS zrusene_obj_ks,
            COALESCE(SUM(ri.zrusene_obj_kc), 0) AS zrusene_obj_kc,
            COALESCE(SUM(ri.zpozdene_rozvozy_5_min), 0) AS zpozdene_rozvozy_5_min,
            COALESCE(SUM(ri.objednavky_nezrusene_ks), 0) AS objednavky_nezrusene_ks,
            COALESCE(SUM(ri.nase_rozvozy_ks), 0) AS nase_rozvozy_ks,
            COALESCE(SUM(ri.woltdrive_ks), 0) AS woltdrive_ks,
            COALESCE(SUM(ri.woltdrive_pozde_5_min), 0) AS woltdrive_pozde_5_min,
            COALESCE(SUM(ri.woltdrive_pozde_nase_vina), 0) AS woltdrive_pozde_nase_vina,
            COALESCE(SUM(ri.woltdrive_zpozdene_ks), 0) AS woltdrive_zpozdene_ks,
            SUM(ri.trzba * ri.col_pomer) / NULLIF(SUM(ri.trzba), 0) AS col_pomer,
            SUM(ri.make_time_prumer_sec * ri.objednavky_nezrusene_ks)
                / NULLIF(SUM(ri.objednavky_nezrusene_ks), 0) AS make_time_prumer_sec
        FROM reporty_is r
        LEFT JOIN reporty_is_pokladna pk ON pk.id_reportu = r.id_reportu
        LEFT JOIN reporty_is_restia ri ON ri.id_reportu = r.id_reportu
        WHERE r.id_pob = ?
          AND r.platny = 1
          AND r.datum_reportu BETWEEN ? AND ?
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit souhrn Kontroly reportů.');
    }
    $stmt->bind_param('iss', $branchId, $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: []) : [];
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    $moneyFields = [
        'trzba', 'wolt', 'bolt', 'damejidlo', 'web', 'wolt_cash', 'dj_cash',
        'hotovost', 'terminal', 'stravenky', 'rozdil', 'vydaje_benzin',
        'vydaje_auta', 'vydaje_suroviny', 'vydaje_ostatni', 'vydaje_phm_soukrome',
        'zrusene_obj_kc',
    ];
    $integerFields = [
        'reports_count', 'vydaje_doklady_ks', 'zrusene_obj_ks',
        'zpozdene_rozvozy_5_min', 'objednavky_nezrusene_ks', 'nase_rozvozy_ks',
        'woltdrive_ks', 'woltdrive_pozde_5_min', 'woltdrive_pozde_nase_vina',
        'woltdrive_zpozdene_ks',
    ];
    foreach ($moneyFields as $field) {
        $row[$field] = (float)($row[$field] ?? 0);
    }
    foreach ($integerFields as $field) {
        $row[$field] = (int)($row[$field] ?? 0);
    }
    $row['col_pomer'] = $row['col_pomer'] === null ? null : (float)$row['col_pomer'];
    $row['make_time_prumer_sec'] = $row['make_time_prumer_sec'] === null ? null : (float)$row['make_time_prumer_sec'];
    $ownDeliveries = (int)$row['nase_rozvozy_ks'];
    $woltDrive = (int)$row['woltdrive_ks'];
    $row['nase_rozvozy_pozde_pomer'] = $ownDeliveries > 0
        ? (int)$row['zpozdene_rozvozy_5_min'] / $ownDeliveries
        : null;
    $row['doruceno_vcas_pomer'] = $row['nase_rozvozy_pozde_pomer'] === null
        ? null
        : max(0.0, 1.0 - (float)$row['nase_rozvozy_pozde_pomer']);
    $row['woltdrive_zpozdene_pomer'] = $woltDrive > 0
        ? (int)$row['woltdrive_zpozdene_ks'] / $woltDrive
        : null;

    return $row;
}

/**
 * Google historie je volitelny srovnavaci zdroj. Tato funkce se vola jen po
 * vedomem zapnuti porovnani a nijak nevstupuje do standardnich IS souctu.
 *
 * @return array<string,int|float|null>
 */
function cb_kontrola_reportu_google_totals(mysqli $conn, int $branchId, string $from, string $to): array
{
    $stmt = $conn->prepare('
        SELECT
            COUNT(r.id_reportu) AS reports_count,
            COALESCE(SUM(ri.trzba), 0) AS trzba,
            COALESCE(SUM(ri.wolt), 0) AS wolt,
            COALESCE(SUM(ri.bolt), 0) AS bolt,
            COALESCE(SUM(ri.damejidlo), 0) AS damejidlo,
            COALESCE(SUM(ri.web), 0) AS web,
            COALESCE(SUM(ri.wolt_cash), 0) AS wolt_cash,
            COALESCE(SUM(ri.dj_cash), 0) AS dj_cash,
            COALESCE(SUM(pk.hotovost), 0) AS hotovost,
            COALESCE(SUM(pk.terminal), 0) AS terminal,
            COALESCE(SUM(pk.stravenky), 0) AS stravenky,
            COALESCE(SUM(pk.rozdil), 0) AS rozdil,
            COALESCE(SUM(pk.vydaje_benzin), 0) AS vydaje_benzin,
            COALESCE(SUM(pk.vydaje_auta), 0) AS vydaje_auta,
            COALESCE(SUM(pk.vydaje_suroviny), 0) AS vydaje_suroviny,
            COALESCE(SUM(pk.vydaje_ostatni), 0) AS vydaje_ostatni,
            COALESCE(SUM(pk.vydaje_phm_soukrome), 0) AS vydaje_phm_soukrome,
            COALESCE(SUM(pk.vydaje_doklady_ks), 0) AS vydaje_doklady_ks,
            COALESCE(SUM(ri.zrusene_obj_ks), 0) AS zrusene_obj_ks,
            COALESCE(SUM(ri.zrusene_obj_kc), 0) AS zrusene_obj_kc,
            COALESCE(SUM(ri.zpozdene_rozvozy_5_min), 0) AS zpozdene_rozvozy_5_min,
            COALESCE(SUM(ri.objednavky_nezrusene_ks), 0) AS objednavky_nezrusene_ks,
            COALESCE(SUM(ri.nase_rozvozy_ks), 0) AS nase_rozvozy_ks,
            COALESCE(SUM(ri.woltdrive_ks), 0) AS woltdrive_ks,
            COALESCE(SUM(ri.woltdrive_pozde_5_min), 0) AS woltdrive_pozde_5_min,
            COALESCE(SUM(ri.woltdrive_pozde_nase_vina), 0) AS woltdrive_pozde_nase_vina,
            COALESCE(SUM(ri.woltdrive_zpozdene_ks), 0) AS woltdrive_zpozdene_ks,
            SUM(ri.trzba * ri.col_pomer) / NULLIF(SUM(ri.trzba), 0) AS col_pomer,
            SUM(ri.make_time_prumer_sec * ri.objednavky_nezrusene_ks)
                / NULLIF(SUM(ri.objednavky_nezrusene_ks), 0) AS make_time_prumer_sec
        FROM reporty r
        LEFT JOIN reporty_pokladna pk ON pk.id_reportu = r.id_reportu
        LEFT JOIN reporty_restia ri ON ri.id_reportu = r.id_reportu
        WHERE r.id_pob = ?
          AND r.platny = 1
          AND r.zdroj = 1
          AND r.datum_reportu BETWEEN ? AND ?
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit Google souhrn Kontroly reportů.');
    }
    $stmt->bind_param('iss', $branchId, $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: []) : [];
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    $moneyFields = [
        'trzba', 'wolt', 'bolt', 'damejidlo', 'web', 'wolt_cash', 'dj_cash',
        'hotovost', 'terminal', 'stravenky', 'rozdil', 'vydaje_benzin',
        'vydaje_auta', 'vydaje_suroviny', 'vydaje_ostatni', 'vydaje_phm_soukrome',
        'zrusene_obj_kc',
    ];
    $integerFields = [
        'reports_count', 'vydaje_doklady_ks', 'zrusene_obj_ks',
        'zpozdene_rozvozy_5_min', 'objednavky_nezrusene_ks', 'nase_rozvozy_ks',
        'woltdrive_ks', 'woltdrive_pozde_5_min', 'woltdrive_pozde_nase_vina',
        'woltdrive_zpozdene_ks',
    ];
    foreach ($moneyFields as $field) {
        $row[$field] = (float)($row[$field] ?? 0);
    }
    foreach ($integerFields as $field) {
        $row[$field] = (int)($row[$field] ?? 0);
    }
    $row['col_pomer'] = $row['col_pomer'] === null ? null : (float)$row['col_pomer'];
    $row['make_time_prumer_sec'] = $row['make_time_prumer_sec'] === null ? null : (float)$row['make_time_prumer_sec'];
    $ownDeliveries = (int)$row['nase_rozvozy_ks'];
    $woltDrive = (int)$row['woltdrive_ks'];
    $row['nase_rozvozy_pozde_pomer'] = $ownDeliveries > 0
        ? (int)$row['zpozdene_rozvozy_5_min'] / $ownDeliveries
        : null;
    $row['doruceno_vcas_pomer'] = $row['nase_rozvozy_pozde_pomer'] === null
        ? null
        : max(0.0, 1.0 - (float)$row['nase_rozvozy_pozde_pomer']);
    $row['woltdrive_zpozdene_pomer'] = $woltDrive > 0
        ? (int)$row['woltdrive_zpozdene_ks'] / $woltDrive
        : null;

    return $row;
}

/**
 * @return array{instor:array<int,array<string,int|float|string>>,kuryr:array<int,array<string,int|float|string>>,instor_hours:float,kuryr_hours:float,kuryr_deliveries:int}
 */
function cb_kontrola_reportu_people(mysqli $conn, int $branchId, string $from, string $to): array
{
    $stmt = $conn->prepare('
        SELECT
            o.id_user,
            o.jmeno,
            o.prijmeni,
            o.slot,
            COUNT(DISTINCT r.datum_reportu) AS days_count,
            COALESCE(SUM(o.odpracovano), 0) AS hours_count,
            COALESCE(SUM(o.rozvozu_celkem), 0) AS deliveries_count
        FROM reporty_is r
        INNER JOIN reporty_is_osoby o ON o.id_reportu = r.id_reportu
        WHERE r.id_pob = ?
          AND r.platny = 1
          AND r.datum_reportu BETWEEN ? AND ?
          AND o.slot IN (1, 2)
        GROUP BY o.id_user, o.jmeno, o.prijmeni, o.slot
        ORDER BY o.slot ASC, o.prijmeni ASC, o.jmeno ASC
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit souhrn osob Kontroly reportů.');
    }
    $stmt->bind_param('iss', $branchId, $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $people = [
        'instor' => [],
        'kuryr' => [],
        'instor_hours' => 0.0,
        'kuryr_hours' => 0.0,
        'kuryr_deliveries' => 0,
    ];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $slot = (int)($row['slot'] ?? 0);
            $person = [
                'id_user' => (int)($row['id_user'] ?? 0),
                'name' => trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? '')),
                'days' => (int)($row['days_count'] ?? 0),
                'hours' => (float)($row['hours_count'] ?? 0),
                'deliveries' => (int)($row['deliveries_count'] ?? 0),
            ];
            if ($slot === 1) {
                $people['instor'][] = $person;
                $people['instor_hours'] += (float)$person['hours'];
            } elseif ($slot === 2) {
                $people['kuryr'][] = $person;
                $people['kuryr_hours'] += (float)$person['hours'];
                $people['kuryr_deliveries'] += (int)$person['deliveries'];
            }
        }
        $result->free();
    }
    $stmt->close();

    return $people;
}

function cb_kontrola_reportu_values_same(mixed $left, mixed $right, string $format = 'number'): bool
{
    if ($left === null || $left === '' || $right === null || $right === '') {
        return ($left === null || $left === '') && ($right === null || $right === '');
    }
    if (is_numeric($left) && is_numeric($right)) {
        $tolerance = in_array($format, ['money', 'hours', 'minutes'], true) ? 0.005 : 0.00005;

        return abs((float)$left - (float)$right) < $tolerance;
    }

    return trim((string)$left) === trim((string)$right);
}

/**
 * V porovnavacim rezimu spoji osoby pres id_user a slot. Standardni souhrn
 * cb_kontrola_reportu_people() zustava beze zmeny a bez Google dotazu.
 *
 * @return array{instor:array<int,array<string,int|float|string>>,kuryr:array<int,array<string,int|float|string>>,instor_hours:float,kuryr_hours:float,kuryr_deliveries:int,google_instor_hours:float,google_kuryr_hours:float}
 */
function cb_kontrola_reportu_google_people_comparison(mysqli $conn, int $branchId, string $from, string $to): array
{
    $load = static function (bool $google) use ($conn, $branchId, $from, $to): array {
        $reportTable = $google ? 'reporty' : 'reporty_is';
        $peopleTable = $google ? 'reporty_osoby' : 'reporty_is_osoby';
        $sourceCondition = $google ? ' AND r.zdroj = 1' : '';
        $sql = '
            SELECT
                o.id_user,
                o.slot,
                IF(
                    COALESCE(o.id_user, 0) > 0,
                    TRIM(CONCAT_WS(" ", u.jmeno, u.prijmeni)),
                    TRIM(CONCAT_WS(" ", o.prijmeni, o.jmeno))
                ) AS name,
                COUNT(DISTINCT r.datum_reportu) AS days_count,
                COALESCE(SUM(o.odpracovano), 0) AS hours_count,
                COALESCE(SUM(o.rozvozu_celkem), 0) AS deliveries_count
            FROM ' . $reportTable . ' r
            INNER JOIN ' . $peopleTable . ' o ON o.id_reportu = r.id_reportu
            LEFT JOIN user u ON u.id_user = o.id_user
            WHERE r.id_pob = ?
              AND r.platny = 1
              AND r.datum_reportu BETWEEN ? AND ?
              AND o.slot IN (1, 2)' . $sourceCondition . '
            GROUP BY
                o.id_user,
                o.slot,
                u.jmeno,
                u.prijmeni,
                IF(COALESCE(o.id_user, 0) > 0, "", TRIM(CONCAT_WS(" ", o.prijmeni, o.jmeno)))
            ORDER BY o.slot ASC, name ASC
        ';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nelze načíst osoby pro porovnání s Google reporty.');
        }
        $stmt->bind_param('iss', $branchId, $from, $to);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $idUser = (int)($row['id_user'] ?? 0);
                $slot = (int)($row['slot'] ?? 0);
                $key = $idUser > 0
                    ? 'user:' . $idUser . ':slot:' . $slot
                    : 'name:' . mb_strtolower(trim((string)($row['name'] ?? '')), 'UTF-8') . ':slot:' . $slot;
                $rows[$key] = [
                    'id_user' => $idUser,
                    'slot' => $slot,
                    'name' => trim((string)($row['name'] ?? '')),
                    'days' => (int)($row['days_count'] ?? 0),
                    'hours' => (float)($row['hours_count'] ?? 0),
                    'deliveries' => (int)($row['deliveries_count'] ?? 0),
                ];
            }
            $result->free();
        }
        $stmt->close();

        return $rows;
    };

    $isRows = $load(false);
    $googleRows = $load(true);
    $keys = array_values(array_unique(array_merge(array_keys($isRows), array_keys($googleRows))));
    $people = [
        'instor' => [],
        'kuryr' => [],
        'instor_hours' => 0.0,
        'kuryr_hours' => 0.0,
        'kuryr_deliveries' => 0,
        'google_instor_hours' => 0.0,
        'google_kuryr_hours' => 0.0,
    ];
    foreach ($keys as $key) {
        $isPerson = (array)($isRows[$key] ?? []);
        $googlePerson = (array)($googleRows[$key] ?? []);
        $slot = (int)($isPerson['slot'] ?? $googlePerson['slot'] ?? 0);
        if (!in_array($slot, [1, 2], true)) {
            continue;
        }
        $person = [
            'id_user' => (int)($isPerson['id_user'] ?? $googlePerson['id_user'] ?? 0),
            'name' => trim((string)($isPerson['name'] ?? $googlePerson['name'] ?? '')),
            'days' => (int)($isPerson['days'] ?? 0),
            'hours' => (float)($isPerson['hours'] ?? 0),
            'deliveries' => (int)($isPerson['deliveries'] ?? 0),
            'google_days' => (int)($googlePerson['days'] ?? 0),
            'google_hours' => (float)($googlePerson['hours'] ?? 0),
            'is_present' => isset($isRows[$key]) ? 1 : 0,
            'google_present' => isset($googleRows[$key]) ? 1 : 0,
            'google_unmatched' => $googlePerson !== [] && (int)($googlePerson['id_user'] ?? 0) <= 0 ? 1 : 0,
        ];
        $bucket = $slot === 1 ? 'instor' : 'kuryr';
        $people[$bucket][] = $person;
        if ($slot === 1) {
            $people['instor_hours'] += (float)$person['hours'];
            $people['google_instor_hours'] += (float)$person['google_hours'];
        } else {
            $people['kuryr_hours'] += (float)$person['hours'];
            $people['google_kuryr_hours'] += (float)$person['google_hours'];
            $people['kuryr_deliveries'] += (int)$person['deliveries'];
        }
    }
    foreach (['instor', 'kuryr'] as $bucket) {
        usort($people[$bucket], static fn (array $a, array $b): int => strnatcasecmp((string)$a['name'], (string)$b['name']));
    }

    return $people;
}

/**
 * @return array{expected_count:int,is_count:int,google_count:int,missing_google:array<int,string>,missing_is:array<int,string>}
 */
function cb_kontrola_reportu_google_coverage(mysqli $conn, int $branchId, string $from, string $to): array
{
    $loadDates = static function (string $table, bool $google) use ($conn, $branchId, $from, $to): array {
        $sql = 'SELECT datum_reportu FROM ' . $table . ' WHERE id_pob = ? AND platny = 1 AND datum_reportu BETWEEN ? AND ?'
            . ($google ? ' AND zdroj = 1' : '') . ' ORDER BY datum_reportu ASC';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Nelze ověřit pokrytí Google reportů.');
        }
        $stmt->bind_param('iss', $branchId, $from, $to);
        $stmt->execute();
        $result = $stmt->get_result();
        $dates = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $date = trim((string)($row['datum_reportu'] ?? ''));
                if ($date !== '') {
                    $dates[$date] = true;
                }
            }
            $result->free();
        }
        $stmt->close();

        return $dates;
    };

    $expected = [];
    $fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $from, new DateTimeZone('Europe/Prague'));
    $toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $to, new DateTimeZone('Europe/Prague'));
    if ($fromDate instanceof DateTimeImmutable && $toDate instanceof DateTimeImmutable) {
        for ($date = $fromDate; $date <= $toDate; $date = $date->modify('+1 day')) {
            $expected[$date->format('Y-m-d')] = true;
        }
    }
    foreach (array_keys(cb_pobocka_provoz_closed_date_set($conn, $from, $to)) as $closedDate) {
        unset($expected[$closedDate]);
    }
    $isDates = $loadDates('reporty_is', false);
    $googleDates = $loadDates('reporty', true);

    return [
        'expected_count' => count($expected),
        'is_count' => count($isDates),
        'google_count' => count($googleDates),
        'missing_google' => array_values(array_diff(array_keys($expected), array_keys($googleDates))),
        'missing_is' => array_values(array_diff(array_keys($expected), array_keys($isDates))),
    ];
}

/** Poslední dostupný Google report v požadovaném intervalu dané pobočky. */
function cb_kontrola_reportu_google_last_date(mysqli $conn, int $branchId, string $from, string $to): string
{
    $stmt = $conn->prepare('
        SELECT MAX(datum_reportu) AS last_date
        FROM reporty
        WHERE id_pob = ? AND platny = 1 AND zdroj = 1
          AND datum_reportu BETWEEN ? AND ?
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze zjistit poslední datum Google reportů.');
    }
    $stmt->bind_param('iss', $branchId, $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    $lastDate = (string)($row['last_date'] ?? '');
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return $lastDate;
}

/**
 * @return array<int,array{date:string,category:string,item:string,is:mixed,google:mixed,format:string}>
 */
function cb_kontrola_reportu_google_daily_differences(mysqli $conn, int $branchId, string $from, string $to): array
{
    $loadMetrics = static function (bool $google) use ($conn, $branchId, $from, $to): array {
        $reportTable = $google ? 'reporty' : 'reporty_is';
        $cashTable = $google ? 'reporty_pokladna' : 'reporty_is_pokladna';
        $restiaTable = $google ? 'reporty_restia' : 'reporty_is_restia';
        $sourceCondition = $google ? ' AND r.zdroj = 1' : '';
        $stmt = $conn->prepare('
            SELECT
                r.datum_reportu,
                pk.hotovost, pk.terminal, pk.stravenky, pk.rozdil,
                pk.vydaje_benzin, pk.vydaje_auta, pk.vydaje_suroviny,
                pk.vydaje_ostatni, pk.vydaje_phm_soukrome, pk.vydaje_doklady_ks,
                ri.trzba, ri.wolt, ri.bolt, ri.damejidlo, ri.web, ri.wolt_cash, ri.dj_cash,
                ri.col_pomer, ri.zrusene_obj_ks, ri.zrusene_obj_kc,
                ri.zpozdene_rozvozy_5_min, ri.make_time_prumer_sec,
                ri.objednavky_nezrusene_ks, ri.nase_rozvozy_ks, ri.woltdrive_ks,
                ri.woltdrive_pozde_5_min, ri.woltdrive_pozde_nase_vina,
                ri.nase_rozvozy_pozde_pomer, ri.woltdrive_zpozdene_ks,
                ri.doruceno_vcas_pomer, ri.woltdrive_zpozdene_pomer
            FROM ' . $reportTable . ' r
            LEFT JOIN ' . $cashTable . ' pk ON pk.id_reportu = r.id_reportu
            LEFT JOIN ' . $restiaTable . ' ri ON ri.id_reportu = r.id_reportu
            WHERE r.id_pob = ? AND r.platny = 1
              AND r.datum_reportu BETWEEN ? AND ?' . $sourceCondition . '
            ORDER BY r.datum_reportu ASC
        ');
        if ($stmt === false) {
            throw new RuntimeException('Nelze načíst denní hodnoty pro Google porovnání.');
        }
        $stmt->bind_param('iss', $branchId, $from, $to);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $ownDeliveries = (int)($row['nase_rozvozy_ks'] ?? 0);
                $ownLate = (int)($row['zpozdene_rozvozy_5_min'] ?? 0);
                $woltDrive = (int)($row['woltdrive_ks'] ?? 0);
                $woltDriveLate = (int)($row['woltdrive_zpozdene_ks'] ?? 0);
                $row['nase_rozvozy_pozde_pomer'] = $ownDeliveries > 0 ? $ownLate / $ownDeliveries : null;
                $row['doruceno_vcas_pomer'] = $ownDeliveries > 0 ? max(0.0, 1.0 - ($ownLate / $ownDeliveries)) : null;
                $row['woltdrive_zpozdene_pomer'] = $woltDrive > 0 ? $woltDriveLate / $woltDrive : null;
                $rows[(string)$row['datum_reportu']] = $row;
            }
            $result->free();
        }
        $stmt->close();

        return $rows;
    };
    $loadPeople = static function (bool $google) use ($conn, $branchId, $from, $to): array {
        $reportTable = $google ? 'reporty' : 'reporty_is';
        $peopleTable = $google ? 'reporty_osoby' : 'reporty_is_osoby';
        $sourceCondition = $google ? ' AND r.zdroj = 1' : '';
        $stmt = $conn->prepare('
            SELECT
                r.datum_reportu, o.id_user, o.slot,
                IF(
                    COALESCE(o.id_user, 0) > 0,
                    TRIM(CONCAT_WS(" ", u.jmeno, u.prijmeni)),
                    TRIM(CONCAT_WS(" ", o.prijmeni, o.jmeno))
                ) AS name,
                COALESCE(SUM(o.odpracovano), 0) AS hours_count
            FROM ' . $reportTable . ' r
            INNER JOIN ' . $peopleTable . ' o ON o.id_reportu = r.id_reportu
            LEFT JOIN user u ON u.id_user = o.id_user
            WHERE r.id_pob = ? AND r.platny = 1
              AND r.datum_reportu BETWEEN ? AND ?
              AND o.slot IN (1, 2)' . $sourceCondition . '
            GROUP BY
                r.datum_reportu,
                o.id_user,
                o.slot,
                u.jmeno,
                u.prijmeni,
                IF(COALESCE(o.id_user, 0) > 0, "", TRIM(CONCAT_WS(" ", o.prijmeni, o.jmeno)))
            ORDER BY r.datum_reportu ASC, o.slot ASC, name ASC
        ');
        if ($stmt === false) {
            throw new RuntimeException('Nelze načíst denní osoby pro Google porovnání.');
        }
        $stmt->bind_param('iss', $branchId, $from, $to);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $date = (string)($row['datum_reportu'] ?? '');
                $idUser = (int)($row['id_user'] ?? 0);
                $slot = (int)($row['slot'] ?? 0);
                $name = trim((string)($row['name'] ?? ''));
                $key = $idUser > 0
                    ? 'user:' . $idUser . ':slot:' . $slot
                    : 'name:' . mb_strtolower($name, 'UTF-8') . ':slot:' . $slot;
                $rows[$date][$key] = [
                    'slot' => $slot,
                    'name' => $name,
                    'hours' => (float)($row['hours_count'] ?? 0),
                    'unmatched' => $google && $idUser <= 0 ? 1 : 0,
                ];
            }
            $result->free();
        }
        $stmt->close();

        return $rows;
    };

    $fieldDefinitions = [
        ['Hotovost', 'hotovost', 'money', 'pokladna'], ['Terminál', 'terminal', 'money', 'pokladna'],
        ['Stravenky', 'stravenky', 'money', 'pokladna'], ['PHM firemní auta', 'vydaje_benzin', 'money', 'pokladna'],
        ['PHM výroba', 'vydaje_auta', 'money', 'pokladna'],
        ['PHM soukromé', 'vydaje_phm_soukrome', 'money', 'pokladna'],
        ['Suroviny', 'vydaje_suroviny', 'money', 'pokladna'], ['Ostatní', 'vydaje_ostatni', 'money', 'pokladna'],
        ['Rozdíl pokladna', 'rozdil', 'money', 'pokladna'],
        ['Výdajové doklady', 'vydaje_doklady_ks', 'integer', 'pokladna'],
        ['Tržba', 'trzba', 'money', 'trzba'], ['Wolt', 'wolt', 'money', 'trzba'],
        ['Bolt', 'bolt', 'money', 'trzba'], ['Foodora', 'damejidlo', 'money', 'trzba'],
        ['Web', 'web', 'money', 'trzba'], ['Wolt drive cash', 'wolt_cash', 'money', 'trzba'],
        ['DJ cash', 'dj_cash', 'money', 'trzba'], ['COL', 'col_pomer', 'percent', 'kontrola'],
        ['Zrušené obj. ks', 'zrusene_obj_ks', 'integer', 'kontrola'],
        ['Zrušené obj. Kč', 'zrusene_obj_kc', 'money', 'kontrola'],
        ['Průměrný make time', 'make_time_prumer_sec', 'minutes', 'kontrola'],
        ['Nezrušené celkem', 'objednavky_nezrusene_ks', 'integer', 'kontrola'],
        ['Naše rozvozy', 'nase_rozvozy_ks', 'integer', 'kontrola'],
        ['Zpožděné naše +5 min', 'zpozdene_rozvozy_5_min', 'integer', 'kontrola'],
        ['Zpožděné naše +5 min %', 'nase_rozvozy_pozde_pomer', 'percent', 'kontrola'],
        ['Doručené včas', 'doruceno_vcas_pomer', 'percent', 'kontrola'],
        ['Wolt Drive', 'woltdrive_ks', 'integer', 'kontrola'],
        ['Pozdě WoltDrive 5+', 'woltdrive_pozde_5_min', 'integer', 'kontrola'],
        ['Pozdě přiřazené naší vinou', 'woltdrive_pozde_nase_vina', 'integer', 'kontrola'],
        ['Zpožděné WoltDrivem', 'woltdrive_zpozdene_ks', 'integer', 'kontrola'],
        ['Zpožděné WoltDrivem %', 'woltdrive_zpozdene_pomer', 'percent', 'kontrola'],
    ];
    $isMetrics = $loadMetrics(false);
    $googleMetrics = $loadMetrics(true);
    $isPeople = $loadPeople(false);
    $googlePeople = $loadPeople(true);
    $slotLabels = cb_cis_slot_nazvy($conn);
    $dates = array_values(array_unique(array_merge(array_keys($isMetrics), array_keys($googleMetrics))));
    sort($dates);
    $differences = [];
    foreach ($dates as $date) {
        $hasIs = isset($isMetrics[$date]);
        $hasGoogle = isset($googleMetrics[$date]);
        if (!$hasIs || !$hasGoogle) {
            $differences[] = [
                'date' => $date,
                'category' => 'kontrola',
                'item' => 'Celý report',
                'is' => $hasIs ? 'uložen' : 'chybí',
                'google' => $hasGoogle ? 'uložen' : 'chybí',
                'format' => 'text',
            ];
            continue;
        }
        foreach ($fieldDefinitions as [$label, $field, $format, $category]) {
            $isValue = $isMetrics[$date][$field] ?? null;
            $googleValue = $googleMetrics[$date][$field] ?? null;
            if (!cb_kontrola_reportu_values_same($isValue, $googleValue, $format)) {
                $differences[] = [
                    'date' => $date,
                    'category' => $category,
                    'item' => $label,
                    'is' => $isValue,
                    'google' => $googleValue,
                    'format' => $format,
                ];
            }
        }
        $peopleKeys = array_values(array_unique(array_merge(
            array_keys((array)($isPeople[$date] ?? [])),
            array_keys((array)($googlePeople[$date] ?? []))
        )));
        foreach ($peopleKeys as $personKey) {
            $isPerson = (array)($isPeople[$date][$personKey] ?? []);
            $googlePerson = (array)($googlePeople[$date][$personKey] ?? []);
            $isHours = array_key_exists('hours', $isPerson) ? (float)$isPerson['hours'] : null;
            $googleHours = array_key_exists('hours', $googlePerson) ? (float)$googlePerson['hours'] : null;
            if (cb_kontrola_reportu_values_same($isHours, $googleHours, 'hours')) {
                continue;
            }
            $person = $isPerson !== [] ? $isPerson : $googlePerson;
            $slot = (int)($person['slot'] ?? 0);
            $name = trim((string)($person['name'] ?? ''));
            $role = $slotLabels[$slot] ?? ('Slot ' . $slot);
            $googleUnmatched = (int)($googlePerson['unmatched'] ?? 0) === 1;
            $differences[] = [
                'date' => $date,
                'category' => 'hodiny',
                'item' => $role . ' – ' . ($name !== '' ? $name : 'Neznámý zaměstnanec')
                    . ($googleUnmatched ? ' – pouze Google, nenalezen v IS' : ' – hodiny'),
                'is' => $isHours,
                'google' => $googleHours,
                'format' => 'hours',
                'google_unmatched' => $googleUnmatched ? 1 : 0,
            ];
        }
    }

    return $differences;
}

/**
 * Restia uklada cele jmeno v jednom poli. Poradi proto menime jen tehdy,
 * kdy lze jednotlive casti bez odhadu priradit ke jmenu a prijmeni v IS.
 */
function cb_kontrola_reportu_restia_display_name(string $restiaName, string $firstName, string $lastName): string
{
    $restiaName = trim($restiaName);
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    if ($restiaName === '' || $firstName === '' || $lastName === '') {
        return $restiaName;
    }

    $restiaParts = preg_split('/\s+/u', $restiaName) ?: [];
    $firstNameParts = preg_split('/\s+/u', $firstName) ?: [];
    $lastNameParts = preg_split('/\s+/u', $lastName) ?: [];
    $firstPartCount = count($firstNameParts);
    $lastPartCount = count($lastNameParts);
    if ($firstPartCount === 0 || $lastPartCount === 0 || count($restiaParts) !== $firstPartCount + $lastPartCount) {
        return $restiaName;
    }

    $restiaFirstCandidate = implode(' ', array_slice($restiaParts, 0, $firstPartCount));
    $restiaLastCandidate = implode(' ', array_slice($restiaParts, $firstPartCount));
    if (
        cb_denni_report_person_name_match_key($restiaFirstCandidate) === cb_denni_report_person_name_match_key($firstName)
        && cb_denni_report_person_name_match_key($restiaLastCandidate) === cb_denni_report_person_name_match_key($lastName)
    ) {
        return trim($restiaLastCandidate . ' ' . $restiaFirstCandidate);
    }

    $restiaLastCandidate = implode(' ', array_slice($restiaParts, 0, $lastPartCount));
    $restiaFirstCandidate = implode(' ', array_slice($restiaParts, $lastPartCount));
    if (
        cb_denni_report_person_name_match_key($restiaLastCandidate) === cb_denni_report_person_name_match_key($lastName)
        && cb_denni_report_person_name_match_key($restiaFirstCandidate) === cb_denni_report_person_name_match_key($firstName)
    ) {
        return $restiaName;
    }

    return $restiaName;
}

/**
 * @return array{processed:array<int,array{date:string,restia:string,is:string}>,unmatched:array<int,array{date:string,restia:string,reason:string}>}
 */
function cb_kontrola_reportu_name_mismatches(mysqli $conn, int $branchId, string $from, string $to): array
{
    $stmtPeople = $conn->prepare('
        SELECT
            r.datum_reportu,
            o.id_user,
            o.jmeno,
            o.prijmeni,
            COALESCE(hp.aktivni, 0) AS aktivni
        FROM reporty_is r
        INNER JOIN reporty_is_osoby o ON o.id_reportu = r.id_reportu
        LEFT JOIN hr_person hp ON hp.id_user = o.id_user
        WHERE r.id_pob = ?
          AND r.platny = 1
          AND r.datum_reportu BETWEEN ? AND ?
          AND o.slot = 2
        ORDER BY r.datum_reportu ASC, o.id_reporty_osoby ASC
    ');
    if ($stmtPeople === false) {
        throw new RuntimeException('Nelze načíst kurýry pro kontrolu jmen.');
    }
    $stmtPeople->bind_param('iss', $branchId, $from, $to);
    $stmtPeople->execute();
    $peopleResult = $stmtPeople->get_result();
    $optionsByDate = [];
    if ($peopleResult instanceof mysqli_result) {
        while ($row = $peopleResult->fetch_assoc()) {
            $date = (string)($row['datum_reportu'] ?? '');
            $idUser = (int)($row['id_user'] ?? 0);
            $firstName = trim((string)($row['jmeno'] ?? ''));
            $lastName = trim((string)($row['prijmeni'] ?? ''));
            if ($date === '' || $idUser <= 0 || ($firstName === '' && $lastName === '')) {
                continue;
            }
            $optionsByDate[$date][] = [
                'id_user' => $idUser,
                'name' => cb_denni_report_person_display_name($firstName, $lastName),
                'restia_name' => cb_denni_report_person_full_name($firstName, $lastName),
                'aktivni' => (int)($row['aktivni'] ?? 0),
                'jmeno' => $firstName,
                'prijmeni' => $lastName,
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        }
        $peopleResult->free();
    }
    $stmtPeople->close();

    $stmtRestia = $conn->prepare('
        SELECT
            r.datum_reportu,
            TRIM(ok.jmeno) AS kuryr_name,
            COUNT(DISTINCT objed.id_obj) AS rozvozu
        FROM reporty_is r
        INNER JOIN objednavky_restia objed ON objed.id_pob = r.id_pob
        INNER JOIN obj_kuryr ok ON ok.id_obj = objed.id_obj
        LEFT JOIN cis_obj_stav s ON s.id_stav = objed.id_stav
        INNER JOIN obj_casy ca ON ca.id_obj = objed.id_obj AND ca.report = r.datum_reportu
        WHERE r.id_pob = ?
          AND r.platny = 1
          AND r.datum_reportu BETWEEN ? AND ?
          AND ok.provider = \'delivery\'
          AND TRIM(COALESCE(ok.jmeno, \'\')) <> \'Wolt Kurýr\'
          AND COALESCE(s.nazev, \'\') NOT IN (\'canceled\', \'rejected\', \'expired\', \'not_accepted\', \'cancel_accepted\')
        GROUP BY r.datum_reportu, kuryr_name
        HAVING kuryr_name <> \'\'
        ORDER BY r.datum_reportu ASC, kuryr_name ASC
    ');
    if ($stmtRestia === false) {
        throw new RuntimeException('Nelze načíst jména kurýrů z Restie pro kontrolní období.');
    }
    $stmtRestia->bind_param('iss', $branchId, $from, $to);
    $stmtRestia->execute();
    $restiaResult = $stmtRestia->get_result();
    $countsByDate = [];
    if ($restiaResult instanceof mysqli_result) {
        while ($row = $restiaResult->fetch_assoc()) {
            $date = (string)($row['datum_reportu'] ?? '');
            $courierName = trim((string)($row['kuryr_name'] ?? ''));
            if ($date === '' || $courierName === '') {
                continue;
            }
            $countsByDate[$date][$courierName] = (int)($row['rozvozu'] ?? 0);
        }
        $restiaResult->free();
    }
    $stmtRestia->close();

    $processed = [];
    $unmatched = [];
    foreach ($countsByDate as $date => $counts) {
        $options = (array)($optionsByDate[$date] ?? []);
        $matches = cb_denni_report_match_courier_names($counts, $options);
        $optionsByUser = [];
        foreach ($options as $option) {
            $optionsByUser[(int)($option['id_user'] ?? 0)] = $option;
        }
        foreach ((array)($matches['mismatches'] ?? []) as $mismatch) {
            $restiaName = trim((string)($mismatch['restia'] ?? ''));
            $matched = (array)($matches['matches'][$restiaName] ?? []);
            $option = (array)($optionsByUser[(int)($matched['id_user'] ?? 0)] ?? []);
            $isName = trim((string)($option['name'] ?? $mismatch['is'] ?? ''));
            $restiaDisplayName = cb_kontrola_reportu_restia_display_name(
                $restiaName,
                (string)($option['first_name'] ?? ''),
                (string)($option['last_name'] ?? '')
            );
            if ($restiaDisplayName === $isName) {
                continue;
            }
            $processed[] = [
                'date' => $date,
                'restia' => $restiaDisplayName,
                'is' => $isName,
            ];
        }
        foreach ((array)($matches['unmatched'] ?? []) as $name) {
            $unmatched[] = [
                'date' => $date,
                'restia' => trim((string)($name['restia'] ?? '')),
                'reason' => (string)($name['reason'] ?? 'nenalezen'),
            ];
        }
    }

    $sortByDateAndName = static function (array $a, array $b): int {
        $dateCompare = strcmp((string)$a['date'], (string)$b['date']);
        return $dateCompare !== 0 ? $dateCompare : strcasecmp((string)$a['restia'], (string)$b['restia']);
    };
    usort($processed, $sortByDateAndName);
    usort($unmatched, $sortByDateAndName);

    return ['processed' => $processed, 'unmatched' => $unmatched];
}

/**
 * @return string[]
 */
function cb_kontrola_reportu_missing_dates(mysqli $conn, int $branchId, string $from, string $to): array
{
    $stmt = $conn->prepare('
        SELECT datum_reportu
        FROM reporty_is
        WHERE id_pob = ? AND platny = 1 AND datum_reportu BETWEEN ? AND ?
        GROUP BY datum_reportu
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze načíst dny uzavřených reportů.');
    }
    $stmt->bind_param('iss', $branchId, $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $present = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $present[(string)$row['datum_reportu']] = true;
        }
        $result->free();
    }
    $stmt->close();

    $closedDates = cb_pobocka_provoz_closed_date_set($conn, $from, $to);
    $missing = [];
    $tz = new DateTimeZone('Europe/Prague');
    $cursor = new DateTimeImmutable($from, $tz);
    $last = new DateTimeImmutable($to, $tz);
    while ($cursor <= $last) {
        $date = $cursor->format('Y-m-d');
        if (!isset($present[$date]) && !isset($closedDates[$date])) {
            $missing[] = $date;
        }
        $cursor = $cursor->modify('+1 day');
    }

    return $missing;
}

/**
 * @return array<string,mixed>
 */
function cb_kontrola_reportu_data(
    mysqli $conn,
    bool $useGlobalPeriod,
    int $requestedBranchId = 0,
    bool $withGoogleComparison = false
): array
{
    if (!cb_kontrola_reportu_ma_pravo()) {
        throw new CbUserVisibleException('Nemáte právo zobrazit Kontrolu denních reportů.');
    }

    $userId = cb_kontrola_reportu_user_id();
    if ($requestedBranchId <= 0) {
        $requestedBranchId = (int)($_SESSION['cb_kontrola_reportu_id_pob'] ?? 0);
    }
    $branch = cb_kontrola_reportu_branch($conn, $userId, $requestedBranchId);
    if (!is_array($branch)) {
        throw new RuntimeException('Pobočka pro Kontrolu není dostupná. Vraťte se do Denního reportu a vyberte pobočku.');
    }
    $_SESSION['cb_kontrola_reportu_id_pob'] = (int)$branch['id'];

    $period = $useGlobalPeriod ? cb_kontrola_reportu_global_period() : cb_kontrola_reportu_previous_week();
    $_SESSION['cb_kontrola_reportu_period_source'] = (string)$period['source'];
    $googlePeriodTruncatedTo = '';
    if ($withGoogleComparison) {
        $googleLastDate = cb_kontrola_reportu_google_last_date(
            $conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']
        );
        if ($googleLastDate !== '' && $googleLastDate < (string)$period['to']) {
            $period['to'] = $googleLastDate;
            $period['display_to'] = $googleLastDate;
            $googlePeriodTruncatedTo = $googleLastDate;
        }
    }
    $totals = cb_kontrola_reportu_totals($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);
    $people = cb_kontrola_reportu_people($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);
    $nameMismatches = cb_kontrola_reportu_name_mismatches($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);
    $missingDates = cb_kontrola_reportu_missing_dates($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);
    $googleComparison = null;
    if ($withGoogleComparison) {
        $googleComparison = [
            'totals' => cb_kontrola_reportu_google_totals($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']),
            'people' => cb_kontrola_reportu_google_people_comparison($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']),
            'coverage' => cb_kontrola_reportu_google_coverage($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']),
            'daily_differences' => cb_kontrola_reportu_google_daily_differences($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']),
            'period_truncated_to' => $googlePeriodTruncatedTo,
        ];
    }

    return [
        'branch' => $branch,
        'period' => $period,
        'totals' => $totals,
        'people' => $people,
        'slot_labels' => cb_cis_slot_nazvy($conn),
        'name_mismatches' => $nameMismatches,
        'missing_dates' => $missingDates,
        'google_comparison' => $googleComparison,
    ];
}
