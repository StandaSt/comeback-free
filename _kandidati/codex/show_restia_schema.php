<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "Missing local DB config.\n");
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
    'obj_casy',
    'obj_ceny',
    'obj_adresa',
    'obj_polozky',
    'obj_polozka_mod',
    'obj_polozka_kds_tag',
    'obj_kuryr',
    'obj_sluzba',
    'zakaznik',
    'obj_import',
    'kontrolni_prehledy',
];

foreach ($tables as $table) {
    $safe = $conn->real_escape_string($table);
    $res = $conn->query('SHOW CREATE TABLE `' . $safe . '`');
    if (!($res instanceof mysqli_result)) {
        echo "--- {$table} MISSING/ERR: {$conn->error}\n";
        continue;
    }

    $row = $res->fetch_assoc();
    $res->free();
    echo "--- {$table}\n";
    echo (string)($row['Create Table'] ?? '') . "\n";
}
