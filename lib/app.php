<?php
// lib/app.php * Verze: V5 * Aktualizace: 12.2.2026
declare(strict_types=1);

/*
 * lib/app.php
 * - žádná DB
 * - PROSTREDI (LOCAL / SERVER)
 * - BASE_PATH: určené na ROOT projektu (kvůli přímému volání /lib/*.php)
 * - cb_url() vrací absolutní URL od rootu webu
 * - cb_header_info(): jedno místo pro technická data do hlavičky (bez HTML)
 */

date_default_timezone_set('Europe/Prague');
mb_internal_encoding('UTF-8');

// ====== PROSTŘEDÍ ======
$HOST = strtolower($_SERVER['HTTP_HOST'] ?? '');
$JE_LOCAL =
    ($HOST === 'localhost') ||
    str_starts_with($HOST, 'localhost:') ||
    ($HOST === '127.0.0.1') ||
    str_starts_with($HOST, '127.0.0.1:');

$PROSTREDI = 'SERVER';
if ($JE_LOCAL) {
    $PROSTREDI = 'LOCAL';
}

// ====== BASE_PATH (neprůstřelné) ======
// 1) Primárně z URL: root projektu (funguje i když se volá /lib/*.php přímo)
$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? ''); // např. "/comeback/index.php" nebo "/comeback/lib/logout.php"
$baseFromUrl = '';
if ($scriptName !== '') {
    $dir = str_replace('\\', '/', dirname($scriptName)); // "/comeback" nebo "/comeback/lib"
    $dir = ($dir === '/' ? '' : rtrim($dir, '/'));

    // pokud se volá přímo skript v /lib, /pages, /includes -> vrať se o úroveň výš (root projektu)
    if ($dir !== '') {
        $parts = explode('/', trim($dir, '/')); // ["comeback","lib"]
        $last = end($parts);
        if (in_array($last, ['lib', 'pages', 'includes'], true)) {
            array_pop($parts);
            $dir = $parts ? '/' . implode('/', $parts) : '';
        }
    }

    $baseFromUrl = $dir;
}

$BASE_PATH = $baseFromUrl;

// 2) Fallback z FS (když by SCRIPT_NAME nebyl použitelný)
if ($BASE_PATH === '') {
    $PROJECT_ROOT_FS = realpath(__DIR__ . '/..') ?: '';
    $DOCROOT_FS      = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';

    if ($PROJECT_ROOT_FS !== '' && $DOCROOT_FS !== '') {
        $pr = str_replace('\\', '/', $PROJECT_ROOT_FS);
        $dr = str_replace('\\', '/', $DOCROOT_FS);

        if (str_starts_with($pr, $dr)) {
            $suffix = substr($pr, strlen($dr)); // např. "/comeback" nebo ""
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

/**
 * Jediné místo pro "technické" informace do hlavičky.
 *
 * Cíl:
 * - hlavička (includes/hlavicka.php) zůstane "hloupá" → jen vypíše hodnoty
 * - tady se připraví vše potřebné (bez HTML a bez DB dotazů "jen kvůli UI")
 *
 * Pozn.:
 * - secrets.php se načítá v bootstrap.php; proto tu DB údaje čteme jen pokud už existují v $SECRETS
 * - nikdy sem nedáváme hesla ani uživatele DB
 */
function cb_header_info(): array
{
    // Host podle HTTP požadavku (to, co je v URL / hlavičce Host)
    $httpHost = (string)($_SERVER['HTTP_HOST'] ?? '---');

    // "Server" = jméno stroje, na kterém běží PHP (nejkonkrétnější bez dalších závislostí)
    $serverName = (string)(php_uname('n') ?: '---');

    // PHP verze (přímo z runtime)
    $phpVersion = (string)PHP_VERSION;

    // Aktuální čas (zatím čas generování stránky; později lze nahradit časem poslední synchronizace)
    $aktualizace = date('j.n.Y H:i');

    // DB info jen jako "metadata" (host + jméno DB), bez připojování do DB
    $dbInfo = '---';
    if (isset($GLOBALS['SECRETS']) && is_array($GLOBALS['SECRETS'])) {
        $SECRETS = $GLOBALS['SECRETS']; // lokální kopie kvůli čitelnosti
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
        // plánované 4 položky pro první technický blok
        'server'      => $serverName,
        'db'          => $dbInfo,
        'host'        => $httpHost,
        'aktualizace' => $aktualizace,

        // další užitečné položky do dalších bloků (když budeš chtít)
        'php'         => $phpVersion,
    ];
}

/* lib/app.php * Verze: V5 * Aktualizace: 12.2.2026 * Počet řádků: 148 */
// Konec souboru