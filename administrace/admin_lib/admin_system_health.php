<?php
declare(strict_types=1);

/*
 * Srozumitelny read-only dohled PHP, weboveho serveru a DB.
 * Ze souborovych logu vraci pouze pocty a casy, nikdy jejich surovy obsah.
 */

/** Vrati posledni cast souboru bez nacitani celeho velkeho logu do pameti. */
function cb_admin_dohled_tail(string $path, int $maxBytes = 524288): string
{
    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }
    try {
        $size = @filesize($path);
        if (is_int($size) && $size > $maxBytes) {
            @fseek($handle, -$maxBytes, SEEK_END);
            fgets($handle);
        }
        $content = stream_get_contents($handle);
        return is_string($content) ? $content : '';
    } finally {
        fclose($handle);
    }
}

/** Pokusi se ziskat cas ze standardniho zacatku radku PHP nebo Apache logu. */
function cb_admin_dohled_cas_radku(string $line): ?int
{
    if (preg_match('/^\[([^\]]+)\]/', $line, $match) !== 1) {
        return null;
    }
    $value = preg_replace('/\.\d{3,6}(?=\s|$)/', '', trim((string)$match[1]));
    $timestamp = is_string($value) ? strtotime($value) : false;
    return $timestamp === false ? null : $timestamp;
}

/**
 * Zhodnoti jeden log za poslednich 24 hodin.
 * Radky bez casu se nezapocitaji, aby stara vice-radkova chyba nezkreslila stav.
 */
function cb_admin_dohled_log(string $label, array $candidates): array
{
    $path = '';
    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '' && is_file($candidate)) {
            $path = $candidate;
            break;
        }
    }

    if ($path === '') {
        return [
            'label' => $label,
            'status' => 'unknown',
            'message' => 'Soubor logu nebyl nalezen nebo zatím nevznikl.',
            'path' => trim((string)($candidates[0] ?? '')),
            'errors' => 0,
            'warnings' => 0,
            'modified_at' => null,
        ];
    }
    if (!is_readable($path)) {
        return [
            'label' => $label,
            'status' => 'unknown',
            'message' => 'Log existuje, ale aplikace jej nemůže bezpečně přečíst.',
            'path' => $path,
            'errors' => 0,
            'warnings' => 0,
            'modified_at' => null,
        ];
    }

    $threshold = time() - 86400;
    $errors = 0;
    $warnings = 0;
    foreach (preg_split('/\R/', cb_admin_dohled_tail($path)) ?: [] as $line) {
        $timestamp = cb_admin_dohled_cas_radku((string)$line);
        if ($timestamp === null || $timestamp < $threshold) {
            continue;
        }
        $lower = strtolower((string)$line);
        if (preg_match('/fatal|uncaught|parse error|\[emerg\]|\[alert\]|\[crit\]|\[error\]/', $lower) === 1) {
            $errors++;
        } elseif (preg_match('/warning|notice|deprecated|\[warn\]/', $lower) === 1) {
            $warnings++;
        }
    }

    $modified = @filemtime($path);
    $status = $errors > 0 ? 'error' : ($warnings > 0 ? 'warning' : 'ok');
    $message = $errors > 0
        ? 'Za posledních 24 hodin byly nalezeny závažné záznamy.'
        : ($warnings > 0
            ? 'Za posledních 24 hodin byla nalezena upozornění.'
            : 'Za posledních 24 hodin nebyla nalezena chyba.');

    return [
        'label' => $label,
        'status' => $status,
        'message' => $message,
        'path' => $path,
        'errors' => $errors,
        'warnings' => $warnings,
        'modified_at' => is_int($modified) ? date('Y-m-d H:i:s', $modified) : null,
    ];
}

/** Sestavi souhrnny stav sluzeb a technickych logu pro jednu administracni kartu. */
function cb_admin_system_health(mysqli $db): array
{
    $dbOk = false;
    try {
        $result = $db->query('SELECT 1 AS ok');
        $dbOk = $result instanceof mysqli_result && (int)($result->fetch_assoc()['ok'] ?? 0) === 1;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } catch (Throwable $error) {
        $dbOk = false;
    }

    $phpLog = trim((string)ini_get('error_log'));
    $apacheCandidates = array_values(array_unique(array_filter([
        dirname(PHP_BINARY) . '/../apache/logs/error.log',
        'C:/xampp/apache/logs/error.log',
        '/var/log/apache2/error.log',
        '/var/log/httpd/error_log',
    ], static fn(string $path): bool => $path !== '')));

    $logs = [
        cb_admin_dohled_log('PHP', $phpLog === '' || strtolower($phpLog) === 'syslog' ? [] : [$phpLog]),
        cb_admin_dohled_log('Apache', $apacheCandidates),
    ];
    $hasError = !$dbOk;
    $hasWarning = false;
    foreach ($logs as $log) {
        $hasError = $hasError || $log['status'] === 'error';
        $hasWarning = $hasWarning || in_array($log['status'], ['warning', 'unknown'], true);
    }

    return [
        'status' => $hasError ? 'error' : ($hasWarning ? 'warning' : 'ok'),
        'services' => [
            [
                'label' => 'Webový server',
                'status' => 'ok',
                'message' => 'Tento administrativní požadavek byl obsloužen.',
                'detail' => (string)($_SERVER['SERVER_SOFTWARE'] ?? 'Webový server'),
            ],
            [
                'label' => 'PHP',
                'status' => 'ok',
                'message' => 'PHP zpracovalo tuto stránku.',
                'detail' => 'PHP ' . PHP_VERSION,
            ],
            [
                'label' => 'Databáze',
                'status' => $dbOk ? 'ok' : 'error',
                'message' => $dbOk ? 'Databáze odpovídá.' : 'Databáze při kontrole neodpověděla.',
                'detail' => '',
            ],
        ],
        'logs' => $logs,
    ];
}

