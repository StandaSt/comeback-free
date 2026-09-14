<?php
// lib/cron_restia_katalog.php * Noční synchronizace katalogu Restia
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SESSION = [];
$GLOBALS['cb_restia_online_session_ready'] = true;

require_once __DIR__ . '/../../common/lib/app.php';
$PROSTREDI = 'SERVER';
require_once __DIR__ . '/restia_katalog.php';

try {
    $results = cb_restia_katalog_sync_all();
    echo cb_restia_katalog_summary($results), PHP_EOL;
    exit(cb_restia_katalog_has_errors($results) ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Synchronizace katalogu Restia selhala: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
