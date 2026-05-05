<?php
// lib/vypocet_prehled_db.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (!function_exists('cb_vypocet_prehled_db_table_count')) {
    function cb_vypocet_prehled_db_table_count(mysqli $conn, string $table): int
    {
        static $cache = [];

        $cacheKey = spl_object_hash($conn) . '|' . $table;
        if (array_key_exists($cacheKey, $cache)) {
            return (int)$cache[$cacheKey];
        }

        $sql = 'SELECT COUNT(*) AS cnt FROM `' . str_replace('`', '``', $table) . '`';
        $res = $conn->query($sql);
        if (!$res instanceof mysqli_result) {
            throw new RuntimeException('Nepodařilo se spočítat tabulku ' . $table . '.');
        }

        $row = $res->fetch_assoc();
        $res->free();

        $count = (int)($row['cnt'] ?? 0);
        $cache[$cacheKey] = $count;

        return $count;
    }
}

if (!function_exists('cb_vypocet_prehled_db_table_bytes')) {
    function cb_vypocet_prehled_db_table_bytes(mysqli $conn, string $table, array $meta = []): int
    {
        if ($meta === [] && function_exists('cb_db_table_meta')) {
            $meta = cb_db_table_meta($conn);
        }

        return (int)($meta[$table]['bytes'] ?? 0);
    }
}

if (!function_exists('cb_vypocet_prehled_db_updated_at')) {
    function cb_vypocet_prehled_db_updated_at(mysqli $conn, string $sourceKey): string
    {
        static $cache = [];

        $cacheKey = spl_object_hash($conn) . '|' . $sourceKey;
        if (array_key_exists($cacheKey, $cache)) {
            return (string)$cache[$cacheKey];
        }

        $queries = [
            'restia' => 'SELECT MAX(`restia_imported_at`) AS dt FROM objednavky_restia',
            'objednavky' => 'SELECT MAX(`restia_imported_at`) AS dt FROM objednavky_restia',
            'smeny' => 'SELECT MAX(`created_at`) AS dt FROM smeny_plan',
            'reporty' => 'SELECT MAX(`created_at`) AS dt FROM smeny_report',
        ];

        $dt = 'Ne';
        if (isset($queries[$sourceKey])) {
            $res = $conn->query($queries[$sourceKey]);
            if ($res instanceof mysqli_result) {
                $row = $res->fetch_assoc();
                $raw = trim((string)($row['dt'] ?? ''));
                if ($raw !== '') {
                    $ts = strtotime($raw);
                    if ($ts !== false) {
                        $dt = date('j.n.Y G:i', $ts);
                    }
                }
                $res->free();
            }
        }

        $cache[$cacheKey] = $dt;

        return $dt;
    }
}

if (!function_exists('cb_vypocet_prehled_db')) {
    function cb_vypocet_prehled_db(mysqli $conn): array
    {
        static $cache = [];

        $cacheKey = spl_object_hash($conn);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $meta = function_exists('cb_db_table_meta') ? cb_db_table_meta($conn) : [];
        $sources = [
            'restia' => [
                'label' => 'Restia',
                'tables' => [
                    'objednavky_restia',
                    'obj_kuryr',
                    'obj_polozka_kds_tag',
                    'obj_polozky',
                    'obj_sluzba',
                    'zakaznik',
                ],
            ],
            'objednavky' => [
                'label' => 'Objednávky',
                'tables' => [
                    'objednavky_restia',
                ],
            ],
            'smeny' => [
                'label' => 'Směny',
                'tables' => [
                    'smeny_akceptovane',
                    'smeny_aktualizace',
                    'smeny_plan',
                ],
            ],
            'reporty' => [
                'label' => 'Reporty',
                'tables' => [
                    'smeny_report',
                ],
            ],
        ];

        $rows = [];
        $totalCount = 0;
        $totalBytes = 0;

        foreach ($sources as $sourceKey => $source) {
            $count = 0;
            $bytes = 0;

            foreach ((array)($source['tables'] ?? []) as $table) {
                $table = (string)$table;
                if ($table === '') {
                    continue;
                }
                $count += cb_vypocet_prehled_db_table_count($conn, $table);
                $bytes += cb_vypocet_prehled_db_table_bytes($conn, $table, $meta);
            }

            $rows[] = [
                'source' => (string)($source['label'] ?? $sourceKey),
                'count' => $count,
                'bytes' => $bytes,
                'updated_at' => cb_vypocet_prehled_db_updated_at($conn, $sourceKey),
            ];

            $totalCount += $count;
            $totalBytes += $bytes;
        }

        $cache[$cacheKey] = [
            'rows' => $rows,
            'totalCount' => $totalCount,
            'totalBytes' => $totalBytes,
        ];

        return $cache[$cacheKey];
    }
}

/* lib/vypocet_prehled_db.php * Verze: V1 * Aktualizace: 23.04.2026 * Konec souboru */
