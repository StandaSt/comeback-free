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
        // DOCASNE MERENI CASU KARET
        $GLOBALS['cb_tmp_db_conn'] = $conn;

        return $conn;
    }
}

// DOCASNE MERENI CASU KARET
if (!function_exists('cb_tmp_time_count_enabled')) {
    function cb_tmp_time_count_enabled(): bool
    {
        return isset($GLOBALS['time_count']) && (int)$GLOBALS['time_count'] === 1;
    }
}

// DOCASNE MERENI CASU KARET
if (!function_exists('cb_tmp_measure_log_write')) {
    function cb_tmp_measure_log_write(string $fileName, string $line): void
    {
        if (!cb_tmp_time_count_enabled()) {
            return;
        }

        $dir = __DIR__ . '/../log';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($dir . '/' . $fileName, $line, FILE_APPEND | LOCK_EX);
    }
}

// DOCASNE MERENI CASU KARET
if (!function_exists('cb_tmp_measure_filters')) {
    function cb_tmp_measure_filters(): array
    {
        $od = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
        $do = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));

        $pob = [];
        if (function_exists('get_selected_pobocky')) {
            $pob = get_selected_pobocky();
        } elseif (isset($_SESSION['selected_pobocky']) && is_array($_SESSION['selected_pobocky'])) {
            $pob = $_SESSION['selected_pobocky'];
        } elseif (isset($_SESSION['cb_pobocka_id'])) {
            $pob = [(int)$_SESSION['cb_pobocka_id']];
        }

        $pob = array_values(array_filter(array_map('intval', $pob), static fn (int $id): bool => $id > 0));

        return [
            'od' => $od,
            'do' => $do,
            'pobocky' => implode(',', $pob),
            'pobocky_mode' => trim((string)($_SESSION['selected_pobocky_mode'] ?? '')),
        ];
    }
}

// DOCASNE MERENI CASU KARET
if (!function_exists('cb_tmp_measure_card_register')) {
    function cb_tmp_measure_card_register(int $cardId, string $title, string $mode): void
    {
        if (!cb_tmp_time_count_enabled()) {
            return;
        }

        $key = $cardId . '|' . $mode;
        if (!isset($GLOBALS['cb_tmp_measure_cards']) || !is_array($GLOBALS['cb_tmp_measure_cards'])) {
            $GLOBALS['cb_tmp_measure_cards'] = [];
        }

        $GLOBALS['cb_tmp_measure_cards'][$key] = [
            'id' => $cardId,
            'title' => trim($title),
            'mode' => trim($mode),
        ];
    }
}

// DOCASNE MERENI CASU KARET
if (!function_exists('cb_tmp_measure_db_init')) {
    function cb_tmp_measure_db_init(mysqli $conn): void
    {
        if (!cb_tmp_time_count_enabled()) {
            return;
        }

        if (!empty($GLOBALS['cb_tmp_measure_db_init_done'])) {
            return;
        }

        $GLOBALS['cb_tmp_measure_db_init_done'] = 1;
        $GLOBALS['cb_tmp_measure_db_available'] = 0;

        try {
            $conn->query('SET profiling = 1');
            $GLOBALS['cb_tmp_measure_db_available'] = 1;
        } catch (Throwable $e) {
            $GLOBALS['cb_tmp_measure_db_available'] = 0;
            $GLOBALS['cb_tmp_measure_db_error'] = $e->getMessage();
        }

        register_shutdown_function(static function (): void {
            if (!cb_tmp_time_count_enabled()) {
                return;
            }

            $conn = $GLOBALS['cb_tmp_db_conn'] ?? null;
            if (!$conn instanceof mysqli) {
                return;
            }

            $filters = cb_tmp_measure_filters();
            $user = $_SESSION['cb_user'] ?? [];
            $userId = (int)($user['id_user'] ?? 0);
            $userName = trim((string)(
                trim((string)($user['name'] ?? '')) . ' ' . trim((string)($user['surname'] ?? ''))
            ));
            $requestUri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
            $requestCardId = (int)($GLOBALS['cb_dashboard_single_card_id'] ?? 0);
            $requestCardName = '';
            $cards = $GLOBALS['cb_tmp_measure_cards'] ?? [];
            if (is_array($cards)) {
                foreach ($cards as $cardInfo) {
                    if ((int)($cardInfo['id'] ?? 0) === $requestCardId) {
                        $requestCardName = trim((string)($cardInfo['title'] ?? ''));
                        break;
                    }
                }
            }

            $cardSummary = 'dashboard';
            if ($requestCardId > 0) {
                $cardSummary = $requestCardId . ($requestCardName !== '' ? ':' . $requestCardName : '');
            } elseif (is_array($cards) && $cards !== []) {
                $parts = [];
                foreach ($cards as $cardInfo) {
                    $parts[] = (int)($cardInfo['id'] ?? 0) . ':' . trim((string)($cardInfo['title'] ?? '')) . ':' . trim((string)($cardInfo['mode'] ?? ''));
                }
                $cardSummary = implode(',', $parts);
            }

            $countSql = 0;
            $totalMs = 0.0;
            $top = [];
            $status = 'ok';
            $error = trim((string)($GLOBALS['cb_tmp_measure_db_error'] ?? ''));

            if (empty($GLOBALS['cb_tmp_measure_db_available'])) {
                $status = 'profiling_off';
            } else {
                try {
                    $resProfiles = $conn->query('SHOW PROFILES');
                    if ($resProfiles instanceof mysqli_result) {
                        while ($row = $resProfiles->fetch_assoc()) {
                            $sql = trim((string)($row['Query'] ?? ''));
                            if ($sql === '' || stripos($sql, 'SHOW PROFILES') === 0 || stripos($sql, 'SET profiling') === 0) {
                                continue;
                            }

                            $durationMs = round(((float)($row['Duration'] ?? 0)) * 1000, 3);
                            $countSql++;
                            $totalMs += $durationMs;
                            $top[] = [
                                'ms' => $durationMs,
                                'sql' => preg_replace('~\s+~', ' ', $sql) ?? $sql,
                            ];
                        }
                        $resProfiles->free();
                    } else {
                        $status = 'profiling_unavailable';
                    }
                } catch (Throwable $e) {
                    $status = 'profiling_error';
                    $error = $e->getMessage();
                }
            }

            usort($top, static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);
            $top = array_slice($top, 0, 5);
            $topText = [];
            foreach ($top as $item) {
                $sqlText = trim((string)($item['sql'] ?? ''));
                if (strlen($sqlText) > 220) {
                    $sqlText = substr($sqlText, 0, 220) . '...';
                }
                $topText[] = (string)($item['ms'] ?? 0) . 'ms [' . $sqlText . ']';
            }

            $line = sprintf(
                "%s | user_id=%d | user=%s | request=%s | karta=%s | sql_count=%d | sql_total_ms=%s | top5=%s | obdobi_od=%s | obdobi_do=%s | pobocky=%s | pobocky_mode=%s | status=%s | error=%s%s",
                date('Y-m-d H:i:s'),
                $userId,
                $userName !== '' ? $userName : '-',
                $requestUri !== '' ? $requestUri : '-',
                $cardSummary !== '' ? $cardSummary : '-',
                $countSql,
                number_format($totalMs, 3, '.', ''),
                $topText !== [] ? implode(' || ', $topText) : '-',
                (string)$filters['od'],
                (string)$filters['do'],
                (string)$filters['pobocky'],
                (string)$filters['pobocky_mode'],
                $status,
                $error !== '' ? preg_replace('~\s+~', ' ', $error) : '-',
                PHP_EOL
            );

            cb_tmp_measure_log_write('db_time.txt', $line);
        });
    }
}

if (!function_exists('cb_db_summary_scopes')) {
    function cb_db_summary_scopes(): array
    {
        return [
            'restia' => [
                'label' => 'Restia',
                'allow_wipe' => false,
                'tables' => [
                    'api_restia',
                    'cis_doruceni',
                    'cis_obj_platby',
                    'cis_obj_platforma',
                    'cis_obj_stav',
                    'objednavky_restia',
                    'obj_adresa',
                    'obj_casy',
                    'obj_ceny',
                    'obj_import',
                    'obj_kuryr',
                    'obj_polozka_kds_tag',
                    'obj_polozka_mod',
                    'obj_polozky',
                    'obj_sluzba',
                    'res_alergen',
                    'res_cena',
                    'res_kategorie',
                    'res_polozky',
                ],
            ],
            'restia_obj' => [
                'label' => 'Restia objednávky',
                'allow_wipe' => true,
                'tables' => [
                    'api_restia',
                    'cis_doruceni',
                    'cis_obj_platby',
                    'cis_obj_platforma',
                    'cis_obj_stav',
                    'objednavky_restia',
                    'obj_adresa',
                    'obj_casy',
                    'obj_ceny',
                    'obj_import',
                    'obj_kuryr',
                    'obj_polozka_kds_tag',
                    'obj_polozka_mod',
                    'obj_polozky',
                    'obj_sluzba',
                    'zakaznik',
                ],
            ],
            'restia_menu' => [
                'label' => 'Restia menu',
                'allow_wipe' => true,
                'tables' => [
                    'res_alergen',
                    'res_cena',
                    'res_kategorie',
                    'res_polozky',
                ],
            ],
            'smeny' => [
                'label' => 'Směny',
                'allow_wipe' => true,
                'tables' => [
                    'api_smeny',
                    'smeny_akceptovane',
                    'smeny_aktualizace',
                    'smeny_plan',
                    'smeny_report',
                ],
            ],
            'reporty' => [
                'label' => 'Reporty',
                'allow_wipe' => true,
                'tables' => [
                    'cb_reporty_person',
                    'cb_reporty',
                ],
            ],
            'system' => [
                'label' => 'Systém',
                'allow_wipe' => false,
                'tables' => [
                    'cis_chyby',
                    'cis_polozka_kat',
                    'cis_polozky',
                    'cis_prac_zarazeni',
                    'cis_role',
                    'cis_slot',
                    'cis_sloupce',
                    'init_scripty',
                    'karty',
                    'log_chyby',
                    'pobocka',
                    'pob_email',
                    'pob_manager',
                    'pob_povoleni',
                    'pob_povoleni_hist',
                    'pob_tel',
                    'push_audit',
                    'push_login_2fa',
                    'push_parovani',
                    'push_zarizeni',
                    'restia_token',
                    'user',
                    'user_bad_login',
                    'user_login',
                    'user_nano',
                    'user_pin',
                    'user_pobocka',
                    'user_pobocka_set',
                    'user_role',
                    'user_set',
                    'user_slot',
                    'user_spy',
                ],
            ],
        ];
    }
}

if (!function_exists('cb_db_table_meta')) {
    function cb_db_table_meta(mysqli $conn): array
    {
        static $cache = [];
        $cacheKey = spl_object_hash($conn);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $sql = '
            SELECT
                table_name,
                COALESCE(table_rows, 0) AS row_count,
                COALESCE(data_length, 0) + COALESCE(index_length, 0) AS size_bytes
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
        ';
        $res = $conn->query($sql);
        if (!$res instanceof mysqli_result) {
            throw new RuntimeException('Nepodařilo se načíst metadata tabulek.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $name = (string)($row['table_name'] ?? '');
            if ($name === '') {
                continue;
            }

            $out[$name] = [
                'count' => (int)($row['row_count'] ?? 0),
                'bytes' => (int)($row['size_bytes'] ?? 0),
            ];
        }
        $res->free();

        $cache[$cacheKey] = $out;

        return $out;
    }
}

if (!function_exists('cb_db_scope_summary')) {
    function cb_db_scope_summary(mysqli $conn, ?array $scopeKeys = null): array
    {
        $scopes = cb_db_summary_scopes();
        $meta = cb_db_table_meta($conn);
        $keys = $scopeKeys ?? array_keys($scopes);
        $out = [];

        foreach ($keys as $scopeKey) {
            $scopeKey = (string)$scopeKey;
            if (!isset($scopes[$scopeKey])) {
                continue;
            }

            $count = 0;
            $bytes = 0;
            foreach ((array)($scopes[$scopeKey]['tables'] ?? []) as $table) {
                $table = (string)$table;
                if (!isset($meta[$table])) {
                    continue;
                }

                $count += (int)($meta[$table]['count'] ?? 0);
                $bytes += (int)($meta[$table]['bytes'] ?? 0);
            }

            $out[$scopeKey] = [
                'label' => (string)($scopes[$scopeKey]['label'] ?? $scopeKey),
                'count' => $count,
                'bytes' => $bytes,
            ];
        }

        return $out;
    }
}

if (!function_exists('cb_db_fmt_rows_approx')) {
    function cb_db_fmt_rows_approx(int $value): string
    {
        return '~ ' . number_format($value, 0, ',', ' ');
    }
}

if (!function_exists('cb_db_fmt_bytes')) {
    function cb_db_fmt_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $unit = 'B';

        foreach ($units as $u) {
            $value /= 1024;
            $unit = $u;
            if ($value < 1024) {
                break;
            }
        }

        return number_format($value, 2, ',', ' ') . ' ' . $unit;
    }
}

if (!function_exists('cb_db_count_style')) {
    function cb_db_count_style(int $value): string
    {
        return $value === 0 ? 'text-align:right; color:#b91c1c; font-weight:700;' : 'text-align:right;';
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
