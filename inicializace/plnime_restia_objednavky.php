<?php
// inicializace/plnime_restia_objednavky.php * Verze: V4 * Aktualizace: 02.04.2026
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

const CB_RESTIA_HIST_LIMIT = 100;
const CB_RESTIA_HIST_START_DATE = '2023-07-01';
const CB_RESTIA_HIST_STEP_DAYS = 20;
const CB_RESTIA_HIST_PAUSE_MS = 500;

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
    function cb_restia_hist_txt_path(): string
    {
        return __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
    }
}

if (!function_exists('cb_restia_hist_log_init')) {
    function cb_restia_hist_log_init(): void
    {
        $path = cb_restia_hist_txt_path();
        if (!is_file($path)) {
            @file_put_contents($path, '', FILE_APPEND | LOCK_EX);
        }
    }
}

if (!function_exists('cb_restia_hist_log')) {
    function cb_restia_hist_log(string $line): void
    {
        @file_put_contents(cb_restia_hist_txt_path(), $line . "\n", FILE_APPEND | LOCK_EX);
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
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $date;
        }
        return $dt->format('d.m.Y');
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
            SELECT id_pob, nazev, restia_activePosId
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
            SELECT id_pob, nazev, restia_activePosId
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
                ];
            }
        }
        throw new RuntimeException('Neni zadna pobocka s vyplnenym restia_activePosId.');
    }
}

if (!function_exists('cb_restia_hist_resume_date')) {
    function cb_restia_hist_resume_date(mysqli $conn, int $idPob): string
    {
        $stmt = $conn->prepare('
            SELECT poznamka
            FROM obj_import
            WHERE typ_importu = "historie"
              AND id_pob = ?
            ORDER BY id_import DESC
            LIMIT 30
        ');
        if ($stmt === false) {
            return CB_RESTIA_HIST_START_DATE;
        }

        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $res = $stmt->get_result();

        $lastDate = '';
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $poznamka = (string)($row['poznamka'] ?? '');
                if (preg_match('/den=(\d{4}-\d{2}-\d{2})/', $poznamka, $m) === 1) {
                    $lastDate = (string)$m[1];
                    break;
                }
            }
            $res->free();
        }
        $stmt->close();

        if ($lastDate === '') {
            return CB_RESTIA_HIST_START_DATE;
        }

        try {
            return cb_restia_hist_next_date($lastDate);
        } catch (Throwable $e) {
            return CB_RESTIA_HIST_START_DATE;
        }
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
        $stav = ($pocetChyb > 0) ? 'chyba' : 'ok';

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

if (!function_exists('cb_restia_hist_insert_raw')) {
    function cb_restia_hist_insert_raw(mysqli $conn, int $idImport, int $idPob, string $restiaIdObj, string $payloadJson): void
    {
        $hash = cb_restia_hist_hash32($payloadJson);

        $stmt = $conn->prepare('
            INSERT INTO obj_raw (id_import, id_pob, restia_id_obj, payload_hash, payload_json, vytvoreno)
            VALUES (?, ?, ?, ?, ?, NOW(3))
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_raw insert.');
        }
        $stmt->bind_param('iisss', $idImport, $idPob, $restiaIdObj, $hash, $payloadJson);
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
    function cb_restia_hist_sync_children(mysqli $conn, int $idObj, array $order): void
    {
        $destination = (isset($order['destination']) && is_array($order['destination'])) ? $order['destination'] : [];
        $addressJson = cb_restia_hist_json($destination);

        $street = (string)($destination['street'] ?? ($destination['address'] ?? ''));
        $house = (string)($destination['houseNumber'] ?? '');
        $city = (string)($destination['city'] ?? '');
        $zip = (string)($destination['zip'] ?? ($destination['postalCode'] ?? ''));
        $country = (string)($destination['country'] ?? '');
        $lat = isset($destination['lat']) && $destination['lat'] !== '' ? (float)$destination['lat'] : null;
        $lng = isset($destination['lng']) && $destination['lng'] !== '' ? (float)$destination['lng'] : null;
        $rawTyp = (string)($destination['type'] ?? '');
        $distance = isset($destination['distance']) ? (int)$destination['distance'] : null;
        $driveTime = isset($destination['time']) ? (int)$destination['time'] : null;

        $stmtAddr = $conn->prepare('
            INSERT INTO obj_adresa (
                id_obj, ulice, cislo_domovni, mesto, psc, stat, lat, lng, raw_typ, vzdalenost_m, cas_jizdy_s, raw_json, vytvoreno, zmeneno
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3), NOW(3))
            ON DUPLICATE KEY UPDATE
                ulice = VALUES(ulice), cislo_domovni = VALUES(cislo_domovni), mesto = VALUES(mesto), psc = VALUES(psc), stat = VALUES(stat),
                lat = VALUES(lat), lng = VALUES(lng), raw_typ = VALUES(raw_typ), vzdalenost_m = VALUES(vzdalenost_m), cas_jizdy_s = VALUES(cas_jizdy_s),
                raw_json = VALUES(raw_json), zmeneno = NOW(3)
        ');
        if ($stmtAddr === false) {
            throw new RuntimeException('DB prepare selhal: obj_adresa.');
        }
        $stmtAddr->bind_param('isssssddsiss', $idObj, $street, $house, $city, $zip, $country, $lat, $lng, $rawTyp, $distance, $driveTime, $addressJson);
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

        $cenaPol = cb_restia_hist_money($order['itemsPrice'] ?? null);
        $cenaBalne = cb_restia_hist_money($order['packingPrice'] ?? null);
        $cenaDopr = cb_restia_hist_money($order['deliveryPrice'] ?? null);
        $dyska = cb_restia_hist_money($order['tipPrice'] ?? null);
        $cenaDoMin = cb_restia_hist_money($order['surchargeToMin'] ?? null);
        $cenaServis = cb_restia_hist_money($order['serviceFeePrice'] ?? null);
        $sleva = cb_restia_hist_money($order['discountPrice'] ?? null);
        $zaokrouhleni = cb_restia_hist_money($order['roundingPrice'] ?? null);
        $sum = (float)$cenaPol + (float)$cenaBalne + (float)$cenaDopr + (float)$dyska + (float)$cenaDoMin + (float)$cenaServis + (float)$zaokrouhleni - (float)$sleva;
        $cenaCelk = number_format($sum, 2, '.', '');

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
        $stmtCeny->bind_param('isssssssss', $idObj, $cenaCelk, $cenaPol, $cenaBalne, $cenaDopr, $dyska, $cenaDoMin, $cenaServis, $sleva, $zaokrouhleni);
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
            $rawCourier = cb_restia_hist_json($courier);
            $dataCourier = cb_restia_hist_json(['expectedArrival' => $courier['expectedArrival'] ?? null]);

            $stmtKuryr = $conn->prepare('INSERT INTO obj_kuryr (id_obj, provider, externi_id, poradi, jmeno, telefon, raw_json, data_json, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(3), NOW(3))');
            if ($stmtKuryr === false) { throw new RuntimeException('DB prepare selhal: obj_kuryr.'); }
            $stmtKuryr->bind_param('ississss', $idObj, $provider, $externiId, $poradi, $jmeno, $telefon, $rawCourier, $dataCourier);
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
            $rawService = cb_restia_hist_json($service);
            $dataService = cb_restia_hist_json($service['data'] ?? null);

            $stmtService = $conn->prepare('INSERT INTO obj_sluzba (id_obj, provider, externi_id, stav, raw_json, data_json, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, ?, ?, NOW(3), NOW(3))');
            if ($stmtService === false) { throw new RuntimeException('DB prepare selhal: obj_sluzba.'); }
            $stmtService->bind_param('isssss', $idObj, $provider, $externiId, $stav, $rawService, $dataService);
            $stmtService->execute();
            $stmtService->close();
        }

        $items = (isset($order['items']) && is_array($order['items'])) ? $order['items'] : [];
        $poradi = 0;
        foreach ($items as $item) {
            if (!is_array($item)) { continue; }
            $poradi++;

            $restiaItemId = (string)($item['id'] ?? '');
            $posId = (string)($item['posId'] ?? '');
            $nazev = (string)($item['label'] ?? 'Polozka');
            $actualLabel = isset($item['actualLabel']) ? (string)$item['actualLabel'] : null;
            $creatorId = isset($item['creatorId']) ? (string)$item['creatorId'] : null;
            $isPackaging = !empty($item['isPackaging']) ? 1 : 0;
            $mainItemId = isset($item['mainItemId']) ? (string)$item['mainItemId'] : null;
            $poznamka = isset($item['note']) ? (string)$item['note'] : null;
            $mnozstvi = isset($item['count']) ? (int)$item['count'] : 1;
            if ($mnozstvi <= 0) { $mnozstvi = 1; }
            $cenaKs = (float)cb_restia_hist_money($item['price'] ?? 0);
            $cenaCelk = isset($item['totalPrice']) ? (float)cb_restia_hist_money($item['totalPrice']) : ($cenaKs * $mnozstvi);
            $jeExtra = !empty($item['isExtra']) ? 1 : 0;
            $itemRaw = cb_restia_hist_json($item);

            $stmtItem = $conn->prepare('INSERT INTO obj_polozky (id_obj, restia_item_id, pos_id, nazev, actual_label, creator_id, is_packaging, main_item_id, poznamka, poradi, mnozstvi, cena_ks, cena_celk, je_extra, raw_json, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3))');
            if ($stmtItem === false) { throw new RuntimeException('DB prepare selhal: obj_polozky.'); }
            $stmtItem->bind_param('isssssisiiiddis', $idObj, $restiaItemId, $posId, $nazev, $actualLabel, $creatorId, $isPackaging, $mainItemId, $poznamka, $poradi, $mnozstvi, $cenaKs, $cenaCelk, $jeExtra, $itemRaw);
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
                    $modRaw = cb_restia_hist_json($mod);

                    $stmtMod = $conn->prepare('INSERT INTO obj_polozka_mod (id_obj_polozka, restia_mod_id, typ, pos_id, nazev, mnozstvi, cena_ks, cena_celk, raw_json, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3))');
                    if ($stmtMod === false) { throw new RuntimeException('DB prepare selhal: obj_polozka_mod.'); }
                    $stmtMod->bind_param('issssddds', $idObjPolozka, $restiaModId, $typ, $modPosId, $modNazev, $modMnoz, $modCenaKs, $modCenaCelk, $modRaw);
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
        $stmt->bind_param('ssssssiisii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni);
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

        if ($emailRaw === '' && $phoneNorm === '') {
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
        if ($phoneNorm !== '') {
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
        }

        if ($idZak <= 0 && $emailRaw !== '') {
            $emailNorm = strtolower($emailRaw);
            $stmt = $conn->prepare('SELECT id_zak FROM zakaznik WHERE LOWER(TRIM(COALESCE(email, ""))) = ? ORDER BY id_zak ASC LIMIT 1');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: find zakaznik by email.');
            }
            $stmt->bind_param('s', $emailNorm);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
            if ($res instanceof mysqli_result) {
                $res->free();
            }
            $stmt->close();
            $idZak = (int)($row['id_zak'] ?? 0);
        }

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

        $restiaOrderNumber = trim((string)($order['orderNumber'] ?? ''));
        $restiaToken = $order['token'] ?? null;
        $restiaToken = ($restiaToken === null || $restiaToken === '') ? null : (string)$restiaToken;
        $zakJmeno = $order['customerName'] ?? null;
        $zakJmeno = ($zakJmeno === null || $zakJmeno === '') ? null : (string)$zakJmeno;
        $zakTelefon = $order['customerPhone'] ?? null;
        $zakTelefon = ($zakTelefon === null || $zakTelefon === '') ? null : (string)$zakTelefon;
        $zakEmail = $order['customerEmail'] ?? null;
        $zakEmail = ($zakEmail === null || $zakEmail === '') ? null : (string)$zakEmail;
        $zakPoznamka = $order['customerNote'] ?? null;
        $zakPoznamka = ($zakPoznamka === null || $zakPoznamka === '') ? null : (string)$zakPoznamka;
        $objPoznamka = $order['note'] ?? null;
        $objPoznamka = ($objPoznamka === null || $objPoznamka === '') ? null : (string)$objPoznamka;
        $importTs = cb_restia_hist_now();

        $rawJson = cb_restia_hist_json($order);
        if ($rawJson === null) {
            throw new RuntimeException('Objednavku nelze serializovat do JSON.');
        }
        $rawHash = cb_restia_hist_hash32($rawJson);

        $sql = '
            INSERT INTO objednavky_restia (
                id_pob, id_zak, id_platforma, restia_id_obj, restia_order_number, restia_token,
                restia_active_pos_id, profil_typ, rest_obj,
                id_stav, id_platba, id_doruceni,
                zak_jmeno, zak_telefon, zak_email, zak_poznamka,
                obj_pozn, raw_hash, raw_json, `import`
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_pob = VALUES(id_pob), id_zak = VALUES(id_zak), id_platforma = VALUES(id_platforma), restia_order_number = VALUES(restia_order_number),
                restia_token = VALUES(restia_token), restia_active_pos_id = VALUES(restia_active_pos_id), profil_typ = VALUES(profil_typ),
                rest_obj = VALUES(rest_obj), id_stav = VALUES(id_stav), id_platba = VALUES(id_platba), id_doruceni = VALUES(id_doruceni),
                zak_jmeno = VALUES(zak_jmeno), zak_telefon = VALUES(zak_telefon), zak_email = VALUES(zak_email), zak_poznamka = VALUES(zak_poznamka),
                obj_pozn = VALUES(obj_pozn), raw_hash = VALUES(raw_hash), raw_json = VALUES(raw_json), `import` = VALUES(`import`)
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) { throw new RuntimeException('DB prepare selhal: objednavky_restia upsert.'); }
        $restObj = $restiaIdObj;
        $stmt->bind_param('iiissssssiiissssssss', $idPob, $idZak, $idPlatforma, $restiaIdObj, $restiaOrderNumber, $restiaToken, $activePosId, $profilTyp, $restObj, $idStav, $idPlatba, $idDoruceni, $zakJmeno, $zakTelefon, $zakEmail, $zakPoznamka, $objPoznamka, $rawHash, $rawJson, $importTs);
        $stmt->execute();
        $stmt->close();

        return cb_restia_hist_get_obj_id($conn, $restiaIdObj);
    }
}
if (!function_exists('cb_restia_hist_try_flush_api')) {
    function cb_restia_hist_try_flush_api(?mysqli $conn, ?array $auth): void
    {
        if (!($conn instanceof mysqli) || !is_array($auth)) { return; }
        try {
            db_api_restia_flush($conn, (int)($auth['id_user'] ?? 0), (int)($auth['id_login'] ?? 0));
        } catch (Throwable $e) { }
    }
}

if (!function_exists('cb_restia_hist_default_state')) {
    function cb_restia_hist_default_state(): array
    {
        return [
            'next_date' => CB_RESTIA_HIST_START_DATE,
            'remaining_days' => 0,
            'days_total' => 0,
            'waiting_continue' => 0,
            'finished' => 0,
            'requested_days' => 0,
            'auto_next' => 0,
            'branch_name' => '',
            'branch_id' => 0,
            'run_started_at_ms' => 0,
            'run_row_no' => 0,
            'step_days' => CB_RESTIA_HIST_STEP_DAYS,
        ];
    }
}

if (!function_exists('cb_restia_hist_import_day')) {
    function cb_restia_hist_import_day(mysqli $conn, array $auth, array $branch, string $date): array
    {
        $dayStartMs = (int)round(microtime(true) * 1000);
        $range = cb_restia_hist_day_range_utc($date);
        $idPob = (int)$branch['id_pob'];
        $nazev = (string)$branch['nazev'];
        $activePosId = (string)$branch['active_pos_id'];

        $idImport = cb_restia_hist_obj_import_begin($conn, $idPob, $range['from_db'], $range['to_db']);

        $pocetObj = 0; $pocetNovych = 0; $pocetZmenenych = 0; $pocetChyb = 0;

        try {
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
                    if (!is_array($order)) { continue; }
                    $restiaIdObj = trim((string)($order['id'] ?? ''));
                    if ($restiaIdObj === '') { $pocetChyb++; continue; }

                    $rawJson = cb_restia_hist_json($order);
                    if ($rawJson === null) { $pocetChyb++; continue; }

                    $exists = cb_restia_hist_order_exists($conn, $restiaIdObj);

                    $conn->begin_transaction();
                    try {
                        $idObj = cb_restia_hist_upsert_order($conn, $idPob, $activePosId, $order);
                        cb_restia_hist_insert_raw($conn, $idImport, $idPob, $restiaIdObj, $rawJson);
                        cb_restia_hist_sync_children($conn, $idObj, $order);
                        $conn->commit();
                    } catch (Throwable $e) {
                        $conn->rollback();
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
            return ['date' => $date, 'branch' => $nazev, 'count' => $pocetObj, 'error' => '', 'day_ms' => $dayMs, 'ok' => 1];
        } catch (Throwable $e) {
            cb_restia_hist_obj_import_finish($conn, $idImport, $pocetObj, $pocetNovych, $pocetZmenenych, $pocetChyb + 1, 'den=' . $date . ' id_pob=' . $idPob . ' ERROR=' . $e->getMessage());
            cb_restia_hist_try_flush_api($conn, $auth);
            cb_restia_hist_log('FATAL_STEP: datum=' . $date . ' | id_pob=' . $idPob . ' | msg=' . $e->getMessage());
            $dayMs = (int)round(microtime(true) * 1000) - $dayStartMs;
            return ['date' => $date, 'branch' => $nazev, 'count' => $pocetObj, 'error' => $e->getMessage(), 'day_ms' => $dayMs, 'ok' => 0];
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
$inputDays = (int)($_POST['cb_days'] ?? 0);
$inputBranchId = (int)($_POST['cb_id_pob'] ?? 0);

try { $auth = cb_restia_hist_get_auth(); } catch (Throwable $e) { $auth = null; $message = $e->getMessage(); }

if ($action === 'start' && $auth !== null) {
    if ($inputDays <= 0) {
        $inputDays = CB_RESTIA_HIST_STEP_DAYS;
    }

    if ($inputBranchId > 0) {
        $branch = cb_restia_hist_get_branch_by_id($conn, $inputBranchId);
    } else {
        $branch = cb_restia_hist_pick_default_branch($conn);
    }
    cb_restia_hist_ensure_default_customer($conn, (int)$branch['id_pob']);
    $resumeDate = cb_restia_hist_resume_date($conn, (int)$branch['id_pob']);
    $today = cb_restia_hist_today();
    if ($resumeDate > $today) { $resumeDate = $today; }

    $state = cb_restia_hist_default_state();
    $state['next_date'] = $resumeDate;
    $state['remaining_days'] = $inputDays;
    $state['requested_days'] = $inputDays;
    $state['step_days'] = $inputDays;
    $state['branch_name'] = (string)$branch['nazev'];
    $state['branch_id'] = (int)$branch['id_pob'];
    $state['run_started_at_ms'] = (int)round(microtime(true) * 1000);
    $state['run_row_no'] = 0;
    $rows = [];
    $message = '';

    cb_restia_hist_log_init();
    cb_restia_hist_log('-----');
    cb_restia_hist_log('START: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now()));
    cb_restia_hist_log('SCRIPT: ' . basename(__FILE__));
    cb_restia_hist_log('RESUME_FROM: ' . cb_restia_hist_format_date_cs($resumeDate));
    cb_restia_hist_log('REQUESTED_DAYS: ' . (string)$inputDays);
    cb_restia_hist_log('LIMIT: ' . (string)CB_RESTIA_HIST_LIMIT);
    cb_restia_hist_log('STEP_DAYS: ' . (string)$inputDays);
    cb_restia_hist_log('PAUSE_MS: ' . (string)CB_RESTIA_HIST_PAUSE_MS);

    $action = 'auto_next';
}

if ($action === 'continue_no') {
    $state['waiting_continue'] = 0; $state['auto_next'] = 0; $state['finished'] = 1;
    $message = 'Import ukoncen uzivatelem.';
    cb_restia_hist_log('KONEC IMPORTU: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now()) . ' | DUVOD: rucne ukonceno');
}
if ($action === 'continue_yes') {
    $nextCycleDays = ($inputDays > 0) ? $inputDays : (int)($state['step_days'] ?? CB_RESTIA_HIST_STEP_DAYS);
    $state['waiting_continue'] = 0;
    $state['auto_next'] = 1;
    $state['finished'] = 0;
    $state['remaining_days'] = $nextCycleDays;
    $state['requested_days'] = $nextCycleDays;
    $state['step_days'] = $nextCycleDays;
    $message = '';
    cb_restia_hist_log('NOVY CYKLUS: ' . (string)$nextCycleDays . ' dni');
    $action = 'auto_next';
}

$shouldRunOneDay = ($auth !== null) && ((int)($state['finished'] ?? 0) === 0) && ((int)($state['remaining_days'] ?? 0) > 0) && ($action === 'auto_next');

if ($shouldRunOneDay) {
    $stateBranchId = (int)($state['branch_id'] ?? 0);
    if ($stateBranchId > 0) {
        $branch = cb_restia_hist_get_branch_by_id($conn, $stateBranchId);
    } else {
        $branch = cb_restia_hist_pick_default_branch($conn);
    }
    cb_restia_hist_ensure_default_customer($conn, (int)$branch['id_pob']);
    $state['branch_id'] = (int)($branch['id_pob'] ?? 0);
    $state['branch_name'] = (string)$branch['nazev'];

    $today = cb_restia_hist_today();
    $stepDays = max(1, (int)($state['step_days'] ?? CB_RESTIA_HIST_STEP_DAYS));
    $maxDaysNow = min((int)($state['remaining_days'] ?? 0), $stepDays);
    $processedNow = 0;

    while ($processedNow < $maxDaysNow) {
        $date = (string)$state['next_date'];
        if ($date > $today) {
            $state['finished'] = 1;
            $state['auto_next'] = 0;
            $state['waiting_continue'] = 0;
            $state['remaining_days'] = 0;
            $message = 'Hotovo. Dosli jsme do aktualniho dne.';
            cb_restia_hist_log('KONEC IMPORTU: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now()));
            break;
        }

        $day = cb_restia_hist_import_day($conn, $auth, $branch, $date);
        $startedAtMs = (int)($state['run_started_at_ms'] ?? 0);
        $nowMs = (int)round(microtime(true) * 1000);
        $day['total_ms'] = ($startedAtMs > 0) ? max(0, $nowMs - $startedAtMs) : 0;
        $rows[] = $day;
        $state['run_row_no'] = (int)($state['run_row_no'] ?? 0) + 1;
        $rowNo = (int)$state['run_row_no'];
        $isOk = ((int)($day['ok'] ?? 0) === 1 && trim((string)($day['error'] ?? '')) === '');
        $status = $isOk ? 'OK' : 'ERR';
        cb_restia_hist_log(
            (string)$rowNo
            . ' / ' . cb_restia_hist_format_date_cs((string)($day['date'] ?? $date))
            . ' / ' . (string)((int)($day['count'] ?? 0))
            . ' / ' . (string)((int)($day['day_ms'] ?? 0)) . ' ms'
            . ' / ' . cb_restia_hist_format_total_time((int)($day['total_ms'] ?? 0))
            . ' / ' . $status
        );

        $state['days_total'] = (int)$state['days_total'] + 1;
        $state['remaining_days'] = (int)$state['remaining_days'] - 1;
        $state['next_date'] = cb_restia_hist_next_date($date);
        $processedNow++;
    }

    if ((int)$state['finished'] === 0 && (int)$state['remaining_days'] <= 0) {
        $state['auto_next'] = 0;
        $state['waiting_continue'] = 1;
        $message = 'Zpracovano ' . (string)((int)($state['requested_days'] ?? $stepDays)) . ' dni. Pokracovat?';
        cb_restia_hist_log('KONEC CYKLU: ' . cb_restia_hist_format_datetime_cs(cb_restia_hist_now()));
    } elseif ((int)$state['finished'] === 0) {
        $state['auto_next'] = 1; $state['waiting_continue'] = 0;
        $message = 'Bezi import, dalsi krok za ' . (string)CB_RESTIA_HIST_PAUSE_MS . ' ms...';
    }
}

$_SESSION[$cbStateKey] = $state;
$_SESSION[$cbRowsKey] = $rows;
$_SESSION[$cbMsgKey] = $message;

$branchOptions = [];
try {
    $branchOptions = cb_restia_hist_get_branches($conn);
} catch (Throwable $e) {
    if ($message === '') {
        $message = $e->getMessage();
    }
}
$selectedBranchId = (int)($state['branch_id'] ?? 0);
if ($selectedBranchId <= 0) {
    foreach ($branchOptions as $branchOpt) {
        if ((bool)($branchOpt['enabled'] ?? false) === true) {
            $selectedBranchId = (int)($branchOpt['id_pob'] ?? 0);
            break;
        }
    }
}
?>
<?php if (!$cbRestiaEmbedMode): ?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><title>Restia import objednavek</title><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
<?php endif; ?>
<div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Restia import objednavek</h2>
  <p class="card_text txt_seda">Start: <?= cb_restia_hist_h(CB_RESTIA_HIST_START_DATE) ?> | Konec: <?= cb_restia_hist_h(cb_restia_hist_today()) ?></p>
  <p class="card_text txt_seda">Limit: <?= cb_restia_hist_h((string)CB_RESTIA_HIST_LIMIT) ?> | Krok: <?= cb_restia_hist_h((string)((int)($state['step_days'] ?? CB_RESTIA_HIST_STEP_DAYS))) ?> dni | Pauza: <?= cb_restia_hist_h((string)CB_RESTIA_HIST_PAUSE_MS) ?> ms</p>
  <p class="card_text txt_seda">Pobocka: <?= cb_restia_hist_h((string)($state['branch_name'] ?? '')) ?></p>
  <?php if ($message !== ''): ?><p class="card_text text_tucny txt_seda"><?= cb_restia_hist_h($message) ?></p><?php endif; ?>

  <div class="card_actions gap_8 displ_flex">
    <form method="post" class="odstup_vnejsi_0 displ_inline_flex">
      <input type="hidden" name="run_restia_obj" value="1"><input type="hidden" name="cb_action" value="start">
      <select name="cb_id_pob" class="card_select ram_sedy txt_seda bg_bila zaobleni_8 vyska_32" style="min-width:220px; margin-right:8px;">
        <?php foreach ($branchOptions as $branchOpt): ?>
          <?php
          $idPobOpt = (int)($branchOpt['id_pob'] ?? 0);
          $nameOpt = (string)($branchOpt['nazev'] ?? '');
          $enabledOpt = ((bool)($branchOpt['enabled'] ?? false) === true);
          $labelOpt = $nameOpt !== '' ? $nameOpt : ('Pobocka #' . (string)$idPobOpt);
          if (!$enabledOpt) {
              $labelOpt .= ' (chybi restia_activePosId)';
          }
          ?>
          <option value="<?= cb_restia_hist_h((string)$idPobOpt) ?>"<?= $idPobOpt === $selectedBranchId ? ' selected' : '' ?><?= $enabledOpt ? '' : ' disabled style="color:#9ca3af;"' ?>><?= cb_restia_hist_h($labelOpt) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" name="cb_days" min="1" step="1" value="<?= cb_restia_hist_h((string)(((int)($state['step_days'] ?? 0) > 0) ? (int)$state['step_days'] : CB_RESTIA_HIST_STEP_DAYS)) ?>" class="card_input ram_sedy txt_seda bg_bila zaobleni_8 vyska_32" style="width:120px; margin-right:8px;">
      <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit</button>
    </form>
    <?php if ((int)($state['waiting_continue'] ?? 0) === 1 && (int)($state['finished'] ?? 0) === 0): ?>
      <form method="post" class="odstup_vnejsi_0"><input type="hidden" name="run_restia_obj" value="1"><input type="hidden" name="cb_action" value="continue_yes"><button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Pokracovat</button></form>
      <form method="post" class="odstup_vnejsi_0"><input type="hidden" name="run_restia_obj" value="1"><input type="hidden" name="cb_action" value="continue_no"><button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Skoncit</button></form>
    <?php endif; ?>
  </div>

  <p class="card_text txt_seda odstup_horni_10">Zpracovano dnu: <?= cb_restia_hist_h((string)($state['days_total'] ?? 0)) ?> | Zbyva: <?= cb_restia_hist_h((string)($state['remaining_days'] ?? 0)) ?></p>

  <?php if ((int)($state['auto_next'] ?? 0) === 1 && (int)($state['waiting_continue'] ?? 0) === 0 && (int)($state['finished'] ?? 0) === 0): ?>
    <form id="cb_restia_auto_next" method="post" class="odstup_vnejsi_0"><input type="hidden" name="run_restia_obj" value="1"><input type="hidden" name="cb_action" value="auto_next"></form>
    <script>setTimeout(function(){var f=document.getElementById('cb_restia_auto_next');if(f){f.submit();}}, <?= (int)CB_RESTIA_HIST_PAUSE_MS ?>);</script>
  <?php endif; ?>
</div>
<?php if (!$cbRestiaEmbedMode): ?></body></html><?php endif; ?>
