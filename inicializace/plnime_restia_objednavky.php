<?php
// inicializace/plnime_restia_objednavky.php * Verze: V5 * Aktualizace: 10.04.2026
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
require_once __DIR__ . '/../db/zapis_log_chyby.php';

const CB_RESTIA_HIST_LIMIT = 200;

$cbRestiaEmbedMode = (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php');
$cbStateKey = 'cb_restia_hist_v4_state';
$cbRowsKey = 'cb_restia_hist_v4_rows';
$cbMsgKey = 'cb_restia_hist_v4_msg';

if (!function_exists('cb_restia_hist_h')) {
    function cb_restia_hist_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_hist_txt_path')) {
    function cb_restia_hist_txt_path(int $idPob): string
    {
        $safeIdPob = max(0, $idPob);
        return __DIR__ . '/../log/plnime_restia_objednavky_' . $safeIdPob . '.txt';
    }
}

if (!function_exists('cb_restia_hist_log_init')) {
    function cb_restia_hist_log_init(int $idPob): void
    {
        $path = cb_restia_hist_txt_path($idPob);
        if (!is_file($path)) {
            @file_put_contents($path, '', FILE_APPEND | LOCK_EX);
        }
    }
}

if (!function_exists('cb_restia_hist_log')) {
    function cb_restia_hist_log(int $idPob, string $line): void
    {
        $path = cb_restia_hist_txt_path($idPob);
        $newLine = $line . "\n";
        $current = is_file($path) ? (string)@file_get_contents($path) : '';
        @file_put_contents($path, $newLine . $current, LOCK_EX);
    }
}

if (!function_exists('cb_restia_hist_error_txt_path')) {
    function cb_restia_hist_error_txt_path(int $idPob): string
    {
        $safeIdPob = max(0, $idPob);
        return __DIR__ . '/../log/plnime_restia_objednavky_' . $safeIdPob . '_error.txt';
    }
}

if (!function_exists('cb_restia_hist_error_log_init')) {
    function cb_restia_hist_error_log_init(int $idPob): void
    {
    }
}

if (!function_exists('cb_restia_hist_error_log')) {
    function cb_restia_hist_error_log(int $idPob, string $line): void
    {
        @file_put_contents(cb_restia_hist_error_txt_path($idPob), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_hist_now')) {
    function cb_restia_hist_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_restia_hist_today')) {
    function cb_restia_hist_today(): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    }
}

if (!function_exists('cb_restia_hist_format_date_cs')) {
    function cb_restia_hist_format_date_cs(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro formatovani: ' . $date);
        }
        return $dt->format('d.m.Y');
    }
}

if (!function_exists('cb_restia_hist_format_date_input_cs')) {
    function cb_restia_hist_format_date_input_cs(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro vstup: ' . $date);
        }
        return $dt->format('j.n.Y');
    }
}

if (!function_exists('cb_restia_hist_format_month_year_cs')) {
    function cb_restia_hist_format_month_year_cs(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro mesic: ' . $date);
        }
        static $months = [
            1 => 'leden',
            2 => 'únor',
            3 => 'březen',
            4 => 'duben',
            5 => 'květen',
            6 => 'červen',
            7 => 'červenec',
            8 => 'srpen',
            9 => 'září',
            10 => 'říjen',
            11 => 'listopad',
            12 => 'prosinec',
        ];
        $month = (int)$dt->format('n');
        return (($months[$month] ?? '') . ' ' . $dt->format('Y'));
    }
}

if (!function_exists('cb_restia_hist_normalize_ymd')) {
    function cb_restia_hist_normalize_ymd(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m) === 1) {
            $y = (int)$m[1];
            $mo = (int)$m[2];
            $d = (int)$m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        throw new RuntimeException('Neplatne datum Y-m-d: ' . $raw);
    }
}

if (!function_exists('cb_restia_hist_count_months_between')) {
    function cb_restia_hist_count_months_between(string $startYmd, string $endYmd): int
    {
        $startYmd = cb_restia_hist_normalize_ymd($startYmd);
        $endYmd = cb_restia_hist_normalize_ymd($endYmd);
        if ($startYmd === '' || $endYmd === '') {
            return 0;
        }

        $tz = new DateTimeZone('Europe/Prague');
        $startDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startYmd . ' 00:00:00', $tz);
        $endDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endYmd . ' 00:00:00', $tz);
        if (!($startDt instanceof DateTimeImmutable) || !($endDt instanceof DateTimeImmutable) || $endDt < $startDt) {
            return 0;
        }

        $months = ((int)$endDt->format('Y') - (int)$startDt->format('Y')) * 12 + ((int)$endDt->format('n') - (int)$startDt->format('n')) + 1;
        return max(0, $months);
    }
}

if (!function_exists('cb_restia_hist_format_days_months')) {
    function cb_restia_hist_format_days_months(int $days, int $months): string
    {
        return cb_restia_hist_h((string)max(0, $days)) . ' dnů / ' . cb_restia_hist_h((string)max(0, $months)) . ' měsíců';
    }
}

if (!function_exists('cb_restia_hist_format_datetime_cs')) {
    function cb_restia_hist_format_datetime_cs(string $datetime): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $datetime;
        }
        return $dt->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_hist_next_date')) {
    function cb_restia_hist_next_date(string $date): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro posun.');
        }

        return $dt->modify('+1 day')->format('Y-m-d');
    }
}

if (!function_exists('cb_restia_hist_day_range_utc')) {
    function cb_restia_hist_day_range_utc(string $date): array
    {
        $tz = new DateTimeZone('Europe/Prague');
        $fromLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $date . ' 00:00:00.000000', $tz);
        $toLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $date . ' 23:59:59.999000', $tz);

        if (!($fromLocal instanceof DateTimeImmutable) || !($toLocal instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatny den pro interval.');
        }

        return [
            'from_z' => $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'to_z' => $toLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'from_db' => $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'to_db' => $toLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('cb_restia_hist_extract_orders')) {
    function cb_restia_hist_extract_orders(array $json): array
    {
        if (array_is_list($json)) {
            return $json;
        }
        if (isset($json['data']) && is_array($json['data']) && array_is_list($json['data'])) {
            return $json['data'];
        }
        if (isset($json['orders']) && is_array($json['orders']) && array_is_list($json['orders'])) {
            return $json['orders'];
        }
        return [];
    }
}

if (!function_exists('cb_restia_hist_json')) {
    function cb_restia_hist_json(mixed $value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return null;
        }
        return $json;
    }
}

if (!function_exists('cb_restia_hist_hash32')) {
    function cb_restia_hist_hash32(string $value): string
    {
        $bin = hash('sha256', $value, true);
        return is_string($bin) ? $bin : str_repeat("\0", 32);
    }
}

if (!function_exists('cb_restia_hist_get_auth')) {
    function cb_restia_hist_get_auth(): array
    {
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = (int)(is_array($user) ? ($user['id_user'] ?? 0) : 0);
        $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);

        if ($idUser <= 0 || $idLogin <= 0) {
            throw new RuntimeException('Chybi prihlaseny uzivatel nebo id_login.');
        }

        return [
            'id_user' => $idUser,
            'id_login' => $idLogin,
        ];
    }
}

if (!function_exists('cb_restia_hist_get_branches')) {
    function cb_restia_hist_get_branches(mysqli $conn): array
    {
        $sql = '
            SELECT id_pob, nazev, restia_activePosId, prvni_obj
            FROM pobocka
            ORDER BY id_pob ASC
        ';

        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na pobocky selhal.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $activePosId = trim((string)($row['restia_activePosId'] ?? ''));
            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => trim((string)($row['nazev'] ?? '')),
                'active_pos_id' => $activePosId,
                'prvni_obj' => trim((string)($row['prvni_obj'] ?? '')),
                'enabled' => ($activePosId !== ''),
            ];
        }
        $res->free();

        return $out;
    }
}

if (!function_exists('cb_restia_hist_get_branch_by_id')) {
    function cb_restia_hist_get_branch_by_id(mysqli $conn, int $idPob): array
    {
        if ($idPob <= 0) {
            throw new RuntimeException('Neplatna pobocka.');
        }

        $stmt = $conn->prepare('
            SELECT id_pob, nazev, restia_activePosId, prvni_obj
            FROM pobocka
            WHERE id_pob = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB dotaz na pobocku selhal.');
        }
        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        if (!is_array($row)) {
            throw new RuntimeException('Vybrana pobocka neexistuje.');
        }

        $activePosId = trim((string)($row['restia_activePosId'] ?? ''));
        if ($activePosId === '') {
            throw new RuntimeException('Vybrana pobocka nema vyplnene restia_activePosId.');
        }

        return [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'nazev' => trim((string)($row['nazev'] ?? '')),
            'active_pos_id' => $activePosId,
            'prvni_obj' => trim((string)($row['prvni_obj'] ?? '')),
        ];
    }
}

if (!function_exists('cb_restia_hist_pick_default_branch')) {
    function cb_restia_hist_pick_default_branch(mysqli $conn): array
    {
        $branches = cb_restia_hist_get_branches($conn);
        foreach ($branches as $branch) {
            if ((bool)($branch['enabled'] ?? false) === true) {
                return [
                    'id_pob' => (int)($branch['id_pob'] ?? 0),
                    'nazev' => (string)($branch['nazev'] ?? ''),
                    'active_pos_id' => (string)($branch['active_pos_id'] ?? ''),
                    'prvni_obj' => (string)($branch['prvni_obj'] ?? ''),
                ];
            }
        }
        throw new RuntimeException('Neni zadna pobocka s vyplnenym restia_activePosId.');
    }
}


if (!function_exists('cb_restia_hist_branch_db_count')) {
    function cb_restia_hist_branch_db_count(mysqli $conn, int $idPob): int
    {
        if ($idPob <= 0) {
            return 0;
        }

        $stmt = $conn->prepare('
            SELECT COUNT(*) AS cnt
            FROM objednavky_restia
            WHERE id_pob = ?
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: objednavky_restia count.');
        }

        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $res = $stmt->get_result();
        $count = 0;
        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            $count = (int)($row['cnt'] ?? 0);
            $res->free();
        }
        $stmt->close();

        return $count;
    }
}

if (!function_exists('cb_restia_hist_run_batch')) {
    function cb_restia_hist_run_batch(mysqli $conn, array $auth, array $branch, array &$state, array &$rows, string &$message): void
    {
        if ((int)($state['finished'] ?? 0) !== 0) {
            return;
        }

        $today = cb_restia_hist_today();
        $foundNow = 0;
        $cycleMonth = '';

        while (true) {
            $date = (string)($state['next_date'] ?? '');
            if ($date === '') {
                $resume = cb_restia_hist_resume_info($conn, $branch);
                $date = (string)($resume['next_date'] ?? '');
                $state['next_date'] = $date;
                $state['start_date'] = (string)($resume['start_date'] ?? '');
                $state['last_date'] = (string)($resume['last_date'] ?? '');
            }

            if ($date > $today) {
                $state['finished'] = 1;
                $message = 'Akce skončila. Došli jsme do aktuálního dne.';
                cb_restia_hist_log((int)($branch['id_pob'] ?? 0), 'KONEC IMPORTU: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now()));
                break;
            }

            $day = cb_restia_hist_import_day($conn, $auth, $branch, $date);
            $startedAtMs = (int)($state['run_started_at_ms'] ?? 0);
            $nowMs = (int)round(microtime(true) * 1000);
            $day['total_ms'] = ($startedAtMs > 0) ? max(0, $nowMs - $startedAtMs) : 0;
            $rows[] = $day;
            if (count($rows) > 200) {
                array_shift($rows);
            }

            $state['run_row_no'] = (int)($state['run_row_no'] ?? 0) + 1;
            $state['next_date'] = cb_restia_hist_next_date($date);

            if ((int)($day['ok'] ?? 0) === 1) {
                $state['last_date'] = $date;
            } else {
                $state['finished'] = 1;
                if ($message === '') {
                    $message = (string)($day['error'] ?? '');
                }
                break;
            }

            $dayCount = max(0, (int)($day['count'] ?? 0));
            $foundNow += $dayCount;
            $state['found_total'] = (int)($state['found_total'] ?? 0) + $dayCount;
            $state['branch_db_count'] = (int)($state['branch_db_count'] ?? 0) + $dayCount;

            cb_restia_hist_log(
                (int)($branch['id_pob'] ?? 0),
                (string)($state['run_row_no'] ?? 0)
                . ' / ' . cb_restia_hist_format_date_cs((string)($day['date'] ?? $date))
                . ' / ' . (string)$dayCount . ' - ' . (string)(int)($state['branch_db_count'] ?? 0)
                . ' / ' . cb_restia_hist_format_total_time((int)($day['total_ms'] ?? 0))
            );

            // STOPKA NA ČAS – max 1:45 (105000 ms)
            $startedAtMs = (int)($state['run_started_at_ms'] ?? 0);
            $nowMsCheck = (int)round(microtime(true) * 1000);
            if ($startedAtMs > 0 && ($nowMsCheck - $startedAtMs) >= 105000) {
                $state['finished'] = 1;
                if ($message === '') {
                    $message = 'Import dočasně ukončen kvůli časovému limitu, spusť znovu.';
                }
                cb_restia_hist_log(
                    (int)($branch['id_pob'] ?? 0),
                    'STOPKA LIMIT: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now())
                );
                break;
            }


            $currentMonth = substr((string)$date, 0, 7);
            $nextMonth = substr((string)($state['next_date'] ?? ''), 0, 7);
            if ($currentMonth !== '' && $nextMonth !== '' && $nextMonth !== $currentMonth) {
                $cycleMonth = cb_restia_hist_format_month_year_cs((string)$date);
                $state['finished'] = 1;
                break;
            }

            $cycleMonth = cb_restia_hist_format_month_year_cs((string)$date);
        }

        if ((int)($state['finished'] ?? 0) === 1 && $message === '') {
            $branchName = (string)($branch['nazev'] ?? '');
            if ($cycleMonth === '') {
                $cycleMonth = cb_restia_hist_format_month_year_cs((string)($day['date'] ?? $date));
            }
            $message = 'Import ' . $cycleMonth . ' pro pobočku "' . $branchName . '", uloženo ' . (string)$foundNow . ' objednávek.';
            cb_restia_hist_log((int)($branch['id_pob'] ?? 0), 'KONEC CYKLU: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now()));
        }
    }
}


if (!function_exists('cb_restia_hist_branch_start_date')) {
    function cb_restia_hist_branch_start_date(array $branch): string
    {
        $startDate = cb_restia_hist_normalize_ymd((string)($branch['prvni_obj'] ?? ''));
        if ($startDate === '') {
            throw new RuntimeException('Chybi prvni_obj pro pobocku id=' . (string)($branch['id_pob'] ?? 0));
        }
        return $startDate;
    }
}

if (!function_exists('cb_restia_hist_last_date')) {
    function cb_restia_hist_last_date(mysqli $conn, int $idPob): string
    {
        $stmt = $conn->prepare('
            SELECT MAX(datum_do) AS last_dt
            FROM obj_import
            WHERE typ_importu = "historie"
              AND id_pob = ?              
        ');
        if ($stmt === false) {
            return '';
        }

        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        $dt = (string)($row['last_dt'] ?? '');
        if ($dt === '') {
            return '';
        }

        return substr($dt, 0, 10);
    }
}

if (!function_exists('cb_restia_hist_resume_info')) {
    function cb_restia_hist_resume_info(mysqli $conn, array $branch): array
    {
        $idPob = (int)($branch['id_pob'] ?? 0);
        $startDate = cb_restia_hist_branch_start_date($branch);
        $lastOkDate = ($idPob > 0) ? cb_restia_hist_last_date($conn, $idPob) : '';
        $nextDate = $startDate;

        if ($lastOkDate !== '') {
            $nextDate = cb_restia_hist_next_date($lastOkDate);
            if ($nextDate < $startDate) {
                throw new RuntimeException('Neposledni datum importu je mensi nez start historie pro pobocku id=' . (string)$idPob);
            }
        }

        return [
            'start_date' => $startDate,
            'last_date' => $lastOkDate,
            'next_date' => $nextDate,
        ];
    }
}

if (!function_exists('cb_restia_hist_day_lock_name')) {
    function cb_restia_hist_day_lock_name(int $idPob, string $date): string
    {
        return 'cb_restia_hist_' . $idPob . '_' . $date;
    }
}

if (!function_exists('cb_restia_hist_day_lock_acquire')) {
    function cb_restia_hist_day_lock_acquire(mysqli $conn, int $idPob, string $date, int $timeoutSec = 10): string
    {
        $lockName = cb_restia_hist_day_lock_name($idPob, $date);
        $stmt = $conn->prepare('SELECT GET_LOCK(?, ?) AS got_lock');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: day lock acquire.');
        }
        $stmt->bind_param('si', $lockName, $timeoutSec);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        if ((int)($row['got_lock'] ?? 0) !== 1) {
            throw new RuntimeException('Nepodarilo se ziskat zamek pro denni import.');
        }

        return $lockName;
    }
}

if (!function_exists('cb_restia_hist_day_lock_release')) {
    function cb_restia_hist_day_lock_release(mysqli $conn, string $lockName): void
    {
        if ($lockName === '') {
            return;
        }
        $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param('s', $lockName);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_hist_lookup_id')) {
    function cb_restia_hist_lookup_id(mysqli $conn, string $table, string $valueCol, string $value, string $idCol): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $sqlSel = 'SELECT `' . $idCol . '` AS id FROM `' . $table . '` WHERE `' . $valueCol . '` = ? LIMIT 1';
        $stmtSel = $conn->prepare($sqlSel);
        if ($stmtSel === false) {
            throw new RuntimeException('DB prepare selhal: ' . $table . ' select.');
        }
        $stmtSel->bind_param('s', $value);
        $stmtSel->execute();
        $resSel = $stmtSel->get_result();
        if ($resSel && ($row = $resSel->fetch_assoc())) {
            $id = (int)($row['id'] ?? 0);
            $resSel->free();
            $stmtSel->close();
            return $id;
        }
        if ($resSel) {
            $resSel->free();
        }
        $stmtSel->close();

        $sqlIns = 'INSERT INTO `' . $table . '` (`' . $valueCol . '`, `aktivni`) VALUES (?, 1)';
        $stmtIns = $conn->prepare($sqlIns);
        if ($stmtIns === false) {
            throw new RuntimeException('DB prepare selhal: ' . $table . ' insert.');
        }
        $stmtIns->bind_param('s', $value);
        $stmtIns->execute();
        $id = (int)$conn->insert_id;
        $stmtIns->close();

        return $id;
    }
}
if (!function_exists('cb_restia_hist_lookup_res_polozka_id')) {
    function cb_restia_hist_lookup_res_polozka_id(mysqli $conn, int $idPob, string $restiaItemId): int
    {
        $restiaItemId = trim($restiaItemId);
        if ($idPob <= 0 || $restiaItemId === '') {
            return 0;
        }

        $stmt = $conn->prepare('
            SELECT id_res_polozka AS id
            FROM res_polozky
            WHERE id_pob = ? AND pos_code = ?
            ORDER BY id_res_polozka DESC
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: res_polozky lookup.');
        }

        $stmt->bind_param('is', $idPob, $restiaItemId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return (int)($row['id'] ?? 0);
    }
}
if (!function_exists('cb_restia_hist_order_exists')) {
    function cb_restia_hist_order_exists(mysqli $conn, string $restiaId): bool
    {
        $stmt = $conn->prepare('SELECT 1 FROM objednavky_restia WHERE restia_id_obj = ? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: order exists.');
        }

        $stmt->bind_param('s', $restiaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = ($res instanceof mysqli_result) ? ($res->fetch_assoc() !== null) : false;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return $exists;
    }
}

if (!function_exists('cb_restia_hist_get_obj_id')) {
    function cb_restia_hist_get_obj_id(mysqli $conn, string $restiaIdObj): int
    {
        $stmt = $conn->prepare('SELECT id_obj FROM objednavky_restia WHERE restia_id_obj = ? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: get id_obj.');
        }

        $stmt->bind_param('s', $restiaIdObj);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        $idObj = (int)($row['id_obj'] ?? 0);
        if ($idObj <= 0) {
            throw new RuntimeException('Nepodarilo se dohledat id_obj.');
        }

        return $idObj;
    }
}

if (!function_exists('cb_restia_hist_obj_import_find')) {
    function cb_restia_hist_obj_import_find(mysqli $conn, int $idPob, string $fromUtcDb, string $toUtcDb): int
    {
        $typ = 'historie';
        $stmt = $conn->prepare('
            SELECT id_import
            FROM obj_import
            WHERE typ_importu = ?
              AND id_pob = ?
              AND datum_od = ?
              AND datum_do = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_import find.');
        }

        $stmt->bind_param('siss', $typ, $idPob, $fromUtcDb, $toUtcDb);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return (int)($row['id_import'] ?? 0);
    }
}

if (!function_exists('cb_restia_hist_obj_import_begin')) {
    function cb_restia_hist_obj_import_begin(mysqli $conn, int $idPob, string $fromUtcDb, string $toUtcDb): int
    {
        $existing = cb_restia_hist_obj_import_find($conn, $idPob, $fromUtcDb, $toUtcDb);
        if ($existing > 0) {
            return $existing;
        }

        $typ = 'historie';
        $stav = 'bezi';
        $stmt = $conn->prepare('
            INSERT INTO obj_import (typ_importu, id_pob, datum_od, datum_do, stav, spusteno)
            VALUES (?, ?, ?, ?, ?, NOW(3))
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_import begin.');
        }
        $stmt->bind_param('sisss', $typ, $idPob, $fromUtcDb, $toUtcDb, $stav);
        $stmt->execute();
        $idImport = (int)$conn->insert_id;
        $stmt->close();

        return $idImport;
    }
}

if (!function_exists('cb_restia_hist_obj_import_finish')) {
    function cb_restia_hist_obj_import_finish(
        mysqli $conn,
        int $idImport,
        int $pocetObj,
        int $pocetNovych,
        int $pocetZmenenych,
        int $pocetChyb,
        string $poznamka
    ): void {
        $stav = 'ok';

        $stmt = $conn->prepare('
            UPDATE obj_import
            SET stav = ?,
                pocet_obj = ?,
                pocet_novych = ?,
                pocet_zmenenych = ?,
                pocet_chyb = ?,
                poznamka = ?,
                dokonceno = NOW(3)
            WHERE id_import = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_import finish.');
        }
        $stmt->bind_param('siiiisi', $stav, $pocetObj, $pocetNovych, $pocetZmenenych, $pocetChyb, $poznamka, $idImport);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_hist_restia_to_local_nullable')) {
    function cb_restia_hist_restia_to_local_nullable(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $dt = new DateTimeImmutable(trim($value), new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cb_restia_hist_report_date')) {
    function cb_restia_hist_report_date(?string $localDateTime): string
    {
        if (!is_string($localDateTime) || trim($localDateTime) === '') {
            return cb_restia_hist_today();
        }

        try {
            $dt = new DateTimeImmutable($localDateTime, new DateTimeZone('Europe/Prague'));
            $hour = (int)$dt->format('G');
            if ($hour < 6) {
                $dt = $dt->modify('-1 day');
            }
            return $dt->format('Y-m-d');
        } catch (Throwable $e) {
            return cb_restia_hist_today();
        }
    }
}

if (!function_exists('cb_restia_hist_money')) {
    function cb_restia_hist_money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        $n = 0.0;
        if (is_int($value) || is_float($value)) {
            $n = (float)$value;
        } elseif (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            $n = (float)$value;
        }

        if (abs($n) >= 1000 || (is_string($value) && preg_match('/^-?\d+$/', trim($value)))) {
            $n = $n / 100.0;
        }

        return number_format($n, 2, '.', '');
    }
}
if (!function_exists('cb_restia_hist_sync_children')) {
    function cb_restia_hist_sync_children(mysqli $conn, int $idObj, int $idPob, array $order): void
    {
        $destination = (isset($order['destination']) && is_array($order['destination'])) ? $order['destination'] : [];
        $street = (string)($destination['street'] ?? ($destination['address'] ?? ''));
        $house = (string)($destination['houseNumber'] ?? '');
        $city = (string)($destination['city'] ?? '');
        $zip = (string)($destination['zip'] ?? ($destination['postalCode'] ?? ''));
        $country = (string)($destination['country'] ?? '');
        $lat = isset($destination['lat']) && $destination['lat'] !== '' ? (float)$destination['lat'] : null;
        $lng = isset($destination['lng']) && $destination['lng'] !== '' ? (float)$destination['lng'] : null;
        $distance = isset($destination['distance']) ? (int)$destination['distance'] : null;
        $driveTime = isset($destination['time']) ? (int)$destination['time'] : null;

        $stmtAddr = $conn->prepare('
            INSERT INTO obj_adresa (
                id_obj, ulice, cislo_domovni, mesto, psc, stat, lat, lng, vzdalenost_m, cas_jizdy_s, vytvoreno, zmeneno
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3), NOW(3))
            ON DUPLICATE KEY UPDATE
                ulice = VALUES(ulice), cislo_domovni = VALUES(cislo_domovni), mesto = VALUES(mesto), psc = VALUES(psc), stat = VALUES(stat),
                lat = VALUES(lat), lng = VALUES(lng), vzdalenost_m = VALUES(vzdalenost_m), cas_jizdy_s = VALUES(cas_jizdy_s),
                zmeneno = NOW(3)
        ');
        if ($stmtAddr === false) {
            throw new RuntimeException('DB prepare selhal: obj_adresa.');
        }
        $stmtAddr->bind_param('isssssddii', $idObj, $street, $house, $city, $zip, $country, $lat, $lng, $distance, $driveTime);
        $stmtAddr->execute();
        $stmtAddr->close();

        $casVytvor = cb_restia_hist_restia_to_local_nullable($order['createdAt'] ?? null);
        $casExp = cb_restia_hist_restia_to_local_nullable($order['expiresAt'] ?? null);
        $casSlib = cb_restia_hist_restia_to_local_nullable($order['promisedAt'] ?? null);
        $casPriprDo = cb_restia_hist_restia_to_local_nullable($order['prepareAt'] ?? null);
        $casPriprV = cb_restia_hist_restia_to_local_nullable($order['preparedAt'] ?? null);
        $casDokonc = cb_restia_hist_restia_to_local_nullable($order['finishedAt'] ?? null);
        $casDoruc = cb_restia_hist_restia_to_local_nullable($order['deliveredAt'] ?? null);
        $casStatus = cb_restia_hist_restia_to_local_nullable($order['statusUpdatedAt'] ?? null);
        $casUzavreni = cb_restia_hist_restia_to_local_nullable($order['closedAt'] ?? null);
        $casImportRestia = cb_restia_hist_restia_to_local_nullable($order['importedAt'] ?? null);
        $casImportPos = cb_restia_hist_restia_to_local_nullable($order['posImportedAt'] ?? null);
        $casVyzv = cb_restia_hist_restia_to_local_nullable($order['pickupAt'] ?? null);
        $casDisp = cb_restia_hist_restia_to_local_nullable($order['deliveryAt'] ?? null);
        $report = cb_restia_hist_report_date($casVytvor ?? cb_restia_hist_now());

        $stmtCasy = $conn->prepare('
            INSERT INTO obj_casy (
                id_obj, report, cas_vytvor, cas_expirace, cas_slib, cas_pripr_do, cas_pripr_v, cas_dokonc, cas_doruc,
                cas_status_zmena, cas_uzavreni, cas_import_restia, cas_import_pos, cas_vyzv, cas_disp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                report = VALUES(report), cas_vytvor = VALUES(cas_vytvor), cas_expirace = VALUES(cas_expirace), cas_slib = VALUES(cas_slib),
                cas_pripr_do = VALUES(cas_pripr_do), cas_pripr_v = VALUES(cas_pripr_v), cas_dokonc = VALUES(cas_dokonc), cas_doruc = VALUES(cas_doruc),
                cas_status_zmena = VALUES(cas_status_zmena), cas_uzavreni = VALUES(cas_uzavreni), cas_import_restia = VALUES(cas_import_restia),
                cas_import_pos = VALUES(cas_import_pos), cas_vyzv = VALUES(cas_vyzv), cas_disp = VALUES(cas_disp)
        ');
        if ($stmtCasy === false) {
            throw new RuntimeException('DB prepare selhal: obj_casy.');
        }
        $stmtCasy->bind_param('issssssssssssss', $idObj, $report, $casVytvor, $casExp, $casSlib, $casPriprDo, $casPriprV, $casDokonc, $casDoruc, $casStatus, $casUzavreni, $casImportRestia, $casImportPos, $casVyzv, $casDisp);
        $stmtCasy->execute();
        $stmtCasy->close();

        $cenaPol = (float)cb_restia_hist_money($order['itemsPrice'] ?? null);
        $cenaBalne = (float)cb_restia_hist_money($order['packingPrice'] ?? null);
        $cenaDopr = (float)cb_restia_hist_money($order['deliveryPrice'] ?? null);
        $dyska = (float)cb_restia_hist_money($order['tipPrice'] ?? null);
        $cenaDoMin = (float)cb_restia_hist_money($order['surchargeToMin'] ?? null);
        $cenaServis = (float)cb_restia_hist_money($order['serviceFeePrice'] ?? null);
        $sleva = (float)cb_restia_hist_money($order['discountPrice'] ?? null);
        $zaokrouhleni = (float)cb_restia_hist_money($order['roundingPrice'] ?? null);
        $cenaCelk = $cenaPol + $cenaBalne + $cenaDopr + $dyska + $cenaDoMin + $cenaServis + $zaokrouhleni - $sleva;

        $stmtCeny = $conn->prepare('
            INSERT INTO obj_ceny (
                id_obj, cena_celk, cena_pol, cena_balne, cena_dopr, dyska, cena_do_min, cena_servis, sleva, zaokrouhleni, mena
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "CZK")
            ON DUPLICATE KEY UPDATE
                cena_celk = VALUES(cena_celk), cena_pol = VALUES(cena_pol), cena_balne = VALUES(cena_balne), cena_dopr = VALUES(cena_dopr),
                dyska = VALUES(dyska), cena_do_min = VALUES(cena_do_min), cena_servis = VALUES(cena_servis), sleva = VALUES(sleva), zaokrouhleni = VALUES(zaokrouhleni), mena = VALUES(mena)
        ');
        if ($stmtCeny === false) {
            throw new RuntimeException('DB prepare selhal: obj_ceny.');
        }
        $stmtCeny->bind_param('iddddddddd', $idObj, $cenaCelk, $cenaPol, $cenaBalne, $cenaDopr, $dyska, $cenaDoMin, $cenaServis, $sleva, $zaokrouhleni);
        $stmtCeny->execute();
        $stmtCeny->close();

        $stmtDelKuryr = $conn->prepare('DELETE FROM obj_kuryr WHERE id_obj = ?');
        $stmtDelSluzba = $conn->prepare('DELETE FROM obj_sluzba WHERE id_obj = ?');
        $stmtDelTags = $conn->prepare('DELETE t FROM obj_polozka_kds_tag t JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka WHERE p.id_obj = ?');
        $stmtDelPolozky = $conn->prepare('DELETE FROM obj_polozky WHERE id_obj = ?');
        if ($stmtDelKuryr === false || $stmtDelSluzba === false || $stmtDelTags === false || $stmtDelPolozky === false) {
            throw new RuntimeException('DB prepare selhal: delete children.');
        }
        $stmtDelKuryr->bind_param('i', $idObj); $stmtDelKuryr->execute(); $stmtDelKuryr->close();
        $stmtDelSluzba->bind_param('i', $idObj); $stmtDelSluzba->execute(); $stmtDelSluzba->close();
        $stmtDelTags->bind_param('i', $idObj); $stmtDelTags->execute(); $stmtDelTags->close();
        $stmtDelPolozky->bind_param('i', $idObj); $stmtDelPolozky->execute(); $stmtDelPolozky->close();

        $courier = (isset($order['courierData']) && is_array($order['courierData'])) ? $order['courierData'] : null;
        if (is_array($courier)) {
            $provider = (string)($order['deliveryType'] ?? '');
            $externiId = (string)($courier['id'] ?? '');
            $poradi = isset($order['courierOrder']) ? (int)$order['courierOrder'] : null;
            $jmeno = (string)($courier['name'] ?? '');
            $telefon = (string)($courier['phone'] ?? '');
        $stmtKuryr = $conn->prepare('INSERT INTO obj_kuryr (id_obj, provider, externi_id, poradi, jmeno, telefon, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, ?, ?, NOW(3), NOW(3))');
        if ($stmtKuryr === false) { throw new RuntimeException('DB prepare selhal: obj_kuryr.'); }
        $stmtKuryr->bind_param('ississ', $idObj, $provider, $externiId, $poradi, $jmeno, $telefon);
        $stmtKuryr->execute();
        $stmtKuryr->close();
    }
        $servicesData = $order['servicesData'] ?? null;
        $services = [];
        if (is_array($servicesData) && array_is_list($servicesData)) { $services = $servicesData; }
        elseif (is_array($servicesData)) { $services[] = $servicesData; }
        foreach ($services as $service) {
            if (!is_array($service)) { continue; }
            $provider = (string)($service['provider'] ?? '');
            $externiId = (string)($service['externalId'] ?? ($service['id'] ?? ''));
            $stav = (string)($service['status'] ?? '');
            $stmtService = $conn->prepare('INSERT INTO obj_sluzba (id_obj, provider, externi_id, stav, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, NOW(3), NOW(3))');
            if ($stmtService === false) { throw new RuntimeException('DB prepare selhal: obj_sluzba.'); }
            $stmtService->bind_param('isss', $idObj, $provider, $externiId, $stav);
            $stmtService->execute();
            $stmtService->close();
        }

        $items = (isset($order['items']) && is_array($order['items'])) ? $order['items'] : [];
        $poradi = 0;
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $poradi++;

            $restiaItemId = (string)($item['posId'] ?? ($item['id'] ?? ''));
            $poznamka = isset($item['note']) ? (string)$item['note'] : null;
            $mnozstvi = isset($item['count']) ? (int)$item['count'] : 1;
            if ($mnozstvi <= 0) { $mnozstvi = 1; }
            $cenaKs = (float)cb_restia_hist_money($item['price'] ?? 0);
            $cenaCelk = isset($item['totalPrice']) ? (float)cb_restia_hist_money($item['totalPrice']) : ($cenaKs * $mnozstvi);
            $jeExtra = !empty($item['isExtra']) ? 1 : 0;
            $idResPolozka = cb_restia_hist_lookup_res_polozka_id($conn, $idPob, $restiaItemId);
            if ($idResPolozka <= 0) {
                 $idResPolozka = null;
            }
           $stmtItem = $conn->prepare('INSERT INTO obj_polozky (id_obj, id_res_polozka, res_item, poznamka, poradi, mnozstvi, cena_ks, cena_celk, je_extra, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3))');
            if ($stmtItem === false) { throw new RuntimeException('DB prepare selhal: obj_polozky.'); }
           $stmtItem->bind_param('iissiiddi', $idObj, $idResPolozka, $restiaItemId, $poznamka, $poradi, $mnozstvi, $cenaKs, $cenaCelk, $jeExtra);
            $stmtItem->execute();
            $idObjPolozka = (int)$conn->insert_id;
            $stmtItem->close();

            $modLists = [];
            foreach (['modifiers', 'mods', 'options', 'extras'] as $key) {
                if (isset($item[$key]) && is_array($item[$key]) && array_is_list($item[$key])) {
                    $modLists[] = $item[$key];
                }
            }
            foreach ($modLists as $mods) {
                foreach ($mods as $mod) {
                    if (!is_array($mod)) { continue; }
                    $restiaModId = isset($mod['id']) ? (string)$mod['id'] : null;
                    $typ = isset($mod['type']) ? (string)$mod['type'] : null;
                    $modPosId = isset($mod['posId']) ? (string)$mod['posId'] : null;
                    $modNazev = (string)($mod['label'] ?? ($mod['name'] ?? 'Modifikator'));
                    $modMnoz = isset($mod['count']) ? (float)$mod['count'] : (isset($mod['qty']) ? (float)$mod['qty'] : 1.0);
                    if ($modMnoz <= 0) { $modMnoz = 1.0; }
                    $modCenaKs = (float)cb_restia_hist_money($mod['price'] ?? 0);
                    $modCenaCelk = isset($mod['totalPrice']) ? (float)cb_restia_hist_money($mod['totalPrice']) : ($modCenaKs * $modMnoz);
                    $stmtMod = $conn->prepare('INSERT INTO obj_polozka_mod (id_obj_polozka, restia_mod_id, typ, pos_id, nazev, mnozstvi, cena_ks, cena_celk, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(3))');
                    if ($stmtMod === false) { throw new RuntimeException('DB prepare selhal: obj_polozka_mod.'); }
                    $stmtMod->bind_param('issssddd', $idObjPolozka, $restiaModId, $typ, $modPosId, $modNazev, $modMnoz, $modCenaKs, $modCenaCelk);
                    $stmtMod->execute();
                    $stmtMod->close();
                }
            }

            $kdsTags = [];
            if (isset($item['KDSTags']) && is_array($item['KDSTags'])) { $kdsTags = $item['KDSTags']; }
            elseif (isset($item['kdsTags']) && is_array($item['kdsTags'])) { $kdsTags = $item['kdsTags']; }
            foreach ($kdsTags as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '') { continue; }
                $stmtTag = $conn->prepare('INSERT INTO obj_polozka_kds_tag (id_obj_polozka, tag) VALUES (?, ?)');
                if ($stmtTag === false) { throw new RuntimeException('DB prepare selhal: obj_polozka_kds_tag.'); }
                $stmtTag->bind_param('is', $idObjPolozka, $tag);
                $stmtTag->execute();
                $stmtTag->close();
            }
        }
    }
}

if (!function_exists('cb_restia_hist_enable_zero_autoinc')) {
    function cb_restia_hist_enable_zero_autoinc(mysqli $conn): void
    {
        $sql = "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO')";
        if ($conn->query($sql) === false) {
            throw new RuntimeException('Nepodarilo se nastavit sql_mode NO_AUTO_VALUE_ON_ZERO.');
        }
    }
}

if (!function_exists('cb_restia_hist_default_pob_id')) {
    function cb_restia_hist_default_pob_id(mysqli $conn): int
    {
        $res = $conn->query('SELECT id_pob FROM pobocka ORDER BY id_pob ASC LIMIT 1');
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na vychozi pobocku selhal.');
        }
        $row = $res->fetch_assoc();
        $res->free();
        $idPob = (int)($row['id_pob'] ?? 0);
        if ($idPob <= 0) {
            throw new RuntimeException('V tabulce pobocka neni zadny zaznam.');
        }
        return $idPob;
    }
}

if (!function_exists('cb_restia_hist_ensure_default_customer')) {
    function cb_restia_hist_ensure_default_customer(mysqli $conn, int $idPobHint = 0): void
    {
        $res = $conn->query('SELECT COUNT(*) AS cnt FROM zakaznik');
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na zakaznik selhal.');
        }
        $row = $res->fetch_assoc();
        $res->free();
        $count = (int)($row['cnt'] ?? 0);
        if ($count > 0) {
            return;
        }

        cb_restia_hist_enable_zero_autoinc($conn);

        $idPob = $idPobHint > 0 ? $idPobHint : cb_restia_hist_default_pob_id($conn);
        $jmeno = 'anonymni';
        $prijmeni = 'zakaznik';
        $telefon = 'nezadano';
        $email = 'nezadano';
        $ulice = 'nezadano';
        $mesto = 'nezadano';
        $zakMenu = 0;
        $zakNews = 0;
        $poznamka = null;
        $blokovany = 0;
        $aktivni = 1;

        $stmt = $conn->prepare('
            INSERT INTO zakaznik (
                id_zak, jmeno, prijmeni, telefon, email, ulice, mesto,
                zak_menu, zak_news, posledni_obj, poznamka, blokovany, id_pob, zadano, aktivni
            ) VALUES (
                0, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), ?
            )
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: insert anonymni zakaznik.');
        }
        $stmt->bind_param('ssssssiisiii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_hist_norm_phone')) {
    function cb_restia_hist_norm_phone(?string $phone): string
    {
        $raw = trim((string)($phone ?? ''));
        if ($raw === '') {
            return '';
        }
        $norm = preg_replace('/[^0-9]/', '', $raw);
        return is_string($norm) ? $norm : '';
    }
}

if (!function_exists('cb_restia_hist_split_name')) {
    function cb_restia_hist_split_name(?string $fullName): array
    {
        $name = trim((string)($fullName ?? ''));
        if ($name === '') {
            return ['jmeno' => 'anonymni', 'prijmeni' => 'zakaznik'];
        }
        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) <= 1) {
            return ['jmeno' => $name, 'prijmeni' => 'zakaznik'];
        }
        $jmeno = trim((string)array_shift($parts));
        $prijmeni = trim(implode(' ', $parts));
        if ($jmeno === '') {
            $jmeno = 'anonymni';
        }
        if ($prijmeni === '') {
            $prijmeni = 'zakaznik';
        }
        return ['jmeno' => $jmeno, 'prijmeni' => $prijmeni];
    }
}

if (!function_exists('cb_restia_hist_upsert_customer')) {
    function cb_restia_hist_upsert_customer(mysqli $conn, int $idPob, array $order): int
    {
        $emailRaw = trim((string)($order['customerEmail'] ?? ''));
        if ($emailRaw === '' || strtolower($emailRaw) === 'null') {
            $emailRaw = '';
        }
        $phoneRaw = trim((string)($order['customerPhone'] ?? ''));
        if ($phoneRaw === '' || strtolower($phoneRaw) === 'null') {
            $phoneRaw = '';
        }
        $phoneNorm = cb_restia_hist_norm_phone($phoneRaw);

        if ($phoneNorm === '') {
            return 0;
        }

        $name = cb_restia_hist_split_name((string)($order['customerName'] ?? ''));
        $jmeno = (string)$name['jmeno'];
        $prijmeni = (string)$name['prijmeni'];
        $telefon = $phoneRaw !== '' ? $phoneRaw : 'nezadano';
        $email = $emailRaw !== '' ? $emailRaw : 'nezadano';
        $ulice = 'nezadano';
        $mesto = 'nezadano';
        $poznamka = trim((string)($order['customerNote'] ?? ''));
        if ($poznamka === '' || strtolower($poznamka) === 'null') {
            $poznamka = null;
        }
        $blokovany = 0;
        $aktivni = 1;
        $zakMenu = 0;
        $zakNews = 0;

        $idZak = 0;
        $stmt = $conn->prepare('SELECT id_zak FROM zakaznik WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefon, ""), " ", ""), "+", ""), "-", ""), "(", ""), ")", ""), "/", "") = ? ORDER BY id_zak ASC LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: find zakaznik by phone.');
        }
        $stmt->bind_param('s', $phoneNorm);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();
        $idZak = (int)($row['id_zak'] ?? 0);

        if ($idZak > 0) {
            $stmt = $conn->prepare('
                UPDATE zakaznik
                SET jmeno = ?, prijmeni = ?, telefon = ?, email = ?, ulice = ?, mesto = ?,
                    zak_menu = ?, zak_news = ?, posledni_obj = NOW(), poznamka = ?, blokovany = ?, id_pob = ?, aktivni = ?
                WHERE id_zak = ?
                LIMIT 1
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: update zakaznik.');
            }
           $stmt->bind_param('ssssssiisiiii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni, $idZak);
            $stmt->execute();
            $stmt->close();
            return $idZak;
        }

        $stmt = $conn->prepare('
            INSERT INTO zakaznik (
                jmeno, prijmeni, telefon, email, ulice, mesto,
                zak_menu, zak_news, posledni_obj, poznamka, blokovany, id_pob, zadano, aktivni
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), ?
            )
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: insert zakaznik.');
        }
        $stmt->bind_param('ssssssiisiii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni);
        $stmt->execute();
        $idZak = (int)$conn->insert_id;
        $stmt->close();

        return $idZak;
    }
}

if (!function_exists('cb_restia_hist_format_total_time')) {
    function cb_restia_hist_format_total_time(int $ms): string
    {
        if ($ms < 0) {
            $ms = 0;
        }
        $totalSec = (int)floor($ms / 1000);
        $min = (int)floor($totalSec / 60);
        $sec = $totalSec % 60;
        return $min . ' min ' . $sec . ' sec';
    }
}

if (!function_exists('cb_restia_hist_sum_orders')) {
    function cb_restia_hist_sum_orders(array $rows): int
    {
        $sum = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sum += max(0, (int)($row['count'] ?? 0));
        }
        return $sum;
    }
}

if (!function_exists('cb_restia_hist_upsert_order')) {
    function cb_restia_hist_upsert_order(mysqli $conn, int $idPob, string $activePosId, array $order): int
    {
        $restiaIdObj = trim((string)($order['id'] ?? ''));
        if ($restiaIdObj === '') {
            throw new RuntimeException('Objednavka nema id.');
        }

        $profile = (isset($order['profile']) && is_array($order['profile'])) ? $order['profile'] : [];
        $profilTyp = trim((string)($profile['type'] ?? 'neznamy'));
        if ($profilTyp === '') {
            $profilTyp = 'neznamy';
        }

        $status = trim((string)($order['status'] ?? ''));
        $paymentType = trim((string)($order['paymentType'] ?? ''));
        $deliveryType = trim((string)($order['deliveryType'] ?? ''));

        $idPlatforma = cb_restia_hist_lookup_id($conn, 'cis_obj_platforma', 'kod', $profilTyp, 'id_platforma');
        $idStav = ($status === '') ? null : cb_restia_hist_lookup_id($conn, 'cis_obj_stav', 'nazev', $status, 'id_stav');
        $idPlatba = ($paymentType === '') ? null : cb_restia_hist_lookup_id($conn, 'cis_obj_platby', 'nazev', $paymentType, 'id_platba');
        $idDoruceni = ($deliveryType === '') ? null : cb_restia_hist_lookup_id($conn, 'cis_doruceni', 'nazev', $deliveryType, 'id_doruceni');
        $idZak = cb_restia_hist_upsert_customer($conn, $idPob, $order);
        if ($idZak === 0) { $idZak = null; }

        $restiaOrderNumber = trim((string)($order['orderNumber'] ?? ''));
        $restiaToken = $order['token'] ?? null;
        $restiaToken = ($restiaToken === null || $restiaToken === '') ? null : (string)$restiaToken;
        $restiaCreatedAt = cb_restia_hist_restia_to_local_nullable($order['createdAt'] ?? null);
        $objPoznamka = $order['note'] ?? null;
        $objPoznamka = ($objPoznamka === null || $objPoznamka === '') ? null : (string)$objPoznamka;
        $importTs = cb_restia_hist_now();

        $sql = '
            INSERT INTO objednavky_restia (
                id_pob, id_zak, id_platforma, restia_id_obj, restia_created_at, restia_order_number, restia_token,
                profil_typ, rest_obj,
                id_stav, id_platba, id_doruceni,
                obj_pozn, restia_imported_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_pob = VALUES(id_pob), id_zak = VALUES(id_zak), id_platforma = VALUES(id_platforma), restia_created_at = VALUES(restia_created_at), restia_order_number = VALUES(restia_order_number),
                restia_token = VALUES(restia_token), profil_typ = VALUES(profil_typ),
                rest_obj = VALUES(rest_obj), id_stav = VALUES(id_stav), id_platba = VALUES(id_platba), id_doruceni = VALUES(id_doruceni),
                obj_pozn = VALUES(obj_pozn), restia_imported_at = VALUES(restia_imported_at)
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) { throw new RuntimeException('DB prepare selhal: objednavky_restia upsert.'); }
        $restObj = $restiaIdObj;
        $stmt->bind_param('iiissssssiisss', $idPob, $idZak, $idPlatforma, $restiaIdObj, $restiaCreatedAt, $restiaOrderNumber, $restiaToken, $profilTyp, $restObj, $idStav, $idPlatba, $idDoruceni, $objPoznamka, $importTs);
        $stmt->execute();
        $stmt->close();

        return cb_restia_hist_get_obj_id($conn, $restiaIdObj);
    }
}
if (!function_exists('cb_restia_hist_try_flush_api')) {
    function cb_restia_hist_try_flush_api(?mysqli $conn, ?array $auth): void
    {
        if (!($conn instanceof mysqli) || !is_array($auth)) { return; }
        db_api_restia_flush($conn, (int)($auth['id_user'] ?? 0), (int)($auth['id_login'] ?? 0));
    }
}

if (!function_exists('cb_restia_hist_default_state')) {
    function cb_restia_hist_default_state(): array
    {
        return [
            'next_date' => '',
            'start_date' => '',
            'last_date' => '',
            'finished' => 0,
            'branch_name' => '',
            'branch_id' => 0,
            'run_started_at_ms' => 0,
            'run_row_no' => 0,
            'found_total' => 0,
            'branch_db_count' => 0,
            'cycle_errors' => 0,
        ];
    }
}

if (!function_exists('cb_restia_hist_import_day')) {
    function cb_restia_hist_import_day(mysqli $conn, array $auth, array $branch, string $date): array
    {
        $dayStartMs = (int)round(microtime(true) * 1000);
        $idPob = (int)$branch['id_pob'];
        $nazev = (string)$branch['nazev'];
        $activePosId = (string)$branch['active_pos_id'];
        $range = cb_restia_hist_day_range_utc($date);

        $idImport = 0;
        $lockName = '';

        $pocetObj = 0; $pocetNovych = 0; $pocetZmenenych = 0; $pocetChyb = 0;

        try {
            $lockName = cb_restia_hist_day_lock_acquire($conn, $idPob, $date, 10);
            $idImport = cb_restia_hist_obj_import_begin($conn, $idPob, $range['from_db'], $range['to_db']);

            $page = 1;
            while (true) {
                $res = cb_restia_get('/api/orders', [
                    'page' => $page,
                    'limit' => CB_RESTIA_HIST_LIMIT,
                    'createdFrom' => $range['from_z'],
                    'createdTo' => $range['to_z'],
                    'activePosId' => $activePosId,
                ], $activePosId, 'historie den=' . $date . ' id_pob=' . $idPob . ' page=' . $page);

                if ((int)($res['ok'] ?? 0) !== 1) {
                    $bodySnippet = mb_substr((string)($res['body'] ?? ''), 0, 300);
                    $http = (int)($res['http_status'] ?? 0);
                    throw new RuntimeException('Restia chyba HTTP=' . $http . ' body=' . $bodySnippet);
                }

                $decoded = json_decode((string)($res['body'] ?? ''), true);
                if (!is_array($decoded)) { throw new RuntimeException('Restia vratila neplatny JSON.'); }

                $orders = cb_restia_hist_extract_orders($decoded);
                $countOrders = count($orders);

                foreach ($orders as $order) {
                    if (!is_array($order)) {
                        throw new RuntimeException('Restia vratila neplatnou objednavku.');
                    }
                    $restiaIdObj = trim((string)($order['id'] ?? ''));
                    if ($restiaIdObj === '') {
                        throw new RuntimeException('Objednavka nema id.');
                    }

                    $exists = cb_restia_hist_order_exists($conn, $restiaIdObj);

                    $conn->begin_transaction();
                    try {
                        $idObj = cb_restia_hist_upsert_order($conn, $idPob, $activePosId, $order);
                        cb_restia_hist_sync_children($conn, $idObj, $idPob, $order);
                        $conn->commit();
                    } catch (Throwable $e) {
                        $conn->rollback();
                        cb_restia_hist_error_log(
                            $idPob,
                            'ORDER_ERR: datum=' . $date
                            . ' | id_pob=' . $idPob
                            . ' | page=' . $page
                            . ' | restia_id_obj=' . $restiaIdObj
                            . ' | msg=' . $e->getMessage()
                        );
                        $pocetChyb++;
                        continue;
                    }

                    $pocetObj++;
                    if ($exists) { $pocetZmenenych++; } else { $pocetNovych++; }
                }

                if ($countOrders < CB_RESTIA_HIST_LIMIT) { break; }
                $totalCount = isset($res['total_count']) ? (int)$res['total_count'] : 0;
                if ($totalCount > 0 && ($page * CB_RESTIA_HIST_LIMIT) >= $totalCount) { break; }
                if ($countOrders === 0) { break; }
                $page++;
            }

            $poznamka = 'den=' . $date . ' id_pob=' . $idPob . ' obj=' . $pocetObj;
            cb_restia_hist_obj_import_finish($conn, $idImport, $pocetObj, $pocetNovych, $pocetZmenenych, $pocetChyb, $poznamka);
            cb_restia_hist_try_flush_api($conn, $auth);

            $dayMs = (int)round(microtime(true) * 1000) - $dayStartMs;
            return ['date' => $date, 'branch' => $nazev, 'count' => $pocetObj, 'errors' => $pocetChyb, 'error' => '', 'day_ms' => $dayMs, 'ok' => 1];
        } catch (Throwable $e) {
            cb_restia_hist_try_flush_api($conn, $auth);
            $fatalLine = 'FATAL_STEP: datum=' . $date . ' | id_pob=' . $idPob . ' | msg=' . $e->getMessage();
            cb_restia_hist_log($idPob, $fatalLine);
            cb_restia_hist_error_log($idPob, $fatalLine);
            try {
                db_zapis_log_chyby(
                    $conn,
                    null,
                    'RESTIA',
                    'IMPORT_DN',
                    'FATAL_STEP',
                    $e->getMessage(),
                    $fatalLine,
                    __FILE__,
                    __LINE__,
                    null,
                    null,
                    0,
                    'datum=' . $date . ' id_pob=' . (string)$idPob
                );
            } catch (Throwable $logErr) {
                cb_restia_hist_error_log($idPob, 'WARN: log_chyby insert selhal: ' . $logErr->getMessage());
            }
            throw $e;
        } finally {
            cb_restia_hist_day_lock_release($conn, $lockName);
        }
    }
}

$conn = db();
$state = $_SESSION[$cbStateKey] ?? cb_restia_hist_default_state();
$rows = $_SESSION[$cbRowsKey] ?? [];
$message = (string)($_SESSION[$cbMsgKey] ?? '');
if (!is_array($state)) { $state = cb_restia_hist_default_state(); }
if (!is_array($rows)) { $rows = []; }
$action = trim((string)($_POST['cb_action'] ?? ''));
$inputBranchId = (int)($_POST['cb_id_pob'] ?? 0);

$auth = cb_restia_hist_get_auth();

if ($action === 'select_branch') {
    if ($inputBranchId > 0) {
        $_SESSION['cb_pobocka_id'] = $inputBranchId;
        $_SESSION['selected_pobocky'] = [$inputBranchId];
        $branchSel = cb_restia_hist_get_branch_by_id($conn, $inputBranchId);
        $resumeSel = cb_restia_hist_resume_info($conn, $branchSel);
        $state['branch_id'] = (int)$branchSel['id_pob'];
        $state['branch_name'] = (string)$branchSel['nazev'];
        $state['start_date'] = (string)$resumeSel['start_date'];
        $state['next_date'] = (string)$resumeSel['next_date'];
        $state['last_date'] = (string)$resumeSel['last_date'];
    }
    $action = '';
}

if ($action === 'start' && $auth !== null) {
    if ($inputBranchId > 0) {
        $branch = cb_restia_hist_get_branch_by_id($conn, $inputBranchId);
    } else {
        $legacyBranchId = (int)($_SESSION['cb_pobocka_id'] ?? 0);
        if ($legacyBranchId > 0) {
            $branch = cb_restia_hist_get_branch_by_id($conn, $legacyBranchId);
        } else {
            $branch = cb_restia_hist_pick_default_branch($conn);
        }
    }
    cb_restia_hist_ensure_default_customer($conn, (int)$branch['id_pob']);
    $resumeInfo = cb_restia_hist_resume_info($conn, $branch);
    $resumeDate = (string)$resumeInfo['next_date'];
    $today = cb_restia_hist_today();
    if ($resumeDate > $today) { $resumeDate = $today; }

    $_SESSION['cb_pobocka_id'] = (int)$branch['id_pob'];
    $_SESSION['selected_pobocky'] = [(int)$branch['id_pob']];

    $state = cb_restia_hist_default_state();
    $state['next_date'] = $resumeDate;
    $state['branch_name'] = (string)$branch['nazev'];
    $state['branch_id'] = (int)$branch['id_pob'];
    $state['start_date'] = (string)$resumeInfo['start_date'];
    $state['last_date'] = (string)$resumeInfo['last_date'];
    $state['run_started_at_ms'] = (int)round(microtime(true) * 1000);
    $state['run_row_no'] = 0;
    $state['found_total'] = 0;
    $state['branch_db_count'] = cb_restia_hist_branch_db_count($conn, (int)$branch['id_pob']);
    $rows = [];
    $message = '';

    cb_restia_hist_log_init((int)$branch['id_pob']);
    cb_restia_hist_error_log_init((int)$branch['id_pob']);
    cb_restia_hist_log((int)$branch['id_pob'], '-----');
    cb_restia_hist_log((int)$branch['id_pob'], 'START: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now()));
    cb_restia_hist_log((int)$branch['id_pob'], 'SCRIPT: ' . basename(__FILE__));
    cb_restia_hist_log((int)$branch['id_pob'], 'START_BASE: ' . cb_restia_hist_format_date_cs((string)$resumeInfo['start_date']));

    cb_restia_hist_log((int)$branch['id_pob'], 'RESUME_FROM: ' . cb_restia_hist_format_date_cs($resumeDate));
    cb_restia_hist_log((int)$branch['id_pob'], 'LIMIT: ' . (string)CB_RESTIA_HIST_LIMIT);

    cb_restia_hist_run_batch($conn, $auth, $branch, $state, $rows, $message);
    $action = '';
}

$_SESSION[$cbStateKey] = $state;
$_SESSION[$cbRowsKey] = $rows;
$_SESSION[$cbMsgKey] = $message;
if ((int)($state['finished'] ?? 0) === 1) {
    unset($_SESSION[$cbStateKey], $_SESSION[$cbRowsKey], $_SESSION[$cbMsgKey]);
}

$branchOptions = [];
$branchResumeMap = [];
try {
    $branchOptions = cb_restia_hist_get_branches($conn);
    foreach ($branchOptions as $branchOpt) {
        $idPobOpt = (int)($branchOpt['id_pob'] ?? 0);
        if ($idPobOpt <= 0) {
            continue;
        }
        $branchResumeMap[$idPobOpt] = cb_restia_hist_resume_info($conn, $branchOpt);
    }
} catch (Throwable $e) {
    throw $e;
}
$selectedBranchId = (int)($_SESSION['cb_pobocka_id'] ?? 0);
if ($selectedBranchId <= 0) {
    foreach ($branchOptions as $branchOpt) {
        if ((bool)($branchOpt['enabled'] ?? false) === true) {
            $selectedBranchId = (int)($branchOpt['id_pob'] ?? 0);
            break;
        }
    }
}
if ((int)($state['branch_id'] ?? 0) !== $selectedBranchId) {
    foreach ($branchOptions as $branchOpt) {
        if ((int)($branchOpt['id_pob'] ?? 0) === $selectedBranchId) {
            $state['branch_id'] = $selectedBranchId;
            $state['branch_name'] = (string)($branchOpt['nazev'] ?? '');
            break;
        }
    }
}
if (!isset($branchResumeMap[$selectedBranchId])) {
    throw new RuntimeException('Chybi data pro vybranou pobočku id=' . (string)$selectedBranchId);
}
$selectedResume = $branchResumeMap[$selectedBranchId];
$startBaseDate = cb_restia_hist_normalize_ymd((string)($selectedResume['start_date'] ?? ''));
$resumeDate = cb_restia_hist_normalize_ymd((string)($selectedResume['next_date'] ?? ''));
$lastOkDate = cb_restia_hist_normalize_ymd((string)($selectedResume['last_date'] ?? ''));

if ((int)($state['branch_id'] ?? 0) === $selectedBranchId && (int)($state['run_started_at_ms'] ?? 0) === 0) {
    $state['start_date'] = $startBaseDate;
    $state['next_date'] = $resumeDate;
    $state['last_date'] = $lastOkDate;
}

$startDateInput = cb_restia_hist_format_date_input_cs($resumeDate);
$stmtCnt = $conn->prepare('SELECT COUNT(*) AS cnt FROM objednavky_restia WHERE id_pob = ?');
$stmtCnt->bind_param('i', $selectedBranchId);
$stmtCnt->execute();
$resCnt = $stmtCnt->get_result();
$rowCnt = ($resCnt instanceof mysqli_result) ? $resCnt->fetch_assoc() : null;
$countObj = (int)($rowCnt['cnt'] ?? 0);
if ($resCnt instanceof mysqli_result) { $resCnt->free(); }
$stmtCnt->close();

$lastOkText = (string)$countObj . ' objednávek';
?>
<?php if (!$cbRestiaEmbedMode): ?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><title>Restia import objednávek</title><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
<?php endif; ?>
<div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <p class="card_title txt_seda text_18 text_tucny odstup_vnejsi_0">Restia import objednávek</p>
  <br>
  <span class="card_text txt_cervena text_11">Admin info:</span> <span class="card_text txt_seda text_12">Limit <?= cb_restia_hist_h((string)CB_RESTIA_HIST_LIMIT) ?> obj. na stránku </span>
  <p class="card_text txt_seda text_12">Pobočka: <?= cb_restia_hist_h((string)($state['branch_name'] ?? '')) ?> (<?= cb_restia_hist_h((string)$selectedBranchId) ?>)</p>
  <p class="card_text txt_seda text_12">První objednávka pobočky: <?= cb_restia_hist_h(cb_restia_hist_format_date_input_cs($startBaseDate)) ?> | V DB je <?= cb_restia_hist_h($lastOkText) ?></p>
  <?php if ($message !== ''): ?><p class="card_text text_tucny txt_seda"><?= cb_restia_hist_h($message) ?></p><?php endif; ?>
    <br><br>
 <span class="card_text txt_cervena text_11">Výběr pobočky</span>
  <div class="card_actions gap_8 displ_flex">
    <form method="post" action="<?= cb_restia_hist_h((string)cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex">
      <input type="hidden" name="run_restia_obj" value="1"><input type="hidden" name="cb_action" value="start" id="cb_action_field">
      <select name="cb_id_pob" class="card_select ram_sedy txt_seda bg_bila zaobleni_8" style="min-width:220px; height:22px; margin-right:8px;" onchange="var a=document.getElementById('cb_action_field');if(a){a.value='select_branch';}this.form.submit();">
        <?php foreach ($branchOptions as $branchOpt): ?>
          <?php
          $idPobOpt = (int)($branchOpt['id_pob'] ?? 0);
          $nameOpt = (string)($branchOpt['nazev'] ?? '');
          $enabledOpt = ((bool)($branchOpt['enabled'] ?? false) === true);
          $labelOpt = $nameOpt !== '' ? $nameOpt : ('Pobočka #' . (string)$idPobOpt);
          $resumeOpt = $branchResumeMap[$idPobOpt] ?? ['last_date' => ''];
          $lastOkOpt = cb_restia_hist_normalize_ymd((string)($resumeOpt['last_date'] ?? ''));
          $statusOpt = ($lastOkOpt !== '') ? ('do ' . cb_restia_hist_format_date_cs($lastOkOpt) . ' OK') : 'bez importu';
          $labelOpt .= ' | ' . $statusOpt;
          if (!$enabledOpt) {
              $labelOpt .= ' (chybí restia_activePosId)';
          }
          ?>
          <option value="<?= cb_restia_hist_h((string)$idPobOpt) ?>"<?= $idPobOpt === $selectedBranchId ? ' selected' : '' ?><?= $enabledOpt ? '' : ' disabled style="color:#9ca3af;"' ?>><?= cb_restia_hist_h($labelOpt) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="card_text txt_seda text_14" style="margin-right:8px; line-height:22px;"><?= cb_restia_hist_h(cb_restia_hist_format_month_year_cs($resumeDate)) ?></span>
      <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" style="align-self:center;">• Spustit import</button>
    </form>
  </div>


  <?php
  $historyTz = new DateTimeZone('Europe/Prague');
$historyTodayDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', cb_restia_hist_today() . ' 00:00:00', $historyTz);
  $historyTableRows = [];
  $historyTableTotals = [
      'downloaded' => 0,
      'missing' => 0,
      'total' => 0,
  ];
  foreach ($branchOptions as $branchOptHistory) {
    $branchIdHistory = (int)($branchOptHistory['id_pob'] ?? 0);
    if ($branchIdHistory <= 0) {
        continue;
    }

    $branchNameHistory = trim((string)($branchOptHistory['nazev'] ?? ''));
    if (!isset($branchResumeMap[$branchIdHistory])) {
        throw new RuntimeException('Chybi data historie pro pobočku id=' . (string)$branchIdHistory);
    }
    $branchResumeHistory = $branchResumeMap[$branchIdHistory];
    $branchStartHistory = cb_restia_hist_normalize_ymd((string)($branchResumeHistory['start_date'] ?? ''));
    $branchNextHistory = cb_restia_hist_normalize_ymd((string)($branchResumeHistory['next_date'] ?? ''));
    $branchStartDtHistory = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $branchStartHistory . ' 00:00:00', $historyTz);
    $branchNextDtHistory = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $branchNextHistory . ' 00:00:00', $historyTz);

    $downloadedDaysHistory = 0;
    $totalDaysHistory = 0;
    $downloadedMonthsHistory = 0;
    $missingMonthsHistory = 0;
    $totalMonthsHistory = 0;
    if ($branchStartDtHistory instanceof DateTimeImmutable && $historyTodayDt instanceof DateTimeImmutable) {
        $totalDaysHistory = max(0, (int)$branchStartDtHistory->diff($historyTodayDt)->days + 1);
        $totalMonthsHistory = cb_restia_hist_count_months_between($branchStartHistory, $historyTodayDt->format('Y-m-d'));
    }
    if ($branchStartDtHistory instanceof DateTimeImmutable && $branchNextDtHistory instanceof DateTimeImmutable && $branchNextDtHistory >= $branchStartDtHistory) {
        $downloadedDaysHistory = max(0, (int)$branchStartDtHistory->diff($branchNextDtHistory)->days);
        $downloadEndDtHistory = $branchNextDtHistory->modify('-1 day');
        if ($downloadEndDtHistory >= $branchStartDtHistory) {
            $downloadedMonthsHistory = cb_restia_hist_count_months_between($branchStartHistory, $downloadEndDtHistory->format('Y-m-d'));
        }
    }
    $missingDaysHistory = max(0, $totalDaysHistory - $downloadedDaysHistory);
    if ($branchNextDtHistory instanceof DateTimeImmutable && $historyTodayDt instanceof DateTimeImmutable && $branchNextDtHistory <= $historyTodayDt) {
        $missingMonthsHistory = cb_restia_hist_count_months_between($branchNextHistory, $historyTodayDt->format('Y-m-d'));
    }

    $historyTableRows[] = [
        'name' => $branchNameHistory !== '' ? $branchNameHistory : ('Pobočka #' . (string)$branchIdHistory),
        'downloaded' => $downloadedDaysHistory,
        'missing' => $missingDaysHistory,
        'total' => $totalDaysHistory,
        'downloaded_months' => $downloadedMonthsHistory,
        'missing_months' => $missingMonthsHistory,
        'total_months' => $totalMonthsHistory,
    ];
    $historyTableTotals['downloaded'] += $downloadedDaysHistory;
    $historyTableTotals['missing'] += $missingDaysHistory;
    $historyTableTotals['total'] += $totalDaysHistory;
    $historyTableTotals['downloaded_months'] = (int)($historyTableTotals['downloaded_months'] ?? 0) + $downloadedMonthsHistory;
    $historyTableTotals['missing_months'] = (int)($historyTableTotals['missing_months'] ?? 0) + $missingMonthsHistory;
    $historyTableTotals['total_months'] = (int)($historyTableTotals['total_months'] ?? 0) + $totalMonthsHistory;
  }
  ?>
  <br><br>
   <p class="card_title txt_zelena text_18 text_tucny odstup_vnejsi_0">Aktuální stav importu</p>
  <div class="table-wrap ram_normal bg_bila zaobleni_8 odstup_vnejsi_10" style="overflow:auto; margin-top:12px;">
    <table class="table" style="width:100%; font-size:12px; line-height:1.1;">
      <thead>
        <tr>
          <th style="padding:4px 8px; text-align:left;">Pobočka</th>
          <th style="padding:4px 8px; text-align:right;">Stáhnuto</th>
          <th style="padding:4px 8px; text-align:right;">Ještě chybí</th>
          <th style="padding:4px 8px; text-align:right;">Historie</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($historyTableRows as $historyRow): ?>
          <tr>
            <td style="padding:4px 8px; white-space:nowrap;"><?= cb_restia_hist_h((string)($historyRow['name'] ?? '')) ?></td>
            <td style="padding:4px 8px; text-align:right; white-space:nowrap;"><?= cb_restia_hist_format_days_months((int)($historyRow['downloaded'] ?? 0), (int)($historyRow['downloaded_months'] ?? 0)) ?></td>
            <td style="padding:4px 8px; text-align:right; white-space:nowrap;"><?= cb_restia_hist_format_days_months((int)($historyRow['missing'] ?? 0), (int)($historyRow['missing_months'] ?? 0)) ?></td>
            <td style="padding:4px 8px; text-align:right; white-space:nowrap;"><?= cb_restia_hist_format_days_months((int)($historyRow['total'] ?? 0), (int)($historyRow['total_months'] ?? 0)) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td style="padding:4px 8px; white-space:nowrap; font-weight:700;">Celkem</td>
          <td style="padding:4px 8px; text-align:right; white-space:nowrap; font-weight:700;"><?= cb_restia_hist_format_days_months((int)$historyTableTotals['downloaded'], (int)($historyTableTotals['downloaded_months'] ?? 0)) ?></td>
          <td style="padding:4px 8px; text-align:right; white-space:nowrap; font-weight:700;"><?= cb_restia_hist_format_days_months((int)$historyTableTotals['missing'], (int)($historyTableTotals['missing_months'] ?? 0)) ?></td>
          <td style="padding:4px 8px; text-align:right; white-space:nowrap; font-weight:700;"><?= cb_restia_hist_format_days_months((int)$historyTableTotals['total'], (int)($historyTableTotals['total_months'] ?? 0)) ?></td>
        </tr>
      </tbody>
    </table>
  </div>
  <div style="margin-top:16px; text-align:right;">
    <form method="post" action="<?= cb_restia_hist_h((string)cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex">
      <input type="hidden" name="back_admin_init" value="1">
      <button type="submit" class="card_btn cursor_ruka ram_btn zaobleni_6 vyska_28 displ_inline_flex" style="background:var(--clr_ruzova_4); border-color:var(--clr_ruzova_1); color:var(--clr_cervena);">Zpět</button>
    </form>
  </div>
</div>
<?php if (!$cbRestiaEmbedMode): ?></body></html><?php endif; ?>
