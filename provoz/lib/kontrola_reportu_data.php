<?php
declare(strict_types=1);

require_once __DIR__ . '/denni_report_data.php';

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
        if ($from > $to) {
            return $fallback;
        }

        return [
            'from' => $from,
            'to' => $to,
            'display_from' => $fromDateTime->format('Y-m-d'),
            'display_to' => $toDateTime->format('Y-m-d'),
            'label' => $fromDateTime->format('j. n. Y H:i') . ' - ' . $toDateTime->format('j. n. Y H:i'),
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

/**
 * @return array<int,array{date:string,restia:string,is:string}>
 */
function cb_kontrola_reportu_name_mismatches(mysqli $conn, int $branchId, string $from, string $to): array
{
    $stmtPeople = $conn->prepare('
        SELECT
            r.datum_reportu,
            o.id_user,
            o.jmeno,
            o.prijmeni
        FROM reporty_is r
        INNER JOIN reporty_is_osoby o ON o.id_reportu = r.id_reportu
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
            ];
        }
        $peopleResult->free();
    }
    $stmtPeople->close();

    if ($optionsByDate === []) {
        return [];
    }

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

    $mismatches = [];
    foreach ($optionsByDate as $date => $options) {
        $matches = cb_denni_report_match_courier_names((array)($countsByDate[$date] ?? []), $options);
        foreach ((array)($matches['mismatches'] ?? []) as $mismatch) {
            $mismatches[] = [
                'date' => $date,
                'restia' => trim((string)($mismatch['restia'] ?? '')),
                'is' => trim((string)($mismatch['is'] ?? '')),
            ];
        }
    }

    usort($mismatches, static function (array $a, array $b): int {
        $dateCompare = strcmp((string)$a['date'], (string)$b['date']);
        return $dateCompare !== 0 ? $dateCompare : strcasecmp((string)$a['restia'], (string)$b['restia']);
    });

    return $mismatches;
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

    $missing = [];
    $tz = new DateTimeZone('Europe/Prague');
    $cursor = new DateTimeImmutable($from, $tz);
    $last = new DateTimeImmutable($to, $tz);
    while ($cursor <= $last) {
        $date = $cursor->format('Y-m-d');
        if (!isset($present[$date])) {
            $missing[] = $date;
        }
        $cursor = $cursor->modify('+1 day');
    }

    return $missing;
}

/**
 * @return array<string,mixed>
 */
function cb_kontrola_reportu_data(mysqli $conn, bool $useGlobalPeriod, int $requestedBranchId = 0): array
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
    $totals = cb_kontrola_reportu_totals($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);
    $people = cb_kontrola_reportu_people($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);
    $nameMismatches = cb_kontrola_reportu_name_mismatches($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);
    $missingDates = cb_kontrola_reportu_missing_dates($conn, (int)$branch['id'], (string)$period['from'], (string)$period['to']);

    return [
        'branch' => $branch,
        'period' => $period,
        'totals' => $totals,
        'people' => $people,
        'name_mismatches' => $nameMismatches,
        'missing_dates' => $missingDates,
    ];
}
