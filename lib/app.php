<?php
// lib/app.php V3 – počet řádků: 78 – aktuální čas v ČR: 19.1.2026 18:12
declare(strict_types=1);

/*
 * lib/app.php
 * - žádná DB
 * - PROSTREDI (LOCAL / SERVER)
 * - BASE_PATH: určené na ROOT projektu (kvůli přímému volání /lib/*.php)
 * - cb_url() vrací absolutní URL od rootu webu
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

$PROSTREDI = $JE_LOCAL ? 'LOCAL' : 'SERVER';

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
            if ($BASE_PATH === '/') $BASE_PATH = '';
        }
    }
}

function cb_url(string $path): string {
    global $BASE_PATH;
    $path = '/' . ltrim($path, '/');
    return ($BASE_PATH !== '' ? $BASE_PATH : '') . $path;
}

/* lib/app.php V3 – počet řádků: 78 – aktuální čas v ČR: 19.1.2026 18:12 */
