<?php
require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'];
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . PHP_EOL);
    exit(1);
}

$res = $conn->query("SHOW COLUMNS FROM res_polozky LIKE 'id_res_polozka'");
if (!$res) {
    fwrite(STDERR, $conn->error . PHP_EOL);
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
