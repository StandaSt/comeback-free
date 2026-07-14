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

$res = $conn->query('SELECT id_zak, jmeno, prijmeni, telefon, email, id_pob, aktivni FROM zakaznik WHERE id_zak = 0 LIMIT 1');
if (!($res instanceof mysqli_result)) {
    fwrite(STDERR, $conn->error . "\n");
    exit(1);
}

$row = $res->fetch_assoc();
$res->free();

if (!is_array($row)) {
    echo "MISSING\n";
    exit(0);
}

echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
