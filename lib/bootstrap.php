<?php
// lib/bootstrap.php V4 – počet řádků: 55 – aktuální čas v ČR: 18.1.2026 12:35
declare(strict_types=1);

/*
 * Startovací (spouštěcí) soubor projektu
 * - spustí session
 * - načte prostředí (app.php)
 * - načte secrets.php (přístupy)
 * - zpřístupní helpery
 * - zpřístupní DB přes db() (jediné místo)
 * - NIC jiného sem nepatří
 */

if (defined('COMEBACK_BOOTSTRAP_LOADED')) {
    return;
}
define('COMEBACK_BOOTSTRAP_LOADED', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/../config/secrets.php';

/**
 * Bezpečný HTML výstup
 */
if (!function_exists('h')) {
    function h(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * DB připojení – lazy (vytvoří se až při prvním použití)
 * Pozn.: starší kód v projektu volá db(), proto ho držíme.
 */
if (!function_exists('db')) {
    function db(): mysqli
    {
        static $conn = null;
        if ($conn instanceof mysqli) {
            return $conn;
        }

        require_once __DIR__ . '/db_connect.php';
        $conn = db_connect();
        return $conn;
    }
}

/* lib/bootstrap.php V4 – počet řádků: 55 – aktuální čas v ČR: 18.1.2026 12:35 */
