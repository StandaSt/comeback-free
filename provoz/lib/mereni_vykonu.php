<?php
// lib/mereni_vykonu.php * Verze: V2 * Aktualizace: 21.05.2026
declare(strict_types=1);

if (!function_exists('cb_tmp_log1_enabled')) {
    function cb_tmp_log1_enabled(): bool
    {
        return function_exists('cb_system_setting') && (int)cb_system_setting('log_1', 0) === 1;
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

if (!function_exists('cb_tmp_measure_detail_add')) {
    /**
     * @param array<string, mixed> $row
     */
    function cb_tmp_measure_detail_add(array $row): void
    {
        if (!isset($GLOBALS['cb_tmp_measure_details']) || !is_array($GLOBALS['cb_tmp_measure_details'])) {
            $GLOBALS['cb_tmp_measure_details'] = [];
        }

        $typ = trim((string)($row['typ'] ?? ''));
        if (!in_array($typ, ['card', 'dashboard', 'ajax', 'db'], true)) {
            return;
        }

        $GLOBALS['cb_tmp_measure_details'][] = $row;
    }
}

if (!function_exists('cb_tmp_measure_sql_type')) {
    function cb_tmp_measure_sql_type(string $sql): string
    {
        $sql = ltrim($sql);
        if ($sql === '') {
            return '';
        }

        if (preg_match('/^([a-z_]+)/i', $sql, $m) !== 1) {
            return '';
        }

        return strtoupper((string)$m[1]);
    }
}

if (!function_exists('cb_tmp_measure_sql_detail_add')) {
    function cb_tmp_measure_sql_detail_add(int $queryId, string $sql, float $durationMs): void
    {
        if (!cb_tmp_log1_enabled()) {
            return;
        }

        $sql = trim(preg_replace('~\s+~', ' ', $sql) ?? $sql);
        if ($sql === '') {
            return;
        }

        $sqlType = cb_tmp_measure_sql_type($sql);
        cb_tmp_measure_detail_add([
            'typ' => 'db',
            'nazev' => $sqlType !== '' ? $sqlType : 'SQL',
            'ms' => round($durationMs, 3),
            'detail' => [
                'query_id' => $queryId,
                'sql_type' => $sqlType,
                'sql' => $sql,
            ],
        ]);
    }
}

if (!function_exists('cb_tmp_measure_detail_flush')) {
    function cb_tmp_measure_detail_flush(mysqli $conn, int $idUserAkceDb): int
    {
        if ($idUserAkceDb <= 0) {
            return 0;
        }

        $rows = $GLOBALS['cb_tmp_measure_details'] ?? [];
        if (!is_array($rows) || $rows === [] || !function_exists('db_user_akce_db_detail_insert_many')) {
            return 0;
        }

        $GLOBALS['cb_tmp_measure_details'] = [];
        return db_user_akce_db_detail_insert_many($conn, $idUserAkceDb, $rows);
    }
}
