<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';

$tables = [
    'hr_sazby',
    'hr_mzdy_mesic',
    'hr_user_pobocka',
];

foreach ($tables as $table) {
    $safe = db()->real_escape_string($table);
    $res = db()->query("SHOW TABLES LIKE '{$safe}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    echo $table . "\t" . ($exists ? 'EXISTS' : 'MISSING') . PHP_EOL;
}
