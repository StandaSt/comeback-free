<?php
declare(strict_types=1);

/**
 * DB dotazy pro seznam a detail zamestnancu v HR.
 */

/**
 * Nacte seznam aktivnich zamestnancu.
 */
function hr_fetch_employees(mysqli $db, int $limit = 100): array
{
    $limit = max(1, min($limit, 500));
    $sql = "
        SELECT
            p.id_person,
            p.osobni_cislo,
            p.overen,
            p.kompletni,
            CASE
                WHEN pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE() THEN 'aktivni'
                ELSE 'ukonceny'
            END AS stav,
            p.vytvoreno AS zadano,
            ou.jmeno,
            ou.druhe_jmeno,
            ou.prijmeni,
            pv.datum_nastupu,
            pv.id_pracovni_vztah,
            pob.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.nazev AS vztah_kod
        FROM hr_person p
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = p.id_person
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_person = p.id_person
           AND pv.platny = 1
           AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
        LEFT JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_pracoviste pp
            ON pp.id_person = p.id_person
           AND pp.platny = 1
           AND pp.hlavni = 1
        LEFT JOIN pobocka pob
            ON pob.id_pob = pp.id_pob
        LEFT JOIN hr_zarazeni pz
            ON pz.id_person = p.id_person
           AND pz.platny = 1
           AND pz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = pz.id_slot
        WHERE p.aktivni = 1
        ORDER BY p.id_person DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalize_employee_row($row);
    }
    $stmt->close();

    return $rows;
}

/**
 * Vrati hodnotu textoveho filtru seznamu zamestnancu.
 */
function hr_employee_list_filter(string $key): string
{
    $filters = $_GET['hr_emp_f'] ?? [];
    if (!is_array($filters)) {
        return '';
    }

    return trim((string)($filters[$key] ?? ''));
}

/**
 * Nacte seznam zamestnancu pro tabulku vcetne filtru, razeni a strankovani.
 *
 * @return array{per_options: array<int, int>, per_page: int, page_num: int, sort: string, dir: string, filters: array<string, string>, active_filters: array<string, string>, rows: array<int, array<string, mixed>>, total_rows: int, total_pages: int, first_row: int, last_row: int, filter_options: array<string, array<int, string>>}
 */
function hr_fetch_employee_list(mysqli $db): array
{
    $queryParams = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
    foreach (['hr_emp_f', 'hr_emp_per', 'hr_emp_p', 'hr_emp_sort', 'hr_emp_dir'] as $key) {
        if (array_key_exists($key, $queryParams)) {
            $_GET[$key] = $queryParams[$key];
        }
    }

    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($queryParams['hr_emp_per'] ?? 20);
    if (!in_array($perPage, $perOptions, true)) {
        $perPage = 20;
    }

    $pageNum = max(1, (int)($queryParams['hr_emp_p'] ?? 1));
    $sortMap = [
        'id' => 'p.id_person',
        'zamestnanec' => "TRIM(CONCAT(COALESCE(ou.prijmeni, ''), ' ', COALESCE(ou.jmeno, ''), ' ', COALESCE(ou.druhe_jmeno, '')))",
        'zarazeni' => 'zarazeni',
        'pracoviste' => 'pracoviste',
        'vztah' => 'vztah_kod',
        'nastup' => 'pv.datum_nastupu',
        'stav' => 'p.aktivni',
        'overen' => 'p.overen',
        'kompletni' => 'p.kompletni',
    ];
    $sort = (string)($queryParams['hr_emp_sort'] ?? 'id');
    if (!isset($sortMap[$sort])) {
        $sort = 'id';
    }
    $dir = strtolower((string)($queryParams['hr_emp_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

    $filters = [
        'id' => hr_employee_list_filter('id'),
        'zamestnanec' => hr_employee_list_filter('zamestnanec'),
        'zarazeni' => hr_employee_list_filter('zarazeni'),
        'pracoviste' => hr_employee_list_filter('pracoviste'),
        'vztah' => hr_employee_list_filter('vztah'),
        'nastup' => hr_employee_list_filter('nastup'),
        'stav' => hr_employee_list_filter('stav'),
        'overen' => hr_employee_list_filter('overen'),
        'kompletni' => hr_employee_list_filter('kompletni'),
    ];
    if (!in_array($filters['stav'], ['aktivni', 'neaktivni', 'vse'], true)) {
        $filters['stav'] = 'aktivni';
    }
    if (!in_array($filters['overen'], ['overeny', 'neovereny', 'vse'], true)) {
        $filters['overen'] = 'vse';
    }
    if (!in_array($filters['kompletni'], ['kompletni', 'nekompletni', 'vse'], true)) {
        $filters['kompletni'] = 'vse';
    }
    $activeFilters = array_filter($filters, static fn (string $value): bool => $value !== '' && $value !== 'vse');

    $fromSql = "
        FROM hr_person p
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = p.id_person
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_person = p.id_person
           AND pv.platny = 1
           AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
        LEFT JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN (
            SELECT
                hp.id_person,
                GROUP_CONCAT(DISTINCT pob.nazev ORDER BY hp.hlavni DESC, pob.nazev SEPARATOR ', ') AS pracoviste,
                MAX(CASE WHEN hp.hlavni = 1 THEN pob.nazev END) AS pracoviste_hlavni
            FROM hr_pracoviste hp
            INNER JOIN pobocka pob
                ON pob.id_pob = hp.id_pob
            WHERE hp.platny = 1
            GROUP BY hp.id_person
        ) pp
            ON pp.id_person = p.id_person
        LEFT JOIN (
            SELECT
                pz.id_person,
                GROUP_CONCAT(DISTINCT cs.slot ORDER BY cs.slot SEPARATOR ', ') AS zarazeni
            FROM hr_zarazeni pz
            INNER JOIN cis_slot cs
                ON cs.id_slot = pz.id_slot
            WHERE pz.platny = 1
            GROUP BY pz.id_person
        ) pz
            ON pz.id_person = p.id_person
    ";

    $where = [];
    $filterSqlMap = [
        'id' => 'CAST(p.id_person AS CHAR)',
        'zamestnanec' => "TRIM(CONCAT(COALESCE(ou.prijmeni, ''), ' ', COALESCE(ou.jmeno, ''), ' ', COALESCE(ou.druhe_jmeno, '')))",
        'zarazeni' => "COALESCE(pz.zarazeni, '')",
        'pracoviste' => "COALESCE(pp.pracoviste, '')",
        'vztah' => "COALESCE(pvt.nazev, '')",
        'nastup' => "COALESCE(DATE_FORMAT(pv.datum_nastupu, '%d.%m.%Y'), '')",
    ];
    foreach ($filterSqlMap as $key => $expression) {
        if ($filters[$key] === '') {
            continue;
        }
        $value = $db->real_escape_string($filters[$key]);
        $where[] = $expression . " LIKE '%{$value}%'";
    }
    if ($filters['stav'] === 'aktivni') {
        $where[] = 'p.aktivni = 1';
    } elseif ($filters['stav'] === 'neaktivni') {
        $where[] = 'p.aktivni = 0';
    }
    if ($filters['overen'] === 'overeny') {
        $where[] = 'p.overen = 1';
    } elseif ($filters['overen'] === 'neovereny') {
        $where[] = 'p.overen = 0';
    }
    if ($filters['kompletni'] === 'kompletni') {
        $where[] = 'p.kompletni = 1';
    } elseif ($filters['kompletni'] === 'nekompletni') {
        $where[] = 'p.kompletni = 0';
    }
    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

    $countResult = $db->query("SELECT COUNT(*) AS cnt {$fromSql} {$whereSql}");
    $countRow = $countResult instanceof mysqli_result ? $countResult->fetch_assoc() : null;
    if ($countResult instanceof mysqli_result) {
        $countResult->free();
    }
    $totalRows = max(0, (int)($countRow['cnt'] ?? 0));
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($pageNum > $totalPages) {
        $pageNum = $totalPages;
    }
    $offset = ($pageNum - 1) * $perPage;
    $orderSql = $sortMap[$sort] . ' ' . strtoupper($dir) . ', p.id_person DESC';

    $dataSql = "
        SELECT
            p.id_person,
            p.osobni_cislo,
            p.overen,
            p.kompletni,
            CASE WHEN p.aktivni = 1 THEN 'aktivni' ELSE 'neaktivni' END AS stav,
            p.vytvoreno AS zadano,
            ou.jmeno,
            ou.druhe_jmeno,
            ou.prijmeni,
            pv.datum_nastupu,
            pv.id_pracovni_vztah,
            pp.pracoviste,
            pp.pracoviste_hlavni,
            pz.zarazeni,
            pvt.nazev AS vztah_kod
        {$fromSql}
        {$whereSql}
        ORDER BY {$orderSql}
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $dataResult = $db->query($dataSql);
    $rows = [];
    if ($dataResult instanceof mysqli_result) {
        while ($row = $dataResult->fetch_assoc()) {
            $rows[] = hr_normalize_employee_row($row);
        }
        $dataResult->free();
    }

    $optionQueries = [
        'zarazeni' => "SELECT DISTINCT cs.slot AS value FROM hr_zarazeni pz INNER JOIN cis_slot cs ON cs.id_slot = pz.id_slot WHERE pz.platny = 1 AND cs.slot <> '' ORDER BY cs.slot",
        'pracoviste' => "SELECT DISTINCT pob.nazev AS value FROM hr_pracoviste pp INNER JOIN pobocka pob ON pob.id_pob = pp.id_pob WHERE pp.platny = 1 AND pob.nazev <> '' ORDER BY pob.nazev",
        'vztah' => "SELECT DISTINCT pvt.nazev AS value FROM hr_pracovni_vztah pv INNER JOIN hr_cis_pracovni_vztah_typ pvt ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ WHERE pv.platny = 1 AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE()) AND pvt.nazev <> '' ORDER BY pvt.nazev",
    ];
    $filterOptions = [];
    foreach ($optionQueries as $key => $sql) {
        $result = $db->query($sql);
        $filterOptions[$key] = [];
        if (!$result instanceof mysqli_result) {
            continue;
        }
        while ($row = $result->fetch_assoc()) {
            $filterOptions[$key][] = (string)$row['value'];
        }
        $result->free();
    }

    return [
        'per_options' => $perOptions,
        'per_page' => $perPage,
        'page_num' => $pageNum,
        'sort' => $sort,
        'dir' => $dir,
        'filters' => $filters,
        'active_filters' => $activeFilters,
        'rows' => $rows,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'first_row' => $totalRows > 0 ? $offset + 1 : 0,
        'last_row' => min($offset + $perPage, $totalRows),
        'filter_options' => $filterOptions,
    ];
}

/**
 * Nacte aktualni pracovni pomer vcetne uvazku a mzdy.
 */
function hr_fetch_employee_work_relation(mysqli $db, int $idPerson): ?array
{
    $sql = '
        SELECT
            pv.id_pracovni_vztah,
            pv.id_pracovni_vztah_typ,
            pvt.nazev AS vztah,
            pv.datum_nastupu,
            pv.datum_ukonceni,
            pu.id_pracovni_ukonceni,
            u.id_pracovni_uvazek,
            u.uvazek,
            u.hodin_tydne,
            m.id_mzda,
            m.id_mzda_typ,
            m.castka AS mzda_castka
        FROM hr_pracovni_vztah pv
        INNER JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_pracovni_uvazek u
            ON u.id_pracovni_vztah = pv.id_pracovni_vztah
           AND u.platny = 1
        LEFT JOIN hr_mzda m
            ON m.id_pracovni_vztah = pv.id_pracovni_vztah
           AND m.platny = 1
        LEFT JOIN hr_pracovni_ukonceni pu
            ON pu.id_pracovni_vztah = pv.id_pracovni_vztah
        WHERE pv.id_person = ?
          AND pv.platny = 1
        ORDER BY pv.id_pracovni_vztah DESC, u.id_pracovni_uvazek DESC, m.id_mzda DESC
        LIMIT 1
    ';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

/**
 * Nacte celou historii pracovnich pomeru osoby, vcetne posledniho uvazku,
 * mzdy, benefitu a pripadneho ukonceni.
 */
function hr_fetch_employee_work_relation_history(mysqli $db, int $idPerson): array
{
    $sql = '
        SELECT
            pv.id_pracovni_vztah,
            pvt.nazev AS vztah,
            pv.datum_nastupu,
            pv.datum_ukonceni,
            pv.poznamka,
            pv.platny,
            u.uvazek,
            u.hodin_tydne,
            mt.nazev AS mzda_typ,
            m.castka AS mzda_castka,
            pu.datum_oznameni,
            pu.datum_ukonceni AS ukonceni_datum,
            put.nazev AS ukonceni_typ,
            pu.poznamka AS ukonceni_poznamka,
            GROUP_CONCAT(DISTINCT cb.nazev ORDER BY cb.nazev SEPARATOR ", ") AS benefity
        FROM hr_pracovni_vztah pv
        INNER JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_pracovni_uvazek u
            ON u.id_pracovni_vztah = pv.id_pracovni_vztah
           AND u.platny = 1
        LEFT JOIN hr_mzda m
            ON m.id_pracovni_vztah = pv.id_pracovni_vztah
           AND m.platny = 1
        LEFT JOIN cis_mzda_typ mt
            ON mt.id_mzda_typ = m.id_mzda_typ
        LEFT JOIN hr_benefit b
            ON b.id_pracovni_vztah = pv.id_pracovni_vztah
           AND b.platny = 1
        LEFT JOIN hr_cis_benefit cb
            ON cb.id_cis_benefit = b.id_cis_benefit
        LEFT JOIN hr_pracovni_ukonceni pu
            ON pu.id_pracovni_vztah = pv.id_pracovni_vztah
        LEFT JOIN hr_cis_pracovni_ukonceni_typ put
            ON put.id_pracovni_ukonceni_typ = pu.id_pracovni_ukonceni_typ
        WHERE pv.id_person = ?
        GROUP BY pv.id_pracovni_vztah
        ORDER BY pv.datum_nastupu DESC, pv.id_pracovni_vztah DESC
    ';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/** Nacte chronologickou historii zmen pracovnich pomeru zamestnance. */
function hr_fetch_employee_work_timeline(mysqli $db, int $idPerson): array
{
    $sql = '
        SELECT * FROM (
            SELECT pv.vytvoreno AS kdy, CONCAT("Nástup / změna vztahu: ", pvt.nazev) AS akce, pv.datum_nastupu AS plati_od, CONCAT(ao.prijmeni, " ", ao.jmeno) AS zapsal, pv.poznamka
            FROM hr_pracovni_vztah pv
            INNER JOIN hr_cis_pracovni_vztah_typ pvt ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
            LEFT JOIN hr_osobni_udaje ao ON ao.id_person = pv.id_person_zadal AND ao.platny = 1
            WHERE pv.id_person = ?
            UNION ALL
            SELECT m.vytvoreno, CONCAT("Změna mzdy: ", m.castka, " Kč / ", mt.nazev), m.platnost_od, CONCAT(ao.prijmeni, " ", ao.jmeno), NULL
            FROM hr_mzda m INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = m.id_pracovni_vztah INNER JOIN cis_mzda_typ mt ON mt.id_mzda_typ = m.id_mzda_typ LEFT JOIN hr_osobni_udaje ao ON ao.id_person = m.zadal AND ao.platny = 1 WHERE pv.id_person = ?
            UNION ALL
            SELECT u.vytvoreno, CONCAT("Změna úvazku: ", u.hodin_tydne, " h/týdně"), u.platnost_od, CONCAT(ao.prijmeni, " ", ao.jmeno), NULL
            FROM hr_pracovni_uvazek u INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = u.id_pracovni_vztah LEFT JOIN hr_osobni_udaje ao ON ao.id_person = u.zadal AND ao.platny = 1 WHERE pv.id_person = ?
            UNION ALL
            SELECT b.vytvoreno, CONCAT("Přidán benefit: ", cb.nazev), b.platnost_od, CONCAT(ao.prijmeni, " ", ao.jmeno), NULL
            FROM hr_benefit b INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = b.id_pracovni_vztah INNER JOIN hr_cis_benefit cb ON cb.id_cis_benefit = b.id_cis_benefit LEFT JOIN hr_osobni_udaje ao ON ao.id_person = b.zadal AND ao.platny = 1 WHERE pv.id_person = ?
            UNION ALL
            SELECT b.zruseno, CONCAT("Odebrán benefit: ", cb.nazev), b.platnost_do, CONCAT(ao.prijmeni, " ", ao.jmeno), NULL
            FROM hr_benefit b INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = b.id_pracovni_vztah INNER JOIN hr_cis_benefit cb ON cb.id_cis_benefit = b.id_cis_benefit LEFT JOIN hr_osobni_udaje ao ON ao.id_person = b.id_person_zrusil AND ao.platny = 1 WHERE pv.id_person = ? AND b.zruseno IS NOT NULL
            UNION ALL
            SELECT pp.vytvoreno, CONCAT("Přerušení: ", pt.nazev), pp.datum_od, CONCAT(uu.prijmeni, " ", uu.jmeno), pp.poznamka
            FROM hr_pracovni_preruseni pp INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = pp.id_pracovni_vztah INNER JOIN hr_cis_pracovni_preruseni_typ pt ON pt.id_pracovni_preruseni_typ = pp.id_pracovni_preruseni_typ LEFT JOIN user uu ON uu.id_user = pp.id_user_zadal WHERE pv.id_person = ?
            UNION ALL
            SELECT pu.vytvoreno, CONCAT("Ukončení: ", put.nazev), pu.datum_ukonceni, CONCAT(uu.prijmeni, " ", uu.jmeno), pu.poznamka
            FROM hr_pracovni_ukonceni pu INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = pu.id_pracovni_vztah INNER JOIN hr_cis_pracovni_ukonceni_typ put ON put.id_pracovni_ukonceni_typ = pu.id_pracovni_ukonceni_typ LEFT JOIN user uu ON uu.id_user = pu.id_user_zadal WHERE pv.id_person = ?
        ) timeline
        ORDER BY kdy DESC
    ';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('iiiiiii', $idPerson, $idPerson, $idPerson, $idPerson, $idPerson, $idPerson, $idPerson);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Nacte evidovana preruseni jednoho pracovniho pomeru. */
function hr_fetch_employee_work_interruptions(mysqli $db, int $idPracovniVztah): array
{
    $sql = '
        SELECT pp.id_pracovni_preruseni, pp.datum_od, pp.datum_do, pp.poznamka, pt.nazev AS typ
        FROM hr_pracovni_preruseni pp
        INNER JOIN hr_cis_pracovni_preruseni_typ pt
            ON pt.id_pracovni_preruseni_typ = pp.id_pracovni_preruseni_typ
        WHERE pp.id_pracovni_vztah = ?
        ORDER BY pp.datum_od DESC, pp.id_pracovni_preruseni DESC
    ';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $idPracovniVztah);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/** Nacte aktivni ciselnik typu preruseni nebo ukonceni. */
function hr_fetch_employee_work_event_types(mysqli $db, string $table, string $idColumn): array
{
    $allowed = [
        'hr_cis_pracovni_preruseni_typ' => 'id_pracovni_preruseni_typ',
        'hr_cis_pracovni_ukonceni_typ' => 'id_pracovni_ukonceni_typ',
    ];
    if (($allowed[$table] ?? null) !== $idColumn) {
        throw new InvalidArgumentException('Neplatny ciselnik pracovniho pomeru.');
    }
    $result = $db->query("SELECT {$idColumn} AS id, nazev AS label FROM {$table} WHERE aktivni = 1 ORDER BY nazev");
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Nacte identifikatory aktualnich benefitu jednoho pracovniho vztahu.
 */
function hr_fetch_employee_work_benefit_ids(mysqli $db, int $idPracovniVztah): array
{
    $stmt = $db->prepare('SELECT id_cis_benefit FROM hr_benefit WHERE id_pracovni_vztah = ? AND platny = 1 ORDER BY id_cis_benefit');
    $stmt->bind_param('i', $idPracovniVztah);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id_cis_benefit'];
    }
    $stmt->close();

    return $ids;
}

/**
 * Nacte detail jednoho zamestnance podle id_person.
 */
function hr_fetch_employee(mysqli $db, int $id): ?array
{
    $sql = "
        SELECT
            p.id_person,
            p.osobni_cislo,
            p.overen,
            p.kompletni,
            CASE
                WHEN pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE() THEN 'aktivni'
                ELSE 'ukonceny'
            END AS stav,
            p.vytvoreno AS zadano,
            ou.jmeno,
            ou.druhe_jmeno,
            ou.prijmeni,
            ou.titul_pred,
            ou.titul_za,
            ou.datum_narozeni,
            ou.rodne_cislo,
            ou.pohlavi,
            ou.zdr_poj,
            ou.statni_obcanstvi,
            ou.misto_narozeni,
            pv.datum_nastupu,
            pv.datum_ukonceni,
            pob.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.nazev AS vztah_kod,
            pvt.nazev AS vztah_nazev,
            tel.telefon,
            em.email
        FROM hr_person p
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = p.id_person
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_person = p.id_person
           AND pv.platny = 1
        LEFT JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_pracoviste pp
            ON pp.id_person = p.id_person
           AND pp.platny = 1
           AND pp.hlavni = 1
        LEFT JOIN pobocka pob
            ON pob.id_pob = pp.id_pob
        LEFT JOIN hr_zarazeni pz
            ON pz.id_person = p.id_person
           AND pz.platny = 1
           AND pz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = pz.id_slot
        LEFT JOIN hr_telefon tel
            ON tel.id_person = p.id_person
           AND tel.platny = 1
           AND tel.hlavni = 1
        LEFT JOIN hr_email em
            ON em.id_person = p.id_person
           AND em.platny = 1
           AND em.hlavni = 1
        WHERE p.id_person = ?
          AND p.aktivni = 1
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? hr_normalize_employee_row($row) : null;
}

/**
 * Nacte aktualni doplnkove udaje pro editaci karty zamestnance.
 */
function hr_fetch_employee_edit_data(mysqli $db, int $idPerson): array
{
    $queries = [
        'adresa_op' => 'SELECT ulice, cp, mesto, psc, stat FROM hr_adresa WHERE id_person = ? AND typ = 0 AND platny = 1 ORDER BY id_adresa DESC LIMIT 1',
        'adresa_dorucovaci' => 'SELECT ulice, cp, mesto, psc, stat FROM hr_adresa WHERE id_person = ? AND typ = 1 AND platny = 1 ORDER BY id_adresa DESC LIMIT 1',
        'nouzovy_kontakt' => 'SELECT jmeno, vztah, telefon, email FROM hr_nouzovy_kontakt WHERE id_person = ? AND platny = 1 AND hlavni = 1 ORDER BY id_nouzovy_kontakt DESC LIMIT 1',
        'bankovni_ucet' => 'SELECT cislo_uctu, kod_banky, iban FROM hr_bankovni_ucet WHERE id_person = ? AND platny = 1 ORDER BY zmena DESC, id_bankovni_ucet DESC LIMIT 1',
    ];
    $data = [];
    foreach ($queries as $key => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $data[$key] = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }
    return $data;
}

/**
 * Doplni radek zamestnance o hodnoty pripravene pro zobrazeni.
 */
function hr_normalize_employee_row(array $row): array
{
    $jmeno = trim((string)($row['jmeno'] ?? ''));
    $druheJmeno = trim((string)($row['druhe_jmeno'] ?? ''));
    $prijmeni = trim((string)($row['prijmeni'] ?? ''));
    $fullName = trim($prijmeni . ' ' . trim($jmeno . ' ' . $druheJmeno));

    return $row + [
        'cele_jmeno' => $fullName !== '' ? $fullName : 'Bez jména',
        'inicialy' => hr_initials($jmeno, $prijmeni),
        'stav_label' => hr_stav_label((string)($row['stav'] ?? '')),
        'stav_badge' => ((string)($row['stav'] ?? '')) === 'aktivni' ? 'hr_success' : 'hr_neutral',
    ];
}

/**
 * Vytvori inicialy ze jmena a prijmeni.
 */
function hr_initials(string $jmeno, string $prijmeni): string
{
    $a = mb_substr(trim($jmeno), 0, 1);
    $b = mb_substr(trim($prijmeni), 0, 1);
    $out = mb_strtoupper($a . $b);
    return $out !== '' ? $out : '?';
}

/**
 * Prevede interni stav zamestnance na text pro zobrazeni.
 */
function hr_stav_label(string $stav): string
{
    return match ($stav) {
        'aktivni' => 'Aktivní',
        'neaktivni' => 'Neaktivní',
        'preruseny' => 'Přerušený',
        'ukonceny' => 'Ukončený',
        default => 'Příprava',
    };
}
