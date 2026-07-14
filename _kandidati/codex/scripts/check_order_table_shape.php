<?php
require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'];
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . PHP_EOL);
    exit(1);
}
$conn->set_charset('utf8mb4');

foreach (['objednavky_restia', 'obj_polozky'] as $table) {
    echo "=== {$table} ===\n";
    $res = $conn->query("SHOW COLUMNS FROM `{$table}`");
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Null'] . ' | ' . $row['Key'] . ' | ' . $row['Extra'] . "\n";
    }
    $res->free();

    echo "--- INDEXES ---\n";
    $res = $conn->query("SHOW INDEX FROM `{$table}`");
    while ($row = $res->fetch_assoc()) {
        echo $row['Key_name'] . ' | ' . $row['Column_name'] . ' | ' . $row['Seq_in_index'] . "\n";
    }
    $res->free();
}
