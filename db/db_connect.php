<?php
// db/db_connect.php * V4 * aktualizace: 18.1.2026
declare(strict_types=1);

/*
 * DB připojení – JEDINÉ MÍSTO
 *
 * - používá lib/app.php (prostředí) + config/secrets.php
 * - rozlišuje LOCAL / SERVER podle $PROSTREDI
 * - vrací mysqli připojení (funkce db_connect())
 * - žádná autodetekce, žádná magie
 * - žádné echo / exit (chyby řeší volající)
 */

require_once __DIR__ . '/../lib/app.php';
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

        if ($PROSTREDI === 'LOCAL') {
            $cfg = $SECRETS['db']['local'] ?? null;
        } else {
            $cfg = $SECRETS['db']['server'] ?? null;
        }

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

/* db/db_connect.php V4 * počet řádků: 62 * aktualizace: 18.1.2026 */
// Konec souboru