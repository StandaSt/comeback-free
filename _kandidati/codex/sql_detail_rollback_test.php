<?php
declare(strict_types=1);

$sessionDir = __DIR__ . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0775, true);
}
session_save_path($sessionDir);
session_start();

$_SESSION['cb_user'] = [
    'id_user' => 1,
    'name' => 'Codex',
    'surname' => 'Test',
];
$_SESSION['cb_system'] = [
    'restia_online' => 0,
    'on_2fa' => 1,
    'system_logout' => 20,
    'pauza_obdobi' => 1000,
    'log_akce' => 0,
    'log_1' => 1,
    'log_2' => 0,
    'log_3' => 0,
    'log_4' => 0,
];

$_SERVER['REQUEST_URI'] = '/sql-detail-rollback-test';

require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';
require __DIR__ . '/../../lib/system.php';

$conn = db();
$conn->begin_transaction();

for ($i = 1; $i <= 37; $i++) {
    $res = $conn->query('SELECT ' . $i . ' AS n');
    if ($res instanceof mysqli_result) {
        $res->free();
    }
}

register_shutdown_function(static function () use ($conn): void {
    $sql = "
        SELECT
            p.id AS id_user_akce_db,
            p.sql_count,
            ROUND(p.sql_total_ms, 3) AS sql_total_ms,
            COUNT(d.id_user_akce_db) AS db_details
        FROM user_akce_db p
        LEFT JOIN user_akce_db_detail d
            ON d.id_user_akce_db = p.id
            AND d.typ = 'db'
        WHERE p.request_uri = '/sql-detail-rollback-test'
        GROUP BY p.id
        ORDER BY p.id DESC
        LIMIT 1
    ";

    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
        $res->free();
    } else {
        echo 'ERR: ' . $conn->error . PHP_EOL;
    }

    $conn->rollback();
});
