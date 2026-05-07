<?php
// lib/mereni_vykonu.php * Verze: V1 * Aktualizace: 06.05.2026
declare(strict_types=1);

if (!function_exists('cb_tmp_time_count_enabled')) {
    function cb_tmp_time_count_enabled(): bool
    {
        return isset($GLOBALS['time_count']) && (int)$GLOBALS['time_count'] === 1;
    }
}

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
