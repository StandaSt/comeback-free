<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';

$res = db()->query('DESCRIBE `user`');
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        echo (string)$row['Field'] . "\t" . (string)$row['Type'] . "\t" . (string)$row['Null'] . "\t" . (string)$row['Default'] . PHP_EOL;
    }
    $res->free();
}
