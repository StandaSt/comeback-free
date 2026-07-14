<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'];
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
$conn->set_charset('utf8mb4');

$queries = [
    'totals' => "
        SELECT 'raw' AS tab, COUNT(*) cnt, MIN(restia_created_at) min_dt, MAX(restia_created_at) max_dt FROM objednavky_raw
        UNION ALL
        SELECT 'raw_import_ok_rows', COUNT(*), MIN(datum_od), MAX(datum_do) FROM objednavky_raw_import WHERE stav = 'ok'
        UNION ALL
        SELECT 'restia', COUNT(*), MIN(restia_created_at), MAX(restia_created_at) FROM objednavky_restia WHERE restia_id_obj IS NOT NULL
    ",
    'by_branch_raw' => "
        SELECT p.id_pob, p.nazev, COUNT(r.id_raw) raw_cnt, MIN(r.restia_created_at) raw_min, MAX(r.restia_created_at) raw_max
        FROM pobocka p
        LEFT JOIN objednavky_raw r ON r.id_pob = p.id_pob
        GROUP BY p.id_pob, p.nazev
        ORDER BY p.id_pob
    ",
    'by_branch_restia' => "
        SELECT p.id_pob, p.nazev, COUNT(o.id_obj) restia_cnt, MIN(o.restia_created_at) restia_min, MAX(o.restia_created_at) restia_max
        FROM pobocka p
        LEFT JOIN objednavky_restia o ON o.id_pob = p.id_pob AND o.restia_id_obj IS NOT NULL
        GROUP BY p.id_pob, p.nazev
        ORDER BY p.id_pob
    ",
    'missing_old_in_raw' => "
        SELECT o.id_pob, COUNT(*) missing_cnt, MIN(o.restia_created_at) min_dt, MAX(o.restia_created_at) max_dt
        FROM objednavky_restia o
        LEFT JOIN objednavky_raw r ON r.restia_id_obj = o.restia_id_obj COLLATE utf8mb4_unicode_ci
        WHERE o.restia_id_obj IS NOT NULL
          AND r.restia_id_obj IS NULL
        GROUP BY o.id_pob
        ORDER BY o.id_pob
    ",
    'import_status' => "
        SELECT id_pob, stav, COUNT(*) days, MIN(datum_od) min_od, MAX(datum_do) max_do,
               SUM(restia_total_count) sum_total, SUM(stazeno_pocet) sum_saved
        FROM objednavky_raw_import
        GROUP BY id_pob, stav
        ORDER BY id_pob, stav
    ",
    'missing_by_hour' => "
        SELECT o.id_pob, HOUR(o.restia_created_at) hodina, COUNT(*) cnt
        FROM objednavky_restia o
        LEFT JOIN objednavky_raw r ON r.restia_id_obj = o.restia_id_obj COLLATE utf8mb4_unicode_ci
        WHERE o.restia_id_obj IS NOT NULL
          AND r.restia_id_obj IS NULL
        GROUP BY o.id_pob, HOUR(o.restia_created_at)
        ORDER BY o.id_pob, hodina
    ",
];

foreach ($queries as $name => $sql) {
    echo '--- ' . $name . PHP_EOL;
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}
