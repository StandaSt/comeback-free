<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'];
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . PHP_EOL);
    exit(1);
}

$queries = [
    'combo_14d' => "
        SELECT
            cp.kod AS platforma,
            COALESCE(d.nazev, '') AS doruceni,
            COALESCE(p.nazev, '') AS platba,
            COUNT(*) AS ks,
            ROUND(SUM(COALESCE(c.cena_celk, 0)), 2) AS kc
        FROM objednavky_restia o
        LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
        LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby p ON p.id_platba = o.id_platba
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        WHERE o.restia_created_at IS NOT NULL
          AND o.restia_created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        GROUP BY cp.kod, d.nazev, p.nazev
        ORDER BY kc DESC
        LIMIT 40
    ",
    'generic_cash_30d' => "
        SELECT
            COALESCE(d.nazev, '') AS doruceni,
            COUNT(*) AS ks,
            ROUND(SUM(COALESCE(c.cena_celk, 0)), 2) AS kc
        FROM objednavky_restia o
        LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
        LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby p ON p.id_platba = o.id_platba
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        WHERE cp.kod = 'generic'
          AND p.nazev = 'cash'
          AND o.restia_created_at IS NOT NULL
          AND o.restia_created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY d.nazev
        ORDER BY kc DESC
    ",
    'saved_vs_live_14d' => "
        SELECT
            r.datum_reportu,
            r.id_pob,
            ROUND(COALESCE(ri.wolt_cash, 0), 2) AS saved_wolt_cash,
            ROUND(COALESCE(src.live_wolt_cash, 0), 2) AS live_wolt_cash
        FROM reporty_is r
        LEFT JOIN reporty_is_restia ri ON ri.id_reportu = r.id_reportu
        LEFT JOIN (
            SELECT
                o.id_pob,
                DATE(o.restia_created_at) AS datum,
                SUM(
                    CASE
                        WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted')
                         AND cp.kod = 'generic'
                         AND COALESCE(d.nazev, '') = 'delivery'
                         AND COALESCE(p.nazev, '') = 'cash'
                        THEN COALESCE(c.cena_celk, 0)
                        ELSE 0
                    END
                ) AS live_wolt_cash
            FROM objednavky_restia o
            LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
            LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
            LEFT JOIN cis_obj_platby p ON p.id_platba = o.id_platba
            LEFT JOIN cis_obj_stav s ON s.id_stav = o.id_stav
            LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
            WHERE o.restia_created_at IS NOT NULL
              AND o.restia_created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
            GROUP BY o.id_pob, DATE(o.restia_created_at)
        ) src ON src.id_pob = r.id_pob AND src.datum = r.datum_reportu
        WHERE r.datum_reportu >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
          AND (COALESCE(ri.wolt_cash, 0) = 0 OR ABS(COALESCE(ri.wolt_cash, 0) - COALESCE(src.live_wolt_cash, 0)) > 0.01)
        ORDER BY r.datum_reportu DESC, r.id_pob DESC
        LIMIT 30
    ",
    'live_sum_vs_trzba_60d' => "
        SELECT
            DATE(o.restia_created_at) AS datum,
            o.id_pob,
            ROUND(SUM(CASE WHEN COALESCE(s.nazev, '') IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') THEN 0 ELSE COALESCE(c.cena_celk, 0) END), 2) AS trzba,
            ROUND(SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'wolt' AND COALESCE(p.nazev, '') <> 'cash' THEN COALESCE(c.cena_celk, 0) ELSE 0 END), 2) AS wolt,
            ROUND(SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'bolt' THEN COALESCE(c.cena_celk, 0) ELSE 0 END), 2) AS bolt,
            ROUND(SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod IN ('foodora', 'damejidlo') AND COALESCE(p.nazev, '') <> 'cash' THEN COALESCE(c.cena_celk, 0) ELSE 0 END), 2) AS dj,
            ROUND(SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'generic' AND COALESCE(p.nazev, '') = 'online' THEN COALESCE(c.cena_celk, 0) ELSE 0 END), 2) AS web,
            ROUND(SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod = 'generic' AND COALESCE(d.nazev, '') = 'delivery' AND COALESCE(p.nazev, '') = 'cash' THEN COALESCE(c.cena_celk, 0) ELSE 0 END), 2) AS wolt_cash,
            ROUND(SUM(CASE WHEN COALESCE(s.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted') AND cp.kod IN ('foodora', 'damejidlo') AND COALESCE(p.nazev, '') = 'cash' THEN COALESCE(c.cena_celk, 0) ELSE 0 END), 2) AS dj_cash
        FROM objednavky_restia o
        LEFT JOIN cis_obj_platforma cp ON cp.id_platforma = o.id_platforma
        LEFT JOIN cis_doruceni d ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby p ON p.id_platba = o.id_platba
        LEFT JOIN cis_obj_stav s ON s.id_stav = o.id_stav
        LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
        WHERE o.restia_created_at IS NOT NULL
          AND o.restia_created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY DATE(o.restia_created_at), o.id_pob
        HAVING ABS(
            (
                COALESCE(wolt, 0) + COALESCE(bolt, 0) + COALESCE(dj, 0) +
                COALESCE(web, 0) + COALESCE(wolt_cash, 0) + COALESCE(dj_cash, 0)
            ) - COALESCE(trzba, 0)
        ) > 0.01
        ORDER BY datum DESC, id_pob DESC
        LIMIT 40
    ",
];

foreach ($queries as $label => $sql) {
    echo '--- ' . $label . PHP_EOL;
    $result = $conn->query($sql);
    if (!$result) {
        fwrite(STDERR, $conn->error . PHP_EOL);
        exit(1);
    }
    while ($row = $result->fetch_assoc()) {
        if ($label === 'live_sum_vs_trzba_60d') {
            $sum = (float)($row['wolt'] ?? 0)
                + (float)($row['bolt'] ?? 0)
                + (float)($row['dj'] ?? 0)
                + (float)($row['web'] ?? 0)
                + (float)($row['wolt_cash'] ?? 0)
                + (float)($row['dj_cash'] ?? 0);
            $row['sum_rows'] = round($sum, 2);
            $row['diff'] = round($sum - (float)($row['trzba'] ?? 0), 2);
        }
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    $result->free();
}
