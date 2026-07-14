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

$tableColumns = [
    'objednavky_restia' => ['raw_hash', 'raw_json'],
    'obj_adresa' => ['raw_json'],
    'obj_kuryr' => ['raw_json'],
    'obj_sluzba' => ['raw_json'],
    'obj_polozky' => ['raw_json'],
    'obj_polozka_mod' => ['raw_json'],
    'res_polozky' => ['raw_json'],
    'res_cena' => ['raw_json'],
];

foreach ($tableColumns as $table => $columns) {
    foreach ($columns as $column) {
        $stmt = $conn->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal pro kontrolu sloupce ' . $table . '.' . $column);
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        if (!$exists) {
            echo $table . '.' . $column . ': preskoceno (neexistuje)' . PHP_EOL;
            continue;
        }

        $sql = 'ALTER TABLE `' . $table . '` DROP COLUMN `' . $column . '`';
        if (!$conn->query($sql)) {
            throw new RuntimeException('ALTER TABLE selhal pro ' . $table . '.' . $column . ': ' . $conn->error);
        }
        echo $table . '.' . $column . ': odstraneno' . PHP_EOL;
    }
}

$stmt = $conn->prepare(
    'SELECT 1
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = ?
     LIMIT 1'
);
if ($stmt === false) {
    throw new RuntimeException('DB prepare selhal pro kontrolu tabulky obj_raw.');
}
$table = 'obj_raw';
$stmt->bind_param('s', $table);
$stmt->execute();
$res = $stmt->get_result();
$exists = $res instanceof mysqli_result && $res->num_rows > 0;
if ($res instanceof mysqli_result) {
    $res->free();
}
$stmt->close();

if ($exists) {
    if (!$conn->query('DROP TABLE `obj_raw`')) {
        throw new RuntimeException('DROP TABLE selhal pro obj_raw: ' . $conn->error);
    }
    echo 'obj_raw: tabulka odstranená' . PHP_EOL;
} else {
    echo 'obj_raw: preskoceno (tabulka neexistuje)' . PHP_EOL;
}

echo 'Hotovo.' . PHP_EOL;
