<?php
// lib/session_boot.php * Verze: V1 * Aktualizace: 08.06.2026

declare(strict_types=1);

/*
 * Centrální start session.
 * - nastaví cookie parametry před session_start()
 * - spustí session pouze pokud ještě neběží
 * - na CLI session nespouští, jen připraví prázdné $_SESSION
 */

if (PHP_SAPI === 'cli') {
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
    return;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

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

if ($https) {
    ini_set('session.cookie_secure', '1');
} else {
    ini_set('session.cookie_secure', '0');
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
