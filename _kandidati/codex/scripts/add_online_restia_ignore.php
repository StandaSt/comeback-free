<?php
declare(strict_types=1);

require __DIR__ . '/../../../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "Chybi local DB konfigurace.\n");
    exit(1);
}

$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);

if ($conn->connect_error) {
    fwrite(STDERR, "DB connect error: " . $conn->connect_error . "\n");
    exit(1);
}

$checkSql = "
    SELECT COUNT(*) AS cnt
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'online_restia'
      AND COLUMN_NAME = 'ignore'
";
$checkRes = $conn->query($checkSql);
if (!$checkRes) {
    fwrite(STDERR, "Check query error: " . $conn->error . "\n");
    exit(1);
}
$checkRow = $checkRes->fetch_assoc();
$checkRes->free();

if ((int)($checkRow['cnt'] ?? 0) > 0) {
    echo "IGNORE_COLUMN_ALREADY_EXISTS\n";
    exit(0);
}

$alterSql = "ALTER TABLE online_restia ADD COLUMN `ignore` INT(11) NOT NULL DEFAULT 0 AFTER aktualizace";
if ($conn->query($alterSql) === false) {
    fwrite(STDERR, "ALTER error: " . $conn->error . "\n");
    exit(1);
}

echo "OK\n";
