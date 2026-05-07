<?php
// lib/app.php * Verze: V7 * Aktualizace: 06.05.2026
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
        // DOCASNE MERENI CASU KARET
        $GLOBALS['cb_tmp_db_conn'] = $conn;

        return $conn;
    }
}

require_once __DIR__ . '/mereni_vykonu.php';
require_once __DIR__ . '/db_prehledy.php';
require_once __DIR__ . '/sesn_kontrola.php';
require_once __DIR__ . '/sesn_regenerate.php';

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
$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
$baseFromUrl = '';
if ($scriptName !== '') {
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = ($dir === '/' ? '' : rtrim($dir, '/'));

    if ($dir !== '') {
        $parts = explode('/', trim($dir, '/'));
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
    $DOCROOT_FS = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';

    if ($PROJECT_ROOT_FS !== '' && $DOCROOT_FS !== '') {
        $pr = str_replace('\\', '/', $PROJECT_ROOT_FS);
        $dr = str_replace('\\', '/', $DOCROOT_FS);

        if (str_starts_with($pr, $dr)) {
            $suffix = substr($pr, strlen($dr));
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
    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $forwardedSsl = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        $forwardedProto === 'https' ||
        $forwardedSsl === 'on';

    $scheme = $isHttps ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');

    return $scheme . '://' . $host . cb_url($path);
}

if (!function_exists('cb_clean_query_array')) {
    /**
     * Odstrani prazdne/default query hodnoty (rekurzivne i pro pole).
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    function cb_clean_query_array(array $params, array $defaults = []): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $cleanNested = cb_clean_query_array($value, []);
                if ($cleanNested !== []) {
                    $out[(string)$key] = $cleanNested;
                }
                continue;
            }

            if ($value === null) {
                continue;
            }

            $v = trim((string)$value);
            if ($v === '') {
                continue;
            }

            if (array_key_exists((string)$key, $defaults)) {
                $d = trim((string)$defaults[(string)$key]);
                if ($v === $d) {
                    continue;
                }
            }

            $out[(string)$key] = $v;
        }

        return $out;
    }
}

if (!function_exists('cb_url_query')) {
    /**
     * Sestavi URL s kanonickou query (bez prazdnych/default hodnot).
     *
     * @param array<string,mixed> $params
     * @param array<string,mixed> $defaults
     */
    function cb_url_query(string $path, array $params, array $defaults = []): string
    {
        $base = cb_url($path);
        $clean = cb_clean_query_array($params, $defaults);
        if ($clean === []) {
            return $base;
        }
        return $base . '?' . http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }
}

/**
 * Jedine misto pro technicke informace do hlavicky.
 */
function cb_header_info(): array
{
    $httpHost = (string)($_SERVER['HTTP_HOST'] ?? '---');
    $serverName = (string)(php_uname('n') ?: '---');
    $phpVersion = (string)PHP_VERSION;
    $aktualizace = date('j.n.Y H:i');

    $dbInfo = '---';
    if (isset($GLOBALS['SECRETS']) && is_array($GLOBALS['SECRETS'])) {
        $SECRETS = $GLOBALS['SECRETS'];
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
        'server' => $serverName,
        'db' => $dbInfo,
        'host' => $httpHost,
        'aktualizace' => $aktualizace,
        'php' => $phpVersion,
    ];
}

/* lib/app.php * Verze: V7 * Aktualizace: 06.05.2026 */
// Konec souboru
