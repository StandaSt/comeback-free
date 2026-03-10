<?php
// lib/app.php * Verze: V6 * Aktualizace: 10.03.2026
declare(strict_types=1);

/*
 * lib/app.php
 * - zadna DB logika navic
 * - PROSTREDI (LOCAL / SERVER)
 * - BASE_PATH: urcene na ROOT projektu (kvuli primemu volani /lib/*.php)
 * - cb_url() vraci absolutni URL od rootu webu
 * - cb_header_info(): jedno misto pro technicka data do hlavicky (bez HTML)
 */

date_default_timezone_set('Europe/Prague');
mb_internal_encoding('UTF-8');

if (!function_exists('h')) {
    function h(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

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

// ====== PROSTREDI ======
$HOST = strtolower($_SERVER['HTTP_HOST'] ?? '');
$IS_CLI = (PHP_SAPI === 'cli');
$JE_LOCAL =
    ($HOST === 'localhost') ||
    str_starts_with($HOST, 'localhost:') ||
    ($HOST === '127.0.0.1') ||
    str_starts_with($HOST, '127.0.0.1:');

$PROSTREDI = 'SERVER';
if ($JE_LOCAL || $IS_CLI) {
    $PROSTREDI = 'LOCAL';
}

// ====== BASE_PATH (neprustrelne) ======
// 1) Primarne z URL: root projektu (funguje i kdyz se vola /lib/*.php primo)
$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? ''); // napr. "/comeback/index.php" nebo "/comeback/lib/logout.php"
$baseFromUrl = '';
if ($scriptName !== '') {
    $dir = str_replace('\\', '/', dirname($scriptName)); // "/comeback" nebo "/comeback/lib"
    $dir = ($dir === '/' ? '' : rtrim($dir, '/'));

    // pokud se vola primo skript v /lib, /pages, /includes -> vrat se o uroven vys (root projektu)
    if ($dir !== '') {
        $parts = explode('/', trim($dir, '/')); // ["comeback","lib"]
        $last = end($parts);
        if (in_array($last, ['lib', 'pages', 'includes', 'mobil', 'modaly', 'notifikace'], true)) {
            array_pop($parts);
            $dir = $parts ? '/' . implode('/', $parts) : '';
        }
    }

    $baseFromUrl = $dir;
}

$BASE_PATH = $baseFromUrl;

// 2) Fallback z FS (kdyz by SCRIPT_NAME nebyl pouzitelny)
if ($BASE_PATH === '') {
    $PROJECT_ROOT_FS = realpath(__DIR__ . '/..') ?: '';
    $DOCROOT_FS      = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';

    if ($PROJECT_ROOT_FS !== '' && $DOCROOT_FS !== '') {
        $pr = str_replace('\\', '/', $PROJECT_ROOT_FS);
        $dr = str_replace('\\', '/', $DOCROOT_FS);

        if (str_starts_with($pr, $dr)) {
            $suffix = substr($pr, strlen($dr)); // napr. "/comeback" nebo ""
            $suffix = str_replace('\\', '/', $suffix);
            $suffix = '/' . ltrim($suffix, '/');
            $BASE_PATH = rtrim($suffix, '/');
            if ($BASE_PATH === '/') {
                $BASE_PATH = '';
            }
        }
    }
}

function cb_url(string $path): string
{
    global $BASE_PATH;
    $path = '/' . ltrim($path, '/');
    if ($BASE_PATH !== '') {
        return $BASE_PATH . $path;
    }
    return $path;
}

function cb_url_abs(string $path): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host . cb_url($path);
}

/**
 * Jedine misto pro "technicke" informace do hlavicky.
 *
 * Cil:
 * - hlavicka (includes/hlavicka.php) zustane "hloupa" -> jen vypise hodnoty
 * - tady se pripravi vse potrebne (bez HTML a bez DB dotazu "jen kvuli UI")
 *
 * Pozn.:
 * - secrets.php se nacita pri startu aplikace; proto tu DB udaje cteme jen pokud uz existuji v $SECRETS
 * - nikdy sem nedavame hesla ani uzivatele DB
 */
function cb_header_info(): array
{
    // Host podle HTTP pozadavku (to, co je v URL / hlavicce Host)
    $httpHost = (string)($_SERVER['HTTP_HOST'] ?? '---');

    // "Server" = jmeno stroje, na kterem bezi PHP (nejkonkretnejsi bez dalsich zavislosti)
    $serverName = (string)(php_uname('n') ?: '---');

    // PHP verze (primo z runtime)
    $phpVersion = (string)PHP_VERSION;

    // Aktualni cas (zatim cas generovani stranky; pozdeji lze nahradit casem posledni synchronizace)
    $aktualizace = date('j.n.Y H:i');

    // DB info jen jako "metadata" (host + jmeno DB), bez pripojovani do DB
    $dbInfo = '---';
    if (isset($GLOBALS['SECRETS']) && is_array($GLOBALS['SECRETS'])) {
        $SECRETS = $GLOBALS['SECRETS']; // lokalni kopie kvuli citelnosti
        if (isset($SECRETS['db']) && is_array($SECRETS['db'])) {
            $cfg = null;
            if (isset($GLOBALS['PROSTREDI']) && $GLOBALS['PROSTREDI'] === 'LOCAL') {
                $cfg = $SECRETS['db']['local'] ?? null;
            } else {
                $cfg = $SECRETS['db']['server'] ?? null;
            }

            if (is_array($cfg)) {
                $dbHost = trim((string)($cfg['host'] ?? ''));
                $dbName = trim((string)($cfg['name'] ?? ''));
                if ($dbHost !== '' && $dbName !== '') {
                    $dbInfo = $dbHost . ' / ' . $dbName;
                } elseif ($dbName !== '') {
                    $dbInfo = $dbName;
                } elseif ($dbHost !== '') {
                    $dbInfo = $dbHost;
                }
            }
        }
    }

    return [
        // planovane 4 polozky pro prvni technicky blok
        'server'      => $serverName,
        'db'          => $dbInfo,
        'host'        => $httpHost,
        'aktualizace' => $aktualizace,

        // dalsi uzitecne polozky do dalsich bloku (kdyz budes chtit)
        'php'         => $phpVersion,
    ];
}

/* lib/app.php * Verze: V6 * Aktualizace: 10.03.2026 */
// Konec souboru
