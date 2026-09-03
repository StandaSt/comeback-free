<?php
// lib/session_boot.php * Verze: V1 * Aktualizace: 08.06.2026

declare(strict_types=1);

/*
 * Centrální start session.
 * - nastaví cookie parametry před session_start()
 * - spustí session pouze pokud ještě neběží
 * - na CLI session nespouští, jen připraví prázdné $_SESSION
 */

function cb_session_je_lokalni_host(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;

    return $host === 'localhost' || $host === '127.0.0.1';
}

function cb_session_je_asynchronni_pozadavek(): bool
{
    foreach (array_keys($_SERVER) as $serverKey) {
        if (str_starts_with((string)$serverKey, 'HTTP_X_COMEBACK_')) {
            return true;
        }
    }

    $fetchDest = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')));
    if ($fetchDest !== '' && $fetchDest !== 'document') {
        return true;
    }

    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        || str_contains($accept, 'application/json');
}

function cb_session_je_dotaznik(): bool
{
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($requestPath)) {
        return false;
    }

    return preg_match('~(?:^|/)dotaznik(?:/|$)~', $requestPath) === 1;
}

function cb_session_ukonci_pro_udrzbu(): void
{
    $_SESSION = [];

    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => (string)($cookie['path'] ?? '/'),
        'domain' => (string)($cookie['domain'] ?? ''),
        'secure' => !empty($cookie['secure']),
        'httponly' => !empty($cookie['httponly']),
        'samesite' => (string)($cookie['samesite'] ?? 'Lax'),
    ]);
    session_destroy();
}

function cb_session_kontrola_udrzby(): void
{
    if (cb_session_je_lokalni_host() || !is_file(__DIR__ . '/../udrzba_on.php')) {
        return;
    }

    cb_session_ukonci_pro_udrzbu();
    http_response_code(503);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Retry-After: 300');

    if (cb_session_je_asynchronni_pozadavek()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Comeback-Maintenance: 1');
        echo json_encode(['ok' => false, 'maintenance' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cbUdrzbaJeDotaznik = cb_session_je_dotaznik();
    $cbUdrzbaImageUrl = '/common/img/udrzba.png';
    require __DIR__ . '/../udrzba_on.php';
    exit;
}

if (PHP_SAPI === 'cli') {
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
    return;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = false;

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $https = true;
    }

    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto === 'https') {
        $https = true;
    }

    $forwardedSsl = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    if ($forwardedSsl === 'on') {
        $https = true;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string)(12 * 60 * 60));

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $cookieDomain = '';
    if ($host === 'comebacks.cz' || str_ends_with($host, '.comebacks.cz')) {
        $cookieDomain = '.comebacks.cz';
    }

    if ($https) {
        ini_set('session.cookie_secure', '1');
    } else {
        ini_set('session.cookie_secure', '0');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

cb_session_kontrola_udrzby();
