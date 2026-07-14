<?php
// lib/restia_kontrola_mesice.php
declare(strict_types=1);

require_once __DIR__ . '/restia_kontrola_import_core.php';

if (!function_exists('cb_restia_kontrola_auth')) {
    function cb_restia_kontrola_auth(): array
    {
        return [
            'id_user' => null,
            'id_login' => null,
        ];
    }
}

if (!function_exists('cb_restia_kontrola_branches')) {
    function cb_restia_kontrola_branches(mysqli $conn): array
    {
        $branches = cb_restia_hist_get_branches($conn);
        $out = [];
        foreach ($branches as $branch) {
            $idPob = (int)($branch['id_pob'] ?? 0);
            $activePosId = trim((string)($branch['active_pos_id'] ?? ''));
            $prvniObj = trim((string)($branch['prvni_obj'] ?? ''));
            if ($idPob <= 0 || $activePosId === '' || $prvniObj === '') {
                continue;
            }
            $out[] = [
                'id_pob' => $idPob,
                'nazev' => trim((string)($branch['nazev'] ?? '')),
                'active_pos_id' => $activePosId,
                'prvni_obj' => $prvniObj,
            ];
        }

        return $out;
    }
}

if (!function_exists('cb_restia_kontrola_month_start')) {
    function cb_restia_kontrola_month_start(string $date): DateTimeImmutable
    {
        $date = substr(trim($date), 0, 10);
        $tz = new DateTimeZone('Europe/Prague');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro mesicni kontrolu: ' . $date);
        }

        return $dt->modify('first day of this month')->setTime(0, 0, 0);
    }
}

if (!function_exists('cb_restia_kontrola_month_range')) {
    function cb_restia_kontrola_month_range(int $rok, int $mesic): array
    {
        $tz = new DateTimeZone('Europe/Prague');
        $from = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-01 00:00:00', $rok, $mesic), $tz);
        if (!($from instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se sestavit pocatek mesice.');
        }
        $to = $from->modify('first day of next month')->setTime(0, 0, 0);

        return [
            'rok' => $rok,
            'mesic' => $mesic,
            'from_db' => $from->format('Y-m-d H:i:s'),
            'to_db' => $to->format('Y-m-d H:i:s'),
            'from_z' => $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'to_z' => $to->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'from_date' => $from->format('Y-m-d'),
            'to_date_exclusive' => $to->format('Y-m-d'),
        ];
    }
}

if (!function_exists('cb_restia_kontrola_current_month')) {
    function cb_restia_kontrola_current_month(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));

        return [
            'rok' => (int)$now->format('Y'),
            'mesic' => (int)$now->format('n'),
        ];
    }
}

if (!function_exists('cb_restia_kontrola_is_current_month')) {
    function cb_restia_kontrola_is_current_month(int $rok, int $mesic): bool
    {
        $current = cb_restia_kontrola_current_month();

        return $rok === (int)$current['rok'] && $mesic === (int)$current['mesic'];
    }
}

if (!function_exists('cb_restia_kontrola_status_for_equal')) {
    function cb_restia_kontrola_status_for_equal(int $rok, int $mesic): string
    {
        return cb_restia_kontrola_is_current_month($rok, $mesic) ? 'srovnano' : 'uzavreno';
    }
}

if (!function_exists('cb_restia_kontrola_ensure_row')) {
    function cb_restia_kontrola_ensure_row(mysqli $conn, int $idPob, int $rok, int $mesic): void
    {
        $stav = 'ke_kontrole';
        $stmt = $conn->prepare('
            INSERT INTO kontrolni_prehledy (id_pob, rok, mesic, stav)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id_pob = id_pob
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: kontrolni_prehledy ensure.');
        }
        $stmt->bind_param('iiis', $idPob, $rok, $mesic, $stav);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_kontrola_load_row')) {
    function cb_restia_kontrola_load_row(mysqli $conn, int $idPob, int $rok, int $mesic): ?array
    {
        $stmt = $conn->prepare('
            SELECT id_prehled, id_pob, rok, mesic, obj_restia, obj_is, rozdil, stav, posledni_kontrola, posledni_oprava, chyba_text, zmeneno
            FROM kontrolni_prehledy
            WHERE id_pob = ? AND rok = ? AND mesic = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: kontrolni_prehledy load.');
        }
        $stmt->bind_param('iii', $idPob, $rok, $mesic);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('cb_restia_kontrola_update_check')) {
    function cb_restia_kontrola_update_check(
        mysqli $conn,
        int $idPob,
        int $rok,
        int $mesic,
        int $objRestia,
        int $objIs,
        string $stav,
        ?string $chybaText = null
    ): void {
        $rozdil = $objIs - $objRestia;
        $stmt = $conn->prepare('
            UPDATE kontrolni_prehledy
            SET obj_restia = ?,
                obj_is = ?,
                rozdil = ?,
                stav = ?,
                posledni_kontrola = NOW(),
                chyba_text = ?
            WHERE id_pob = ? AND rok = ? AND mesic = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: kontrolni_prehledy update check.');
        }
        $stmt->bind_param('iiissiii', $objRestia, $objIs, $rozdil, $stav, $chybaText, $idPob, $rok, $mesic);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_kontrola_update_after_repair')) {
    function cb_restia_kontrola_update_after_repair(
        mysqli $conn,
        int $idPob,
        int $rok,
        int $mesic,
        int $objRestia,
        int $objIs,
        string $stav,
        ?string $chybaText = null
    ): void {
        $rozdil = $objIs - $objRestia;
        $stmt = $conn->prepare('
            UPDATE kontrolni_prehledy
            SET obj_restia = ?,
                obj_is = ?,
                rozdil = ?,
                stav = ?,
                posledni_kontrola = NOW(),
                posledni_oprava = NOW(),
                chyba_text = ?
            WHERE id_pob = ? AND rok = ? AND mesic = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: kontrolni_prehledy update repair.');
        }
        $stmt->bind_param('iiissiii', $objRestia, $objIs, $rozdil, $stav, $chybaText, $idPob, $rok, $mesic);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_kontrola_mark_error')) {
    function cb_restia_kontrola_mark_error(mysqli $conn, int $idPob, int $rok, int $mesic, string $chybaText): void
    {
        $stav = 'chyba';
        $stmt = $conn->prepare('
            UPDATE kontrolni_prehledy
            SET stav = ?,
                posledni_kontrola = NOW(),
                chyba_text = ?
            WHERE id_pob = ? AND rok = ? AND mesic = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: kontrolni_prehledy mark error.');
        }
        $stmt->bind_param('ssiii', $stav, $chybaText, $idPob, $rok, $mesic);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_kontrola_db_count')) {
    function cb_restia_kontrola_db_count(mysqli $conn, int $idPob, array $range): int
    {
        $stmt = $conn->prepare('
            SELECT COUNT(*) AS cnt
            FROM objednavky_restia
            WHERE id_pob = ?
              AND restia_created_at IS NOT NULL
              AND restia_created_at >= ?
              AND restia_created_at < ?
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: kontrolni_prehledy db count.');
        }
        $stmt->bind_param('iss', $idPob, $range['from_db'], $range['to_db']);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('cb_restia_kontrola_restia_count')) {
    function cb_restia_kontrola_restia_count(array $branch, array $range): int
    {
        $activePosId = (string)($branch['active_pos_id'] ?? '');
        $res = cb_restia_get('/api/orders', [
            'page' => 1,
            'limit' => 1,
            'createdFrom' => (string)$range['from_z'],
            'createdTo' => (string)$range['to_z'],
            'activePosId' => $activePosId,
        ], $activePosId, 'kontrolni prehled mesic=' . (string)$range['rok'] . '-' . sprintf('%02d', (int)$range['mesic']) . ' id_pob=' . (string)($branch['id_pob'] ?? 0));

        if ((int)($res['ok'] ?? 0) !== 1) {
            throw new RuntimeException('Restia chyba HTTP=' . (string)($res['http_status'] ?? '') . ' ' . (string)($res['chyba'] ?? ''));
        }
        if (!array_key_exists('total_count', $res) || $res['total_count'] === null) {
            throw new RuntimeException('Restia nevratila X-Total-Count.');
        }

        return max(0, (int)$res['total_count']);
    }
}

if (!function_exists('cb_restia_kontrola_reimport_month')) {
    function cb_restia_kontrola_reimport_month(mysqli $conn, array $auth, array $branch, array $range): array
    {
        $date = $range['from_date'];
        $limitDate = $range['to_date_exclusive'];
        $days = 0;
        $orders = 0;

        while ($date < $limitDate) {
            $day = cb_restia_hist_import_day($conn, $auth, $branch, $date);
            $days++;
            $orders += (int)($day['count'] ?? 0);
            $date = cb_restia_hist_next_date($date);
        }

        return [
            'days' => $days,
            'orders' => $orders,
        ];
    }
}

if (!function_exists('cb_restia_kontrola_process_month')) {
    function cb_restia_kontrola_process_month(mysqli $conn, array $auth, array $branch, int $rok, int $mesic, bool $forceAll = false): array
    {
        $idPob = (int)($branch['id_pob'] ?? 0);
        cb_restia_kontrola_ensure_row($conn, $idPob, $rok, $mesic);
        $row = cb_restia_kontrola_load_row($conn, $idPob, $rok, $mesic);
        if (!is_array($row)) {
            throw new RuntimeException('Nepodarilo se nacist radek kontrolniho prehledu.');
        }

        $currentStatus = trim((string)($row['stav'] ?? ''));
        if (!$forceAll && $currentStatus === 'uzavreno') {
            return [
                'action' => 'skip',
                'status' => $currentStatus,
                'id_pob' => $idPob,
                'rok' => $rok,
                'mesic' => $mesic,
            ];
        }

        $range = cb_restia_kontrola_month_range($rok, $mesic);
        $objRestia = cb_restia_kontrola_restia_count($branch, $range);
        $objIs = cb_restia_kontrola_db_count($conn, $idPob, $range);
        $rozdil = $objIs - $objRestia;

        if ($rozdil === 0) {
            $stav = cb_restia_kontrola_status_for_equal($rok, $mesic);
            cb_restia_kontrola_update_check($conn, $idPob, $rok, $mesic, $objRestia, $objIs, $stav, null);

            return [
                'action' => 'checked',
                'status' => $stav,
                'id_pob' => $idPob,
                'rok' => $rok,
                'mesic' => $mesic,
                'obj_restia' => $objRestia,
                'obj_is' => $objIs,
                'rozdil' => 0,
            ];
        }

        cb_restia_kontrola_update_check($conn, $idPob, $rok, $mesic, $objRestia, $objIs, 'ke_kontrole', null);
        cb_restia_kontrola_reimport_month($conn, $auth, $branch, $range);

        $objRestiaAfter = cb_restia_kontrola_restia_count($branch, $range);
        $objIsAfter = cb_restia_kontrola_db_count($conn, $idPob, $range);
        $rozdilAfter = $objIsAfter - $objRestiaAfter;
        $stavAfter = ($rozdilAfter === 0) ? cb_restia_kontrola_status_for_equal($rok, $mesic) : 'manual_check';
        $chybaText = ($rozdilAfter === 0) ? null : 'Po oprave zustal rozdil ' . (string)$rozdilAfter . '.';
        cb_restia_kontrola_update_after_repair($conn, $idPob, $rok, $mesic, $objRestiaAfter, $objIsAfter, $stavAfter, $chybaText);

        return [
            'action' => 'repair',
            'status' => $stavAfter,
            'id_pob' => $idPob,
            'rok' => $rok,
            'mesic' => $mesic,
            'obj_restia' => $objRestiaAfter,
            'obj_is' => $objIsAfter,
            'rozdil' => $rozdilAfter,
        ];
    }
}

if (!function_exists('cb_restia_kontrola_branch_months')) {
    function cb_restia_kontrola_branch_months(array $branch): array
    {
        $start = cb_restia_kontrola_month_start((string)($branch['prvni_obj'] ?? ''));
        $current = new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('Europe/Prague'));
        $out = [];
        $month = $start;

        while ($month <= $current) {
            $out[] = [
                'rok' => (int)$month->format('Y'),
                'mesic' => (int)$month->format('n'),
            ];
            $month = $month->modify('first day of next month')->setTime(0, 0, 0);
        }

        return $out;
    }
}

if (!function_exists('cb_restia_kontrola_run')) {
    function cb_restia_kontrola_run(bool $forceAll = false): array
    {
        $conn = db();
        $auth = cb_restia_kontrola_auth();
        $branches = cb_restia_kontrola_branches($conn);
        $results = [];
        $summary = [
            'branches' => count($branches),
            'months_checked' => 0,
            'months_skipped' => 0,
            'months_repaired' => 0,
            'months_manual' => 0,
            'months_error' => 0,
        ];

        foreach ($branches as $branch) {
            $branchResult = [
                'id_pob' => (int)($branch['id_pob'] ?? 0),
                'nazev' => (string)($branch['nazev'] ?? ''),
                'months' => [],
            ];

            foreach (cb_restia_kontrola_branch_months($branch) as $monthInfo) {
                $rok = (int)($monthInfo['rok'] ?? 0);
                $mesic = (int)($monthInfo['mesic'] ?? 0);
                try {
                    $monthResult = cb_restia_kontrola_process_month($conn, $auth, $branch, $rok, $mesic, $forceAll);
                    $branchResult['months'][] = $monthResult;

                    if (($monthResult['action'] ?? '') === 'skip') {
                        $summary['months_skipped']++;
                        continue;
                    }

                    $summary['months_checked']++;
                    if (($monthResult['action'] ?? '') === 'repair') {
                        $summary['months_repaired']++;
                    }
                    if (($monthResult['status'] ?? '') === 'manual_check') {
                        $summary['months_manual']++;
                    }
                } catch (Throwable $e) {
                    cb_restia_kontrola_mark_error($conn, (int)($branch['id_pob'] ?? 0), $rok, $mesic, $e->getMessage());
                    $summary['months_checked']++;
                    $summary['months_error']++;
                    $branchResult['months'][] = [
                        'action' => 'error',
                        'status' => 'chyba',
                        'id_pob' => (int)($branch['id_pob'] ?? 0),
                        'rok' => $rok,
                        'mesic' => $mesic,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $results[] = $branchResult;
        }

        return [
            'summary' => $summary,
            'branches' => $results,
        ];
    }
}
