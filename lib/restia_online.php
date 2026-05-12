<?php
// lib/restia_online.php * Verze: V5 * Aktualizace: 28.04.2026
declare(strict_types=1);

if (empty($GLOBALS['cb_restia_online_session_ready']) && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/restia_access_exist.php';
require_once __DIR__ . '/../lib/restia_client.php';
require_once __DIR__ . '/../db/db_api_restia.php';
require_once __DIR__ . '/../db/zapis_log_chyby.php';

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}

if (!function_exists('cb_restia_online_txt_path')) {
    function cb_restia_online_txt_path(int $idPob): string
    {
        $safeIdPob = max(0, $idPob);
        return __DIR__ . '/../log/restia_online.txt';
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

if (!function_exists('cb_restia_online_error_log')) {
    function cb_restia_online_error_log(int $idPob, string $line): void
    {
        @file_put_contents(cb_restia_online_error_txt_path($idPob), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_online_now')) {
    function cb_restia_online_now(): string
    {
        return cb_dt_now_prague();
    }
}

if (!function_exists('cb_restia_online_now_utc_z')) {
    function cb_restia_online_now_utc_z(): string
    {
        return cb_dt_now_utc_z();
    }
}

if (!function_exists('cb_restia_online_today')) {
    function cb_restia_online_today(): string
    {
        return cb_dt_today_prague();
    }
}

if (!function_exists('cb_restia_online_format_date_cs')) {
    function cb_restia_online_format_date_cs(string $date): string
    {
        return cb_dt_format_date_cs($date);
    }
}

if (!function_exists('cb_restia_online_format_month_year_cs')) {
    function cb_restia_online_format_month_year_cs(string $date): string
    {
        return cb_dt_format_month_year_cs($date);
    }
}

if (!function_exists('cb_restia_online_normalize_ymd')) {
    function cb_restia_online_normalize_ymd(string $raw): string
    {
        return cb_dt_normalize_ymd($raw);
    }
}

if (!function_exists('cb_restia_online_format_datetime_cs')) {
    function cb_restia_online_format_datetime_cs(string $datetime): string
    {
        return cb_dt_format_datetime_cs($datetime);
    }
}

if (!function_exists('cb_restia_online_format_range_cs')) {
    function cb_restia_online_format_range_cs(string $fromDate, string $toDate): string
    {
        return cb_dt_format_range_cs($fromDate, $toDate, 6);
    }
}

if (!function_exists('cb_restia_online_next_date')) {
    function cb_restia_online_next_date(string $date): string
    {
        return cb_dt_next_date($date);
    }
}

if (!function_exists('cb_restia_online_day_range_utc')) {
    function cb_restia_online_day_range_utc(string $date): array
    {
        return cb_dt_workday_range_utc($date, 6);
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

if (!function_exists('cb_restia_online_first_open_created_at')) {
    function cb_restia_online_first_open_created_at(mysqli $conn, int $idPob, string $workdayStartLocal): string
    {
        if ($idPob <= 0) {
            return '';
        }

        $workdayStartLocal = trim($workdayStartLocal);
        if ($workdayStartLocal === '') {
            return '';
        }

        $stmt = $conn->prepare('
            SELECT MIN(o.restia_created_at) AS first_open_created_at
            FROM objednavky_restia o
            LEFT JOIN obj_casy c ON c.id_obj = o.id_obj
            WHERE o.id_pob = ?
              AND o.restia_created_at IS NOT NULL
              AND o.restia_created_at >= ?
              AND c.cas_uzavreni IS NULL
        ');
        if ($stmt === false) {
            return '';
        }

        $stmt->bind_param('is', $idPob, $workdayStartLocal);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        return trim((string)($row['first_open_created_at'] ?? ''));
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
            SELECT o.restia_id_obj, o.id_obj, c.cas_uzavreni, c.cas_status_zmena
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
                    'cas_status_zmena' => trim((string)($row['cas_status_zmena'] ?? '')),
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
        return cb_dt_utc_to_local_nullable($value);
    }
}

if (!function_exists('cb_restia_online_report_date')) {
    function cb_restia_online_report_date(?string $localDateTime): string
    {
        return cb_dt_report_date($localDateTime, 6);
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

if (!function_exists('cb_restia_online_upsert_order')) {
    function cb_restia_online_upsert_order(mysqli $conn, int $idPob, array $order, int $existingIdObj = 0): array
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
        $profilNazev = trim((string)($profile['name'] ?? ''));
        $profilNazev = ($profilNazev === '') ? null : $profilNazev;
        $shortCode = $order['shortCode'] ?? null;
        $shortCode = ($shortCode === null || $shortCode === '') ? null : (string)$shortCode;
        $serioveCislo = $order['serialNumber'] ?? null;
        $serioveCislo = ($serioveCislo === null || $serioveCislo === '') ? null : (string)$serioveCislo;
        $zpozdeniMin = isset($order['cookingTimeMinutes']) ? (int)$order['cookingTimeMinutes'] : null;
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
                    profil_nazev = ?,
                    rest_obj = ?,
                    short_code = ?,
                    seriove_cislo = ?,
                    id_stav = ?,
                    id_platba = ?,
                    id_doruceni = ?,
                    zpozdeni_min = ?,
                    obj_pozn = ?,
                    restia_imported_at = ?
                WHERE id_obj = ?
                  AND restia_id_obj = ?
                LIMIT 1
            ';

            $stmt = cb_restia_online_stmt($conn, 'objednavky_restia_update_by_restia_id', $sql, 'objednavky_restia update by restia_id_obj');
            $stmt->bind_param(
                'iiissssssssiiiissis',
                $idPob,
                $idZak,
                $idPlatforma,
                $restiaCreatedAt,
                $restiaOrderNumber,
                $restiaToken,
                $profilTyp,
                $profilNazev,
                $restObj,
                $shortCode,
                $serioveCislo,
                $idStav,
                $idPlatba,
                $idDoruceni,
                $zpozdeniMin,
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
                profil_typ, profil_nazev, rest_obj, short_code, seriove_cislo,
                id_stav, id_platba, id_doruceni,
                zpozdeni_min, obj_pozn, restia_imported_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';

        $stmt = cb_restia_online_stmt($conn, 'objednavky_restia_insert', $sql, 'objednavky_restia insert');
        $stmt->bind_param('iiissssssssiiiisss', $idPob, $idZak, $idPlatforma, $restiaIdObj, $restiaCreatedAt, $restiaOrderNumber, $restiaToken, $profilTyp, $profilNazev, $restObj, $shortCode, $serioveCislo, $idStav, $idPlatba, $idDoruceni, $zpozdeniMin, $objPoznamka, $importTs);
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

if (!function_exists('cb_restia_online_import_day')) {
    function cb_restia_online_import_day(mysqli $conn, array $auth, array $branch, string $date): array
    {
        $dayStartMs = (int)round(microtime(true) * 1000);
        $idPob = (int)$branch['id_pob'];
        $nazev = (string)$branch['nazev'];
        $activePosId = (string)$branch['active_pos_id'];
        $dayRange = cb_restia_online_day_range_utc($date);
        $createdFromLocal = (string)($dayRange['from_db'] ?? '');
        $firstOpenCreatedAt = cb_restia_online_first_open_created_at($conn, $idPob, $createdFromLocal);
        if ($firstOpenCreatedAt !== '') {
            $createdFromLocal = $firstOpenCreatedAt;
        }
        $createdFromDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $createdFromLocal, new DateTimeZone('Europe/Prague'));
        if (!($createdFromDt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nelze sestavit createdFrom pro online dotaz.');
        }
        $createdFromZ = $createdFromDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
        $createdToZ = cb_restia_online_now_utc_z();

        $idImport = 0;
        $lockName = '';

        $pocetObj = 0; $pocetNovych = 0; $pocetZmenenych = 0; $pocetIgnore = 0; $pocetChyb = 0;
        $logLines = [];

        try {
            $lockName = cb_restia_online_day_lock_acquire($conn, $idPob, $date, 10);
            $idImport = cb_restia_online_obj_import_begin($conn, $idPob, $dayRange['from_db'], $dayRange['to_db']);
            $res = cb_restia_get('/api/orders', [
                'page' => 1,
                'limit' => 200,
                'createdFrom' => $createdFromZ,
                'createdTo' => $createdToZ,
                'activePosId' => $activePosId,
            ], $activePosId, 'online den=' . $date . ' id_pob=' . $idPob . ' createdFrom=' . $createdFromZ . ' createdTo=' . $createdToZ);

            if ((int)($res['ok'] ?? 0) !== 1) {
                $bodySnippet = mb_substr((string)($res['body'] ?? ''), 0, 300);
                $http = (int)($res['http_status'] ?? 0);
                throw new RuntimeException('Restia chyba HTTP=' . $http . ' body=' . $bodySnippet);
            }

            $decoded = json_decode((string)($res['body'] ?? ''), true);
            if (!is_array($decoded)) { throw new RuntimeException('Restia vratila neplatny JSON.'); }

            $orders = cb_restia_online_extract_orders($decoded);
            $restiaIds = [];
            foreach ($orders as $order) {
                if (!is_array($order)) {
                    throw new RuntimeException('Restia vratila neplatnou objednavku.');
                }
                $restiaIdObj = trim((string)($order['id'] ?? ''));
                if ($restiaIdObj === '') {
                    throw new RuntimeException('Objednavka nema id.');
                }
                $restiaIds[] = $restiaIdObj;
            }

            $existingMap = cb_restia_online_existing_order_map($conn, $restiaIds);

            $conn->begin_transaction();
            try {
                foreach ($orders as $orderIndex => $order) {
                    $restiaIdObj = $restiaIds[$orderIndex] ?? '';
                    $existingInfo = (isset($existingMap[$restiaIdObj]) && is_array($existingMap[$restiaIdObj])) ? $existingMap[$restiaIdObj] : [];
                    $existingIdObj = (int)($existingInfo['id_obj'] ?? 0);
                    $existingCasStatus = trim((string)($existingInfo['cas_status_zmena'] ?? ''));
                    $incomingCasStatus = cb_restia_online_restia_to_local_nullable($order['statusUpdatedAt'] ?? null);
                    if ($existingIdObj > 0 && $existingCasStatus !== '' && $incomingCasStatus !== null && $existingCasStatus === $incomingCasStatus) {
                        $pocetIgnore++;
                        continue;
                    }
                    $savepoint = 'restia_1_' . ($orderIndex + 1);
                    if ($conn->query('SAVEPOINT ' . $savepoint) === false) {
                        throw new RuntimeException('Nepodarilo se zalozit savepoint pro objednavku.');
                    }

                    try {
                        $upsert = cb_restia_online_upsert_order($conn, $idPob, $order, $existingIdObj);
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
                            . ' | page=1'
                            . ' | restia_id_obj=' . $restiaIdObj
                            . ' | msg=' . $e->getMessage()
                        );
                        $pocetChyb++;
                        continue;
                    }

                    $pocetObj++;
                    if ((bool)($upsert['is_new'] ?? false)) {
                        $pocetNovych++;
                    } else {
                        $pocetZmenenych++;
                    }
                }

                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            $poznamka = 'den=' . $date . ' id_pob=' . $idPob . ' obj=' . $pocetObj;
            cb_restia_online_obj_import_finish($conn, $idImport, $pocetObj, $pocetNovych, $pocetZmenenych, $pocetChyb, $poznamka);
            cb_restia_online_try_flush_api($conn, $auth);

            $dayMs = (int)round(microtime(true) * 1000) - $dayStartMs;
            return ['date' => $date, 'branch' => $nazev, 'count' => $pocetObj, 'nove' => $pocetNovych, 'aktualizace' => $pocetZmenenych, 'ignore' => $pocetIgnore, 'errors' => $pocetChyb, 'error' => '', 'day_ms' => $dayMs, 'ok' => 1, 'log_lines' => $logLines];
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
        return cb_dt_workday_date(null, 6);
    }
}

if (!function_exists('cb_restia_online_current_workday_start')) {
    function cb_restia_online_current_workday_start(): DateTimeImmutable
    {
        return cb_dt_workday_start(null, 6);
    }
}

if (!function_exists('cb_restia_online_status_updated_from')) {
    function cb_restia_online_status_updated_from(mysqli $conn, int $overlapSeconds = 120): array
    {
        $workdayStart = cb_restia_online_current_workday_start();
        $fromLocal = $workdayStart;

        $res = $conn->query("
            SELECT konec
            FROM online_restia
            WHERE aktivni = 0
              AND konec IS NOT NULL
            ORDER BY konec DESC
            LIMIT 1
        ");
        if ($res === false) {
            throw new RuntimeException('DB dotaz na posledni online_restia beh selhal.');
        }

        $row = $res->fetch_assoc();
        $res->free();
        $lastEnd = trim((string)($row['konec'] ?? ''));
        if ($lastEnd !== '') {
            $lastDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lastEnd, new DateTimeZone('Europe/Prague'));
            if ($lastDt instanceof DateTimeImmutable) {
                $fromLocal = $lastDt->modify('-' . max(0, $overlapSeconds) . ' seconds');
                if ($fromLocal < $workdayStart) {
                    $fromLocal = $workdayStart;
                }
            }
        }

        return [
            'workday_date' => $workdayStart->format('Y-m-d'),
            'from_db' => $fromLocal->format('Y-m-d H:i:s'),
            'from_z' => $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
        ];
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
                'ignore' => 0,
                'chyba' => '',
            ];
        }

        $zapisy = 0;
        $aktualizace = 0;
        $ignore = 0;
        $chyba = '';

        try {
            $auth = cb_restia_online_get_auth();

            cb_restia_online_log_line('');
            cb_restia_online_log_line('');
            cb_restia_online_log_line('Spusteno ' . cb_restia_online_now());

            $onlineRange = cb_restia_online_status_updated_from($conn);
            $date = (string)($onlineRange['workday_date'] ?? cb_restia_online_current_workday_date());
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
                $ignore += (int)($day['ignore'] ?? 0);
            }
        } catch (Throwable $e) {
            $chyba = $e->getMessage();
            cb_restia_online_log_line('CHYBA: ' . $chyba);
        }

        return [
            'zapisy' => $zapisy,
            'aktualizace' => $aktualizace,
            'ignore' => $ignore,
            'chyba' => $chyba,
        ];
    }
}

return cb_restia_online_run();
