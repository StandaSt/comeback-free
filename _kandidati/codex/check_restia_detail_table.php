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

$res = $conn->query("SHOW TABLES LIKE '%restia%detail%'");
if (!($res instanceof mysqli_result)) {
    fwrite(STDERR, $conn->error . "\n");
    exit(1);
}

while ($row = $res->fetch_array(MYSQLI_NUM)) {
    echo (string)$row[0] . "\n";
}

