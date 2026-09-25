<?php
declare(strict_types=1);

/*
 * Data první čtecí verze mzdového přehledu. Všechny hodnoty jsou načítané
 * z existující evidence HR a z finálně uložených denních reportů IS.
 * Tato vrstva neprovádí uzávěrku, nepřijímá ruční bonusy ani do DB nezapisuje.
 */

function hr_mzdovy_prehled_id_firmy(mysqli $db, int $idUser): int
{
    if ($idUser <= 0) {
        return 0;
    }
    $stmt = $db->prepare('SELECT p.id_firma FROM hr_person p WHERE p.id_user = ? AND p.aktivni = 1 LIMIT 1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? (int)($row['id_firma'] ?? 0) : 0;
}

function hr_mzdovy_prehled_nazev_firmy(mysqli $db, int $idUser): string
{
    $idFirma = hr_mzdovy_prehled_id_firmy($db, $idUser);
    if ($idFirma <= 0) {
        return '';
    }
    $stmt = $db->prepare('SELECT obchodni_jmeno FROM firma WHERE id_firma = ? AND aktivni = 1 AND platnost_do IS NULL LIMIT 1');
    $stmt->bind_param('i', $idFirma);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return trim((string)($row['obchodni_jmeno'] ?? ''));
}

function hr_mzdovy_prehled_uvazek_text(array $row): string
{
    $text = trim((string)($row['pracovni_vztah'] ?? ''));
    if ($row['uvazek'] !== null) {
        $text .= ($text === '' ? '' : ' ') . cb_format('n', (float)$row['uvazek']);
    }
    return trim($text);
}

function hr_mzdovy_prehled_dostupna_obdobi(mysqli $db, int $idFirma): array
{
    $stmt = $db->prepare('
        SELECT YEAR(datum_reportu) AS rok, MONTH(datum_reportu) AS mesic, MAX(datum_reportu) AS posledni_datum
        FROM reporty_is
        WHERE id_firma = ? AND platny = 1 AND datum_reportu IS NOT NULL
        GROUP BY YEAR(datum_reportu), MONTH(datum_reportu)
        ORDER BY rok DESC, mesic ASC
    ');
    $stmt->bind_param('i', $idFirma);
    $stmt->execute();
    $result = $stmt->get_result();
    $years = [];
    $monthsByYear = [];
    $latestDate = '';
    while ($row = $result->fetch_assoc()) {
        $year = (int)($row['rok'] ?? 0);
        $month = (int)($row['mesic'] ?? 0);
        if ($year < 1000 || $year > 9999 || $month < 1 || $month > 12) {
            continue;
        }
        if (!isset($monthsByYear[$year])) {
            $years[] = $year;
            $monthsByYear[$year] = [];
        }
        $monthsByYear[$year][] = $month;
        $rowLatestDate = (string)($row['posledni_datum'] ?? '');
        if ($rowLatestDate > $latestDate) {
            $latestDate = $rowLatestDate;
        }
    }
    $result->free();
    $stmt->close();

    return ['years' => $years, 'months_by_year' => $monthsByYear, 'latest_date' => $latestDate];
}

function hr_mzdovy_prehled_data(mysqli $db, array $request, int $idUser): array
{
    $idFirma = hr_mzdovy_prehled_id_firmy($db, $idUser);
    if ($idFirma <= 0) {
        throw new RuntimeException('Uživatel nemá přiřazenou aktivní firmu.');
    }

    $availablePeriods = hr_mzdovy_prehled_dostupna_obdobi($db, $idFirma);
    $periodYears = $availablePeriods['years'];
    $periodMonthsByYear = $availablePeriods['months_by_year'];
    if ($periodYears === []) {
        throw new CbUserVisibleException('Pro firmu zatím nejsou dostupná žádná mzdová období.');
    }

    $defaultPeriod = new DateTimeImmutable('first day of last month');
    $defaultYear = (int)$defaultPeriod->format('Y');
    $defaultMonth = (int)$defaultPeriod->format('n');
    if (!in_array($defaultMonth, $periodMonthsByYear[$defaultYear] ?? [], true)) {
        $latestPeriod = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$availablePeriods['latest_date']);
        if (!$latestPeriod instanceof DateTimeImmutable) {
            throw new RuntimeException('Poslední dostupné mzdové období je neplatné.');
        }
        $defaultPeriod = $latestPeriod->modify('first day of this month');
    }

    $requestedYear = is_scalar($request['mzd_rok'] ?? null) ? (int)$request['mzd_rok'] : 0;
    $requestedMonth = is_scalar($request['mzd_mesic'] ?? null) ? (int)$request['mzd_mesic'] : 0;
    $rawPeriod = '';
    if (in_array($requestedYear, $periodYears, true)) {
        $availableMonths = $periodMonthsByYear[$requestedYear] ?? [];
        if (in_array($requestedMonth, $availableMonths, true)) {
            $rawPeriod = sprintf('%04d-%02d', $requestedYear, $requestedMonth);
        } elseif ($availableMonths !== []) {
            $rawPeriod = sprintf('%04d-%02d', $requestedYear, max($availableMonths));
        }
    } else {
        $legacyPeriod = is_scalar($request['mzd_obdobi'] ?? null) ? trim((string)$request['mzd_obdobi']) : '';
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $legacyPeriod, $legacyMatch) === 1
            && in_array((int)$legacyMatch[2], $periodMonthsByYear[(int)$legacyMatch[1]] ?? [], true)) {
            $rawPeriod = $legacyPeriod;
        }
    }
    if ($rawPeriod === '') {
        $rawPeriod = $defaultPeriod->format('Y-m');
    }

    $periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $rawPeriod . '-01');
    if (!$periodStart instanceof DateTimeImmutable) {
        throw new CbUserVisibleException('Vyberte platné mzdové období.');
    }
    $periodEnd = $periodStart->modify('last day of this month');
    $from = $periodStart->format('Y-m-d');
    $to = $periodEnd->format('Y-m-d');

    $sql = '
        SELECT
            hp.id_person,
            ou.jmeno,
            ou.prijmeni,
            TRIM(CONCAT(COALESCE(ou.prijmeni, ""), " ", COALESCE(ou.jmeno, ""))) AS cele_jmeno,
            (
                SELECT CASE WHEN prac.id_pob = 0 THEN \'Výroba\' ELSE p.nazev END
                FROM hr_pracoviste prac
                LEFT JOIN pobocka p ON p.id_pob = prac.id_pob
                WHERE prac.id_person = hp.id_person AND prac.platny = 1
                  AND (prac.platnost_od IS NULL OR prac.platnost_od <= ?)
                  AND (prac.platnost_do IS NULL OR prac.platnost_do >= ?)
                ORDER BY prac.hlavni DESC, COALESCE(prac.platnost_od, \'1000-01-01\') DESC, prac.id_pracoviste DESC
                LIMIT 1
            ) AS pobocka,
            (
                SELECT prac.id_pob
                FROM hr_pracoviste prac
                WHERE prac.id_person = hp.id_person AND prac.platny = 1
                  AND (prac.platnost_od IS NULL OR prac.platnost_od <= ?)
                  AND (prac.platnost_do IS NULL OR prac.platnost_do >= ?)
                ORDER BY prac.hlavni DESC, COALESCE(prac.platnost_od, \'1000-01-01\') DESC, prac.id_pracoviste DESC
                LIMIT 1
            ) AS id_pob,
            (
                SELECT cs.slot
                FROM hr_zarazeni z
                INNER JOIN cis_slot cs ON cs.id_slot = z.id_slot
                WHERE z.id_person = hp.id_person AND z.platny = 1
                  AND (z.platnost_od IS NULL OR z.platnost_od <= ?)
                  AND (z.platnost_do IS NULL OR z.platnost_do >= ?)
                ORDER BY z.hlavni DESC, COALESCE(z.platnost_od, \'1000-01-01\') DESC, z.id_zarazeni DESC
                LIMIT 1
            ) AS zarazeni,
            (
                SELECT pvt.nazev
                FROM hr_pracovni_vztah pv
                INNER JOIN hr_cis_pracovni_vztah_typ pvt ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
                WHERE pv.id_person = hp.id_person AND pv.platny = 1
                  AND pv.datum_nastupu <= ?
                  AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= ?)
                ORDER BY pv.datum_nastupu DESC, pv.id_pracovni_vztah DESC
                LIMIT 1
            ) AS pracovni_vztah,
            (
                SELECT u.uvazek
                FROM hr_pracovni_uvazek u
                INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = u.id_pracovni_vztah
                WHERE pv.id_person = hp.id_person AND pv.platny = 1 AND u.platny = 1
                  AND (u.platnost_od IS NULL OR u.platnost_od <= ?)
                  AND (u.platnost_do IS NULL OR u.platnost_do >= ?)
                ORDER BY u.platnost_od DESC, u.id_pracovni_uvazek DESC
                LIMIT 1
            ) AS uvazek,
            (
                SELECT mt.nazev
                FROM hr_mzda m
                INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = m.id_pracovni_vztah
                INNER JOIN cis_mzda_typ mt ON mt.id_mzda_typ = m.id_mzda_typ
                WHERE pv.id_person = hp.id_person AND pv.platny = 1 AND m.platny = 1
                  AND (m.platnost_od IS NULL OR m.platnost_od <= ?)
                  AND (m.platnost_do IS NULL OR m.platnost_do >= ?)
                ORDER BY m.platnost_od DESC, m.id_mzda DESC
                LIMIT 1
            ) AS mzda_typ,
            (
                SELECT m.castka
                FROM hr_mzda m
                INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = m.id_pracovni_vztah
                WHERE pv.id_person = hp.id_person AND pv.platny = 1 AND m.platny = 1
                  AND (m.platnost_od IS NULL OR m.platnost_od <= ?)
                  AND (m.platnost_do IS NULL OR m.platnost_do >= ?)
                ORDER BY m.platnost_od DESC, m.id_mzda DESC
                LIMIT 1
            ) AS mzda_castka,
            COALESCE(hodiny.odpracovano, 0) AS odpracovano
        FROM hr_person hp
        INNER JOIN hr_osobni_udaje ou ON ou.id_person = hp.id_person AND ou.platny = 1
        LEFT JOIN (
            SELECT ro.id_user, SUM(ro.odpracovano) AS odpracovano
            FROM reporty_is_osoby ro
            INNER JOIN reporty_is r ON r.id_reportu = ro.id_reportu
            WHERE r.platny = 1 AND r.datum_reportu BETWEEN ? AND ? AND r.id_firma = ?
            GROUP BY ro.id_user
        ) hodiny ON hodiny.id_user = hp.id_user
        INNER JOIN firma f ON f.id_firma = hp.id_firma AND f.aktivni = 1 AND f.platnost_do IS NULL
        WHERE hp.aktivni = 1
          AND hp.id_firma = ?
          AND EXISTS (
              SELECT 1 FROM hr_pracovni_vztah pv
              WHERE pv.id_person = hp.id_person AND pv.platny = 1
                AND pv.datum_nastupu <= ?
                AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= ?)
          )
        ORDER BY ou.prijmeni ASC, ou.jmeno ASC, hp.id_person ASC
    ';

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Mzdový přehled se nepodařilo připravit.');
    }
    $stmt->bind_param(
        'ssssssssssssssssiiss',
        $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $from, $to, $idFirma, $idFirma, $to, $from
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['cele_jmeno'] = trim((string)($row['cele_jmeno'] ?? ''));
        if ($row['cele_jmeno'] === '') {
            $row['cele_jmeno'] = 'Zaměstnanec #' . (string)(int)$row['id_person'];
        }
        $row['odpracovano'] = (float)($row['odpracovano'] ?? 0);
        $row['uvazek_text'] = hr_mzdovy_prehled_uvazek_text($row);
        $rows[] = $row;
    }
    $stmt->close();

    $branches = [];
    $branchStmt = $db->prepare('SELECT p.id_pob, p.nazev, f.obchodni_jmeno AS firma FROM pobocka p INNER JOIN firma f ON f.id_firma = p.id_firma WHERE p.id_firma = ? AND p.aktivni = 1 AND f.aktivni = 1 AND f.platnost_do IS NULL ORDER BY p.id_pob');
    $branchStmt->bind_param('i', $idFirma);
    $branchStmt->execute();
    $result = $branchStmt->get_result();
    while ($branch = $result->fetch_assoc()) {
        $branches[] = [
            'id_pob' => (int)$branch['id_pob'],
            'name' => (string)$branch['nazev'],
            'firma' => (string)$branch['firma'],
        ];
    }
    $result->free();
    $branchStmt->close();

    $mzdFilterRequest = is_array($request['mzd_f'] ?? null) ? $request['mzd_f'] : [];
    $filterValue = static function (string $key, string $legacyKey) use ($mzdFilterRequest, $request): string {
        $value = $mzdFilterRequest[$key] ?? ($request[$legacyKey] ?? '');
        return is_scalar($value) ? trim((string)$value) : '';
    };

    $selectedBranch = $filterValue('pobocka', 'mzd_pobocka');
    $branchIds = array_map(static fn(array $branch): string => (string)$branch['id_pob'], $branches);
    if ($selectedBranch !== '' && !in_array($selectedBranch, $branchIds, true)) {
        $selectedBranch = '';
    }

    $positionOptions = array_values(array_unique(array_filter(
        array_map(static fn(array $row): string => trim((string)($row['zarazeni'] ?? '')), $rows),
        static fn(string $value): bool => $value !== ''
    )));
    $workloadOptions = array_values(array_unique(array_filter(
        array_map(static fn(array $row): string => trim((string)($row['uvazek_text'] ?? '')), $rows),
        static fn(string $value): bool => $value !== ''
    )));
    sort($positionOptions, SORT_NATURAL | SORT_FLAG_CASE);
    sort($workloadOptions, SORT_NATURAL | SORT_FLAG_CASE);

    $filters = [
        'id' => mb_substr($filterValue('id', 'mzd_id'), 0, 20),
        'jmeno' => mb_substr($filterValue('jmeno', 'mzd_jmeno'), 0, 100),
        'prijmeni' => mb_substr($filterValue('prijmeni', 'mzd_prijmeni'), 0, 100),
        'pobocka' => $selectedBranch,
        'pozice' => $filterValue('pozice', 'mzd_pozice'),
        'uvazek' => $filterValue('uvazek', 'mzd_uvazek'),
    ];
    if (!in_array($filters['pozice'], $positionOptions, true)) {
        $filters['pozice'] = '';
    }
    if (!in_array($filters['uvazek'], $workloadOptions, true)) {
        $filters['uvazek'] = '';
    }

    foreach ($rows as $index => &$row) {
        $row['_poradi'] = $index;
    }
    unset($row);
    $textContains = static fn(string $haystack, string $needle): bool => $needle === '' || mb_stripos($haystack, $needle) !== false;
    $rows = array_values(array_filter($rows, static function (array $row) use ($filters, $textContains): bool {
        return ($filters['id'] === '' || str_contains((string)(int)$row['id_person'], $filters['id']))
            && $textContains((string)($row['jmeno'] ?? ''), $filters['jmeno'])
            && $textContains((string)($row['prijmeni'] ?? ''), $filters['prijmeni'])
            && ($filters['pobocka'] === '' || (string)($row['id_pob'] ?? '') === $filters['pobocka'])
            && ($filters['pozice'] === '' || (string)($row['zarazeni'] ?? '') === $filters['pozice'])
            && ($filters['uvazek'] === '' || (string)($row['uvazek_text'] ?? '') === $filters['uvazek']);
    }));

    $sortFields = [
        'id' => 'id_person', 'jmeno' => 'jmeno', 'prijmeni' => 'prijmeni', 'pobocka' => 'pobocka',
        'pozice' => 'zarazeni', 'uvazek' => 'uvazek_text', 'mzda' => 'mzda_castka', 'prumer' => 'prumer',
        'osatne' => 'osatne', 'stravenkovy_pausal' => 'stravenkovy_pausal', 'celkem' => 'odpracovano',
        'nocni' => 'nocni', 'svatek' => 'svatek', 'svatek_noc' => 'svatek_noc', 'vikend' => 'vikend',
        'dovolena_pocatecni' => 'dovolena_pocatecni', 'dovolena_cerpano' => 'dovolena_cerpano',
        'dovolena_zustatek' => 'dovolena_zustatek', 'noc_kc' => 'noc_kc', 'svatek_kc' => 'svatek_kc',
        'svatek_noc_kc' => 'svatek_noc_kc', 'vikend_kc' => 'vikend_kc', 'bonus_isk_procento' => 'bonus_isk_procento',
        'bonus_isk_kc' => 'bonus_isk_kc', 'bonus_trzba' => 'bonus_trzba', 'prescas_h' => 'prescas_h',
        'prescas_kc' => 'prescas_kc', 'hruba_mzda' => 'hruba_mzda', 'cista_mzda' => 'cista_mzda', 'zsp' => 'zsp',
    ];
    $numericSorts = ['id', 'mzda', 'prumer', 'osatne', 'stravenkovy_pausal', 'celkem', 'nocni', 'svatek', 'svatek_noc', 'vikend', 'dovolena_pocatecni', 'dovolena_cerpano', 'dovolena_zustatek', 'noc_kc', 'svatek_kc', 'svatek_noc_kc', 'vikend_kc', 'bonus_isk_procento', 'bonus_isk_kc', 'bonus_trzba', 'prescas_h', 'prescas_kc', 'hruba_mzda', 'cista_mzda', 'zsp'];
    $sort = trim((string)($request['mzd_sort'] ?? 'prijmeni'));
    if (!isset($sortFields[$sort])) {
        $sort = 'prijmeni';
    }
    $dir = strtolower((string)($request['mzd_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    $sortField = $sortFields[$sort];
    usort($rows, static function (array $a, array $b) use ($sort, $sortField, $dir, $numericSorts): int {
        $aValue = $a[$sortField] ?? null;
        $bValue = $b[$sortField] ?? null;
        $aEmpty = $aValue === null || $aValue === '';
        $bEmpty = $bValue === null || $bValue === '';
        if ($aEmpty !== $bEmpty) {
            return $aEmpty ? 1 : -1;
        }
        $comparison = in_array($sort, $numericSorts, true)
            ? ((float)$aValue <=> (float)$bValue)
            : strnatcasecmp((string)$aValue, (string)$bValue);
        if ($comparison !== 0 && $dir === 'desc') {
            $comparison *= -1;
        }
        return $comparison !== 0 ? $comparison : ((int)$a['_poradi'] <=> (int)$b['_poradi']);
    });

    $totalHours = 0.0;
    foreach ($rows as $row) {
        $totalHours += (float)$row['odpracovano'];
    }

    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($request['mzd_per'] ?? 100);
    if (!in_array($perPage, $perOptions, true)) {
        $perPage = 100;
    }
    $totalRows = count($rows);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $pageNum = max(1, min((int)($request['mzd_p'] ?? 1), $totalPages));
    $offset = ($pageNum - 1) * $perPage;
    $pagedRows = array_slice($rows, $offset, $perPage);

    return [
        'id_firma' => $idFirma,
        'period' => $rawPeriod,
        'period_year' => (int)$periodStart->format('Y'),
        'period_month' => (int)$periodStart->format('n'),
        'period_years' => $periodYears,
        'period_months' => $periodMonthsByYear[(int)$periodStart->format('Y')] ?? [],
        'period_label' => $periodStart->format('m/Y'),
        'branches' => $branches,
        'position_options' => $positionOptions,
        'workload_options' => $workloadOptions,
        'filters' => $filters,
        'active_filters' => array_filter($filters, static fn(string $value): bool => $value !== ''),
        'sort' => $sort,
        'dir' => $dir,
        'per_options' => $perOptions,
        'per_page' => $perPage,
        'page_num' => $pageNum,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'first_row' => $totalRows === 0 ? 0 : $offset + 1,
        'last_row' => $totalRows === 0 ? 0 : $offset + count($pagedRows),
        'rows' => $pagedRows,
        'total_hours' => $totalHours,
    ];
}
