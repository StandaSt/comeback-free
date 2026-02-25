<?php
// lib/bootstrap.php * Verze: V6 * Aktualizace: 24.2.2026

/*
 * Startovací (spouštěcí) soubor projektu
 * - spustí session
 * - načte prostředí (app.php)
 * - načte system.php (nastavení)
 * - načte secrets.php (přístupy)
 * - zpřístupní helpery
 * - zpřístupní DB přes db() (jediné místo)
 * - NIC jiného sem nepatří
 */

declare(strict_types=1);

if (defined('COMEBACK_BOOTSTRAP_LOADED')) {
    return;
}
define('COMEBACK_BOOTSTRAP_LOADED', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/system.php';
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

        require_once __DIR__ . '/../db/db_connect.php';
        $conn = db_connect();

        return $conn;
    }
}

/* lib/bootstrap.php * Verze: V6 * Aktualizace: 24.2.2026 * Počet řádků: 61 */
// Konec souboru