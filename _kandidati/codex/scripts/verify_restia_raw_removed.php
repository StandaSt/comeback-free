<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "Chybi DB konfigurace.\n");
    exit(1);
}

$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . "\n");
    exit(1);
}

$conn->set_charset('utf8mb4');

$checks = [
    ['objednavky_restia', 'raw_hash'],
    ['objednavky_restia', 'raw_json'],
    ['obj_adresa', 'raw_json'],
    ['obj_kuryr', 'raw_json'],
    ['obj_sluzba', 'raw_json'],
    ['obj_polozky', 'raw_json'],
    ['obj_polozka_mod', 'raw_json'],
    ['res_polozky', 'raw_json'],
    ['res_cena', 'raw_json'],
];

foreach ($checks as [$table, $column]) {
    $stmt = $conn->prepare(
        'SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('Prepare selhal pro ' . $table . '.' . $column);
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();
    echo $table . '.' . $column . '=' . ($exists ? 'YES' : 'NO') . PHP_EOL;
}

$res = $conn->query("SHOW TABLES LIKE 'obj_raw'");
if (!$res) {
    fwrite(STDERR, $conn->error . "\n");
    exit(1);
}
echo 'obj_raw=' . ($res->num_rows > 0 ? 'YES' : 'NO') . PHP_EOL;
