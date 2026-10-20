<?php
// Přehled objednávek s místním obdobím a filtry; povolené pobočky i jejich oblasti slouží k výběru podle ID.
declare(strict_types=1);

require_once __DIR__ . '/../../common/db/db_user_pobocka.php';
require_once __DIR__ . '/../../common/lib/obdobi_stranky.php';

function cb_db_objednavky_prehled_cancel_statuses(): array
{
    return ['canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted'];
}

/** Objednávky mají vlastní interval; bez volby začínají aktuálním provozním dnem i před šestou. */
function cb_db_objednavky_prehled_period(): array
{
    $period = cb_obdobi_stranky_model('provoz.objednavky');
    return [new DateTimeImmutable($period['od']), new DateTimeImmutable($period['do'])];
}

function cb_db_objednavky_prehled_filter(string $key): string
{
    $filters = $_GET['obj_f'] ?? [];
    if (!is_array($filters)) {
        return '';
    }

    return trim((string)($filters[$key] ?? ''));
}

/**
 * Vrati volby selectu filtru bez dalsich dotazu nad objednavkami.
 * Pobočky respektuji opravneni uzivatele, ostatni hodnoty jsou
 * stabilni ciselniky pouzivane pri zobrazeni objednavky.
 *
 * @param array<int, int> $allowedPobocky
 * @return array<string, array<int, string>> Pobočky mají jako klíč své ID.
 */
function cb_db_objednavky_prehled_filter_options(mysqli $db, array $allowedPobocky): array
{
    $queries = [
        'pobocka' => 'SELECT id_pob, nazev AS value FROM pobocka WHERE nazev <> \'\'',
        'stav' => 'SELECT nazev AS value FROM cis_obj_stav WHERE nazev <> \'\'',
        'typ' => 'SELECT nazev AS value FROM cis_doruceni WHERE nazev <> \'\'',
        'platba' => 'SELECT nazev AS value FROM cis_obj_platby WHERE nazev <> \'\'',
    ];
    $queries['pobocka'] .= $allowedPobocky !== []
        ? ' AND id_pob IN (' . implode(',', $allowedPobocky) . ')'
        : ' AND 1 = 0';

    $options = [];
    foreach ($queries as $key => $sql) {
        // Výrobu určuje stabilní kód; zůstane poslední i při jiném ID nebo změně zobrazovaného názvu.
        $orderBy = $key === 'pobocka'
            ? "CASE WHEN kod = 'Vyroba' THEN 1 ELSE 0 END, id_pob"
            : 'value';
        $result = $db->query($sql . ' ORDER BY ' . $orderBy);
        $values = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $value = trim((string)($row['value'] ?? ''));
                if ($value !== '') {
                    if ($key === 'pobocka') {
                        $values[(int)$row['id_pob']] = $value;
                    } else {
                        $values[] = $value;
                    }
                }
            }
            $result->free();
        }
        $options[$key] = $values;
    }

    $statusOptions = [];
    $hasCancelled = false;
    foreach ($options['stav'] as $status) {
        if (in_array($status, cb_db_objednavky_prehled_cancel_statuses(), true)) {
            $hasCancelled = true;
            continue;
        }
        $statusOptions[] = $status;
    }
    if ($hasCancelled) {
        $statusOptions[] = '__storno__';
    }
    $options['stav'] = $statusOptions;

    return $options;
}

/** Přijme seznam ID i starý odkaz s názvem; neznámá volba nesmí rozšířit výsledek na všechny pobočky. */
function cb_db_objednavky_prehled_branches(array $options): array
{
    $raw = $_GET['obj_f']['pobocka'] ?? [];
    $values = is_array($raw) ? $raw : ($raw === '' ? [] : [$raw]);
    $ids = [];
    foreach ($values as $value) {
        if (!is_scalar($value)) {
            $ids[] = 0;
            continue;
        }
        $text = trim((string)$value);
        $id = ctype_digit($text) ? (int)$text : array_search($text, $options, true);
        $ids[] = $id !== false && isset($options[$id]) ? (int)$id : 0;
    }
    return array_values(array_unique($ids));
}

// Nacte stranku objednavek jako prunik horniho obdobi, povolenych pobocek a mistnich filtru.
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

    // Přístup vychází z HR oprávnění; prázdný seznam nikdy neznamená všechny pobočky.
    $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    $allowedBranches = cb_db_user_pobocky($db, $idUser);
    $allowedPobocky = array_values(array_filter(array_map('intval', array_column($allowedBranches, 'id_pob')), static fn(int $id): bool => $id > 0));
    $filterOptions = cb_db_objednavky_prehled_filter_options($db, $allowedPobocky);

    // Vyhledávání a řazení pracuje se všemi syrovými identifikátory Restie.
    // Jejich uživatelské složení provádí až cb_format('o') ve výstupní vrstvě.
    $orderNumberSearchSql = "CONCAT_WS(' ',
        NULLIF(TRIM(o.restia_order_number), ''),
        NULLIF(TRIM(o.short_code), ''),
        NULLIF(TRIM(o.seriove_cislo), ''),
        NULLIF(TRIM(o.restia_id_obj), '')
    )";
    $orderNumberSortSql = "COALESCE(
        NULLIF(TRIM(o.restia_order_number), ''),
        NULLIF(TRIM(o.short_code), ''),
        NULLIF(TRIM(o.restia_id_obj), '')
    )";

    $perOptions = [20, 50, 100, 500];
    $perPage = (int)($queryParams['obj_per'] ?? 20);
    if (!in_array($perPage, $perOptions, true)) {
        $perPage = 20;
    }

    $pageNum = max(1, (int)($queryParams['obj_p'] ?? 1));
    $sortMap = [
        'cislo' => $orderNumberSortSql,
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
        'pobocka' => cb_db_objednavky_prehled_branches($filterOptions['pobocka']),
        'stav' => cb_db_objednavky_prehled_filter('stav'),
        'typ' => cb_db_objednavky_prehled_filter('typ'),
        'platba' => cb_db_objednavky_prehled_filter('platba'),
        'zakaznik' => cb_db_objednavky_prehled_filter('zakaznik'),
        'cena' => cb_db_objednavky_prehled_filter('cena'),
    ];
    if (in_array($filters['stav'], cb_db_objednavky_prehled_cancel_statuses(), true)) {
        $filters['stav'] = '__storno__';
    }
    $activeFilters = array_filter($filters, static fn (string|array $value): bool => is_array($value) ? $value !== [] : $value !== '');

    $where = [$allowedPobocky !== []
        ? 'o.id_pob IN (' . implode(',', $allowedPobocky) . ')'
        : '1 = 0'];

    $filterSqlMap = [
        'cislo' => $orderNumberSearchSql,
        'vytvoreno' => "COALESCE(DATE_FORMAT(COALESCE((SELECT MIN(ca_vytvor.cas_vytvor) FROM obj_casy ca_vytvor WHERE ca_vytvor.id_obj = o.id_obj), o.restia_created_at, o.restia_imported_at), '%d.%m.%Y %H:%i:%s'), '')",
        'pobocka' => "COALESCE(pb.nazev, '')",
        'stav' => "COALESCE(s.nazev, '')",
        'typ' => "COALESCE(d.nazev, '')",
        'platba' => "COALESCE(pl.nazev, '')",
        'zakaznik' => "TRIM(CONCAT(COALESCE(z.jmeno, ''), ' ', COALESCE(z.prijmeni, '')))",
        'cena' => "COALESCE((SELECT MAX(cena_filter.cena_celk) FROM obj_ceny cena_filter WHERE cena_filter.id_obj = o.id_obj), '')",
    ];

    foreach ($activeFilters as $key => $value) {
        // Seznam ID se protíná s nezávislou podmínkou oprávněných poboček výše.
        if ($key === 'pobocka') {
            $where[] = 'o.id_pob IN (' . implode(',', $value) . ')';
            continue;
        }
        if (!isset($filterSqlMap[$key])) {
            continue;
        }
        if ($key === 'stav' && $value === '__storno__') {
            $cancelStatuses = array_map(
                static fn(string $status): string => "'" . $db->real_escape_string($status) . "'",
                cb_db_objednavky_prehled_cancel_statuses()
            );
            $where[] = 's.nazev IN (' . implode(',', $cancelStatuses) . ')';
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
            o.short_code,
            o.seriove_cislo,
            o.restia_id_obj,
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
            (SELECT MAX(cena.cena_celk) FROM obj_ceny cena WHERE cena.id_obj = o.id_obj) AS cena_celk,
            (SELECT MAX(cena.sleva) FROM obj_ceny cena WHERE cena.id_obj = o.id_obj) AS sleva
        {$fromSql}
        {$whereSql}
        ORDER BY {$orderSql}
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $dataRes = $db->query($dataSql);
    $rows = [];
    if ($dataRes instanceof mysqli_result) {
        while ($row = $dataRes->fetch_assoc()) {
            $row['polozky'] = [];
            $rows[] = $row;
        }
        $dataRes->free();
    }

    $rowIndexes = [];
    foreach ($rows as $index => $row) {
        $idObj = (int)($row['id_obj'] ?? 0);
        if ($idObj > 0) {
            $rowIndexes[$idObj] = $index;
        }
    }
    if ($rowIndexes !== []) {
        $itemsSql = "
            SELECT
                op.id_obj,
                COALESCE(
                    NULLIF(TRIM(op.restia_nazev), ''),
                    NULLIF(TRIM(rp.nazev), ''),
                    CONCAT('Položka ', NULLIF(TRIM(op.res_item), '')),
                    'Položka'
                ) AS nazev,
                op.mnozstvi,
                op.cena_celk,
                COALESCE(op.poznamka, '') AS poznamka
            FROM obj_polozky op
            LEFT JOIN res_polozky rp ON rp.id_res_polozka = op.id_res_polozka
            WHERE op.id_obj IN (" . implode(',', array_keys($rowIndexes)) . ")
            ORDER BY op.id_obj ASC, op.poradi ASC, op.id_obj_polozka ASC
        ";
        $itemsResult = $db->query($itemsSql);
        if ($itemsResult instanceof mysqli_result) {
            while ($item = $itemsResult->fetch_assoc()) {
                $idObj = (int)($item['id_obj'] ?? 0);
                if (isset($rowIndexes[$idObj])) {
                    $rows[$rowIndexes[$idObj]]['polozky'][] = $item;
                }
            }
            $itemsResult->free();
        }
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
        'filter_options' => $filterOptions,
        // Hromadné volby využívají oblasti již načtených povolených poboček.
        'branch_regions' => array_column($allowedBranches, 'oblast', 'id_pob'),
        'active_filters' => $activeFilters,
        'rows' => $rows,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'first_row' => $totalRows > 0 ? $offset + 1 : 0,
        'last_row' => min($offset + $perPage, $totalRows),
    ];
}
