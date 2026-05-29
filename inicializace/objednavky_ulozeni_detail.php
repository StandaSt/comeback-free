<?php
// inicializace/objednavky_ulozeni_detail.php * Verze: V1 * Aktualizace: 27.05.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';

const CB_DETAIL_DEFAULT_LIMIT = 500;
const CB_DETAIL_DEFAULT_PAUSE_SEC = 5;

if (!function_exists('cb_detail_h')) {
    function cb_detail_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_detail_now')) {
    function cb_detail_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_detail_stmt')) {
    function cb_detail_stmt(mysqli $conn, string $key, string $sql, string $label): mysqli_stmt
    {
        static $stmts = [];
        if (!isset($stmts[$key])) {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: ' . $label . ' - ' . $conn->error);
            }
            $stmts[$key] = $stmt;
        }
        return $stmts[$key];
    }
}

if (!function_exists('cb_detail_restia_to_local_nullable')) {
    function cb_detail_restia_to_local_nullable(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return null;
        }
        try {
            $dt = new DateTimeImmutable($value);
            return $dt->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cb_detail_report_date')) {
    function cb_detail_report_date(?string $localDateTime): string
    {
        $value = trim((string)($localDateTime ?? ''));
        if ($value === '') {
            return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($value, 0, 19), new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
        }
        if ((int)$dt->format('G') < 8) {
            $dt = $dt->modify('-1 day');
        }
        return $dt->format('Y-m-d');
    }
}

if (!function_exists('cb_detail_nullable_string')) {
    function cb_detail_nullable_string(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }
}

if (!function_exists('cb_detail_money')) {
    function cb_detail_money(mixed $value): string
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

if (!function_exists('cb_detail_lookup_id')) {
    function cb_detail_lookup_id(mysqli $conn, string $table, string $valueCol, string $value, string $idCol): int
    {
        static $cache = [];

        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $key = $table . '|' . $value;
        if (isset($cache[$key])) {
            return (int)$cache[$key];
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
}

if (!function_exists('cb_detail_lookup_res_polozka_id')) {
    function cb_detail_lookup_res_polozka_id(mysqli $conn, int $idPob, string $restiaItemId): int
    {
        static $cache = [];

        $restiaItemId = trim($restiaItemId);
        if ($idPob <= 0 || $restiaItemId === '') {
            return 0;
        }

        $key = $idPob . '|' . $restiaItemId;
        if (isset($cache[$key])) {
            return (int)$cache[$key];
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

        $id = (int)($row['id'] ?? 0);
        $cache[$key] = $id;
        return $id;
    }
}

if (!function_exists('cb_detail_existing_order_id')) {
    function cb_detail_existing_order_id(mysqli $conn, string $restiaIdObj): int
    {
        $stmt = cb_detail_stmt($conn, 'existing_order_id', 'SELECT id_obj FROM objednavky_restia WHERE restia_id_obj = ? LIMIT 1', 'existing order');
        $stmt->bind_param('s', $restiaIdObj);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        return (int)($row['id_obj'] ?? 0);
    }
}

if (!function_exists('cb_detail_enable_zero_autoinc')) {
    function cb_detail_enable_zero_autoinc(mysqli $conn): void
    {
        $sql = "SET SESSION sql_mode = CONCAT_WS(',', @@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO')";
        if ($conn->query($sql) === false) {
            throw new RuntimeException('Nepodařilo se nastavit sql_mode NO_AUTO_VALUE_ON_ZERO.');
        }
    }
}

if (!function_exists('cb_detail_default_pob_id')) {
    function cb_detail_default_pob_id(mysqli $conn): int
    {
        $res = $conn->query('SELECT id_pob FROM pobocka ORDER BY id_pob ASC LIMIT 1');
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na výchozí pobočku selhal.');
        }
        $row = $res->fetch_assoc();
        $res->free();
        $idPob = (int)($row['id_pob'] ?? 0);
        if ($idPob <= 0) {
            throw new RuntimeException('V tabulce pobočka není žádný záznam.');
        }
        return $idPob;
    }
}

if (!function_exists('cb_detail_ensure_default_customer')) {
    function cb_detail_ensure_default_customer(mysqli $conn, int $idPobHint = 0): void
    {
        $res = $conn->query('SELECT COUNT(*) AS cnt FROM zakaznik');
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na zákazníka selhal.');
        }
        $row = $res->fetch_assoc();
        $res->free();
        if ((int)($row['cnt'] ?? 0) > 0) {
            return;
        }

        cb_detail_enable_zero_autoinc($conn);

        $idPob = $idPobHint > 0 ? $idPobHint : cb_detail_default_pob_id($conn);
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
            throw new RuntimeException('DB prepare selhal: insert anonymní zákazník.');
        }
        $stmt->bind_param('ssssssiisiii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_detail_norm_phone')) {
    function cb_detail_norm_phone(?string $phone): string
    {
        $raw = trim((string)($phone ?? ''));
        if ($raw === '') {
            return '';
        }
        $norm = preg_replace('/[^0-9]/', '', $raw);
        return is_string($norm) ? $norm : '';
    }
}

if (!function_exists('cb_detail_split_name')) {
    function cb_detail_split_name(?string $fullName): array
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
        return [
            'jmeno' => $jmeno !== '' ? $jmeno : 'anonymni',
            'prijmeni' => $prijmeni !== '' ? $prijmeni : 'zakaznik',
        ];
    }
}

if (!function_exists('cb_detail_upsert_customer')) {
    function cb_detail_upsert_customer(mysqli $conn, int $idPob, array $order): int
    {
        $emailRaw = trim((string)($order['customerEmail'] ?? ''));
        if ($emailRaw === '' || strtolower($emailRaw) === 'null') {
            $emailRaw = '';
        }
        $phoneRaw = trim((string)($order['customerPhone'] ?? ''));
        if ($phoneRaw === '' || strtolower($phoneRaw) === 'null') {
            $phoneRaw = '';
        }
        $phoneNorm = cb_detail_norm_phone($phoneRaw);
        if ($phoneNorm === '') {
            return 0;
        }

        $name = cb_detail_split_name((string)($order['customerName'] ?? ''));
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

        $stmt = cb_detail_stmt($conn, 'zakaznik_find_by_phone', 'SELECT id_zak FROM zakaznik WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefon, ""), " ", ""), "+", ""), "-", ""), "(", ""), ")", ""), "/", "") = ? ORDER BY id_zak ASC LIMIT 1', 'find zakaznik by phone');
        $stmt->bind_param('s', $phoneNorm);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $idZak = (int)($row['id_zak'] ?? 0);

        if ($idZak > 0) {
            $stmt = cb_detail_stmt($conn, 'zakaznik_update', '
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

        $stmt = cb_detail_stmt($conn, 'zakaznik_insert', '
            INSERT INTO zakaznik (
                jmeno, prijmeni, telefon, email, ulice, mesto,
                zak_menu, zak_news, posledni_obj, poznamka, blokovany, id_pob, zadano, aktivni
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW(), ?
            )
        ', 'insert zakaznik');
        $stmt->bind_param('ssssssiisiii', $jmeno, $prijmeni, $telefon, $email, $ulice, $mesto, $zakMenu, $zakNews, $poznamka, $blokovany, $idPob, $aktivni);
        $stmt->execute();
        return (int)$conn->insert_id;
    }
}

if (!function_exists('cb_detail_upsert_order')) {
    function cb_detail_upsert_order(mysqli $conn, int $idRaw, int $idPob, array $order, int $existingIdObj): array
    {
        if ($idRaw <= 0) {
            throw new RuntimeException('Neplatné id_raw pro id_obj.');
        }

        $restiaIdObj = trim((string)($order['id'] ?? ''));
        if ($restiaIdObj === '') {
            throw new RuntimeException('Objednávka nemá id.');
        }

        $profile = (isset($order['profile']) && is_array($order['profile'])) ? $order['profile'] : [];
        $profilTyp = trim((string)($profile['type'] ?? 'neznamy'));
        if ($profilTyp === '') {
            $profilTyp = 'neznamy';
        }

        $status = trim((string)($order['status'] ?? ''));
        $paymentType = trim((string)($order['paymentType'] ?? ''));
        $deliveryType = trim((string)($order['deliveryType'] ?? ''));

        $idPlatforma = cb_detail_lookup_id($conn, 'cis_obj_platforma', 'kod', $profilTyp, 'id_platforma');
        $idStav = ($status === '') ? null : cb_detail_lookup_id($conn, 'cis_obj_stav', 'nazev', $status, 'id_stav');
        $idPlatba = ($paymentType === '') ? null : cb_detail_lookup_id($conn, 'cis_obj_platby', 'nazev', $paymentType, 'id_platba');
        $idDoruceni = ($deliveryType === '') ? null : cb_detail_lookup_id($conn, 'cis_doruceni', 'nazev', $deliveryType, 'id_doruceni');
        $idZak = cb_detail_upsert_customer($conn, $idPob, $order);
        if ($idZak === 0) {
            $idZak = null;
        }

        $restiaOrderNumber = trim((string)($order['orderNumber'] ?? ''));
        $restiaToken = $order['token'] ?? null;
        $restiaToken = ($restiaToken === null || $restiaToken === '') ? null : (string)$restiaToken;
        $restiaCreatedAt = cb_detail_restia_to_local_nullable($order['createdAt'] ?? null);
        $report = cb_detail_report_date($restiaCreatedAt ?? cb_detail_now());
        $profilKlic = cb_detail_nullable_string($profile['key'] ?? null);
        $profilNazev = trim((string)($profile['name'] ?? ''));
        $profilNazev = ($profilNazev === '') ? null : $profilNazev;
        $profilMenuId = cb_detail_nullable_string($profile['menuId'] ?? null);
        $profilPosId = cb_detail_nullable_string($profile['posId'] ?? null);
        $profilUrl = cb_detail_nullable_string($profile['url'] ?? null);
        $jeVyzvednuti = !empty($order['isPickup']) ? 1 : 0;
        $jeVRestauraci = !empty($order['isInRestaurant']) ? 1 : 0;
        $jeVlastniRozvoz = !empty($order['isSelfDelivery']) ? 1 : 0;
        $kuryrPoradi = isset($order['courierOrder']) && $order['courierOrder'] !== '' ? (int)$order['courierOrder'] : null;
        $posImportStav = cb_detail_nullable_string($order['posImportStatus'] ?? null);
        $shortCode = $order['shortCode'] ?? null;
        $shortCode = ($shortCode === null || $shortCode === '') ? null : (string)$shortCode;
        $serioveCislo = $order['serialNumber'] ?? null;
        $serioveCislo = ($serioveCislo === null || $serioveCislo === '') ? null : (string)$serioveCislo;
        $casPripravy = isset($order['cookingTimeMinutes']) ? (int)$order['cookingTimeMinutes'] : null;
        $objPoznamka = $order['note'] ?? null;
        $objPoznamka = ($objPoznamka === null || $objPoznamka === '') ? null : (string)$objPoznamka;
        $importTs = cb_detail_now();
        $restObj = $restiaIdObj;

        $stmt = cb_detail_stmt($conn, 'objednavky_restia_upsert', '
            INSERT INTO objednavky_restia (
                id_obj, id_pob, report, id_zak, id_platforma, restia_id_obj, restia_created_at, restia_order_number, restia_token,
                profil_typ, profil_klic, profil_nazev, profil_menu_id, profil_pos_id, profil_url,
                je_vyzvednuti, je_v_restauraci, je_vlastni_rozvoz, kuryr_poradi, pos_import_stav,
                rest_obj, short_code, seriove_cislo,
                id_stav, id_platba, id_doruceni,
                cas_pripravy, obj_pozn, restia_imported_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_obj = LAST_INSERT_ID(id_obj),
                id_pob = VALUES(id_pob), report = VALUES(report), id_zak = VALUES(id_zak), id_platforma = VALUES(id_platforma), restia_created_at = VALUES(restia_created_at), restia_order_number = VALUES(restia_order_number),
                restia_token = VALUES(restia_token), profil_typ = VALUES(profil_typ), profil_klic = VALUES(profil_klic), profil_nazev = VALUES(profil_nazev),
                profil_menu_id = VALUES(profil_menu_id), profil_pos_id = VALUES(profil_pos_id), profil_url = VALUES(profil_url),
                je_vyzvednuti = VALUES(je_vyzvednuti), je_v_restauraci = VALUES(je_v_restauraci), je_vlastni_rozvoz = VALUES(je_vlastni_rozvoz),
                kuryr_poradi = VALUES(kuryr_poradi), pos_import_stav = VALUES(pos_import_stav),
                rest_obj = VALUES(rest_obj), short_code = VALUES(short_code), seriove_cislo = VALUES(seriove_cislo),
                id_stav = VALUES(id_stav), id_platba = VALUES(id_platba), id_doruceni = VALUES(id_doruceni),
                cas_pripravy = VALUES(cas_pripravy), obj_pozn = VALUES(obj_pozn), restia_imported_at = VALUES(restia_imported_at)
        ', 'objednavky_restia upsert');
        $stmt->bind_param('iisiissssssssssiiiissssiiiiss', $idRaw, $idPob, $report, $idZak, $idPlatforma, $restiaIdObj, $restiaCreatedAt, $restiaOrderNumber, $restiaToken, $profilTyp, $profilKlic, $profilNazev, $profilMenuId, $profilPosId, $profilUrl, $jeVyzvednuti, $jeVRestauraci, $jeVlastniRozvoz, $kuryrPoradi, $posImportStav, $restObj, $shortCode, $serioveCislo, $idStav, $idPlatba, $idDoruceni, $casPripravy, $objPoznamka, $importTs);
        $stmt->execute();

        $idObj = (int)$conn->insert_id;
        if ($idObj <= 0 && $existingIdObj > 0) {
            $idObj = $existingIdObj;
        }
        if ($idObj <= 0) {
            throw new RuntimeException('Nepodařilo se dohledat id_obj po upsertu objednávky.');
        }

        return [
            'id_obj' => $idObj,
            'is_new' => ($existingIdObj <= 0),
        ];
    }
}

if (!function_exists('cb_detail_sync_children')) {
    function cb_detail_sync_children(mysqli $conn, int $idObj, int $idPob, array $order, bool $isNewOrder): void
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

        $stmtAddr = cb_detail_stmt($conn, 'obj_adresa_upsert', '
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

        $casVytvor = cb_detail_restia_to_local_nullable($order['createdAt'] ?? null);
        $casExp = cb_detail_restia_to_local_nullable($order['expiresAt'] ?? null);
        $casSlib = cb_detail_restia_to_local_nullable($order['promisedAt'] ?? null);
        $casPriprDo = cb_detail_restia_to_local_nullable($order['prepareAt'] ?? null);
        $casPriprV = cb_detail_restia_to_local_nullable($order['preparedAt'] ?? null);
        $casDokonc = cb_detail_restia_to_local_nullable($order['finishedAt'] ?? null);
        $casDoruc = cb_detail_restia_to_local_nullable($order['deliveredAt'] ?? null);
        $casStatus = cb_detail_restia_to_local_nullable($order['statusUpdatedAt'] ?? null);
        $casUzavreni = cb_detail_restia_to_local_nullable($order['closedAt'] ?? null);
        $casImportRestia = cb_detail_restia_to_local_nullable($order['importedAt'] ?? null);
        $casImportPos = cb_detail_restia_to_local_nullable($order['posImportedAt'] ?? null);
        $casVyzv = cb_detail_restia_to_local_nullable($order['pickupAt'] ?? null);
        $casDisp = cb_detail_restia_to_local_nullable($order['deliveryAt'] ?? null);
        $report = cb_detail_report_date($casVytvor ?? cb_detail_now());

        $stmtCasy = cb_detail_stmt($conn, 'obj_casy_upsert', '
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

        $cenaPol = (float)cb_detail_money($order['itemsPrice'] ?? null);
        $cenaBalne = (float)cb_detail_money($order['packingPrice'] ?? null);
        $cenaDopr = (float)cb_detail_money($order['deliveryPrice'] ?? null);
        $dyska = (float)cb_detail_money($order['tipPrice'] ?? null);
        $cenaDoMin = (float)cb_detail_money($order['surchargeToMin'] ?? null);
        $cenaServis = (float)cb_detail_money($order['serviceFeePrice'] ?? null);
        $sleva = (float)cb_detail_money($order['discountPrice'] ?? null);
        $zaokrouhleni = (float)cb_detail_money($order['roundingPrice'] ?? null);
        $cenaCelk = $cenaPol + $cenaBalne + $cenaDopr + $dyska + $cenaDoMin + $cenaServis + $zaokrouhleni - $sleva;

        $stmtCeny = cb_detail_stmt($conn, 'obj_ceny_upsert', '
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
            $stmtDelKuryr = cb_detail_stmt($conn, 'obj_kuryr_delete', 'DELETE FROM obj_kuryr WHERE id_obj = ?', 'delete obj_kuryr');
            $stmtDelSluzba = cb_detail_stmt($conn, 'obj_sluzba_delete', 'DELETE FROM obj_sluzba WHERE id_obj = ?', 'delete obj_sluzba');
            $stmtDelTags = cb_detail_stmt($conn, 'obj_polozka_kds_tag_delete', 'DELETE t FROM obj_polozka_kds_tag t JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka WHERE p.id_obj = ?', 'delete obj_polozka_kds_tag');
            $stmtDelPolozky = cb_detail_stmt($conn, 'obj_polozky_delete', 'DELETE FROM obj_polozky WHERE id_obj = ?', 'delete obj_polozky');
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
            $stmtKuryr = cb_detail_stmt($conn, 'obj_kuryr_insert', 'INSERT INTO obj_kuryr (id_obj, provider, externi_id, poradi, jmeno, telefon, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, ?, ?, NOW(3), NOW(3))', 'obj_kuryr');
            $stmtKuryr->bind_param('ississ', $idObj, $provider, $externiId, $poradi, $jmeno, $telefon);
            $stmtKuryr->execute();
        }

        $servicesData = $order['servicesData'] ?? null;
        $services = [];
        if (is_array($servicesData) && array_is_list($servicesData)) {
            $services = $servicesData;
        } elseif (is_array($servicesData)) {
            $services[] = $servicesData;
        }
        foreach ($services as $service) {
            if (!is_array($service)) {
                continue;
            }
            $provider = (string)($service['provider'] ?? '');
            $externiId = (string)($service['externalId'] ?? ($service['id'] ?? ''));
            $stav = (string)($service['status'] ?? '');
            $stmtService = cb_detail_stmt($conn, 'obj_sluzba_insert', 'INSERT INTO obj_sluzba (id_obj, provider, externi_id, stav, vytvoreno, zmeneno) VALUES (?, ?, ?, ?, NOW(3), NOW(3))', 'obj_sluzba');
            $stmtService->bind_param('isss', $idObj, $provider, $externiId, $stav);
            $stmtService->execute();
        }

        $items = (isset($order['items']) && is_array($order['items'])) ? $order['items'] : [];
        $poradi = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $poradi++;

            $restiaItemId = (string)($item['posId'] ?? ($item['id'] ?? ''));
            $poznamka = isset($item['note']) ? (string)$item['note'] : null;
            $mnozstvi = isset($item['count']) ? (int)$item['count'] : 1;
            if ($mnozstvi <= 0) {
                $mnozstvi = 1;
            }
            $cenaKs = (float)cb_detail_money($item['price'] ?? 0);
            $cenaCelk = isset($item['totalPrice']) ? (float)cb_detail_money($item['totalPrice']) : ($cenaKs * $mnozstvi);
            $jeExtra = !empty($item['isExtra']) ? 1 : 0;
            $idResPolozka = cb_detail_lookup_res_polozka_id($conn, $idPob, $restiaItemId);
            if ($idResPolozka <= 0) {
                $idResPolozka = null;
            }
            $stmtItem = cb_detail_stmt($conn, 'obj_polozky_insert', 'INSERT INTO obj_polozky (id_obj, id_res_polozka, res_item, poznamka, poradi, mnozstvi, cena_ks, cena_celk, je_extra, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3))', 'obj_polozky');
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
                    if (!is_array($mod)) {
                        continue;
                    }
                    $restiaModId = isset($mod['id']) ? (string)$mod['id'] : null;
                    $typ = isset($mod['type']) ? (string)$mod['type'] : null;
                    $modPosId = isset($mod['posId']) ? (string)$mod['posId'] : null;
                    $modNazev = (string)($mod['label'] ?? ($mod['name'] ?? 'Modifikator'));
                    $modMnoz = isset($mod['count']) ? (float)$mod['count'] : (isset($mod['qty']) ? (float)$mod['qty'] : 1.0);
                    if ($modMnoz <= 0) {
                        $modMnoz = 1.0;
                    }
                    $modCenaKs = (float)cb_detail_money($mod['price'] ?? 0);
                    $modCenaCelk = isset($mod['totalPrice']) ? (float)cb_detail_money($mod['totalPrice']) : ($modCenaKs * $modMnoz);
                    $stmtMod = cb_detail_stmt($conn, 'obj_polozka_mod_insert', 'INSERT INTO obj_polozka_mod (id_obj_polozka, restia_mod_id, typ, pos_id, nazev, mnozstvi, cena_ks, cena_celk, zadano) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(3))', 'obj_polozka_mod');
                    $stmtMod->bind_param('issssddd', $idObjPolozka, $restiaModId, $typ, $modPosId, $modNazev, $modMnoz, $modCenaKs, $modCenaCelk);
                    $stmtMod->execute();
                }
            }

            $kdsTags = [];
            if (isset($item['KDSTags']) && is_array($item['KDSTags'])) {
                $kdsTags = $item['KDSTags'];
            } elseif (isset($item['kdsTags']) && is_array($item['kdsTags'])) {
                $kdsTags = $item['kdsTags'];
            }
            foreach ($kdsTags as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '') {
                    continue;
                }
                $stmtTag = cb_detail_stmt($conn, 'obj_polozka_kds_tag_insert', 'INSERT INTO obj_polozka_kds_tag (id_obj_polozka, tag) VALUES (?, ?)', 'obj_polozka_kds_tag');
                $stmtTag->bind_param('is', $idObjPolozka, $tag);
                $stmtTag->execute();
            }
        }
    }
}

if (!function_exists('cb_detail_mark_raw')) {
    function cb_detail_mark_raw(mysqli $conn, int $idRaw, bool $ok, ?string $error): void
    {
        if ($ok) {
            $stmt = cb_detail_stmt($conn, 'raw_mark_ok', '
                UPDATE objednavky_raw
                SET zpracovano = 1, zpracovano_kdy = NOW(3), chyba = NULL
                WHERE id_raw = ?
                LIMIT 1
            ', 'raw mark ok');
            $stmt->bind_param('i', $idRaw);
            $stmt->execute();
            return;
        }

        $stmt = cb_detail_stmt($conn, 'raw_mark_error', '
            UPDATE objednavky_raw
            SET zpracovano = 0, zpracovano_kdy = NULL, chyba = ?
            WHERE id_raw = ?
            LIMIT 1
        ', 'raw mark error');
        $stmt->bind_param('si', $error, $idRaw);
        $stmt->execute();
    }
}

if (!function_exists('cb_detail_process_raw')) {
    function cb_detail_process_raw(mysqli $conn, array $row): array
    {
        $idRaw = (int)($row['id_raw'] ?? 0);
        $idPob = (int)($row['id_pob'] ?? 0);
        $rawJson = (string)($row['raw_json'] ?? '');
        if ($idRaw <= 0 || $idPob <= 0 || $rawJson === '') {
            throw new RuntimeException('Neplatný RAW řádek.');
        }

        $order = json_decode($rawJson, true);
        if (!is_array($order)) {
            throw new RuntimeException('Neplatný JSON v RAW řádku id=' . (string)$idRaw);
        }

        $restiaIdObj = trim((string)($order['id'] ?? ''));
        if ($restiaIdObj === '') {
            throw new RuntimeException('Objednávka nemá id.');
        }

        $existingIdObj = cb_detail_existing_order_id($conn, $restiaIdObj);

        $conn->begin_transaction();
        try {
            cb_detail_ensure_default_customer($conn, $idPob);
            $upsert = cb_detail_upsert_order($conn, $idRaw, $idPob, $order, $existingIdObj);
            cb_detail_sync_children($conn, (int)$upsert['id_obj'], $idPob, $order, (bool)$upsert['is_new']);
            cb_detail_mark_raw($conn, $idRaw, true, null);
            $conn->commit();
            return [
                'id_raw' => $idRaw,
                'restia_id_obj' => $restiaIdObj,
                'id_obj' => (int)$upsert['id_obj'],
                'ok' => 1,
                'error' => '',
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            cb_detail_mark_raw($conn, $idRaw, false, $e->getMessage());
            return [
                'id_raw' => $idRaw,
                'restia_id_obj' => $restiaIdObj,
                'id_obj' => 0,
                'ok' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
}

if (!function_exists('cb_detail_load_rows')) {
    function cb_detail_load_rows(mysqli $conn, int $limit, int $idPob): array
    {
        $limit = max(1, min(5000, $limit));
        if ($idPob > 0) {
            $stmt = $conn->prepare('
                SELECT id_raw, id_pob, restia_id_obj, raw_json
                FROM objednavky_raw
                WHERE zpracovano = 0
                  AND id_pob = ?
                ORDER BY id_raw ASC
                LIMIT ?
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: RAW load.');
            }
            $stmt->bind_param('ii', $idPob, $limit);
        } else {
            $stmt = $conn->prepare('
                SELECT id_raw, id_pob, restia_id_obj, raw_json
                FROM objednavky_raw
                WHERE zpracovano = 0
                ORDER BY id_raw ASC
                LIMIT ?
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: RAW load.');
            }
            $stmt->bind_param('i', $limit);
        }

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

        return $rows;
    }
}

if (!function_exists('cb_detail_counts')) {
    function cb_detail_counts(mysqli $conn): array
    {
        $res = $conn->query('
            SELECT
                COUNT(*) AS celkem,
                SUM(CASE WHEN zpracovano = 1 THEN 1 ELSE 0 END) AS hotovo,
                SUM(CASE WHEN zpracovano = 0 THEN 1 ELSE 0 END) AS ceka,
                SUM(CASE WHEN chyba IS NOT NULL AND chyba <> "" THEN 1 ELSE 0 END) AS chyby
            FROM objednavky_raw
        ');
        if (!($res instanceof mysqli_result)) {
            return ['celkem' => 0, 'hotovo' => 0, 'ceka' => 0, 'chyby' => 0];
        }
        $row = $res->fetch_assoc();
        $res->free();
        return [
            'celkem' => (int)($row['celkem'] ?? 0),
            'hotovo' => (int)($row['hotovo'] ?? 0),
            'ceka' => (int)($row['ceka'] ?? 0),
            'chyby' => (int)($row['chyby'] ?? 0),
        ];
    }
}

$conn = db();
$idPob = (int)($_REQUEST['id_pob'] ?? 0);
$run = isset($_REQUEST['run']) && (string)$_REQUEST['run'] === '1';
$limit = (int)($_REQUEST['limit'] ?? CB_DETAIL_DEFAULT_LIMIT);
$limit = max(1, min(5000, $limit));
$pauseSec = (int)($_REQUEST['pause_sec'] ?? CB_DETAIL_DEFAULT_PAUSE_SEC);
$pauseSec = max(1, $pauseSec);
$autoContinue = isset($_REQUEST['auto_continue']) && (string)$_REQUEST['auto_continue'] === '1';
$processed = [];
$error = '';
$batchMs = 0;

try {
    if ($run) {
        $startedAt = microtime(true);
        $rows = cb_detail_load_rows($conn, $limit, $idPob);
        foreach ($rows as $row) {
            $processed[] = cb_detail_process_raw($conn, $row);
        }
        $batchMs = (int)round((microtime(true) - $startedAt) * 1000);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$counts = cb_detail_counts($conn);
$loaderText = 'Zpracovávám RAW';
$processedCount = count($processed);
$okCount = 0;
$lastIdObj = 0;
foreach ($processed as $processedRow) {
    if ((int)($processedRow['ok'] ?? 0) === 1) {
        $okCount++;
        $lastIdObj = max($lastIdObj, (int)($processedRow['id_obj'] ?? 0));
    }
}
$canAutoContinue = ($autoContinue && $error === '' && (int)$counts['ceka'] > 0);
?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Restia uložení detailu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<h1>Restia uložení detailu</h1>
<p>RAW celkem: <?= (int)$counts['celkem'] ?> | hotovo: <?= (int)$counts['hotovo'] ?> | ceka: <?= (int)$counts['ceka'] ?> | chyby: <?= (int)$counts['chyby'] ?></p>
<?php if ($run): ?>
  <p>
    Dávka: <?= (int)$processedCount ?> řádků |
    OK: <?= (int)$okCount ?> |
    id_obj: <?= (int)$lastIdObj ?> |
    čas: <?= number_format($batchMs / 1000, 2, ',', ' ') ?> s
  </p>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <p style="color:#b00020;">Chyba: <?= cb_detail_h($error) ?></p>
<?php endif; ?>

<form method="post" id="cbDetailForm" data-cb-loader-text="<?= cb_detail_h($loaderText) ?>">
  <input type="hidden" name="open_restia_detail" value="1">
  <input type="hidden" name="run" value="1">
  <label>id_pob: <input type="number" name="id_pob" value="<?= $idPob > 0 ? $idPob : 0 ?>" min="0"></label>
  <label>limit: <input type="number" name="limit" value="<?= $limit ?>" min="1" max="5000"></label>
  <label>pauza po dávce (s): <input type="number" name="pause_sec" value="<?= (int)$pauseSec ?>" min="1"></label>
  <label><input type="checkbox" id="cbDetailAutoContinue" name="auto_continue" value="1"<?= $autoContinue ? ' checked' : '' ?>> Po dávce automaticky pokračovat</label>
  <button type="submit" data-cb-loader-text="<?= cb_detail_h($loaderText) ?>">Zpracovat RAW</button>
  <button type="button" id="cbDetailStop">Stop</button>
</form>

<?php if ($canAutoContinue): ?>
  <p id="cbDetailAutoInfo">Další dávka začne za <?= (int)$pauseSec ?> s. id_obj: <?= (int)$lastIdObj ?></p>
  <div
    id="cb_restia_auto_resume"
    style="display:none;"
    data-cb-restia-auto-resume="1"
    data-cb-restia-auto-resume-delay="<?= (int)$pauseSec * 1000 ?>"
    data-cb-restia-auto-resume-form="#cbDetailForm"
    data-cb-restia-auto-resume-info="#cbDetailAutoInfo"
    data-cb-restia-auto-resume-stop="#cbDetailStop"
    data-cb-restia-auto-resume-checkbox="#cbDetailAutoContinue"
    data-cb-restia-auto-resume-cycle="detail"
    data-cb-restia-auto-resume-next-text="id_obj <?= (int)$lastIdObj ?>"
  ></div>
<?php endif; ?>

</body>
</html>
