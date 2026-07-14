<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';

$conn = db();
$sql = "
    SELECT
        COUNT(*) AS checked_rows,
        SUM(CASE WHEN x.sql_count <> x.db_details THEN 1 ELSE 0 END) AS mismatches
    FROM (
        SELECT
            p.id,
            p.sql_count,
            COUNT(d.id_user_akce_db) AS db_details
        FROM user_akce_db p
        LEFT JOIN user_akce_db_detail d
            ON d.id_user_akce_db = p.id
            AND d.typ = 'db'
        WHERE p.id >= 1480
        GROUP BY p.id
    ) x
";

$res = $conn->query($sql);
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    $res->free();
}
