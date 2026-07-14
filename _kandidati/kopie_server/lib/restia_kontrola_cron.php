<?php
// lib/restia_kontrola_cron.php
declare(strict_types=1);

require_once __DIR__ . '/restia_kontrola_mesice.php';

if (!function_exists('cb_restia_kontrola_cli_options')) {
    function cb_restia_kontrola_cli_options(): array
    {
        if (PHP_SAPI !== 'cli') {
            return ['force_all' => false];
        }

        $options = getopt('', ['force-all']);

        return [
            'force_all' => isset($options['force-all']),
        ];
    }
}

if (!function_exists('cb_restia_kontrola_cron_run')) {
    function cb_restia_kontrola_cron_run(?bool $forceAll = null): array
    {
        $opts = cb_restia_kontrola_cli_options();
        $runForceAll = ($forceAll !== null) ? $forceAll : (bool)($opts['force_all'] ?? false);

        return cb_restia_kontrola_run($runForceAll);
    }
}

$result = cb_restia_kontrola_cron_run();

if (PHP_SAPI === 'cli') {
    $summary = (array)($result['summary'] ?? []);
    echo 'restia_kontrola_cron', PHP_EOL;
    echo 'branches=', (string)($summary['branches'] ?? 0), PHP_EOL;
    echo 'months_checked=', (string)($summary['months_checked'] ?? 0), PHP_EOL;
    echo 'months_skipped=', (string)($summary['months_skipped'] ?? 0), PHP_EOL;
    echo 'months_repaired=', (string)($summary['months_repaired'] ?? 0), PHP_EOL;
    echo 'months_manual=', (string)($summary['months_manual'] ?? 0), PHP_EOL;
    echo 'months_error=', (string)($summary['months_error'] ?? 0), PHP_EOL;
}

return $result;
