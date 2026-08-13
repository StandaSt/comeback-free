<?php
// db/db_objednavky_prehled.php * Prehled objednavek v PP
declare(strict_types=1);

function cb_db_objednavky_prehled_period(): array
{
    $odRaw = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
    $doRaw = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));

    try {
        $od = $odRaw !== '' ? new DateTimeImmutable($odRaw) : new DateTimeImmutable('today 06:00');
    } catch (Throwable $e) {
        $od = new DateTimeImmutable('today 06:00');
    }

    try {
        $do = $doRaw !== '' ? new DateTimeImmutable($doRaw) : new DateTimeImmutable('now');
    } catch (Throwable $e) {
        $do = new DateTimeImmutable('now');
    }

    if ($do <= $od) {
        $do = $od->modify('+1 day');
    }

    return [$od, $do];
}

function cb_db_objednavky_prehled_filter(string $key): string
{
    $filters = $_GET['obj_f'] ?? [];
    if (!is_array($filters)) {
        return '';
    }

    return trim((string)($filters[$key] ?? ''));
}

function cb_db_objednavky_prehled_nacti(): array
{
    $db = db();
    $queryParams = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
    if (isset($queryParams['obj_f']) && is_array($queryParams['obj_f'])) {
        $_GET['obj_f'] = $queryParams['obj_f'];
    }

    [$periodOd, $periodDo] = cb_db_objednavky_prehled_period();
    $periodOdSql = $db->real_escape_string($periodOd->format('Y-m-d H:i:s'));
    $periodDoSql = $db->real_escape_string($periodDo->format('Y-m-d H:i:s'));
    $periodReportOdSql = $db->real_escape_string($periodOd->format('Y-m-d'));
    $periodReportDoSql = $db->real_escape_string($periodDo->modify('-1 second')->format('Y-m-d'));

    $selectedPobocky = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
    $selectedPobocky = array_values(array_filter(array_map('intval', $selectedPobocky), static fn (int $id): bool => $id > 0));
    if ($selectedPobocky === [] && (int)($_SESSION['cb_pobocka_id'] ?? 0) > 0) {
        $selectedPobocky[] = (int)$_SESSION['cb_pobocka_id'];
    }

    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($queryParams['obj_per'] ?? 20);
    if (!in_array($perPage, $perOptions, true)) {
        $perPage = 20;
    }

    $pageNum = max(1, (int)($queryParams['obj_p'] ?? 1));
    $sortMap = [
        'cislo' => 'o.restia_order_number',
        'vytvoreno' => 'vytvoreno',
        'pobocka' => 'pobocka_nazev',
        'stav' => 'stav_nazev',
        'typ' => 'typ_nazev',
        'platba' => 'platba_nazev',
        'zakaznik' => 'zakaznik_jmeno',
        'cena' => 'cena_celk',
    ];
    $sort = (string)($queryParams['obj_sort'] ?? 'vytvoreno');
    if (!isset($sortMap[$sort])) {
        $sort = 'vytvoreno';
    }
    $dir = strtolower((string)($queryParams['obj_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

    $filters = [
        'cislo' => cb_db_objednavky_prehled_filter('cislo'),
        'vytvoreno' => cb_db_objednavky_prehled_filter('vytvoreno'),
        'pobocka' => cb_db_objednavky_prehled_filter('pobocka'),
        'stav' => cb_db_objednavky_prehled_filter('stav'),
        'typ' => cb_db_objednavky_prehled_filter('typ'),
        'platba' => cb_db_objednavky_prehled_filter('platba'),
        'zakaznik' => cb_db_objednavky_prehled_filter('zakaznik'),
        'cena' => cb_db_objednavky_prehled_filter('cena'),
    ];
    $activeFilters = array_filter($filters, static fn (string $value): bool => $value !== '');

    $where = [];
    if ($selectedPobocky !== []) {
        $where[] = 'o.id_pob IN (' . implode(',', $selectedPobocky) . ')';
    }

    $filterSqlMap = [
        'cislo' => "COALESCE(o.restia_order_number, '')",
        'vytvoreno' => "COALESCE(DATE_FORMAT(COALESCE((SELECT MIN(ca_vytvor.cas_vytvor) FROM obj_casy ca_vytvor WHERE ca_vytvor.id_obj = o.id_obj), o.restia_created_at, o.restia_imported_at), '%d.%m.%Y %H:%i:%s'), '')",
        'pobocka' => "COALESCE(pb.nazev, '')",
        'stav' => "COALESCE(s.nazev, '')",
        'typ' => "COALESCE(d.nazev, '')",
        'platba' => "COALESCE(pl.nazev, '')",
        'zakaznik' => "TRIM(CONCAT(COALESCE(z.jmeno, ''), ' ', COALESCE(z.prijmeni, '')))",
        'cena' => "COALESCE((SELECT MAX(cena_filter.cena_celk) FROM obj_ceny cena_filter WHERE cena_filter.id_obj = o.id_obj), '')",
    ];

    foreach ($activeFilters as $key => $value) {
        if (!isset($filterSqlMap[$key])) {
            continue;
        }
        $safeValue = $db->real_escape_string($value);
        $where[] = $filterSqlMap[$key] . " LIKE '%{$safeValue}%'";
    }

    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
    $periodJoinSql = "
        INNER JOIN (
            SELECT DISTINCT period_raw.id_obj
            FROM (
                SELECT ca.id_obj
                FROM obj_casy ca
                WHERE ca.cas_vytvor IS NOT NULL
                  AND ca.cas_vytvor >= '{$periodOdSql}'
                  AND ca.cas_vytvor < '{$periodDoSql}'
                UNION ALL
                SELECT o2.id_obj
                FROM objednavky_restia o2
                LEFT JOIN obj_casy ca2 ON ca2.id_obj = o2.id_obj
                WHERE ca2.cas_vytvor IS NULL
                  AND o2.restia_created_at IS NOT NULL
                  AND o2.restia_created_at >= '{$periodOdSql}'
                  AND o2.restia_created_at < '{$periodDoSql}'
                UNION ALL
                SELECT o3.id_obj
                FROM objednavky_restia o3
                LEFT JOIN obj_casy ca3 ON ca3.id_obj = o3.id_obj
                WHERE ca3.cas_vytvor IS NULL
                  AND o3.restia_created_at IS NULL
                  AND o3.restia_imported_at >= '{$periodOdSql}'
                  AND o3.restia_imported_at < '{$periodDoSql}'
                UNION ALL
                SELECT ca4.id_obj
                FROM obj_casy ca4
                INNER JOIN objednavky_restia o4 ON o4.id_obj = ca4.id_obj
                WHERE ca4.cas_vytvor IS NULL
                  AND o4.restia_created_at IS NULL
                  AND o4.restia_imported_at IS NULL
                  AND ca4.report IS NOT NULL
                  AND ca4.report >= '{$periodReportOdSql}'
                  AND ca4.report <= '{$periodReportDoSql}'
            ) period_raw
        ) period_ids ON period_ids.id_obj = o.id_obj
    ";
    $fromSql = "
        FROM objednavky_restia o
        {$periodJoinSql}
        LEFT JOIN cis_obj_stav s ON s.id_stav = o.id_stav
        LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby pl ON pl.id_platba = o.id_platba
        LEFT JOIN zakaznik z ON z.id_zak = o.id_zak
        LEFT JOIN pobocka pb ON pb.id_pob = o.id_pob
    ";

    $countRes = $db->query("SELECT COUNT(*) AS cnt {$fromSql} {$whereSql}");
    $countRow = ($countRes instanceof mysqli_result) ? $countRes->fetch_assoc() : null;
    if ($countRes instanceof mysqli_result) {
        $countRes->free();
    }

    $totalRows = max(0, (int)($countRow['cnt'] ?? 0));
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($pageNum > $totalPages) {
        $pageNum = $totalPages;
    }
    $offset = ($pageNum - 1) * $perPage;

    $orderSql = $sortMap[$sort] . ' ' . strtoupper($dir) . ', o.id_obj DESC';
    $dataSql = "
        SELECT
            o.id_obj,
            o.restia_order_number,
            COALESCE(
                (SELECT MIN(ca_sort.cas_vytvor) FROM obj_casy ca_sort WHERE ca_sort.id_obj = o.id_obj),
                o.restia_created_at,
                o.restia_imported_at
            ) AS vytvoreno,
            pb.nazev AS pobocka_nazev,
            s.nazev AS stav_nazev,
            d.nazev AS typ_nazev,
            pl.nazev AS platba_nazev,
            TRIM(CONCAT(COALESCE(z.jmeno, ''), ' ', COALESCE(z.prijmeni, ''))) AS zakaznik_jmeno,
            (SELECT MAX(cena.cena_celk) FROM obj_ceny cena WHERE cena.id_obj = o.id_obj) AS cena_celk
        {$fromSql}
        {$whereSql}
        ORDER BY {$orderSql}
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $dataRes = $db->query($dataSql);
    $rows = [];
    if ($dataRes instanceof mysqli_result) {
        while ($row = $dataRes->fetch_assoc()) {
            $rows[] = $row;
        }
        $dataRes->free();
    }

    return [
        'period_od' => $periodOd,
        'period_do' => $periodDo,
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
    ];
}
