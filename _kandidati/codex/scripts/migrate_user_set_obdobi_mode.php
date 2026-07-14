<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';

$conn = db();
$conn->set_charset('utf8mb4');

$res = $conn->query("SHOW COLUMNS FROM user_set LIKE 'obdobi_mode'");
if (!($res instanceof mysqli_result)) {
    fwrite(STDERR, 'Nelze overit sloupec obdobi_mode: ' . $conn->error . PHP_EOL);
    exit(1);
}

$exists = ($res->num_rows > 0);
$res->free();

if (!$exists) {
    $sql = "ALTER TABLE user_set ADD COLUMN obdobi_mode VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER obdobi_do";
    if (!$conn->query($sql)) {
        fwrite(STDERR, 'Nelze pridat sloupec obdobi_mode: ' . $conn->error . PHP_EOL);
        exit(1);
    }
    echo "ADDED obdobi_mode\n";
} else {
    echo "EXISTS obdobi_mode\n";
}

$allowed = ["'vcera'", "'tyden'", "'mesic'", "'rok'", "'manual'"];
$sqlNormalize = 'UPDATE user_set SET obdobi_mode = "manual" WHERE obdobi_mode IS NULL OR obdobi_mode NOT IN (' . implode(',', $allowed) . ')';
if (!$conn->query($sqlNormalize)) {
    fwrite(STDERR, 'Nelze normalizovat obdobi_mode: ' . $conn->error . PHP_EOL);
    exit(1);
}

echo 'NORMALIZED affected=' . (string)$conn->affected_rows . "\n";

$res = $conn->query("SHOW COLUMNS FROM user_set LIKE 'obdobi_mode'");
if ($res instanceof mysqli_result && ($row = $res->fetch_assoc())) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $res->free();
}
