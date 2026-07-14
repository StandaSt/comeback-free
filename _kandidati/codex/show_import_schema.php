<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "Missing local DB config\n");
    exit(1);
}

$conn = new mysqli(
    (string)$cfg['host'],
    (string)$cfg['user'],
    (string)$cfg['pass'],
    (string)$cfg['name']
);

if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . "\n");
    exit(1);
}

$tables = [
    'objednavky_restia',
    'obj_restia_detail',
    'obj_casy',
    'obj_ceny',
    'obj_import',
    'kontrolni_prehledy',
    'zakaznik',
];

foreach ($tables as $table) {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query('SHOW COLUMNS FROM `' . $safe . '`');
    if (!($res instanceof mysqli_result)) {
        echo 'ERR ' . $table . ': ' . $conn->error . "\n";
        continue;
    }

    echo 'TABLE ' . $table . "\n";
    while ($row = $res->fetch_assoc()) {
        echo (string)$row['Field'] . ' | ' . (string)$row['Type'] . ' | ' . (string)$row['Null'] . ' | ' . (string)$row['Key'] . ' | ' . (string)$row['Default'] . "\n";
    }
    $res->free();
    echo "\n";
}

