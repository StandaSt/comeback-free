<?php
// lib/db_connect.php V4 – počet řádků: 59 – aktuální čas v ČR: 18.1.2026 12:35
declare(strict_types=1);

/*
 * DB připojení – JEDINÉ MÍSTO
 *
 * - používá app.php (prostředí) + config/secrets.php
 * - rozlišuje LOCAL / SERVER podle $PROSTREDI
 * - vrací mysqli připojení (funkce db_connect())
 * - žádná autodetekce, žádná magie
 * - žádné echo / exit (chyby řeší volající)
 */

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/../config/secrets.php';

if (!function_exists('db_connect')) {
    function db_connect(): mysqli
    {
        static $conn = null;

        if ($conn instanceof mysqli) {
            return $conn;
        }

        global $PROSTREDI, $SECRETS;

        if (!isset($SECRETS['db']) || !is_array($SECRETS['db'])) {
            throw new RuntimeException('Chybí konfigurace DB v secrets.php');
        }

        $cfg = ($PROSTREDI === 'LOCAL')
            ? ($SECRETS['db']['local'] ?? null)
            : ($SECRETS['db']['server'] ?? null);

        if (!is_array($cfg)) {
            throw new RuntimeException('Neplatná konfigurace DB pro prostředí: ' . $PROSTREDI);
        }

        $host = (string)($cfg['host'] ?? '');
        $user = (string)($cfg['user'] ?? '');
        $pass = (string)($cfg['pass'] ?? '');
        $name = (string)($cfg['name'] ?? '');
  

        if ($host === '' || $user === '' || $name === '') {
            throw new RuntimeException('Neúplné DB přihlašovací údaje');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $conn = new mysqli($host, $user, $pass, $name);
        $conn->set_charset('utf8mb4');

        return $conn;
    }
}

/* lib/db_connect.php V4 – počet řádků: 59 – aktuální čas v ČR: 18.1.2026 12:35 */
