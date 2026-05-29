<?php
// inicializace/kontrola_restia_objednavky.php * Verze: V1 * Aktualizace: 27.05.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/restia_access_exist.php';
require_once __DIR__ . '/../lib/restia_client.php';
require_once __DIR__ . '/../db/db_api_restia.php';

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}

if (!function_exists('cb_restia_kontrola_h')) {
    function cb_restia_kontrola_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_kontrola_get_auth')) {
    function cb_restia_kontrola_get_auth(): array
    {
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = (int)(is_array($user) ? ($user['id_user'] ?? 0) : 0);
        $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);

        return [
            'id_user' => $idUser > 0 ? $idUser : null,
            'id_login' => $idLogin > 0 ? $idLogin : null,
        ];
    }
}

if (!function_exists('cb_restia_kontrola_table_exists')) {
    function cb_restia_kontrola_table_exists(mysqli $conn): bool
    {
        $res = $conn->query("SHOW TABLES LIKE 'objednavky_kontrola'");
        if (!($res instanceof mysqli_result)) {
            return false;
        }
        $exists = ($res->num_rows > 0);
        $res->free();

        return $exists;
    }
}

if (!function_exists('cb_restia_kontrola_get_branches')) {
    function cb_restia_kontrola_get_branches(mysqli $conn): array
    {
        $res = $conn->query('
            SELECT id_pob, nazev, restia_activePosId, prvni_obj
            FROM pobocka
            WHERE restia_activePosId IS NOT NULL
              AND restia_activePosId <> ""
              AND prvni_obj IS NOT NULL
              AND prvni_obj <> ""
            ORDER BY id_pob ASC
        ');
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na pobočky selhal.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => trim((string)($row['nazev'] ?? '')),
                'active_pos_id' => trim((string)($row['restia_activePosId'] ?? '')),
                'prvni_obj' => trim((string)($row['prvni_obj'] ?? '')),
            ];
        }
        $res->free();

        return $out;
    }
}

if (!function_exists('cb_restia_kontrola_month_start')) {
    function cb_restia_kontrola_month_start(string $date): DateTimeImmutable
    {
        $date = trim($date);
        $tz = new DateTimeZone('Europe/Prague');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($date, 0, 10) . ' 00:00:00', $tz);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatné datum první objednávky: ' . $date);
        }

        return $dt->modify('first day of this month')->setTime(0, 0, 0);
    }
}

if (!function_exists('cb_restia_kontrola_month_range')) {
    function cb_restia_kontrola_month_range(DateTimeImmutable $monthStart): array
    {
        $next = $monthStart->modify('first day of next month')->setTime(0, 0, 0);

        return [
            'rok' => (int)$monthStart->format('Y'),
            'mesic' => (int)$monthStart->format('n'),
            'from_db' => $monthStart->format('Y-m-d H:i:s'),
            'to_db' => $next->format('Y-m-d H:i:s'),
            'from_z' => $monthStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'to_z' => $next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }
}

if (!function_exists('cb_restia_kontrola_is_done')) {
    function cb_restia_kontrola_is_done(mysqli $conn, int $rok, int $mesic, int $idPob): bool
    {
        $stmt = $conn->prepare('
            SELECT rozdil
            FROM objednavky_kontrola
            WHERE rok = ? AND mesic = ? AND id_pob = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: objednavky_kontrola kontrola.');
        }

        $stmt->bind_param('iii', $rok, $mesic, $idPob);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return is_array($row) && (int)($row['rozdil'] ?? 0) === 0;
    }
}

if (!function_exists('cb_restia_kontrola_db_count')) {
    function cb_restia_kontrola_db_count(mysqli $conn, int $idPob, string $fromDb, string $toDb): int
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
            throw new RuntimeException('DB prepare selhal: objednavky_restia count.');
        }

        $stmt->bind_param('iss', $idPob, $fromDb, $toDb);
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
        ], $activePosId, 'kontrola mesic=' . (string)$range['rok'] . '-' . sprintf('%02d', (int)$range['mesic']) . ' id_pob=' . (string)($branch['id_pob'] ?? 0));

        if ((int)($res['ok'] ?? 0) !== 1) {
            throw new RuntimeException('Restia chyba HTTP=' . (string)($res['http_status'] ?? '') . ' ' . (string)($res['chyba'] ?? ''));
        }

        if (!array_key_exists('total_count', $res) || $res['total_count'] === null) {
            throw new RuntimeException('Restia nevrátila X-Total-Count.');
        }

        return max(0, (int)$res['total_count']);
    }
}

if (!function_exists('cb_restia_kontrola_upsert')) {
    function cb_restia_kontrola_upsert(mysqli $conn, int $rok, int $mesic, int $idPob, int $restiaPocet, int $dbPocet): int
    {
        $rozdil = $dbPocet - $restiaPocet;

        $stmt = $conn->prepare('
            INSERT INTO objednavky_kontrola
                (rok, mesic, id_pob, restia_pocet, db_pocet, rozdil, kontrola_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                restia_pocet = VALUES(restia_pocet),
                db_pocet = VALUES(db_pocet),
                rozdil = VALUES(rozdil),
                kontrola_at = VALUES(kontrola_at)
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: objednavky_kontrola upsert.');
        }

        $stmt->bind_param('iiiiii', $rok, $mesic, $idPob, $restiaPocet, $dbPocet, $rozdil);
        $stmt->execute();
        $stmt->close();

        return $rozdil;
    }
}

if (!function_exists('cb_restia_kontrola_load_branch_rows')) {
    function cb_restia_kontrola_load_branch_rows(mysqli $conn, int $idPob): array
    {
        if ($idPob <= 0) {
            return [];
        }

        $current = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $currentYear = (int)$current->format('Y');
        $currentMonth = (int)$current->format('n');

        $stmt = $conn->prepare('
            SELECT k.rok, k.mesic, k.id_pob, k.restia_pocet, k.db_pocet, k.rozdil, p.nazev
            FROM objednavky_kontrola k
            LEFT JOIN pobocka p ON p.id_pob = k.id_pob
            WHERE k.id_pob = ?
              AND (k.rok < ? OR (k.rok = ? AND k.mesic < ?))
            ORDER BY k.rok ASC, k.mesic ASC
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: objednavky_kontrola výpis.');
        }

        $stmt->bind_param('iiii', $idPob, $currentYear, $currentYear, $currentMonth);
        $stmt->execute();
        $res = $stmt->get_result();

        $out = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $out[] = [
                    'pobocka' => trim((string)($row['nazev'] ?? ('Pobočka #' . (string)$idPob))),
                    'id_pob' => (int)($row['id_pob'] ?? $idPob),
                    'rok' => (int)($row['rok'] ?? 0),
                    'mesic' => (int)($row['mesic'] ?? 0),
                    'restia_pocet' => (int)($row['restia_pocet'] ?? 0),
                    'db_pocet' => (int)($row['db_pocet'] ?? 0),
                    'rozdil' => (int)($row['rozdil'] ?? 0),
                ];
            }
            $res->free();
        }
        $stmt->close();

        return $out;
    }
}

if (!function_exists('cb_restia_kontrola_branch_has_work')) {
    function cb_restia_kontrola_branch_has_work(mysqli $conn, array $branch, DateTimeImmutable $nowMonth): bool
    {
        $idPob = (int)($branch['id_pob'] ?? 0);
        if ($idPob <= 0) {
            return false;
        }

        $month = cb_restia_kontrola_month_start((string)($branch['prvni_obj'] ?? ''));
        while ($month <= $nowMonth) {
            $range = cb_restia_kontrola_month_range($month);
            if (!cb_restia_kontrola_is_done($conn, (int)$range['rok'], (int)$range['mesic'], $idPob)) {
                return true;
            }
            $month = $month->modify('first day of next month')->setTime(0, 0, 0);
        }

        return false;
    }
}

if (!function_exists('cb_restia_kontrola_next_branch')) {
    function cb_restia_kontrola_next_branch(mysqli $conn, array $branches, DateTimeImmutable $nowMonth, int $afterIdPob): ?array
    {
        foreach ($branches as $branch) {
            $idPob = (int)($branch['id_pob'] ?? 0);
            if ($idPob <= $afterIdPob) {
                continue;
            }
            if (cb_restia_kontrola_branch_has_work($conn, $branch, $nowMonth)) {
                return $branch;
            }
        }

        return null;
    }
}

if (!function_exists('cb_restia_kontrola_offset_range')) {
    function cb_restia_kontrola_offset_range(int $rok, int $mesic, int $offsetHours): array
    {
        $tz = new DateTimeZone('Europe/Prague');
        $start = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-01 00:00:00', $rok, $mesic),
            $tz
        );
        if (!($start instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatný měsíc pro diagnostiku.');
        }

        $next = $start->modify('first day of next month')->setTime(0, 0, 0);
        $modifier = ($offsetHours >= 0 ? '+' : '') . (string)$offsetHours . ' hours';

        return [
            'from_z' => $start->modify($modifier)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'to_z' => $next->modify($modifier)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }
}

if (!function_exists('cb_restia_kontrola_restia_count_raw')) {
    function cb_restia_kontrola_restia_count_raw(string $activePosId, string $fromZ, string $toZ, string $note): int
    {
        $res = cb_restia_get('/api/orders', [
            'page' => 1,
            'limit' => 1,
            'createdFrom' => $fromZ,
            'createdTo' => $toZ,
            'activePosId' => $activePosId,
        ], $activePosId, $note);

        if ((int)($res['ok'] ?? 0) !== 1) {
            throw new RuntimeException('Restia diagnostika chyba HTTP=' . (string)($res['http_status'] ?? '') . ' ' . (string)($res['chyba'] ?? ''));
        }

        if (!array_key_exists('total_count', $res) || $res['total_count'] === null) {
            throw new RuntimeException('Restia diagnostika nevrátila X-Total-Count.');
        }

        return max(0, (int)$res['total_count']);
    }
}

if (!function_exists('cb_restia_kontrola_diag_env')) {
    function cb_restia_kontrola_diag_env(): string
    {
        $env = strtoupper(trim((string)($GLOBALS['PROSTREDI'] ?? '')));
        if ($env === 'LOCAL' || $env === 'SERVER') {
            return $env;
        }

        return (PHP_SAPI === 'cli') ? 'LOCAL' : 'SERVER';
    }
}

if (!function_exists('cb_restia_kontrola_diag_path')) {
    function cb_restia_kontrola_diag_path(): string
    {
        return __DIR__ . '/../log/restia_kontrola_diagnostika_' . cb_restia_kontrola_diag_env() . '.txt';
    }
}

if (!function_exists('cb_restia_kontrola_summary_path')) {
    function cb_restia_kontrola_summary_path(): string
    {
        return __DIR__ . '/../log/restia_kontrola_souhrn_' . cb_restia_kontrola_diag_env() . '.txt';
    }
}

if (!function_exists('cb_restia_kontrola_diag_line')) {
    function cb_restia_kontrola_diag_line(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (!is_string($value)) {
            $value = (string)$value;
        }

        return $key . '=' . str_replace(["\r", "\n"], [' ', ' '], $value);
    }
}

if (!function_exists('cb_restia_kontrola_write_diag')) {
    function cb_restia_kontrola_write_diag(mysqli $conn, ?array $branch, array $rows, array $completeRows, array $currentRows, array $completeTotals, array $diagnosticRows, ?array $exampleRange, string $error, string $diagnosticError): void
    {
        $path = cb_restia_kontrola_diag_path();
        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $dbName = '';
        $resDb = $conn->query('SELECT DATABASE() AS db_name');
        if ($resDb instanceof mysqli_result) {
            $rowDb = $resDb->fetch_assoc();
            $dbName = (string)($rowDb['db_name'] ?? '');
            $resDb->free();
        }

        $lines = [];
        $lines[] = '';
        $lines[] = '=== RUN ' . $now->format('Y-m-d H:i:s') . ' Europe/Prague ===';
        $lines[] = cb_restia_kontrola_diag_line('PROSTREDI', cb_restia_kontrola_diag_env());
        $lines[] = cb_restia_kontrola_diag_line('PHP_SAPI', PHP_SAPI);
        $lines[] = cb_restia_kontrola_diag_line('HTTP_HOST', (string)($_SERVER['HTTP_HOST'] ?? ''));
        $lines[] = cb_restia_kontrola_diag_line('SERVER_NAME', (string)($_SERVER['SERVER_NAME'] ?? ''));
        $lines[] = cb_restia_kontrola_diag_line('SCRIPT_NAME', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $lines[] = cb_restia_kontrola_diag_line('DB_NAME', $dbName);
        $lines[] = cb_restia_kontrola_diag_line('ERROR', $error);
        $lines[] = cb_restia_kontrola_diag_line('DIAG_ERROR', $diagnosticError);

        if (is_array($branch)) {
            $lines[] = cb_restia_kontrola_diag_line('POBOCKA_ID', (int)($branch['id_pob'] ?? 0));
            $lines[] = cb_restia_kontrola_diag_line('POBOCKA_NAZEV', (string)($branch['nazev'] ?? ''));
            $lines[] = cb_restia_kontrola_diag_line('ACTIVE_POS_ID', (string)($branch['active_pos_id'] ?? ''));
            $lines[] = cb_restia_kontrola_diag_line('PRVNI_OBJ', (string)($branch['prvni_obj'] ?? ''));
        } else {
            $lines[] = 'POBOCKA=NULL';
        }

        if (is_array($exampleRange)) {
            $lines[] = 'EXAMPLE_RANGE ' . cb_restia_kontrola_diag_line('rok', $exampleRange['rok'] ?? '') . ' ' . cb_restia_kontrola_diag_line('mesic', $exampleRange['mesic'] ?? '');
            $lines[] = cb_restia_kontrola_diag_line('EXAMPLE_RESTIA_FROM_Z', $exampleRange['from_z'] ?? '');
            $lines[] = cb_restia_kontrola_diag_line('EXAMPLE_RESTIA_TO_Z', $exampleRange['to_z'] ?? '');
            $lines[] = cb_restia_kontrola_diag_line('EXAMPLE_DB_FROM', $exampleRange['from_db'] ?? '');
            $lines[] = cb_restia_kontrola_diag_line('EXAMPLE_DB_TO', $exampleRange['to_db'] ?? '');
        }

        $lines[] = 'ROWS_ALL count=' . (string)count($rows);
        foreach ($rows as $row) {
            $lines[] = sprintf(
                'ROW %04d-%02d id_pob=%d restia=%d db=%d diff_db_minus_restia=%+d',
                (int)($row['rok'] ?? 0),
                (int)($row['mesic'] ?? 0),
                (int)($row['id_pob'] ?? 0),
                (int)($row['restia_pocet'] ?? 0),
                (int)($row['db_pocet'] ?? 0),
                (int)($row['rozdil'] ?? 0)
            );
        }

        $lines[] = sprintf(
            'TOTAL_COMPLETE count=%d restia=%d db=%d diff_db_minus_restia=%+d',
            count($completeRows),
            (int)($completeTotals['restia_pocet'] ?? 0),
            (int)($completeTotals['db_pocet'] ?? 0),
            (int)($completeTotals['rozdil'] ?? 0)
        );

        $lines[] = 'CURRENT_ROWS count=' . (string)count($currentRows);
        foreach ($currentRows as $row) {
            $lines[] = sprintf(
                'CURRENT %04d-%02d id_pob=%d restia=%d db=%d diff_db_minus_restia=%+d',
                (int)($row['rok'] ?? 0),
                (int)($row['mesic'] ?? 0),
                (int)($row['id_pob'] ?? 0),
                (int)($row['restia_pocet'] ?? 0),
                (int)($row['db_pocet'] ?? 0),
                (int)($row['rozdil'] ?? 0)
            );
        }

        $lines[] = 'OFFSET_DIAG count=' . (string)count($diagnosticRows);
        foreach ($diagnosticRows as $row) {
            $lines[] = sprintf(
                'OFFSET %+d restia=%d db=%d diff_db_minus_restia=%+d',
                (int)($row['offset'] ?? 0),
                (int)($row['restia_pocet'] ?? 0),
                (int)($row['db_pocet'] ?? 0),
                (int)($row['rozdil'] ?? 0)
            );
        }

        $lines[] = '=== END RUN ===';
        @file_put_contents($path, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_kontrola_write_summary')) {
    function cb_restia_kontrola_write_summary(mysqli $conn): void
    {
        $current = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $currentYear = (int)$current->format('Y');
        $currentMonth = (int)$current->format('n');

        $stmt = $conn->prepare('
            SELECT k.rok, k.mesic, k.id_pob, k.restia_pocet, k.db_pocet, k.rozdil, p.nazev
            FROM objednavky_kontrola k
            LEFT JOIN pobocka p ON p.id_pob = k.id_pob
            WHERE k.rok < ? OR (k.rok = ? AND k.mesic < ?)
            ORDER BY k.id_pob ASC, k.rok ASC, k.mesic ASC
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: objednavky_kontrola souhrn.');
        }

        $stmt->bind_param('iii', $currentYear, $currentYear, $currentMonth);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->free();
        }
        $stmt->close();

        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        $dbName = '';
        $resDb = $conn->query('SELECT DATABASE() AS db_name');
        if ($resDb instanceof mysqli_result) {
            $rowDb = $resDb->fetch_assoc();
            $dbName = (string)($rowDb['db_name'] ?? '');
            $resDb->free();
        }

        $lines = [];
        $lines[] = '=== RESTIA KONTROLA SOUHRN ' . $now->format('Y-m-d H:i:s') . ' Europe/Prague ===';
        $lines[] = cb_restia_kontrola_diag_line('PROSTREDI', cb_restia_kontrola_diag_env());
        $lines[] = cb_restia_kontrola_diag_line('HTTP_HOST', (string)($_SERVER['HTTP_HOST'] ?? ''));
        $lines[] = cb_restia_kontrola_diag_line('SERVER_NAME', (string)($_SERVER['SERVER_NAME'] ?? ''));
        $lines[] = cb_restia_kontrola_diag_line('DB_NAME', $dbName);
        $lines[] = cb_restia_kontrola_diag_line('ONLY_CLOSED_MONTHS_BEFORE', sprintf('%04d-%02d', $currentYear, $currentMonth));
        $lines[] = '';

        $totalRestia = 0;
        $totalDb = 0;
        $totalDiff = 0;
        $branchRestia = 0;
        $branchDb = 0;
        $branchDiff = 0;
        $branchCount = 0;
        $lastPob = null;
        $lastName = '';

        foreach ($rows as $row) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $name = trim((string)($row['nazev'] ?? ('Pobočka #' . (string)$idPob)));

            if ($lastPob !== null && $idPob !== $lastPob) {
                $lines[] = sprintf('POBOCKA_TOTAL id_pob=%d nazev=%s months=%d restia=%d db=%d diff_db_minus_restia=%+d', $lastPob, $lastName, $branchCount, $branchRestia, $branchDb, $branchDiff);
                $lines[] = '';
                $branchRestia = 0;
                $branchDb = 0;
                $branchDiff = 0;
                $branchCount = 0;
            }

            if ($lastPob === null || $idPob !== $lastPob) {
                $lines[] = sprintf('--- POBOCKA id_pob=%d nazev=%s ---', $idPob, $name);
                $lastPob = $idPob;
                $lastName = $name;
            }

            $restia = (int)($row['restia_pocet'] ?? 0);
            $db = (int)($row['db_pocet'] ?? 0);
            $diff = (int)($row['rozdil'] ?? 0);

            $lines[] = sprintf('ROW %04d-%02d restia=%d db=%d diff_db_minus_restia=%+d', (int)($row['rok'] ?? 0), (int)($row['mesic'] ?? 0), $restia, $db, $diff);

            $branchRestia += $restia;
            $branchDb += $db;
            $branchDiff += $diff;
            $branchCount++;
            $totalRestia += $restia;
            $totalDb += $db;
            $totalDiff += $diff;
        }

        if ($lastPob !== null) {
            $lines[] = sprintf('POBOCKA_TOTAL id_pob=%d nazev=%s months=%d restia=%d db=%d diff_db_minus_restia=%+d', $lastPob, $lastName, $branchCount, $branchRestia, $branchDb, $branchDiff);
            $lines[] = '';
        }

        $lines[] = sprintf('ALL_TOTAL rows=%d restia=%d db=%d diff_db_minus_restia=%+d', count($rows), $totalRestia, $totalDb, $totalDiff);
        $lines[] = '=== END SOUHRN ===';

        @file_put_contents(cb_restia_kontrola_summary_path(), implode("\n", $lines) . "\n", LOCK_EX);
    }
}

$conn = db();
$conn->set_charset('utf8mb4');
$auth = cb_restia_kontrola_get_auth();
$rows = [];
$stats = [
    'checked' => 0,
    'skipped' => 0,
    'diff' => 0,
];
$error = '';
$selectedBranch = null;
$hasNextBranch = false;
$afterBranchId = max(0, (int)($_POST['cb_restia_kontrola_after'] ?? 0));

try {
    if (!cb_restia_kontrola_table_exists($conn)) {
        throw new RuntimeException('Chybí tabulka objednavky_kontrola.');
    }

    $branches = cb_restia_kontrola_get_branches($conn);
    $lastClosedMonth = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->modify('first day of this month')->setTime(0, 0, 0)->modify('-1 month');
    $selectedBranch = cb_restia_kontrola_next_branch($conn, $branches, $lastClosedMonth, $afterBranchId);

    if ($selectedBranch !== null) {
        $branch = $selectedBranch;
        $idPob = (int)($branch['id_pob'] ?? 0);

        $month = cb_restia_kontrola_month_start((string)($branch['prvni_obj'] ?? ''));
        while ($month <= $lastClosedMonth) {
            $range = cb_restia_kontrola_month_range($month);
            $rok = (int)$range['rok'];
            $mesic = (int)$range['mesic'];

            if (cb_restia_kontrola_is_done($conn, $rok, $mesic, $idPob)) {
                $stats['skipped']++;
                $month = $month->modify('first day of next month')->setTime(0, 0, 0);
                continue;
            }

            $restiaPocet = cb_restia_kontrola_restia_count($branch, $range);
            $dbPocet = cb_restia_kontrola_db_count($conn, $idPob, (string)$range['from_db'], (string)$range['to_db']);
            $rozdil = cb_restia_kontrola_upsert($conn, $rok, $mesic, $idPob, $restiaPocet, $dbPocet);

            $stats['checked']++;
            if ($rozdil !== 0) {
                $stats['diff']++;
            }

            $rows[] = [
                'pobocka' => (string)($branch['nazev'] ?? ('Pobočka #' . (string)$idPob)),
                'id_pob' => $idPob,
                'rok' => $rok,
                'mesic' => $mesic,
                'restia_pocet' => $restiaPocet,
                'db_pocet' => $dbPocet,
                'rozdil' => $rozdil,
            ];

            $month = $month->modify('first day of next month')->setTime(0, 0, 0);
        }

        $hasNextBranch = (cb_restia_kontrola_next_branch($conn, $branches, $lastClosedMonth, $idPob) !== null);
        $rows = cb_restia_kontrola_load_branch_rows($conn, $idPob);
    }

    db_api_restia_flush($conn, $auth['id_user'], $auth['id_login']);
} catch (Throwable $e) {
    $error = $e->getMessage();
    try {
        db_api_restia_flush($conn, $auth['id_user'], $auth['id_login']);
    } catch (Throwable $flushError) {
        $error .= ' / api_restia flush: ' . $flushError->getMessage();
    }
}

$displayNow = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
$displayCurrentYear = (int)$displayNow->format('Y');
$displayCurrentMonth = (int)$displayNow->format('n');
$completeRows = [];
$currentRows = [];
$completeTotals = [
    'restia_pocet' => 0,
    'db_pocet' => 0,
    'rozdil' => 0,
];
foreach ($rows as $row) {
    $rowYear = (int)($row['rok'] ?? 0);
    $rowMonth = (int)($row['mesic'] ?? 0);
    if ($rowYear === $displayCurrentYear && $rowMonth === $displayCurrentMonth) {
        $currentRows[] = $row;
        continue;
    }

    $completeRows[] = $row;
    $completeTotals['restia_pocet'] += (int)($row['restia_pocet'] ?? 0);
    $completeTotals['db_pocet'] += (int)($row['db_pocet'] ?? 0);
    $completeTotals['rozdil'] += (int)($row['rozdil'] ?? 0);
}

$exampleRow = $completeRows[0] ?? ($currentRows[0] ?? null);
$exampleRange = null;
if (is_array($exampleRow)) {
    $exampleYear = (int)($exampleRow['rok'] ?? 0);
    $exampleMonth = (int)($exampleRow['mesic'] ?? 0);
    if ($exampleYear > 0 && $exampleMonth >= 1 && $exampleMonth <= 12) {
        $exampleStart = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%04d-%02d-01 00:00:00', $exampleYear, $exampleMonth),
            new DateTimeZone('Europe/Prague')
        );
        if ($exampleStart instanceof DateTimeImmutable) {
            $exampleRange = cb_restia_kontrola_month_range($exampleStart);
        }
    }
}

$diagnosticRows = [];
$diagnosticError = '';
if ($error === '' && is_array($selectedBranch) && count($completeRows) > 0) {
    $activePosIdDiag = (string)($selectedBranch['active_pos_id'] ?? '');
    if ($activePosIdDiag !== '') {
        try {
            foreach ([-2, -1, 0, 1, 2] as $offsetHours) {
                $dbTotalDiag = 0;
                $restiaTotalDiag = 0;
                foreach ($completeRows as $row) {
                    $rokDiag = (int)($row['rok'] ?? 0);
                    $mesicDiag = (int)($row['mesic'] ?? 0);
                    if ($rokDiag <= 0 || $mesicDiag < 1 || $mesicDiag > 12) {
                        continue;
                    }
                    $rangeDiag = cb_restia_kontrola_offset_range($rokDiag, $mesicDiag, $offsetHours);
                    $restiaTotalDiag += cb_restia_kontrola_restia_count_raw(
                        $activePosIdDiag,
                        (string)$rangeDiag['from_z'],
                        (string)$rangeDiag['to_z'],
                        'diagnostika offset=' . (string)$offsetHours . ' mesic=' . (string)$rokDiag . '-' . sprintf('%02d', $mesicDiag) . ' id_pob=' . (string)($selectedBranch['id_pob'] ?? 0)
                    );
                    $dbTotalDiag += (int)($row['db_pocet'] ?? 0);
                }

                $diagnosticRows[] = [
                    'offset' => $offsetHours,
                    'db_pocet' => $dbTotalDiag,
                    'restia_pocet' => $restiaTotalDiag,
                    'rozdil' => $dbTotalDiag - $restiaTotalDiag,
                ];
            }
        } catch (Throwable $diagError) {
            $diagnosticError = $diagError->getMessage();
        }

        try {
            db_api_restia_flush($conn, $auth['id_user'], $auth['id_login']);
        } catch (Throwable $flushError) {
            $diagnosticError = trim($diagnosticError . ' / api_restia flush: ' . $flushError->getMessage(), ' /');
        }
    }
}

cb_restia_kontrola_write_diag(
    $conn,
    is_array($selectedBranch) ? $selectedBranch : null,
    $rows,
    $completeRows,
    $currentRows,
    $completeTotals,
    $diagnosticRows,
    $exampleRange,
    $error,
    $diagnosticError
);
cb_restia_kontrola_write_summary($conn);
?>
<div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <p class="card_title txt_seda text_18 text_tucny odstup_vnejsi_0">Kontrola Restia objednávek</p>
  <p class="card_text txt_seda odstup_vnejsi_0">Kontrola porovnává kalendářní měsíce podle času vytvoření objednávky.</p>
  <?php if (is_array($selectedBranch)): ?>
    <p class="card_text txt_seda odstup_vnejsi_0">Zpracovaná pobočka: <?= cb_restia_kontrola_h((string)($selectedBranch['nazev'] ?? '')) ?> (<?= cb_restia_kontrola_h((string)($selectedBranch['id_pob'] ?? '')) ?>)</p>
  <?php endif; ?>
  <div class="table-wrap ram_normal bg_bila zaobleni_8 odstup_vnejsi_10 odstup_vnitrni_10">
    <p class="card_text txt_seda text_tucny odstup_vnejsi_0">Použité období</p>
    <p class="card_text txt_seda odstup_vnejsi_0">Restia: <code>createdFrom</code> je začátek kalendářního měsíce Praha převedený do UTC, <code>createdTo</code> je začátek dalšího kalendářního měsíce Praha převedený do UTC.</p>
    <p class="card_text txt_seda odstup_vnejsi_0">DB: <code>restia_created_at</code> je větší nebo rovno začátku kalendářního měsíce Praha a menší než začátek dalšího kalendářního měsíce Praha.</p>
    <?php if (is_array($exampleRange)): ?>
      <p class="card_text txt_seda odstup_vnejsi_0">
        Příklad <?= cb_restia_kontrola_h((string)$exampleRange['rok']) ?>-<?= cb_restia_kontrola_h(sprintf('%02d', (int)$exampleRange['mesic'])) ?>:
        Restia <?= cb_restia_kontrola_h((string)$exampleRange['from_z']) ?> až <?= cb_restia_kontrola_h((string)$exampleRange['to_z']) ?>,
        DB <?= cb_restia_kontrola_h((string)$exampleRange['from_db']) ?> až <?= cb_restia_kontrola_h((string)$exampleRange['to_db']) ?>.
      </p>
    <?php endif; ?>
  </div>

  <?php if ($error !== ''): ?>
    <p class="card_text txt_cervena text_tucny"><?= cb_restia_kontrola_h($error) ?></p>
  <?php else: ?>
    <p class="card_text txt_seda">
      Zkontrolováno: <?= cb_restia_kontrola_h((string)$stats['checked']) ?> |
      přeskočeno s rozdílem 0: <?= cb_restia_kontrola_h((string)$stats['skipped']) ?> |
      rozdíl nenula: <?= cb_restia_kontrola_h((string)$stats['diff']) ?>
    </p>
  <?php endif; ?>

  <?php if (count($completeRows) > 0): ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_8 odstup_vnejsi_10" style="overflow:auto;">
      <table class="table sirka100">
        <thead>
          <tr>
            <th class="txt_l">Pobočka</th>
            <th class="txt_r">Rok</th>
            <th class="txt_r">Měsíc</th>
            <th class="txt_r">Restia</th>
            <th class="txt_r">DB</th>
            <th class="txt_r">Rozdíl</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($completeRows as $row): ?>
            <?php $diff = (int)($row['rozdil'] ?? 0); ?>
            <tr>
              <td><?= cb_restia_kontrola_h((string)($row['pobocka'] ?? '')) ?> (<?= cb_restia_kontrola_h((string)($row['id_pob'] ?? '')) ?>)</td>
              <td class="txt_r"><?= cb_restia_kontrola_h((string)($row['rok'] ?? '')) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(sprintf('%02d', (int)($row['mesic'] ?? 0))) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(number_format((int)($row['restia_pocet'] ?? 0), 0, ',', ' ')) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(number_format((int)($row['db_pocet'] ?? 0), 0, ',', ' ')) ?></td>
              <td class="txt_r <?= $diff === 0 ? 'txt_zelena' : 'txt_cervena text_tucny' ?>"><?= cb_restia_kontrola_h(number_format($diff, 0, ',', ' ')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php $totalDiff = (int)$completeTotals['rozdil']; ?>
          <tr>
            <td class="text_tucny" colspan="3">Celkem kompletní měsíce</td>
            <td class="txt_r text_tucny"><?= cb_restia_kontrola_h(number_format((int)$completeTotals['restia_pocet'], 0, ',', ' ')) ?></td>
            <td class="txt_r text_tucny"><?= cb_restia_kontrola_h(number_format((int)$completeTotals['db_pocet'], 0, ',', ' ')) ?></td>
            <td class="txt_r text_tucny <?= $totalDiff === 0 ? 'txt_zelena' : 'txt_cervena' ?>"><?= cb_restia_kontrola_h(number_format($totalDiff, 0, ',', ' ')) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <p class="card_text txt_zelena text_tucny">Nebyl nalezen žádný kompletní měsíc ke kontrole.</p>
  <?php endif; ?>

  <?php if (count($currentRows) > 0): ?>
    <p class="card_text txt_seda text_tucny" style="margin-top:14px;">Aktuální nekompletní měsíc</p>
    <div class="table-wrap ram_normal bg_bila zaobleni_8 odstup_vnejsi_10" style="overflow:auto;">
      <table class="table sirka100">
        <thead>
          <tr>
            <th class="txt_l">Pobočka</th>
            <th class="txt_r">Rok</th>
            <th class="txt_r">Měsíc</th>
            <th class="txt_r">Restia</th>
            <th class="txt_r">DB</th>
            <th class="txt_r">Rozdíl</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($currentRows as $row): ?>
            <?php $diff = (int)($row['rozdil'] ?? 0); ?>
            <tr>
              <td><?= cb_restia_kontrola_h((string)($row['pobocka'] ?? '')) ?> (<?= cb_restia_kontrola_h((string)($row['id_pob'] ?? '')) ?>)</td>
              <td class="txt_r"><?= cb_restia_kontrola_h((string)($row['rok'] ?? '')) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(sprintf('%02d', (int)($row['mesic'] ?? 0))) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(number_format((int)($row['restia_pocet'] ?? 0), 0, ',', ' ')) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(number_format((int)($row['db_pocet'] ?? 0), 0, ',', ' ')) ?></td>
              <td class="txt_r <?= $diff === 0 ? 'txt_zelena' : 'txt_cervena text_tucny' ?>"><?= cb_restia_kontrola_h(number_format($diff, 0, ',', ' ')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php elseif (count($rows) === 0): ?>
    <p class="card_text txt_zelena text_tucny">Nebyla nalezena žádná další pobočka ke kontrole.</p>
  <?php endif; ?>

  <?php if ($diagnosticError !== ''): ?>
    <p class="card_text txt_cervena text_tucny">Diagnostika hranice období: <?= cb_restia_kontrola_h($diagnosticError) ?></p>
  <?php elseif (count($diagnosticRows) > 0): ?>
    <p class="card_text txt_seda text_tucny" style="margin-top:14px;">Diagnostika hranice období</p>
    <p class="card_text txt_seda odstup_vnejsi_0">Součty kompletních měsíců při posunu hranice Restia dotazu. DB zůstává stejná, mění se jen <code>createdFrom</code>/<code>createdTo</code> poslané do Restie.</p>
    <div class="table-wrap ram_normal bg_bila zaobleni_8 odstup_vnejsi_10" style="overflow:auto;">
      <table class="table">
        <thead>
          <tr>
            <th class="txt_r">Posun hodin</th>
            <th class="txt_r">Restia</th>
            <th class="txt_r">DB</th>
            <th class="txt_r">Rozdíl DB - Restia</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($diagnosticRows as $diagRow): ?>
            <?php $diagDiff = (int)($diagRow['rozdil'] ?? 0); ?>
            <?php $diagOffset = (int)($diagRow['offset'] ?? 0); ?>
            <tr>
              <td class="txt_r"><?= cb_restia_kontrola_h(($diagOffset >= 0 ? '+' : '') . (string)$diagOffset) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(number_format((int)($diagRow['restia_pocet'] ?? 0), 0, ',', ' ')) ?></td>
              <td class="txt_r"><?= cb_restia_kontrola_h(number_format((int)($diagRow['db_pocet'] ?? 0), 0, ',', ' ')) ?></td>
              <td class="txt_r <?= $diagDiff === 0 ? 'txt_zelena' : 'txt_cervena text_tucny' ?>"><?= cb_restia_kontrola_h(number_format($diagDiff, 0, ',', ' ')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="displ_flex gap_8" style="margin-top:16px; justify-content:flex-end;">
    <?php if ($error === '' && is_array($selectedBranch) && $hasNextBranch): ?>
      <form method="post" action="<?= cb_restia_kontrola_h((string)cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex" data-cb-max-form="1">
        <input type="hidden" name="run_restia_kontrola" value="1">
        <input type="hidden" name="cb_restia_kontrola_after" value="<?= cb_restia_kontrola_h((string)($selectedBranch['id_pob'] ?? 0)) ?>">
        <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-loader-text="Kontroluji další pobočku">Pokračovat další pobočkou</button>
      </form>
    <?php endif; ?>
    <form method="post" action="<?= cb_restia_kontrola_h((string)cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex">
      <input type="hidden" name="back_admin_init" value="1">
      <button type="submit" class="card_btn cursor_ruka ram_btn zaobleni_6 vyska_28 displ_inline_flex" style="background:var(--clr_ruzova_4); border-color:var(--clr_ruzova_1); color:var(--clr_cervena);">Zpět</button>
    </form>
  </div>
</div>

<?php
// inicializace/kontrola_restia_objednavky.php * Verze: V1 * Aktualizace: 27.05.2026
// Konec souboru
