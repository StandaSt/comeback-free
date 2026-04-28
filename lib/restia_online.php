<?php
// lib/restia_online.php * Verze: V5 * Aktualizace: 28.04.2026
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

const CB_RESTIA_ONLINE_LIMIT = 100;
if (!function_exists('cb_restia_online_h')) {
    function cb_restia_online_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_online_txt_path')) {
    function cb_restia_online_txt_path(int $idPob): string
    {
        $safeIdPob = max(0, $idPob);
        return __DIR__ . '/../log/restia_online.txt';
    }
}

if (!function_exists('cb_restia_online_log_init')) {
    function cb_restia_online_log_init(int $idPob): void
    {
        $path = cb_restia_online_txt_path($idPob);
        if (!is_file($path)) {
            @file_put_contents($path, '', FILE_APPEND | LOCK_EX);
        }
    }
}

if (!function_exists('cb_restia_online_log')) {
    function cb_restia_online_log(int $idPob, string $line): void
    {
        $path = cb_restia_online_txt_path($idPob);
        @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_online_error_txt_path')) {
    function cb_restia_online_error_txt_path(int $idPob): string
    {
        $safeIdPob = max(0, $idPob);
        return __DIR__ . '/../log/plnime_restia_objednavky_' . $safeIdPob . '_error.txt';
    }
}

if (!function_exists('cb_restia_online_error_log_init')) {
    function cb_restia_online_error_log_init(int $idPob): void
    {
    }
}

if (!function_exists('cb_restia_online_error_log')) {
    function cb_restia_online_error_log(int $idPob, string $line): void
    {
        @file_put_contents(cb_restia_online_error_txt_path($idPob), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_online_now')) {
    function cb_restia_online_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_restia_online_today')) {
    function cb_restia_online_today(): string
    {
        return (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    }
}

if (!function_exists('cb_restia_online_import_end_date')) {
    function cb_restia_online_import_end_date(): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        $now = new DateTimeImmutable('now', $tz);
        $todayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' 08:00:00', $tz);
        if (!($todayStart instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se urcit konec importu.');
        }

        $currentWorkdayStart = ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;
        return $currentWorkdayStart->modify('-1 day')->format('Y-m-d');
    }
}

if (!function_exists('cb_restia_online_format_date_cs')) {
    function cb_restia_online_format_date_cs(string $date): string
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

if (!function_exists('cb_restia_online_format_date_input_cs')) {
    function cb_restia_online_format_date_input_cs(string $date): string
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

if (!function_exists('cb_restia_online_format_month_year_cs')) {
    function cb_restia_online_format_month_year_cs(string $date): string
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

if (!function_exists('cb_restia_online_normalize_ymd')) {
    function cb_restia_online_normalize_ymd(string $raw): string
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

if (!function_exists('cb_restia_online_count_months_between')) {
    function cb_restia_online_count_months_between(string $startYmd, string $endYmd): int
    {
        $startYmd = cb_restia_online_normalize_ymd($startYmd);
        $endYmd = cb_restia_online_normalize_ymd($endYmd);
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

if (!function_exists('cb_restia_online_format_days_months')) {
    function cb_restia_online_format_days_months(int $days, int $months): string
    {
        return cb_restia_online_h((string)max(0, $days)) . ' dnů / ' . cb_restia_online_h((string)max(0, $months)) . ' měsíců';
    }
}

if (!function_exists('cb_restia_online_format_datetime_cs')) {
    function cb_restia_online_format_datetime_cs(string $datetime): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $datetime;
        }
        return $dt->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_online_format_range_cs')) {
    function cb_restia_online_format_range_cs(string $fromDate, string $toDate): string
    {
        $fromDate = cb_restia_online_normalize_ymd($fromDate);
        $toDate = cb_restia_online_normalize_ymd($toDate);
        if ($fromDate === '' || $toDate === '' || $fromDate > $toDate) {
            return '';
        }

        $tz = new DateTimeZone('Europe/Prague');
        $fromDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fromDate . ' 08:00:00', $tz);
        $toDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', cb_restia_online_next_date($toDate) . ' 08:00:00', $tz);
        if (!($fromDt instanceof DateTimeImmutable) || !($toDt instanceof DateTimeImmutable)) {
            return '';
        }

        return $fromDt->format('d.m.Y H:i:s') . ' - ' . $toDt->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_online_next_date')) {
    function cb_restia_online_next_date(string $date): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', $tz);
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatne datum pro posun.');
        }

        return $dt->modify('+1 day')->format('Y-m-d');
    }
}

if (!function_exists('cb_restia_online_day_range_utc')) {
    function cb_restia_online_day_range_utc(string $date): array
    {
        $tz = new DateTimeZone('Europe/Prague');
        $fromLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $date . ' 08:00:00.000000', $tz);
        $nextDate = cb_restia_online_next_date($date);
        $toLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $nextDate . ' 07:59:59.999000', $tz);

        if (!($fromLocal instanceof DateTimeImmutable) || !($toLocal instanceof DateTimeImmutable)) {
            throw new RuntimeException('Neplatny den pro interval.');
        }

        return [
            'from_z' => $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'to_z' => $toLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
            'from_db' => $fromLocal->format('Y-m-d H:i:s'),
            'to_db' => $toLocal->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('cb_restia_online_extract_orders')) {
    function cb_restia_online_extract_orders(array $json): array
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

if (!function_exists('cb_restia_online_json')) {
    function cb_restia_online_json(mixed $value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return null;
        }
        return $json;
    }
}

if (!function_exists('cb_restia_online_hash32')) {
    function cb_restia_online_hash32(string $value): string
    {
        $bin = hash('sha256', $value, true);
        return is_string($bin) ? $bin : str_repeat("\0", 32);
    }
}

if (!function_exists('cb_restia_online_get_auth')) {
    function cb_restia_online_get_auth(): array
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

if (!function_exists('cb_restia_online_get_branches')) {
    function cb_restia_online_get_branches(mysqli $conn): array
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

if (!function_exists('cb_restia_online_get_branch_by_id')) {
    function cb_restia_online_get_branch_by_id(mysqli $conn, int $idPob): array
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

if (!function_exists('cb_restia_online_pick_default_branch')) {
    function cb_restia_online_pick_default_branch(mysqli $conn): array
    {
        $branches = cb_restia_online_get_branches($conn);
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


if (!function_exists('cb_restia_online_branch_db_count')) {
    function cb_restia_online_branch_db_count(mysqli $conn, int $idPob): int
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

if (!function_exists('cb_restia_online_run_batch')) {
    function cb_restia_online_run_batch(mysqli $conn, array $auth, array $branch, array &$state, array &$rows, string &$message, string $importEndDate): void
    {
        if ((int)($state['finished'] ?? 0) !== 0) {
            return;
        }

        $importEndDate = cb_restia_online_normalize_ymd($importEndDate);
        $foundNow = 0;
        $cycleMonth = '';

        while (true) {
            $date = (string)($state['next_date'] ?? '');
            if ($date === '') {
                $resume = cb_restia_online_resume_info($conn, $branch);
                $date = (string)($resume['next_date'] ?? '');
                $state['next_date'] = $date;
                $state['start_date'] = (string)($resume['start_date'] ?? '');
                $state['last_date'] = (string)($resume['last_date'] ?? '');
            }

            if ($date > $importEndDate) {
                $state['finished'] = 1;
                $branchName = trim((string)($branch['nazev'] ?? ''));
                $runFromDate = cb_restia_online_normalize_ymd((string)($state['run_from_date'] ?? ''));
                $rangeText = cb_restia_online_format_range_cs($runFromDate, $importEndDate);
                if ($branchName !== '' && $rangeText !== '') {
                    $message = 'Import ' . $branchName . ' období ' . $rangeText . ' skončil. OK';
                } else {
                    $message = 'Akce skončila. Došli jsme do konce importu.';
                }
                cb_restia_online_log(
                    (int)($branch['id_pob'] ?? 0),
                    'KONEC IMPORTU: '
                    . cb_restia_online_format_datetime_cs(cb_restia_online_now())
                    . ' | pobočka=' . ($branchName !== '' ? $branchName : '-')
                    . ' | součet_cyklus=' . (string)$foundNow
                );
                break;
            }

            $day = cb_restia_online_import_day($conn, $auth, $branch, $date);
            $startedAtMs = (int)($state['run_started_at_ms'] ?? 0);
            $nowMs = (int)round(microtime(true) * 1000);
            $day['total_ms'] = ($startedAtMs > 0) ? max(0, $nowMs - $startedAtMs) : 0;
            $rows[] = $day;
            if (count($rows) > 200) {
                array_shift($rows);
            }

            $state['run_row_no'] = (int)($state['run_row_no'] ?? 0) + 1;
            $state['next_date'] = cb_restia_online_next_date($date);

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

            cb_restia_online_log(
                (int)($branch['id_pob'] ?? 0),
                (string)($state['run_row_no'] ?? 0)
                . ' / ' . cb_restia_online_format_date_cs((string)($day['date'] ?? $date))
                . ' / ' . (string)$dayCount . ' - ' . (string)(int)($state['branch_db_count'] ?? 0)
                . ' / ' . cb_restia_online_format_total_time((int)($day['total_ms'] ?? 0))
            );

            // STOPKA NA ČAS – max 1:45 (105000 ms)
            $startedAtMs = (int)($state['run_started_at_ms'] ?? 0);
            $nowMsCheck = (int)round(microtime(true) * 1000);
            if ($startedAtMs > 0 && ($nowMsCheck - $startedAtMs) >= 105000) {
                $state['finished'] = 1;
                if ($message === '') {
                    $message = 'Import dočasně ukončen kvůli časovému limitu, spusť znovu.';
                }
                $branchName = (string)($branch['nazev'] ?? '');
                cb_restia_online_log(
                    (int)($branch['id_pob'] ?? 0),
                    'STOPKA LIMIT: '
                    . cb_restia_online_format_datetime_cs(cb_restia_online_now())
                    . ' | pobočka=' . ($branchName !== '' ? $branchName : '-')
                    . ' | součet_průběžně=' . (string)$foundNow
                );
                break;
            }


            $currentMonth = substr((string)$date, 0, 7);
            $nextMonth = substr((string)($state['next_date'] ?? ''), 0, 7);
            if ($currentMonth !== '' && $nextMonth !== '' && $nextMonth !== $currentMonth) {
                $cycleMonth = cb_restia_online_format_month_year_cs((string)$date);
                $state['finished'] = 1;
                break;
            }

            $cycleMonth = cb_restia_online_format_month_year_cs((string)$date);
        }

        if ((int)($state['finished'] ?? 0) === 1 && $message === '') {
            $branchName = (string)($branch['nazev'] ?? '');
            if ($cycleMonth === '') {
                $cycleMonth = cb_restia_online_format_month_year_cs((string)($day['date'] ?? $date));
            }
            $message = 'Import ' . $cycleMonth . ' pro pobočku "' . $branchName . '", uloženo ' . (string)$foundNow . ' objednávek.';
            cb_restia_online_log(
                (int)($branch['id_pob'] ?? 0),
                'KONEC CYKLU: '
                . cb_restia_online_format_datetime_cs(cb_restia_online_now())
                . ' | pobočka=' . ($branchName !== '' ? $branchName : '-')
                . ' | součet_cyklus=' . (string)$foundNow
            );
        }
    }
}


if (!function_exists('cb_restia_online_branch_start_date')) {
    function cb_restia_online_branch_start_date(array $branch): string
    {
        $startDate = cb_restia_online_normalize_ymd((string)($branch['prvni_obj'] ?? ''));
        if ($startDate === '') {
            throw new RuntimeException('Chybi prvni_obj pro pobocku id=' . (string)($branch['id_pob'] ?? 0));
        }
        return $startDate;
    }
}

if (!function_exists('cb_restia_online_last_date')) {
    function cb_restia_online_last_date(mysqli $conn, int $idPob): string
    {
        $stmt = $conn->prepare('
            SELECT datum_od, datum_do
            FROM obj_import
            WHERE typ_importu = "historie"
              AND stav = "ok"
              AND id_pob = ?
            ORDER BY datum_do DESC, id_import DESC
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_import last date.');
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
            return '';
        }

        $datumOd = trim((string)($row['datum_od'] ?? ''));
        $datumDo = trim((string)($row['datum_do'] ?? ''));
        if ($datumOd === '' || $datumDo === '') {
            throw new RuntimeException('Posledni import historie ma prazdny interval pro pobocku id=' . (string)$idPob);
        }

        $tz = new DateTimeZone('Europe/Prague');
        $utc = new DateTimeZone('UTC');

        $fromLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumOd, $tz);
        $toLocal = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumDo, $tz);
        if (($fromLocal instanceof DateTimeImmutable) && ($toLocal instanceof DateTimeImmutable)) {
            $fromTime = $fromLocal->format('H:i:s');
            $toTime = $toLocal->format('H:i:s');
            if ($fromTime === '08:00:00' && $toTime === '07:59:59' && $toLocal->format('Y-m-d') === $fromLocal->modify('+1 day')->format('Y-m-d')) {
                return $fromLocal->format('Y-m-d');
            }
            if ($fromTime === '00:00:00' && $toTime === '23:59:59' && $toLocal->format('Y-m-d') === $fromLocal->format('Y-m-d')) {
                return $fromLocal->modify('-1 day')->format('Y-m-d');
            }
        }

        $fromUtc = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumOd, $utc);
        $toUtc = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datumDo, $utc);
        if (($fromUtc instanceof DateTimeImmutable) && ($toUtc instanceof DateTimeImmutable)) {
            $fromUtcLocal = $fromUtc->setTimezone($tz);
            $toUtcLocal = $toUtc->setTimezone($tz);
            $fromTime = $fromUtcLocal->format('H:i:s');
            $toTime = $toUtcLocal->format('H:i:s');
            if ($fromTime === '08:00:00' && $toTime === '07:59:59' && $toUtcLocal->format('Y-m-d') === $fromUtcLocal->modify('+1 day')->format('Y-m-d')) {
                return $fromUtcLocal->format('Y-m-d');
            }
            if ($fromTime === '00:00:00' && $toTime === '23:59:59' && $toUtcLocal->format('Y-m-d') === $fromUtcLocal->format('Y-m-d')) {
                return $fromUtcLocal->modify('-1 day')->format('Y-m-d');
            }
        }

        throw new RuntimeException(
            'Neznamy interval posledniho importu historie pro pobocku id=' . (string)$idPob
            . ' (' . $datumOd . ' - ' . $datumDo . ').'
        );
    }
}

if (!function_exists('cb_restia_online_last_data_at')) {
    function cb_restia_online_last_data_at(mysqli $conn, int $idPob): string
    {
        if ($idPob <= 0) {
            return '';
        }

        $stmt = $conn->prepare('
            SELECT MAX(restia_created_at) AS last_dt
            FROM objednavky_restia
            WHERE id_pob = ?
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

        return trim((string)($row['last_dt'] ?? ''));
    }
}

if (!function_exists('cb_restia_online_resume_info')) {
    function cb_restia_online_resume_info(mysqli $conn, array $branch): array
    {
        $idPob = (int)($branch['id_pob'] ?? 0);
        $startDate = cb_restia_online_branch_start_date($branch);
        $lastOkDate = ($idPob > 0) ? cb_restia_online_last_date($conn, $idPob) : '';
        $lastDataAt = ($idPob > 0) ? cb_restia_online_last_data_at($conn, $idPob) : '';
        $nextDate = $startDate;

        if ($lastOkDate !== '') {
            $nextDate = cb_restia_online_next_date($lastOkDate);
            if ($nextDate < $startDate) {
                throw new RuntimeException('Neposledni datum importu je mensi nez start historie pro pobocku id=' . (string)$idPob);
            }
        }

        return [
            'start_date' => $startDate,
            'last_date' => $lastOkDate,
            'last_data_at' => $lastDataAt,
            'next_date' => $nextDate,
        ];
    }
}

if (!function_exists('cb_restia_online_day_lock_name')) {
    function cb_restia_online_day_lock_name(int $idPob, string $date): string
    {
        return 'cb_restia_online_' . $idPob . '_' . $date;
    }
}

if (!function_exists('cb_restia_online_day_lock_acquire')) {
    function cb_restia_online_day_lock_acquire(mysqli $conn, int $idPob, string $date, int $timeoutSec = 10): string
    {
        $lockName = cb_restia_online_day_lock_name($idPob, $date);
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

if (!function_exists('cb_restia_online_day_lock_release')) {
    function cb_restia_online_day_lock_release(mysqli $conn, string $lockName): void
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

if (!function_exists('cb_restia_online_lookup_id')) {
    function cb_restia_online_lookup_id(mysqli $conn, string $table, string $valueCol, string $value, string $idCol): int
{
    static $cache = [];

    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $key = $table . '|' . $value;

    if (in_array($table, ['cis_obj_platforma','cis_obj_stav','cis_obj_platby','cis_doruceni'], true)) {

        if (isset($cache[$key])) {
            return $cache[$key];
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
            $cache[$key] = $id;
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

        $cache[$key] = $id;

        return $id;
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
if (!function_exists('cb_restia_online_lookup_res_polozka_id')) {
    function cb_restia_online_lookup_res_polozka_id(mysqli $conn, int $idPob, string $restiaItemId): int
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
if (!function_exists('cb_restia_online_order_exists')) {
    function cb_restia_online_order_exists(mysqli $conn, string $restiaId): bool
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

if (!function_exists('cb_restia_online_get_obj_id')) {
    function cb_restia_online_get_obj_id(mysqli $conn, string $restiaIdObj): int
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

if (!function_exists('cb_restia_online_existing_order_map')) {
    function cb_restia_online_existing_order_map(mysqli $conn, array $restiaIds): array
    {
        $ids = [];
        foreach ($restiaIds as $restiaId) {
            $restiaId = trim((string)$restiaId);
            if ($restiaId !== '') {
                $ids[$restiaId] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $quoted = [];
        foreach (array_keys($ids) as $restiaId) {
            $quoted[] = "'" . $conn->real_escape_string($restiaId) . "'";
        }

        $sql = '
            SELECT o.restia_id_obj, o.id_obj, c.cas_uzavreni
            FROM objednavky_restia o
            LEFT JOIN obj_casy c ON c.id_obj = o.id_obj
            WHERE o.restia_id_obj IN (' . implode(',', $quoted) . ')
        ';
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na existujici objednavky selhal.');
        }

        $map = [];
        while ($row = $res->fetch_assoc()) {
            $restiaId = trim((string)($row['restia_id_obj'] ?? ''));
            $idObj = (int)($row['id_obj'] ?? 0);
            if ($restiaId !== '' && $idObj > 0) {
                $map[$restiaId] = [
                    'id_obj' => $idObj,
                    'cas_uzavreni' => trim((string)($row['cas_uzavreni'] ?? '')),
                ];
            }
        }
        $res->free();

        return $map;
    }
}

if (!function_exists('cb_restia_online_stmt')) {
    function cb_restia_online_stmt(mysqli $conn, string $key, string $sql, string $label): mysqli_stmt
    {
        static $cache = [];

        $cacheKey = spl_object_hash($conn) . '|' . $key;
        if (isset($cache[$cacheKey]) && $cache[$cacheKey] instanceof mysqli_stmt) {
            return $cache[$cacheKey];
        }

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: ' . $label . '.');
        }

        $cache[$cacheKey] = $stmt;

        return $stmt;
    }
}

if (!function_exists('cb_restia_online_obj_import_find')) {
    function cb_restia_online_obj_import_find(mysqli $conn, int $idPob, string $fromUtcDb, string $toUtcDb): int
    {
        $typ = 'online';
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

if (!function_exists('cb_restia_online_obj_import_begin')) {
    function cb_restia_online_obj_import_begin(mysqli $conn, int $idPob, string $fromUtcDb, string $toUtcDb): int
    {
        $existing = cb_restia_online_obj_import_find($conn, $idPob, $fromUtcDb, $toUtcDb);
        if ($existing > 0) {
            return $existing;
        }

        $typ = 'online';
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

if (!function_exists('cb_restia_online_obj_import_finish')) {
    function cb_restia_online_obj_import_finish(
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

if (!function_exists('cb_restia_online_restia_to_local_nullable')) {
    function cb_restia_online_restia_to_local_nullable(mixed $value): ?string
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

if (!function_exists('cb_restia_online_report_date')) {
    function cb_restia_online_report_date(?string $localDateTime): string
    {
        if (!is_string($localDateTime) || trim($localDateTime) === '') {
            return cb_restia_online_today();
        }

        try {
            $dt = new DateTimeImmutable($localDateTime, new DateTimeZone('Europe/Prague'));
            $hour = (int)$dt->format('G');
            if ($hour < 6) {
                $dt = $dt->modify('-1 day');
            }
            return $dt->format('Y-m-d');
        } catch (Throwable $e) {
            return cb_restia_online_today();
        }
    }
}

if (!function_exists('cb_restia_online_money')) {
    function cb_restia_online_money(mixed $value): string
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
if (!function_exists('cb_restia_online_sync_children')) {
    function cb_restia_online_sync_children(mysqli $conn, int $idObj, int $idPob, array $order, bool $isNewOrder = false): void
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

        $stmtAddr = cb_restia_online_stmt($conn, 'obj_adresa_upsert', '
            INSERT INTO obj_adresa (
                id_obj, ulice, cislo_domovni, mesto, psc, stat, lat, lng, vzdalenost_m, cas_jizdy_s, vytvoreno, zmeneno
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3), NOW(3))
            ON DUPLICATE KEY UPDATE
                ulice = VALUES(ulice), cislo_domovni = VALUES(cislo_domovni), mesto = VALUES(mesto), psc = VALUES(psc), stat = VALUES(stat),
                lat = VALUES(lat), lng = VALUES(lng), vzdalenost_m = VALUES(vzdalenost_m), cas_jizdy_s = VALUES(cas_jizdy_s),
                zmeneno = NOW(3)
        ', 'obj_adresa');
        $stmtAddr->bind_param('isssssddii', $idObj, $street, $house, $city, $zip, $country, $lat, $lng, $distance, $driveTime);
        $stmtAddr->execute();

        $casVytvor = cb_restia_online_restia_to_local_nullable($order['createdAt'] ?? null);
        $casExp = cb_restia_online_restia_to_local_nullable($order['expiresAt'] ?? null);
        $casSlib = cb_restia_online_restia_to_local_nullable($order['promisedAt'] ?? null);
        $casPriprDo = cb_restia_online_restia_to_local_nullable($order['prepareAt'] ?? null);
        $casPriprV = cb_restia_online_restia_to_local_nullable($order['preparedAt'] ?? null);
        $casDokonc = cb_restia_online_restia_to_local_nullable($order['finishedAt'] ?? null);
        $casDoruc = cb_restia_online_restia_to_local_nullable($order['deliveredAt'] ?? null);
        $casStatus = cb_restia_online_restia_to_local_nullable($order['statusUpdatedAt'] ?? null);
        $casUzavreni = cb_restia_online_restia_to_local_nullable($order['closedAt'] ?? null);
        $casImportRestia = cb_restia_online_restia_to_local_nullable($order['importedAt'] ?? null);
        $casImportPos = cb_restia_online_restia_to_local_nullable($order['posImportedAt'] ?? null);
        $casVyzv = cb_restia_online_restia_to_local_nullable($order['pickupAt'] ?? null);
        $casDisp = cb_restia_online_restia_to_local_nullable($order['deliveryAt'] ?? null);
        $report = cb_restia_online_report_date($casVytvor ?? cb_restia_online_now());

        $stmtCasy = cb_restia_online_stmt($conn, 'obj_casy_upsert', '
            INSERT INTO obj_casy (
                id_obj, report, cas_vytvor, cas_expirace, cas_slib, cas_pripr_do, cas_pripr_v, cas_dokonc, cas_doruc,
                cas_status_zmena, cas_uzavreni, cas_import_restia, cas_import_pos, cas_vyzv, cas_disp
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                report = VALUES(report), cas_vytvor = VALUES(cas_vytvor), cas_expirace = VALUES(cas_expirace), cas_slib = VALUES(cas_slib),
                cas_pripr_do = VALUES(cas_pripr_do), cas_pripr_v = VALUES(cas_pripr_v), cas_dokonc = VALUES(cas_dokonc), cas_doruc = VALUES(cas_doruc),
                cas_status_zmena = VALUES(cas_status_zmena), cas_uzavreni = VALUES(cas_uzavreni), cas_import_restia = VALUES(cas_import_restia),
                cas_import_pos = VALUES(cas_import_pos), cas_vyzv = VALUES(cas_vyzv), cas_disp = VALUES(cas_disp)
        ', 'obj_casy');
        $stmtCasy->bind_param('issssssssssssss', $idObj, $report, $casVytvor, $casExp, $casSlib, $casPriprDo, $casPriprV, $casDokonc, $casDoruc, $casStatus, $casUzavreni, $casImportRestia, $casImportPos, $casVyzv, $casDisp);
        $stmtCasy->execute();

        $cenaPol = (float)cb_restia_online_money($order['itemsPrice'] ?? null);
        $cenaBalne = (float)cb_restia_online_money($order['packingPrice'] ?? null);
        $cenaDopr = (float)cb_restia_online_money($order['deliveryPrice'] ?? null);
        $dyska = (float)cb_restia_online_money($order['tipPrice'] ?? null);
        $cenaDoMin = (float)cb_restia_online_money($order['surchargeToMin'] ?? null);
        $cenaServis = (float)cb_restia_online_money($order['serviceFeePrice'] ?? null);
        $sleva = (float)cb_restia_online_money($order['discountPrice'] ?? null);
        $zaokrouhleni = (float)cb_restia_online_money($order['roundingPrice'] ?? null);
        $cenaCelk = $cenaPol + $cenaBalne + $cenaDopr + $dyska + $cenaDoMin + $cenaServis + $zaokrouhleni - $sleva;

        $stmtCeny = cb_restia_online_stmt($conn, 'obj_ceny_upsert', '
            INSERT INTO obj_ceny (
                id_obj, cena_celk, cena_pol, cena_balne, cena_dopr, dyska, cena_do_min, cena_servis, sleva, zaokrouhleni, mena
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "CZK")
            ON DUPLICATE KEY UPDATE
                cena_celk = VALUES(cena_celk), cena_pol = VALUES(cena_pol), cena_balne = VALUES(cena_balne), cena_dopr = VALUES(cena_dopr),
                dyska = VALUES(dyska), cena_do_min = VALUES(cena_do_min), cena_servis = VALUES(cena_servis), sleva = VALUES(sleva), zaokrouhleni = VALUES(zaokrouhleni), mena = VALUES(mena)
        ', 'obj_ceny');
        $stmtCeny->bind_param('iddddddddd', $idObj, $cenaCelk, $cenaPol, $cenaBalne, $cenaDopr, $dyska, $cenaDoMin, $cenaServis, $sleva, $zaokrouhleni);
        $stmtCeny->execute();

        if (!$isNewOrder) {
            $stmtDelKuryr = cb_restia_online_stmt($conn, 'obj_kuryr_delete', 'DELETE FROM obj_kuryr WHERE id_obj = ?', 'delete obj_kuryr');
            $stmtDelSluzba = cb_restia_online_stmt($conn, 'obj_sluzba_delete', 'DELETE FROM obj_sluzba WHERE id_obj = ?', 'delete obj_sluzba');
            $stmtDelTags = cb_restia_online_stmt($conn, 'obj_polozka_kds_tag_delete', 'DELETE t FROM obj_polozka_kds_tag t JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka WHERE p.id_obj = ?', 'delete obj_polozka_kds_tag');
            $stmtDelPolozky = cb_restia_online_stmt($conn, 'obj_polozky_delete', 'DELETE FROM obj_polozky WHERE id_obj = ?', 'delete obj_polozky');
            $stmtDelKuryr->bind_param('i', $idObj); $stmtDelKuryr->execute();
            $stmtDelSluzba->bind_param('i', $idObj); $stmtDelSluzba->execute();
            $stmtDelTags->bind_param('i', $idObj); $stmtDelTags->execute();
            $stmtDelPolozky->bind_param('i', $idObj); $stmtDelPolozky->execute();
        }

        $courier = (isset($order['courierData']) && is_array($order['courierData'])) ? $order['courierData'] : null;
        if (is_array($courier)) {
            $provider = (string)($order['deliveryType'] ?? '');
            $externiId = (string)($courier['id'] ?? '');
            $poradi = isset($order['courierOrder']) ? (int)$order['courierOrder'] : null;
            $jmeno = (string)($courier['name'] ?? '');
            $telefon = (string)($courier['phone'] ?? '');
            $stmtKuryr = cb_restia_online_stmt($conn, 'obj_kuryr_insert', 'INSERT INTO obj_kuryr (id_obj, provider, externi_id, poradi, jmeno, telefon, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, ?, ?, NOW(3), NOW(3))', 'obj_kuryr');
            $stmtKuryr->bind_param('ississ', $idObj, $provider, $externiId, $poradi, $jmeno, $telefon);
            $stmtKuryr->execute();
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
            $stmtService = cb_restia_online_stmt($conn, 'obj_sluzba_insert', 'INSERT INTO obj_sluzba (id_obj, provider, externi_id, stav, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, NOW(3), NOW(3))', 'obj_sluzba');
            $stmtService->bind_param('isss', $idObj, $provider, $externiId, $stav);
            $stmtService->execute();
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
            $cenaKs = (float)cb_restia_online_money($item['price'] ?? 0);
            $cenaCelk = isset($item['totalPrice']) ? (float)cb_restia_online_money($item['totalPrice']) : ($cenaKs * $mnozstvi);
            $jeExtra = !empty($item['isExtra']) ? 1 : 0;
            $idResPolozka = cb_restia_online_lookup_res_polozka_id($conn, $idPob, $restiaItemId);
            if ($idResPolozka <= 0) {
                 $idResPolozka = null;
            }
            $stmtItem = cb_restia_online_stmt($conn, 'obj_polozky_insert', 'INSERT INTO obj_polozky (id_obj, id_res_polozka, res_item, poznamka, poradi, mnozstvi, cena_ks, cena_celk, je_extra, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3))', 'obj_polozky');
            $stmtItem->bind_param('iissiiddi', $idObj, $idResPolozka, $restiaItemId, $poznamka, $poradi, $mnozstvi, $cenaKs, $cenaCelk, $jeExtra);
            $stmtItem->execute();
            $idObjPolozka = (int)$conn->insert_id;

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
                    $modCenaKs = (float)cb_restia_online_money($mod['price'] ?? 0);
                    $modCenaCelk = isset($mod['totalPrice']) ? (float)cb_restia_online_money($mod['totalPrice']) : ($modCenaKs * $modMnoz);
                    $stmtMod = cb_restia_online_stmt($conn, 'obj_polozka_mod_insert', 'INSERT INTO obj_polozka_mod (id_obj_polozka, restia_mod_id, typ, pos_id, nazev, mnozstvi, cena_ks, cena_celk, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(3))', 'obj_polozka_mod');
                    $stmtMod->bind_param('issssddd', $idObjPolozka, $restiaModId, $typ, $modPosId, $modNazev, $modMnoz, $modCenaKs, $modCenaCelk);
                    $stmtMod->execute();
                }
            }

            $kdsTags = [];
            if (isset($item['KDSTags']) && is_array($item['KDSTags'])) { $kdsTags = $item['KDSTags']; }
            elseif (isset($item['kdsTags']) && is_array($item['kdsTags'])) { $kdsTags = $item['kdsTags']; }
            foreach ($kdsTags as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '') { continue; }
                $stmtTag = cb_restia_online_stmt($conn, 'obj_polozka_kds_tag_insert', 'INSERT INTO obj_polozka_kds_tag (id_obj_polozka, tag) VALUES (?, ?)', 'obj_polozka_kds_tag');
                $stmtTag->bind_param('is', $idObjPolozka, $tag);
                $stmtTag->execute();
            }
        }
    }
}

if (!function_exists('cb_restia_online_enable_zero_autoinc')) {
    function cb_restia_online_enable_zero_autoinc(mysqli $conn): void
    {
        $sql = "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO')";
        if ($conn->query($sql) === false) {
            throw new RuntimeException('Nepodarilo se nastavit sql_mode NO_AUTO_VALUE_ON_ZERO.');
        }
    }
}

if (!function_exists('cb_restia_online_default_pob_id')) {
    function cb_restia_online_default_pob_id(mysqli $conn): int
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

if (!function_exists('cb_restia_online_ensure_default_customer')) {
    function cb_restia_online_ensure_default_customer(mysqli $conn, int $idPobHint = 0): void
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

        cb_restia_online_enable_zero_autoinc($conn);

        $idPob = $idPobHint > 0 ? $idPobHint : cb_restia_online_default_pob_id($conn);
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

if (!function_exists('cb_restia_online_norm_phone')) {
    function cb_restia_online_norm_phone(?string $phone): string
    {
        $raw = trim((string)($phone ?? ''));
        if ($raw === '') {
            return '';
        }
        $norm = preg_replace('/[^0-9]/', '', $raw);
        return is_string($norm) ? $norm : '';
    }
}

if (!function_exists('cb_restia_online_split_name')) {
    function cb_restia_online_split_name(?string $fullName): array
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

if (!function_exists('cb_restia_online_upsert_customer')) {
    function cb_restia_online_upsert_customer(mysqli $conn, int $idPob, array $order): int
    {
        $emailRaw = trim((string)($order['customerEmail'] ?? ''));
        if ($emailRaw === '' || strtolower($emailRaw) === 'null') {
            $emailRaw = '';
        }
        $phoneRaw = trim((string)($order['customerPhone'] ?? ''));
        if ($phoneRaw === '' || strtolower($phoneRaw) === 'null') {
            $phoneRaw = '';
        }
        $phoneNorm = cb_restia_online_norm_phone($phoneRaw);

        if ($phoneNorm === '') {
            return 0;
        }

        $name = cb_restia_online_split_name((string)($order['customerName'] ?? ''));
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
        $stmt = cb_restia_online_stmt($conn, 'zakaznik_find_by_phone', 'SELECT id_zak FROM zakaznik WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefon, ""), " ", ""), "+", ""), "-", ""), "(", ""), ")", ""), "/", "") = ? ORDER BY id_zak ASC LIMIT 1', 'find zakaznik by phone');
        $stmt->bind_param('s', $phoneNorm);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $idZak = (int)($row['id_zak'] ?? 0);

        if ($idZak > 0) {
            $stmt = cb_restia_online_stmt($conn, 'zakaznik_update', '
                UPDATE zakaznik
                SET jmeno = ?, prijmeni = ?, telefon = ?, email = ?, ulice = ?, mesto = ?,
                    zak_menu = ?, zak_news = ?, posledni_obj = NOW(), poznamka = ?, blokovany = ?, id_pob = ?, aktivni = ?
                WHERE id_zak = ?
                LIMIT 1
            ', 'update zakaznik');
           $stmt->bind_param('ssssssiisiiii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni, $idZak);
            $stmt->execute();
            return $idZak;
        }

        $stmt = cb_restia_online_stmt($conn, 'zakaznik_insert', '
            INSERT INTO zakaznik (
                jmeno, prijmeni, telefon, email, ulice, mesto,
                zak_menu, zak_news, posledni_obj, poznamka, blokovany, id_pob, zadano, aktivni
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), ?
            )
        ', 'insert zakaznik');
        $stmt->bind_param('ssssssiisiii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni);
        $stmt->execute();
        $idZak = (int)$conn->insert_id;

        return $idZak;
    }
}

if (!function_exists('cb_restia_online_format_total_time')) {
    function cb_restia_online_format_total_time(int $ms): string
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

if (!function_exists('cb_restia_online_sum_orders')) {
    function cb_restia_online_sum_orders(array $rows): int
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

if (!function_exists('cb_restia_online_upsert_order')) {
    function cb_restia_online_upsert_order(mysqli $conn, int $idPob, string $activePosId, array $order, int $existingIdObj = 0): array
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

        $idPlatforma = cb_restia_online_lookup_id($conn, 'cis_obj_platforma', 'kod', $profilTyp, 'id_platforma');
        $idStav = ($status === '') ? null : cb_restia_online_lookup_id($conn, 'cis_obj_stav', 'nazev', $status, 'id_stav');
        $idPlatba = ($paymentType === '') ? null : cb_restia_online_lookup_id($conn, 'cis_obj_platby', 'nazev', $paymentType, 'id_platba');
        $idDoruceni = ($deliveryType === '') ? null : cb_restia_online_lookup_id($conn, 'cis_doruceni', 'nazev', $deliveryType, 'id_doruceni');
        $idZak = cb_restia_online_upsert_customer($conn, $idPob, $order);
        if ($idZak === 0) { $idZak = null; }

        $restiaOrderNumber = trim((string)($order['orderNumber'] ?? ''));
        $restiaToken = $order['token'] ?? null;
        $restiaToken = ($restiaToken === null || $restiaToken === '') ? null : (string)$restiaToken;
        $restiaCreatedAt = cb_restia_online_restia_to_local_nullable($order['createdAt'] ?? null);
        $objPoznamka = $order['note'] ?? null;
        $objPoznamka = ($objPoznamka === null || $objPoznamka === '') ? null : (string)$objPoznamka;
        $importTs = cb_restia_online_now();

        $restObj = $restiaIdObj;

        if ($existingIdObj > 0) {
            $sql = '
                UPDATE objednavky_restia
                SET id_pob = ?,
                    id_zak = ?,
                    id_platforma = ?,
                    restia_created_at = ?,
                    restia_order_number = ?,
                    restia_token = ?,
                    profil_typ = ?,
                    rest_obj = ?,
                    id_stav = ?,
                    id_platba = ?,
                    id_doruceni = ?,
                    obj_pozn = ?,
                    restia_imported_at = ?
                WHERE id_obj = ?
                  AND restia_id_obj = ?
                LIMIT 1
            ';

            $stmt = cb_restia_online_stmt($conn, 'objednavky_restia_update_by_restia_id', $sql, 'objednavky_restia update by restia_id_obj');
            $stmt->bind_param(
                'iiisssssiiissis',
                $idPob,
                $idZak,
                $idPlatforma,
                $restiaCreatedAt,
                $restiaOrderNumber,
                $restiaToken,
                $profilTyp,
                $restObj,
                $idStav,
                $idPlatba,
                $idDoruceni,
                $objPoznamka,
                $importTs,
                $existingIdObj,
                $restiaIdObj
            );
            $stmt->execute();

            return [
                'id_obj' => $existingIdObj,
                'is_new' => false,
            ];
        }

        $sql = '
            INSERT INTO objednavky_restia (
                id_pob, id_zak, id_platforma, restia_id_obj, restia_created_at, restia_order_number, restia_token,
                profil_typ, rest_obj,
                id_stav, id_platba, id_doruceni,
                obj_pozn, restia_imported_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';

        $stmt = cb_restia_online_stmt($conn, 'objednavky_restia_insert', $sql, 'objednavky_restia insert');
        $stmt->bind_param('iiissssssiisss', $idPob, $idZak, $idPlatforma, $restiaIdObj, $restiaCreatedAt, $restiaOrderNumber, $restiaToken, $profilTyp, $restObj, $idStav, $idPlatba, $idDoruceni, $objPoznamka, $importTs);
        $stmt->execute();
        $idObj = (int)$conn->insert_id;
        if ($idObj <= 0) {
            throw new RuntimeException('Nepodarilo se zapsat novou objednavku.');
        }

        return [
            'id_obj' => $idObj,
            'is_new' => true,
        ];
    }
}
if (!function_exists('cb_restia_online_try_flush_api')) {
    function cb_restia_online_try_flush_api(?mysqli $conn, ?array $auth): void
    {
        if (!($conn instanceof mysqli) || !is_array($auth)) { return; }
        db_api_restia_flush($conn, (int)($auth['id_user'] ?? 0), (int)($auth['id_login'] ?? 0));
    }
}

if (!function_exists('cb_restia_online_default_state')) {
    function cb_restia_online_default_state(): array
    {
        return [
            'next_date' => '',
            'start_date' => '',
            'last_date' => '',
            'run_from_date' => '',
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

if (!function_exists('cb_restia_online_import_day')) {
    function cb_restia_online_import_day(mysqli $conn, array $auth, array $branch, string $date): array
    {
        $dayStartMs = (int)round(microtime(true) * 1000);
        $idPob = (int)$branch['id_pob'];
        $nazev = (string)$branch['nazev'];
        $activePosId = (string)$branch['active_pos_id'];
        $range = cb_restia_online_day_range_utc($date);

        $idImport = 0;
        $lockName = '';

        $pocetObj = 0; $pocetNovych = 0; $pocetZmenenych = 0; $pocetChyb = 0;
        $logLines = [];

        try {
            $lockName = cb_restia_online_day_lock_acquire($conn, $idPob, $date, 10);
            $idImport = cb_restia_online_obj_import_begin($conn, $idPob, $range['from_db'], $range['to_db']);

            $page = 1;
            while (true) {
                $res = cb_restia_get('/api/orders', [
                    'page' => $page,
                    'limit' => CB_RESTIA_ONLINE_LIMIT,
                    'createdFrom' => $range['from_z'],
                    'createdTo' => $range['to_z'],
                    'activePosId' => $activePosId,
                ], $activePosId, 'online den=' . $date . ' id_pob=' . $idPob . ' page=' . $page);

                if ((int)($res['ok'] ?? 0) !== 1) {
                    $bodySnippet = mb_substr((string)($res['body'] ?? ''), 0, 300);
                    $http = (int)($res['http_status'] ?? 0);
                    throw new RuntimeException('Restia chyba HTTP=' . $http . ' body=' . $bodySnippet);
                }

                $decoded = json_decode((string)($res['body'] ?? ''), true);
                if (!is_array($decoded)) { throw new RuntimeException('Restia vratila neplatny JSON.'); }

                $orders = cb_restia_online_extract_orders($decoded);
                $countOrders = count($orders);

                $pageRestiaIds = [];
                foreach ($orders as $order) {
                    if (!is_array($order)) {
                        throw new RuntimeException('Restia vratila neplatnou objednavku.');
                    }
                    $restiaIdObj = trim((string)($order['id'] ?? ''));
                    if ($restiaIdObj === '') {
                        throw new RuntimeException('Objednavka nema id.');
                    }
                    $pageRestiaIds[] = $restiaIdObj;
                }

                $existingMap = cb_restia_online_existing_order_map($conn, $pageRestiaIds);
                $pagePocetObj = 0;
                $pagePocetNovych = 0;
                $pagePocetZmenenych = 0;
                $pagePocetChyb = 0;

                $conn->begin_transaction();
                try {
                    foreach ($orders as $orderIndex => $order) {
                        $restiaIdObj = $pageRestiaIds[$orderIndex] ?? '';
                        $existingInfo = (isset($existingMap[$restiaIdObj]) && is_array($existingMap[$restiaIdObj])) ? $existingMap[$restiaIdObj] : [];
                        $existingIdObj = (int)($existingInfo['id_obj'] ?? 0);
                        $existingCasUzavreni = trim((string)($existingInfo['cas_uzavreni'] ?? ''));
                        if ($existingIdObj > 0 && $existingCasUzavreni !== '') {
                            continue;
                        }
                        $savepoint = 'restia_' . $page . '_' . ($orderIndex + 1);
                        if ($conn->query('SAVEPOINT ' . $savepoint) === false) {
                            throw new RuntimeException('Nepodarilo se zalozit savepoint pro objednavku.');
                        }

                        try {
                            $upsert = cb_restia_online_upsert_order($conn, $idPob, $activePosId, $order, $existingIdObj);
                            cb_restia_online_sync_children($conn, (int)$upsert['id_obj'], $idPob, $order, (bool)$upsert['is_new']);
                            $logLines[] = cb_restia_online_log_order((int)$upsert['id_obj'], $order, (bool)($upsert['is_new'] ?? false));
                            $conn->query('RELEASE SAVEPOINT ' . $savepoint);
                        } catch (Throwable $e) {
                            $conn->query('ROLLBACK TO SAVEPOINT ' . $savepoint);
                            $conn->query('RELEASE SAVEPOINT ' . $savepoint);
                            cb_restia_online_error_log(
                                $idPob,
                                'ORDER_ERR: datum=' . $date
                                . ' | id_pob=' . $idPob
                                . ' | page=' . $page
                                . ' | restia_id_obj=' . $restiaIdObj
                                . ' | msg=' . $e->getMessage()
                            );
                            $pagePocetChyb++;
                            continue;
                        }

                        $pagePocetObj++;
                        if ((bool)($upsert['is_new'] ?? false)) {
                            $pagePocetNovych++;
                        } else {
                            $pagePocetZmenenych++;
                        }
                    }

                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }

                $pocetObj += $pagePocetObj;
                $pocetNovych += $pagePocetNovych;
                $pocetZmenenych += $pagePocetZmenenych;
                $pocetChyb += $pagePocetChyb;

                if ($countOrders < CB_RESTIA_ONLINE_LIMIT) { break; }
                $totalCount = isset($res['total_count']) ? (int)$res['total_count'] : 0;
                if ($totalCount > 0 && ($page * CB_RESTIA_ONLINE_LIMIT) >= $totalCount) { break; }
                if ($countOrders === 0) { break; }
                $page++;
            }

            $poznamka = 'den=' . $date . ' id_pob=' . $idPob . ' obj=' . $pocetObj;
            cb_restia_online_obj_import_finish($conn, $idImport, $pocetObj, $pocetNovych, $pocetZmenenych, $pocetChyb, $poznamka);
            cb_restia_online_try_flush_api($conn, $auth);

            $dayMs = (int)round(microtime(true) * 1000) - $dayStartMs;
            return ['date' => $date, 'branch' => $nazev, 'count' => $pocetObj, 'nove' => $pocetNovych, 'aktualizace' => $pocetZmenenych, 'errors' => $pocetChyb, 'error' => '', 'day_ms' => $dayMs, 'ok' => 1, 'log_lines' => $logLines];
        } catch (Throwable $e) {
            cb_restia_online_try_flush_api($conn, $auth);
            $fatalLine = 'FATAL_STEP: datum=' . $date . ' | id_pob=' . $idPob . ' | msg=' . $e->getMessage();
            cb_restia_online_log($idPob, $fatalLine);
            cb_restia_online_error_log($idPob, $fatalLine);
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
                cb_restia_online_error_log($idPob, 'WARN: log_chyby insert selhal: ' . $logErr->getMessage());
            }
            throw $e;
        } finally {
            cb_restia_online_day_lock_release($conn, $lockName);
        }
    }
}


if (!function_exists('cb_restia_online_log_line')) {
    function cb_restia_online_log_line(string $line): void
    {
        @file_put_contents(cb_restia_online_txt_path(0), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_online_log_order')) {
    function cb_restia_online_log_time_only(?string $datetime, string $fallback = ''): string
    {
        $datetime = trim((string)$datetime);
        if ($datetime === '') {
            return $fallback;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datetime, new DateTimeZone('Europe/Prague'));
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('H:i:s');
        }

        return $datetime;
    }

    function cb_restia_online_log_order(int $idObj, array $order, bool $isNew): string
    {
        $casVytvor = cb_restia_online_restia_to_local_nullable($order['createdAt'] ?? null);
        $casDokonc = cb_restia_online_restia_to_local_nullable($order['finishedAt'] ?? null);
        $casDoruc = cb_restia_online_restia_to_local_nullable($order['deliveredAt'] ?? null);
        $casUzavreni = cb_restia_online_restia_to_local_nullable($order['closedAt'] ?? null);
        $casDokoncText = cb_restia_online_log_time_only($casDokonc, 'vyrabi se');
        $casDorucText = ($casDokoncText === 'vyrabi se')
            ? '-'
            : cb_restia_online_log_time_only($casDoruc, 'na ceste');
        $prefix = $isNew ? 'N - ' : 'A - ';

        return $prefix
            . (string)$idObj
            . ' / ' . ($casVytvor ?? '')
            . ' / ' . $casDokoncText
            . ' / ' . $casDorucText
            . ' / ' . cb_restia_online_log_time_only($casUzavreni)
        ;
    }
}

if (!function_exists('cb_restia_online_current_workday_date')) {
    function cb_restia_online_current_workday_date(): string
    {
        $tz = new DateTimeZone('Europe/Prague');
        $now = new DateTimeImmutable('now', $tz);
        $todayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' 08:00:00', $tz);
        if (!($todayStart instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se urcit aktualni pracovni den.');
        }
        if ($now < $todayStart) {
            return $todayStart->modify('-1 day')->format('Y-m-d');
        }
        return $todayStart->format('Y-m-d');
    }
}


if (!function_exists('cb_restia_online_active_branches')) {
    function cb_restia_online_active_branches(mysqli $conn): array
    {
        $branches = cb_restia_online_get_branches($conn);
        $out = [];
        foreach ($branches as $branch) {
            if ((bool)($branch['enabled'] ?? false) === true) {
                $out[] = [
                    'id_pob' => (int)($branch['id_pob'] ?? 0),
                    'nazev' => (string)($branch['nazev'] ?? ''),
                    'active_pos_id' => (string)($branch['active_pos_id'] ?? ''),
                    'prvni_obj' => (string)($branch['prvni_obj'] ?? ''),
                ];
            }
        }
        return $out;
    }
}


if (!function_exists('cb_restia_online_run')) {
    function cb_restia_online_run(): array
    {
        $conn = db();
        $resSet = $conn->query('SELECT restia_online FROM set_system WHERE id_set = 1');
        $rowSet = $resSet->fetch_assoc();
        $resSet->free();
        if ((int)$rowSet['restia_online'] !== 1) {
            return [
                'zapisy' => 0,
                'aktualizace' => 0,
                'chyba' => '',
            ];
        }

        $zapisy = 0;
        $aktualizace = 0;
        $chyba = '';

        try {
            $auth = cb_restia_online_get_auth();

            cb_restia_online_log_line('');
            cb_restia_online_log_line('');
            cb_restia_online_log_line('Spusteno ' . cb_restia_online_now());

            $date = cb_restia_online_current_workday_date();
            $branches = cb_restia_online_active_branches($conn);
            foreach ($branches as $branch) {
                $day = cb_restia_online_import_day($conn, $auth, $branch, $date);
                $branchName = trim((string)($branch['nazev'] ?? ''));
                if ($branchName === '') {
                    $branchName = 'Pobocka #' . (string)((int)($branch['id_pob'] ?? 0));
                }
                if ((int)($day['nove'] ?? 0) > 0 || (int)($day['aktualizace'] ?? 0) > 0) {
                    cb_restia_online_log_line('');
                    cb_restia_online_log_line('Pobocka ' . $branchName);
                    foreach ((array)($day['log_lines'] ?? []) as $logLine) {
                        cb_restia_online_log_line((string)$logLine);
                    }
                }
                $zapisy += (int)($day['nove'] ?? 0);
                $aktualizace += (int)($day['aktualizace'] ?? 0);
            }
        } catch (Throwable $e) {
            $chyba = $e->getMessage();
            cb_restia_online_log_line('CHYBA: ' . $chyba);
        }

        return [
            'zapisy' => $zapisy,
            'aktualizace' => $aktualizace,
            'chyba' => $chyba,
        ];
    }
}

return cb_restia_online_run();
