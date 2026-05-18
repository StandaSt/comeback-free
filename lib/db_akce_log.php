<?php
// lib/db_akce_log.php * Verze: V1 * Aktualizace: 18.05.2026
declare(strict_types=1);

require_once __DIR__ . '/../db/db_user_akce_db.php';

if (!function_exists('cb_db_akce_log_enabled')) {
    function cb_db_akce_log_enabled(): bool
    {
        if (!function_exists('cb_system_setting')) {
            return false;
        }

        return (int)cb_system_setting('log_1', 0) === 1;
    }
}

if (!function_exists('cb_db_akce_log_datetime')) {
    function cb_db_akce_log_datetime(float $time): string
    {
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $time));
        if (!$dt instanceof DateTimeImmutable) {
            $dt = new DateTimeImmutable();
        }

        return $dt->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s.v');
    }
}

if (!function_exists('cb_db_akce_log_stat_diff')) {
    /**
     * @param array<string, int|float> $start
     * @param array<string, int|float> $end
     */
    function cb_db_akce_log_stat_diff(array $start, array $end, string $key): int
    {
        $startValue = (int)($start[$key] ?? 0);
        $endValue = (int)($end[$key] ?? 0);
        $diff = $endValue - $startValue;

        return $diff > 0 ? $diff : 0;
    }
}

if (!function_exists('cb_db_akce_log_request_uri')) {
    function cb_db_akce_log_request_uri(): string
    {
        $uri = trim((string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($uri === '') {
            return '';
        }

        $path = $uri;
        $query = '';
        $queryPos = strpos($uri, '?');
        if ($queryPos !== false) {
            $path = substr($uri, 0, $queryPos);
            $query = substr($uri, $queryPos);
        }

        $basePath = '';
        if (function_exists('cb_url')) {
            $basePath = rtrim((string)cb_url('/'), '/');
        }

        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath));
        } elseif ($basePath !== '' && $basePath !== '/' && $path === $basePath) {
            $path = '/';
        }

        if ($path === '') {
            $path = '/';
        }

        if ($path === '/index.php') {
            $path = '/i';
        }

        return $path . $query;
    }
}

if (!function_exists('cb_db_akce_log_id_akce')) {
    function cb_db_akce_log_id_akce(): int
    {
        if (!isset($_SERVER['HTTP_X_COMEBACK_USER_AKCE'])) {
            return 0;
        }

        $raw = (string)file_get_contents('php://input');
        if ($raw === '') {
            return 0;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return 0;
        }

        $idAkce = (int)($data['id_akce'] ?? 0);
        return $idAkce > 0 ? $idAkce : 0;
    }
}

if (!function_exists('cb_db_akce_log_init')) {
    function cb_db_akce_log_init(mysqli $conn): void
    {
        if (!cb_db_akce_log_enabled()) {
            return;
        }

        if (!empty($GLOBALS['cb_db_akce_log_init_done'])) {
            return;
        }

        $GLOBALS['cb_db_akce_log_init_done'] = 1;

        $startTime = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $startStats = [];
        $idAkce = cb_db_akce_log_id_akce();
        $profilingOk = true;
        $profilingError = '';

        try {
            $conn->query('SET profiling = 1');
        } catch (Throwable $e) {
            $profilingOk = false;
            $profilingError = $e->getMessage();
        }

        if (function_exists('mysqli_get_connection_stats')) {
            $stats = mysqli_get_connection_stats($conn);
            if (is_array($stats)) {
                $startStats = $stats;
            }
        }

        register_shutdown_function(static function () use ($conn, $startTime, $startStats, $idAkce, $profilingOk, $profilingError): void {
            if (!cb_db_akce_log_enabled()) {
                return;
            }

            $user = $_SESSION['cb_user'] ?? [];
            $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
            if ($idUser <= 0) {
                return;
            }

            $endTime = microtime(true);
            $endStats = [];
            if (function_exists('mysqli_get_connection_stats')) {
                $stats = mysqli_get_connection_stats($conn);
                if (is_array($stats)) {
                    $endStats = $stats;
                }
            }

            $sqlCount = 0;
            $sqlTotalMs = 0.0;
            $sqlMaxMs = 0.0;
            $status = 'ok';
            $errMsg = '';

            if (!$profilingOk) {
                $status = 'profiling_off';
                $errMsg = $profilingError;
            } else {
                try {
                    $resProfiles = $conn->query('SHOW PROFILES');
                    if ($resProfiles instanceof mysqli_result) {
                        while ($row = $resProfiles->fetch_assoc()) {
                            $sql = trim((string)($row['Query'] ?? ''));
                            if ($sql === '') {
                                continue;
                            }

                            if (
                                stripos($sql, 'SET profiling') === 0
                                || stripos($sql, 'SHOW PROFILES') === 0
                            ) {
                                continue;
                            }

                            $durationMs = round(((float)($row['Duration'] ?? 0)) * 1000, 3);
                            $sqlCount++;
                            $sqlTotalMs += $durationMs;
                            if ($durationMs > $sqlMaxMs) {
                                $sqlMaxMs = $durationMs;
                            }
                        }
                        $resProfiles->free();
                    } else {
                        $status = 'profiling_unavailable';
                    }
                } catch (Throwable $e) {
                    $status = 'profiling_error';
                    $errMsg = $e->getMessage();
                }
            }

            $rowsReturned =
                cb_db_akce_log_stat_diff($startStats, $endStats, 'rows_fetched_from_server_normal')
                + cb_db_akce_log_stat_diff($startStats, $endStats, 'rows_fetched_from_server_ps');
            $rowsAffected =
                cb_db_akce_log_stat_diff($startStats, $endStats, 'rows_affected_normal')
                + cb_db_akce_log_stat_diff($startStats, $endStats, 'rows_affected_ps');

            try {
                db_user_akce_db_insert($conn, [
                    'cas_start' => cb_db_akce_log_datetime($startTime),
                    'id_user' => $idUser,
                    'id_akce' => $idAkce,
                    'request_uri' => cb_db_akce_log_request_uri(),
                    'metoda' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
                    'request_ms' => round(($endTime - $startTime) * 1000, 3),
                    'sql_count' => $sqlCount,
                    'sql_total_ms' => round($sqlTotalMs, 3),
                    'sql_max_ms' => round($sqlMaxMs, 3),
                    'rows_returned' => $rowsReturned,
                    'rows_affected' => $rowsAffected,
                    'bytes_received' => cb_db_akce_log_stat_diff($startStats, $endStats, 'bytes_received'),
                    'bytes_sent' => cb_db_akce_log_stat_diff($startStats, $endStats, 'bytes_sent'),
                    'status' => $status,
                    'err_msg' => $errMsg,
                ]);
            } catch (Throwable $e) {
                // Logovani provozu DB nesmi rozbit bezny request.
            }
        });
    }
}

/* lib/db_akce_log.php * Verze: V1 * Aktualizace: 18.05.2026 */
// Konec souboru
