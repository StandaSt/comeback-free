<?php
// lib/db_prehledy.php * Verze: V1 * Aktualizace: 06.05.2026
declare(strict_types=1);

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
                'label' => 'Restia objednĂˇvky',
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
                'label' => 'SmÄ›ny',
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
                'label' => 'SystĂ©m',
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
            throw new RuntimeException('NepodaĹ™ilo se naÄŤĂ­st metadata tabulek.');
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
            return number_format((float)$bytes / 1024, 2, ',', ' ');
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

        return number_format($value, 2, ',', ' ');
    }
}

if (!function_exists('cb_db_count_style')) {
    function cb_db_count_style(int $value): string
    {
        return $value === 0 ? 'text-align:right; color:#b91c1c; font-weight:700;' : 'text-align:right;';
    }
}
