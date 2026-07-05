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

        return $conn;
    }
}

require_once __DIR__ . '/mereni_vykonu.php';
require_once __DIR__ . '/db_akce_log.php';
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

if (!function_exists('cb_system_settings_defaults')) {
    /**
     * @return array<string, int>
     */
    function cb_system_settings_defaults(): array
    {
        return [
            'restia_online' => 0,
            'on_2fa' => 1,
            'system_logout' => 20,
            'pauza_obdobi' => 1000,
            'zamek' => 0,
            'log_akce' => 0,
            'log_1' => 0,
            'log_2' => 0,
            'log_3' => 0,
            'log_4' => 0,
            'notif_chyby' => 0,
            'notif_bad_login' => 0,
        ];
    }
}

if (!function_exists('cb_store_system_settings')) {
    /**
     * @param array<string, mixed> $values
     */
    function cb_store_system_settings(array $values): void
    {
        $data = cb_system_settings_defaults();

        $data['restia_online'] = ((int)($values['restia_online'] ?? $data['restia_online']) === 1) ? 1 : 0;
        $data['on_2fa'] = ((int)($values['on_2fa'] ?? $data['on_2fa']) === 1) ? 1 : 0;
        $data['zamek'] = ((int)($values['zamek'] ?? $data['zamek']) === 1) ? 1 : 0;

        $logout = (int)($values['system_logout'] ?? $data['system_logout']);
        if (!in_array($logout, [2, 5, 10, 15, 20, 30, 60], true)) {
            $logout = 20;
        }
        $data['system_logout'] = $logout;

        $pauza = (int)($values['pauza_obdobi'] ?? $data['pauza_obdobi']);
        if (!in_array($pauza, [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000], true)) {
            $pauza = 1000;
        }
        $data['pauza_obdobi'] = $pauza;

        $data['log_akce'] = ((int)($values['log_akce'] ?? $data['log_akce']) === 1) ? 1 : 0;

        foreach (['log_1', 'log_2', 'log_3', 'log_4', 'notif_chyby', 'notif_bad_login'] as $logKey) {
            $data[$logKey] = ((int)($values[$logKey] ?? $data[$logKey]) === 1) ? 1 : 0;
        }

        $_SESSION['cb_system'] = $data;
        $userSettings = (isset($_SESSION['cb_user_settings']) && is_array($_SESSION['cb_user_settings']))
            ? $_SESSION['cb_user_settings']
            : [];
        $userLogoutLimit = $userSettings['logout_limit'] ?? null;
        $_SESSION['cb_timeout_min'] = ($userLogoutLimit !== null && in_array((int)$userLogoutLimit, [30, 60, 120, 240, 480], true))
            ? (int)$userLogoutLimit
            : $data['system_logout'];
    }
}

if (!function_exists('cb_system_settings')) {
    /**
     * @return array<string, int>
     */
    function cb_system_settings(): array
    {
        if (!isset($_SESSION['cb_system']) || !is_array($_SESSION['cb_system'])) {
            cb_store_system_settings([]);
        }

        /** @var array<string, int> $settings */
        $settings = $_SESSION['cb_system'];
        return $settings;
    }
}

if (!function_exists('cb_system_setting')) {
    function cb_system_setting(string $key, mixed $default = null): mixed
    {
        $settings = cb_system_settings();
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('cb_user_settings_defaults')) {
    /**
     * @return array<string, mixed>
     */
    function cb_user_settings_defaults(): array
    {
        return [
            'nano_kde' => 0,
            'prodleva' => 3000,
            'pismo' => 2,
            'dark' => 0,
            'logout_limit' => null,
            'obdobi_od' => '',
            'obdobi_do' => '',
            'obdobi_mode' => 'manual',
        ];
    }
}

if (!function_exists('cb_store_user_settings')) {
    /**
     * @param array<string, mixed> $values
     */
    function cb_store_user_settings(array $values): void
    {
        $current = [];
        if (isset($_SESSION['cb_user_settings']) && is_array($_SESSION['cb_user_settings'])) {
            $current = $_SESSION['cb_user_settings'];
        }

        $data = array_merge(cb_user_settings_defaults(), $current);

        $nanoKde = (int)($values['nano_kde'] ?? $data['nano_kde']);
        if (!in_array($nanoKde, [0, 1], true)) {
            $nanoKde = 0;
        }
        $data['nano_kde'] = $nanoKde;

        $prodleva = (int)($values['prodleva'] ?? $data['prodleva']);
        if (!in_array($prodleva, [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000], true)) {
            $prodleva = 3000;
        }
        $data['prodleva'] = $prodleva;

        $pismo = (int)($values['pismo'] ?? $data['pismo']);
        if (!in_array($pismo, [1, 2, 3], true)) {
            $pismo = 2;
        }
        $data['pismo'] = $pismo;

        $dark = (int)($values['dark'] ?? $data['dark']);
        if (!in_array($dark, [0, 1], true)) {
            $dark = 0;
        }
        $data['dark'] = $dark;

        $logoutLimit = $values['logout_limit'] ?? $data['logout_limit'];
        if ($logoutLimit === null || $logoutLimit === '') {
            $data['logout_limit'] = null;
        } else {
            $logoutLimit = (int)$logoutLimit;
            $data['logout_limit'] = in_array($logoutLimit, [30, 60, 120, 240, 480], true) ? $logoutLimit : null;
        }

        $mode = trim((string)($values['obdobi_mode'] ?? $data['obdobi_mode']));
        if ($mode === 'dnes') {
            $mode = 'vcera';
        }
        if (!in_array($mode, ['vcera', 'tyden', 'mesic', 'rok', 'manual'], true)) {
            $mode = 'manual';
        }
        $data['obdobi_mode'] = $mode;

        $data['obdobi_od'] = trim((string)($values['obdobi_od'] ?? $data['obdobi_od']));
        $data['obdobi_do'] = trim((string)($values['obdobi_do'] ?? $data['obdobi_do']));

        $_SESSION['cb_user_settings'] = $data;
        $_SESSION['cb_timeout_min'] = $data['logout_limit'] !== null
            ? (int)$data['logout_limit']
            : (int)cb_system_setting('system_logout', 20);
    }
}

if (!function_exists('cb_user_settings')) {
    /**
     * @return array<string, mixed>
     */
    function cb_user_settings(): array
    {
        if (!isset($_SESSION['cb_user_settings']) || !is_array($_SESSION['cb_user_settings'])) {
            cb_store_user_settings([]);
        }

        /** @var array<string, mixed> $settings */
        $settings = $_SESSION['cb_user_settings'];
        return $settings;
    }
}

if (!function_exists('cb_user_setting')) {
    function cb_user_setting(string $key, mixed $default = null): mixed
    {
        $settings = cb_user_settings();
        return $settings[$key] ?? $default;
    }
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
