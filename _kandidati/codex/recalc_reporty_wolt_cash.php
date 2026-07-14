<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'];
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . PHP_EOL);
    exit(1);
}

$previewSql = "
    SELECT
        r.id_reportu,
        r.datum_reportu,
        r.id_pob,
        ROUND(COALESCE(ri.wolt_cash, 0), 2) AS old_wolt_cash,
        ROUND(COALESCE(src.live_wolt_cash, 0), 2) AS new_wolt_cash
    FROM reporty_is_restia ri
    INNER JOIN reporty_is r
        ON r.id_reportu = ri.id_reportu
    LEFT JOIN (
        SELECT
            o.id_pob,
            DATE(o.restia_created_at) AS datum_reportu,
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
        LEFT JOIN cis_obj_platforma cp
            ON cp.id_platforma = o.id_platforma
        LEFT JOIN cis_doruceni d
            ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby p
            ON p.id_platba = o.id_platba
        LEFT JOIN cis_obj_stav s
            ON s.id_stav = o.id_stav
        LEFT JOIN obj_ceny c
            ON c.id_obj = o.id_obj
        WHERE o.restia_created_at IS NOT NULL
        GROUP BY o.id_pob, DATE(o.restia_created_at)
    ) src
        ON src.id_pob = r.id_pob
       AND src.datum_reportu = r.datum_reportu
    WHERE ABS(COALESCE(ri.wolt_cash, 0) - COALESCE(src.live_wolt_cash, 0)) > 0.009
    ORDER BY r.datum_reportu DESC, r.id_pob DESC, r.id_reportu DESC
";

$updateSql = "
    UPDATE reporty_is_restia ri
    INNER JOIN reporty_is r
        ON r.id_reportu = ri.id_reportu
    LEFT JOIN (
        SELECT
            o.id_pob,
            DATE(o.restia_created_at) AS datum_reportu,
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
        LEFT JOIN cis_obj_platforma cp
            ON cp.id_platforma = o.id_platforma
        LEFT JOIN cis_doruceni d
            ON d.id_doruceni = o.id_doruceni
        LEFT JOIN cis_obj_platby p
            ON p.id_platba = o.id_platba
        LEFT JOIN cis_obj_stav s
            ON s.id_stav = o.id_stav
        LEFT JOIN obj_ceny c
            ON c.id_obj = o.id_obj
        WHERE o.restia_created_at IS NOT NULL
        GROUP BY o.id_pob, DATE(o.restia_created_at)
    ) src
        ON src.id_pob = r.id_pob
       AND src.datum_reportu = r.datum_reportu
    SET ri.wolt_cash = COALESCE(src.live_wolt_cash, 0)
    WHERE ABS(COALESCE(ri.wolt_cash, 0) - COALESCE(src.live_wolt_cash, 0)) > 0.009
";

$previewRows = [];
$previewResult = $conn->query($previewSql);
if (!$previewResult) {
    fwrite(STDERR, $conn->error . PHP_EOL);
    exit(1);
}
while ($row = $previewResult->fetch_assoc()) {
    $previewRows[] = $row;
}
$previewResult->free();

echo 'affected_rows_preview=' . count($previewRows) . PHP_EOL;
foreach (array_slice($previewRows, 0, 20) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

if ($previewRows === []) {
    echo "no_changes\n";
    exit(0);
}

if (!$conn->begin_transaction()) {
    fwrite(STDERR, "Nelze zahajit transakci.\n");
    exit(1);
}

try {
    if ($conn->query($updateSql) === false) {
        throw new RuntimeException($conn->error);
    }
    $updatedRows = $conn->affected_rows;
    $conn->commit();
    echo 'updated_rows=' . $updatedRows . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
