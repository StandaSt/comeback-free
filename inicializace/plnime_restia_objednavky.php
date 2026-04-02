<?php
// inicializace/plnime_restia_objednavky.php * Verze: V3  * Aktualizace: 02.04.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- po spusteni automaticky projizdi historii od 01.07.2023 do dneska
- pro kazdy den bere interval 05:00 az nasledujici den 05:00 (lokalni cas Praha)
- lokalni cas si sam prevede na UTC pro Restii
- stahne objednavky po strankach pres cb_restia_get()
- uklada hlavicku objednavky + raw payload + adresu + casy + ceny + kuryra + sluzby + polozky + modifikatory + KDS tagy
- prubezne zapisuje technicky log do TXT po celou dobu behu
- log volani do api_restia zkusi flushnout az nakonec, ale nesmi shodit TXT log ani import

POZNAMKA
- je to bezobsluzny historicky import objednavek z Restie
- zdroj pravdy je Restia, nic neprepocitavame mimo technicke dopocty cen v souctu
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/restia_access_exist.php';
require_once __DIR__ . '/../lib/restia_client.php';
require_once __DIR__ . '/../db/db_api_restia.php';

const CB_RESTIA_IMPORT_A_LIMIT = 100;
const CB_RESTIA_IMPORT_A_VERZE = 'V8';
$cbRestiaEmbedMode = (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php');

if (!function_exists('cb_restia_import_a_h')) {
    function cb_restia_import_a_h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_import_a_now')) {
    function cb_restia_import_a_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_restia_import_a_txt_path')) {
    function cb_restia_import_a_txt_path(): string
    {
        return __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
    }
}

if (!function_exists('cb_restia_import_a_log_init')) {
    function cb_restia_import_a_log_init(): void
    {
        $GLOBALS['__cb_log'] = [];
        @file_put_contents(cb_restia_import_a_txt_path(), '');
    }
}

if (!function_exists('cb_restia_import_a_log')) {
    function cb_restia_import_a_log(string $line = ''): void
    {
        if (!isset($GLOBALS['__cb_log']) || !is_array($GLOBALS['__cb_log'])) {
            $GLOBALS['__cb_log'] = [];
        }
        $GLOBALS['__cb_log'][] = $line;
        @file_put_contents(cb_restia_import_a_txt_path(), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_import_a_local_to_utc_z')) {
    function cb_restia_import_a_local_to_utc_z(string $local): string
    {
        $dt = new DateTimeImmutable($local, new DateTimeZone('Europe/Prague'));
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}

if (!function_exists('cb_restia_import_a_restia_to_local')) {
    function cb_restia_import_a_restia_to_local(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cb_restia_import_a_report_date')) {
    function cb_restia_import_a_report_date(?string $value): ?string
    {
        $local = cb_restia_import_a_restia_to_local($value);
        if ($local === null) {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($local, new DateTimeZone('Europe/Prague'));
            $hour = (int)$dt->format('G');
            if ($hour < 6) {
                $dt = $dt->modify('-1 day');
            }
            return $dt->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cb_restia_import_a_get_auth')) {
    function cb_restia_import_a_get_auth(): array
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

if (!function_exists('cb_restia_import_a_get_pobocka')) {
    function cb_restia_import_a_get_pobocka(mysqli $conn, int $idPob): array
    {
        $stmt = $conn->prepare('
            SELECT id_pob, nazev, restia_activePosId
            FROM pobocka
            WHERE id_pob = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: pobocka.');
        }

        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res) {
            $stmt->close();
            throw new RuntimeException('DB get_result selhal: pobocka.');
        }

        $row = $res->fetch_assoc();
        $res->free();
        $stmt->close();

        if (!is_array($row)) {
            throw new RuntimeException('Pobocka nebyla nalezena.');
        }

        $activePosId = trim((string)($row['restia_activePosId'] ?? ''));

        return [
            'id_pob' => (int)$row['id_pob'],
            'nazev' => (string)($row['nazev'] ?? ''),
            'active_pos_id' => $activePosId,
        ];
    }
}

if (!function_exists('cb_restia_import_a_get_pobocky')) {
    function cb_restia_import_a_get_pobocky(mysqli $conn, int $idUser): array
    {
        $sql = '
            SELECT up.id_pob, p.nazev, p.restia_activePosId
            FROM user_pobocka up
            JOIN pobocka p ON p.id_pob = up.id_pob
            WHERE up.id_user = ?
            ORDER BY up.id_pob ASC
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: seznam pobocky.');
        }

        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res === false) {
            $stmt->close();
            throw new RuntimeException('DB get_result selhal: seznam pobocky.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }

            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => (string)($row['nazev'] ?? ''),
                'active_pos_id' => (string)($row['restia_activePosId'] ?? ''),
            ];
        }
        $res->free();
        $stmt->close();

        return $out;
    }
}

if (!function_exists('cb_restia_import_a_default_date')) {
    function cb_restia_import_a_default_date(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    }
}

if (!function_exists('cb_restia_import_a_format_date_cs')) {
    function cb_restia_import_a_format_date_cs(string $date): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $date;
        }

        return $dt->format('d. m. Y');
    }
}

if (!function_exists('cb_restia_import_a_format_datetime_cs')) {
    function cb_restia_import_a_format_datetime_cs(string $value): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $value;
        }

        return $dt->format('d. m. Y H:i:s');
    }
}

if (!function_exists('cb_restia_import_a_format_datetime_cs_short')) {
    function cb_restia_import_a_format_datetime_cs_short(string $value): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            return $value;
        }

        return $dt->format('d. m. Y H:i');
    }
}

if (!function_exists('cb_restia_import_a_normalize_date')) {
    function cb_restia_import_a_normalize_date(?string $date): string
    {
        if (!is_string($date)) {
            return cb_restia_import_a_default_date();
        }

        $date = trim($date);
        if ($date === '') {
            return cb_restia_import_a_default_date();
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable) || $dt->format('Y-m-d') !== $date) {
            throw new RuntimeException('Neplatne datum.');
        }

        return $dt->format('Y-m-d');
    }
}

if (!function_exists('cb_restia_import_a_selected_day_range')) {
    function cb_restia_import_a_selected_day_range(string $selectedDate): array
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $selectedDate . ' 05:00:00', new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se spocitat datumovy rozsah.');
        }

        $doLocal = $dt->format('Y-m-d H:i:s');
        $odLocal = $dt->modify('-1 day')->format('Y-m-d H:i:s');

        return [
            'datum' => $selectedDate,
            'od_local' => $odLocal,
            'do_local' => $doLocal,
        ];
    }
}

if (!function_exists('cb_restia_import_a_post_string')) {
    function cb_restia_import_a_post_string(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }
}

if (!function_exists('cb_restia_import_a_extract_orders')) {
    function cb_restia_import_a_extract_orders(array $json): array
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

if (!function_exists('cb_restia_import_a_json')) {
    function cb_restia_import_a_json(mixed $value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return null;
        }
        return $json;
    }
}

if (!function_exists('cb_restia_import_a_hash32')) {
    function cb_restia_import_a_hash32(string $value): string
    {
        $bin = hash('sha256', $value, true);
        return is_string($bin) ? $bin : str_repeat("\0", 32);
    }
}

if (!function_exists('cb_restia_import_a_lookup_id')) {
    function cb_restia_import_a_lookup_id(mysqli $conn, string $table, string $col, string $value, string $idCol): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $sqlSel = 'SELECT `' . $idCol . '` AS id FROM `' . $table . '` WHERE `' . $col . '` = ? LIMIT 1';
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

        $sqlIns = 'INSERT INTO `' . $table . '` (`' . $col . '`, `aktivni`) VALUES (?, 1)';
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

if (!function_exists('cb_restia_import_a_order_exists')) {
    function cb_restia_import_a_order_exists(mysqli $conn, string $restiaId): bool
    {
        $stmt = $conn->prepare('
            SELECT 1
            FROM objednavky_restia
            WHERE restia_id_obj = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: order exists.');
        }

        $stmt->bind_param('s', $restiaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $exists = false;

        if ($res) {
            $exists = ($res->fetch_assoc() !== null);
            $res->free();
        }

        $stmt->close();
        return $exists;
    }
}

if (!function_exists('cb_restia_import_a_insert_import')) {
    function cb_restia_import_a_insert_import(mysqli $conn, int $idPob, string $odUtc, string $doUtc): int
    {
        $typImportu = 'test';
        $stav = 'bezi';

        $stmt = $conn->prepare('
            INSERT INTO obj_import (typ_importu, id_pob, datum_od, datum_do, stav, spusteno)
            VALUES (?, ?, ?, ?, ?, NOW(3))
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_import insert.');
        }

        $stmt->bind_param('sisss', $typImportu, $idPob, $odUtc, $doUtc, $stav);
        $stmt->execute();
        $idImport = (int)$conn->insert_id;
        $stmt->close();

        return $idImport;
    }
}

if (!function_exists('cb_restia_import_a_finish_import')) {
    function cb_restia_import_a_finish_import(
        mysqli $conn,
        int $idImport,
        string $stav,
        int $pocetObj,
        int $pocetNovych,
        int $pocetZmenenych,
        int $pocetChyb,
        string $poznamka
    ): void {
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
            throw new RuntimeException('DB prepare selhal: obj_import update.');
        }

        $stmt->bind_param(
            'siiiisi',
            $stav,
            $pocetObj,
            $pocetNovych,
            $pocetZmenenych,
            $pocetChyb,
            $poznamka,
            $idImport
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_import_a_money')) {
    function cb_restia_import_a_money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }

        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', trim($value)))) {
            $n = (float)$value;
            if (abs($n) >= 1000 || (is_string($value) && preg_match('/^-?\d+$/', trim($value)))) {
                $n = $n / 100.0;
            }
            return number_format($n, 2, '.', '');
        }

        return '0.00';
    }
}

if (!function_exists('cb_restia_import_a_money_sum')) {
    function cb_restia_import_a_money_sum(string ...$values): string
    {
        $sum = 0.0;
        foreach ($values as $value) {
            $sum += (float)$value;
        }
        return number_format($sum, 2, '.', '');
    }
}

if (!function_exists('cb_restia_import_a_get_obj_id')) {
    function cb_restia_import_a_get_obj_id(mysqli $conn, string $restiaIdObj): int
    {
        $stmt = $conn->prepare('
            SELECT id_obj
            FROM objednavky_restia
            WHERE restia_id_obj = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: get id_obj.');
        }

        $stmt->bind_param('s', $restiaIdObj);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res) {
            $stmt->close();
            throw new RuntimeException('DB get_result selhal: get id_obj.');
        }

        $row = $res->fetch_assoc();
        $res->free();
        $stmt->close();

        $idObj = (int)($row['id_obj'] ?? 0);
        if ($idObj <= 0) {
            throw new RuntimeException('Nepodarilo se dohledat id_obj.');
        }

        return $idObj;
    }
}

if (!function_exists('cb_restia_import_a_upsert_order')) {
    function cb_restia_import_a_upsert_order(mysqli $conn, int $idPob, string $activePosId, array $order): int
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

        $status = trim((string)($order['status'] ?? 'neznamy'));
        if ($status === '') {
            $status = 'neznamy';
        }

        $paymentType = trim((string)($order['paymentType'] ?? 'neznamy'));
        if ($paymentType === '') {
            $paymentType = 'neznamy';
        }

        $deliveryType = trim((string)($order['deliveryType'] ?? 'neznamy'));
        if ($deliveryType === '') {
            $deliveryType = 'neznamy';
        }

        $idPlatforma = cb_restia_import_a_lookup_id($conn, 'cis_obj_platforma', 'kod', $profilTyp, 'id_platforma');
        $idStav = cb_restia_import_a_lookup_id($conn, 'cis_obj_stav', 'nazev', $status, 'id_stav');
        $idPlatba = cb_restia_import_a_lookup_id($conn, 'cis_obj_platby', 'nazev', $paymentType, 'id_platba');
        $idDoruceni = cb_restia_import_a_lookup_id($conn, 'cis_doruceni', 'nazev', $deliveryType, 'id_doruceni');

        $restiaOrderNumber = (string)($order['orderNumber'] ?? '');
        $restiaToken = ($order['token'] ?? null);
        $restiaToken = ($restiaToken === null || $restiaToken === '') ? null : (string)$restiaToken;
        $profilKlic = (string)($profile['key'] ?? '');
        $profilNazev = (string)($profile['name'] ?? '');
        $profilMenuId = ($profile['menuId'] ?? null);
        $profilMenuId = ($profilMenuId === null || $profilMenuId === '') ? null : (string)$profilMenuId;
        $profilPosId = (string)($profile['posId'] ?? '');
        $profilUrl = ($profile['url'] ?? null);
        $profilUrl = ($profilUrl === null || $profilUrl === '') ? null : (string)$profilUrl;
        $jeVyzvednuti = (!empty($order['isPickup'])) ? 1 : 0;
        $jeVRestauraci = (!empty($order['isInRestaurant'])) ? 1 : 0;
        $jeVlastniRozvoz = (!empty($order['isSelfDelivery'])) ? 1 : 0;
        $kuryrPoradi = isset($order['courierOrder']) && $order['courierOrder'] !== null ? (int)$order['courierOrder'] : null;
        $posImportStav = ($order['posImportStatus'] ?? null);
        $posImportStav = ($posImportStav === null || $posImportStav === '') ? null : (string)$posImportStav;
        $restObj = $restiaIdObj;
        $shortCode = ($order['shortCode'] ?? null);
        $shortCode = ($shortCode === null || $shortCode === '') ? null : (string)$shortCode;
        $serioveCislo = ($order['serialNumber'] ?? null);
        $serioveCislo = ($serioveCislo === null || $serioveCislo === '') ? null : (string)$serioveCislo;
        $zakJmeno = ($order['customerName'] ?? null);
        $zakJmeno = ($zakJmeno === null || $zakJmeno === '') ? null : (string)$zakJmeno;
        $zakTelefon = ($order['customerPhone'] ?? null);
        $zakTelefon = ($zakTelefon === null || $zakTelefon === '') ? null : (string)$zakTelefon;
        $zakEmail = ($order['customerEmail'] ?? null);
        $zakEmail = ($zakEmail === null || $zakEmail === '') ? null : (string)$zakEmail;
        $zakPoznamka = ($order['customerNote'] ?? null);
        $zakPoznamka = ($zakPoznamka === null || $zakPoznamka === '') ? null : (string)$zakPoznamka;
        $zpozdeniMin = isset($order['cookingTimeMinutes']) ? (int)$order['cookingTimeMinutes'] : null;
        $objPozn = ($order['note'] ?? null);
        $objPozn = ($objPozn === null || $objPozn === '') ? null : (string)$objPozn;
        $rawJson = cb_restia_import_a_json($order);
        if ($rawJson === null) {
            throw new RuntimeException('Nepodarilo se prevest objednavku na JSON.');
        }
        $rawHash = cb_restia_import_a_hash32($rawJson);

        $sql = '
            INSERT INTO objednavky_restia (
                id_pob, id_platforma, restia_id_obj, restia_order_number, restia_token, restia_active_pos_id,
                profil_typ, profil_klic, profil_nazev, profil_menu_id, profil_pos_id, profil_url,
                je_vyzvednuti, je_v_restauraci, je_vlastni_rozvoz, kuryr_poradi, pos_import_stav,
                rest_obj, short_code, seriove_cislo, id_stav, id_platba, id_doruceni,
                zak_jmeno, zak_telefon, zak_email, zak_poznamka, zpozdeni_min, obj_pozn, raw_hash, raw_json
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                id_pob = VALUES(id_pob),
                id_platforma = VALUES(id_platforma),
                restia_order_number = VALUES(restia_order_number),
                restia_token = VALUES(restia_token),
                restia_active_pos_id = VALUES(restia_active_pos_id),
                profil_typ = VALUES(profil_typ),
                profil_klic = VALUES(profil_klic),
                profil_nazev = VALUES(profil_nazev),
                profil_menu_id = VALUES(profil_menu_id),
                profil_pos_id = VALUES(profil_pos_id),
                profil_url = VALUES(profil_url),
                je_vyzvednuti = VALUES(je_vyzvednuti),
                je_v_restauraci = VALUES(je_v_restauraci),
                je_vlastni_rozvoz = VALUES(je_vlastni_rozvoz),
                kuryr_poradi = VALUES(kuryr_poradi),
                pos_import_stav = VALUES(pos_import_stav),
                rest_obj = VALUES(rest_obj),
                short_code = VALUES(short_code),
                seriove_cislo = VALUES(seriove_cislo),
                id_stav = VALUES(id_stav),
                id_platba = VALUES(id_platba),
                id_doruceni = VALUES(id_doruceni),
                zak_jmeno = VALUES(zak_jmeno),
                zak_telefon = VALUES(zak_telefon),
                zak_email = VALUES(zak_email),
                zak_poznamka = VALUES(zak_poznamka),
                zpozdeni_min = VALUES(zpozdeni_min),
                obj_pozn = VALUES(obj_pozn),
                raw_hash = VALUES(raw_hash),
                raw_json = VALUES(raw_json)
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: objednavky_restia upsert.');
        }

        $stmt->bind_param(
            'iissssssssssiiiissssiiissssisss',
            $idPob,
            $idPlatforma,
            $restiaIdObj,
            $restiaOrderNumber,
            $restiaToken,
            $activePosId,
            $profilTyp,
            $profilKlic,
            $profilNazev,
            $profilMenuId,
            $profilPosId,
            $profilUrl,
            $jeVyzvednuti,
            $jeVRestauraci,
            $jeVlastniRozvoz,
            $kuryrPoradi,
            $posImportStav,
            $restObj,
            $shortCode,
            $serioveCislo,
            $idStav,
            $idPlatba,
            $idDoruceni,
            $zakJmeno,
            $zakTelefon,
            $zakEmail,
            $zakPoznamka,
            $zpozdeniMin,
            $objPozn,
            $rawHash,
            $rawJson
        );

        $stmt->execute();
        $stmt->close();

        return cb_restia_import_a_get_obj_id($conn, $restiaIdObj);
    }
}

if (!function_exists('cb_restia_import_a_insert_raw')) {
    function cb_restia_import_a_insert_raw(mysqli $conn, int $idImport, int $idPob, string $restiaIdObj, string $rawJson): void
    {
        $payloadHash = cb_restia_import_a_hash32($rawJson);

        $stmt = $conn->prepare('
            INSERT INTO obj_raw (id_import, id_pob, restia_id_obj, payload_hash, payload_json, vytvoreno)
            VALUES (?, ?, ?, ?, ?, NOW(3))
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_raw insert.');
        }

        $stmt->bind_param('iisss', $idImport, $idPob, $restiaIdObj, $payloadHash, $rawJson);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_import_a_upsert_adresa')) {
    function cb_restia_import_a_upsert_adresa(mysqli $conn, int $idObj, array $order): void
    {
        $destination = $order['destination'] ?? null;
        if (!is_array($destination)) {
            $destination = [];
        }

        $deliveryData = (isset($destination['deliveryData']) && is_array($destination['deliveryData'])) ? $destination['deliveryData'] : [];
        $street = $destination['street'] ?? null;
        $street = ($street === null || $street === '') ? null : (string)$street;
        $city = $destination['city'] ?? null;
        $city = ($city === null || $city === '') ? null : (string)$city;
        $zip = $destination['zip'] ?? null;
        $zip = ($zip === null || $zip === '') ? null : (string)$zip;
        $country = $destination['country'] ?? null;
        $country = ($country === null || $country === '') ? null : (string)$country;
        $lat = isset($destination['latitude']) && $destination['latitude'] !== null && $destination['latitude'] !== '' ? (float)$destination['latitude'] : null;
        $lng = isset($destination['longitude']) && $destination['longitude'] !== null && $destination['longitude'] !== '' ? (float)$destination['longitude'] : null;
        $distance = isset($deliveryData['distanceMeters']) && $deliveryData['distanceMeters'] !== null ? (int)round((float)$deliveryData['distanceMeters']) : null;
        $duration = isset($deliveryData['durationSeconds']) && $deliveryData['durationSeconds'] !== null ? (int)round((float)$deliveryData['durationSeconds']) : null;
        $rawTyp = array_is_list($destination) ? 'array' : 'object';
        $rawJson = cb_restia_import_a_json($destination);

        $stmt = $conn->prepare('
            INSERT INTO obj_adresa (
                id_obj, ulice, cislo_domovni, mesto, psc, stat, lat, lng, raw_typ, vzdalenost_m, cas_jizdy_s, raw_json, vytvoreno, zmeneno
            ) VALUES (
                ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3), NOW(3)
            )
            ON DUPLICATE KEY UPDATE
                ulice = VALUES(ulice),
                mesto = VALUES(mesto),
                psc = VALUES(psc),
                stat = VALUES(stat),
                lat = VALUES(lat),
                lng = VALUES(lng),
                raw_typ = VALUES(raw_typ),
                vzdalenost_m = VALUES(vzdalenost_m),
                cas_jizdy_s = VALUES(cas_jizdy_s),
                raw_json = VALUES(raw_json),
                zmeneno = NOW(3)
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_adresa upsert.');
        }

        $stmt->bind_param('issssddsiss', $idObj, $street, $city, $zip, $country, $lat, $lng, $rawTyp, $distance, $duration, $rawJson);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_import_a_upsert_casy')) {
    function cb_restia_import_a_upsert_casy(mysqli $conn, int $idObj, array $order): void
    {
        $report = cb_restia_import_a_report_date((string)($order['createdAt'] ?? ''));
        if ($report === null) {
            $report = cb_restia_import_a_now();
            $report = substr($report, 0, 10);
        }

        $casVytvor = cb_restia_import_a_restia_to_local($order['createdAt'] ?? null);
        $casExpirace = cb_restia_import_a_restia_to_local($order['expiresAt'] ?? null);
        $casSlib = cb_restia_import_a_restia_to_local($order['promisedAt'] ?? null);
        $casPriprDo = cb_restia_import_a_restia_to_local($order['prepareAt'] ?? null);
        $casPriprV = cb_restia_import_a_restia_to_local($order['preparedAt'] ?? null);
        $casDokonc = cb_restia_import_a_restia_to_local($order['finishedAt'] ?? null);
        $casDoruc = cb_restia_import_a_restia_to_local($order['deliveredAt'] ?? null);
        $casStatusZmena = cb_restia_import_a_restia_to_local($order['statusUpdatedAt'] ?? null);
        $casUzavreni = cb_restia_import_a_restia_to_local($order['closedAt'] ?? null);
        $casImportRestia = cb_restia_import_a_restia_to_local($order['importedAt'] ?? null);
        $casImportPos = cb_restia_import_a_restia_to_local($order['posImportedAt'] ?? null);
        $casVyzv = cb_restia_import_a_restia_to_local($order['pickupAt'] ?? null);
        $casDisp = cb_restia_import_a_restia_to_local($order['deliveryAt'] ?? null);

        $stmt = $conn->prepare('
            INSERT INTO obj_casy (
                id_obj, report, cas_vytvor, cas_expirace, cas_slib, cas_pripr_do, cas_pripr_v, cas_dokonc, cas_doruc,
                cas_status_zmena, cas_uzavreni, cas_import_restia, cas_import_pos, cas_vyzv, cas_disp
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                report = VALUES(report),
                cas_vytvor = VALUES(cas_vytvor),
                cas_expirace = VALUES(cas_expirace),
                cas_slib = VALUES(cas_slib),
                cas_pripr_do = VALUES(cas_pripr_do),
                cas_pripr_v = VALUES(cas_pripr_v),
                cas_dokonc = VALUES(cas_dokonc),
                cas_doruc = VALUES(cas_doruc),
                cas_status_zmena = VALUES(cas_status_zmena),
                cas_uzavreni = VALUES(cas_uzavreni),
                cas_import_restia = VALUES(cas_import_restia),
                cas_import_pos = VALUES(cas_import_pos),
                cas_vyzv = VALUES(cas_vyzv),
                cas_disp = VALUES(cas_disp)
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_casy upsert.');
        }

        $stmt->bind_param(
            'issssssssssssss',
            $idObj,
            $report,
            $casVytvor,
            $casExpirace,
            $casSlib,
            $casPriprDo,
            $casPriprV,
            $casDokonc,
            $casDoruc,
            $casStatusZmena,
            $casUzavreni,
            $casImportRestia,
            $casImportPos,
            $casVyzv,
            $casDisp
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_import_a_upsert_ceny')) {
    function cb_restia_import_a_upsert_ceny(mysqli $conn, int $idObj, array $order): void
    {
        $cenaPol = cb_restia_import_a_money($order['itemsPrice'] ?? null);
        $cenaBalne = cb_restia_import_a_money($order['packingPrice'] ?? null);
        $cenaDopr = cb_restia_import_a_money($order['deliveryPrice'] ?? null);
        $dyska = cb_restia_import_a_money($order['tipPrice'] ?? null);
        $cenaDoMin = cb_restia_import_a_money($order['surchargeToMin'] ?? null);
        $cenaServis = cb_restia_import_a_money($order['serviceFeePrice'] ?? null);
        $sleva = cb_restia_import_a_money($order['discountPrice'] ?? null);
        $zaokrouhleni = cb_restia_import_a_money($order['roundingPrice'] ?? null);
        $cenaCelk = cb_restia_import_a_money_sum($cenaPol, $cenaBalne, $cenaDopr, $dyska, $cenaDoMin, $cenaServis, $zaokrouhleni, number_format(-(float)$sleva, 2, '.', ''));

        $stmt = $conn->prepare('
            INSERT INTO obj_ceny (
                id_obj, cena_celk, cena_pol, cena_balne, cena_dopr, dyska, cena_do_min, cena_servis, sleva, zaokrouhleni, mena
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "CZK"
            )
            ON DUPLICATE KEY UPDATE
                cena_celk = VALUES(cena_celk),
                cena_pol = VALUES(cena_pol),
                cena_balne = VALUES(cena_balne),
                cena_dopr = VALUES(cena_dopr),
                dyska = VALUES(dyska),
                cena_do_min = VALUES(cena_do_min),
                cena_servis = VALUES(cena_servis),
                sleva = VALUES(sleva),
                zaokrouhleni = VALUES(zaokrouhleni),
                mena = VALUES(mena)
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_ceny upsert.');
        }

        $stmt->bind_param('isssssssss', $idObj, $cenaCelk, $cenaPol, $cenaBalne, $cenaDopr, $dyska, $cenaDoMin, $cenaServis, $sleva, $zaokrouhleni);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('cb_restia_import_a_delete_children')) {
    function cb_restia_import_a_delete_children(mysqli $conn, int $idObj): void
    {
        $sqls = [
            'DELETE t FROM obj_polozka_kds_tag t INNER JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka WHERE p.id_obj = ?',
            'DELETE m FROM obj_polozka_mod m INNER JOIN obj_polozky p ON p.id_obj_polozka = m.id_obj_polozka WHERE p.id_obj = ?',
            'DELETE FROM obj_polozky WHERE id_obj = ?',
            'DELETE FROM obj_kuryr WHERE id_obj = ?',
            'DELETE FROM obj_sluzba WHERE id_obj = ?',
        ];

        foreach ($sqls as $sql) {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: delete children.');
            }
            $stmt->bind_param('i', $idObj);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('cb_restia_import_a_insert_kuryr')) {
    function cb_restia_import_a_insert_kuryr(mysqli $conn, int $idObj, array $order): int
    {
        $courier = $order['courierData'] ?? null;
        if (!is_array($courier) || $courier === []) {
            return 0;
        }

        $provider = 'restia';
        $externiId = ($courier['id'] ?? null);
        $externiId = ($externiId === null || $externiId === '') ? null : (string)$externiId;
        $poradi = isset($order['courierOrder']) && $order['courierOrder'] !== null ? (int)$order['courierOrder'] : null;
        $jmeno = ($courier['name'] ?? null);
        $jmeno = ($jmeno === null || $jmeno === '') ? null : (string)$jmeno;
        $telefon = ($courier['phone'] ?? null);
        $telefon = ($telefon === null || $telefon === '') ? null : (string)$telefon;
        $rawJson = cb_restia_import_a_json($courier);
        $dataJson = cb_restia_import_a_json([
            'routeId' => $courier['routeId'] ?? null,
            'orderAssignedAt' => $courier['orderAssignedAt'] ?? null,
        ]);

        $stmt = $conn->prepare('
            INSERT INTO obj_kuryr (
                id_obj, provider, externi_id, poradi, jmeno, telefon, raw_json, data_json, vytvoreno, zmeneno
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, NOW(3), NOW(3)
            )
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_kuryr insert.');
        }

        $stmt->bind_param('ississss', $idObj, $provider, $externiId, $poradi, $jmeno, $telefon, $rawJson, $dataJson);
        $stmt->execute();
        $stmt->close();

        return 1;
    }
}

if (!function_exists('cb_restia_import_a_insert_sluzby')) {
    function cb_restia_import_a_insert_sluzby(mysqli $conn, int $idObj, array $order): int
    {
        $services = $order['servicesData'] ?? null;
        if (!is_array($services) || $services === []) {
            return 0;
        }

        $pocet = 0;
        foreach ($services as $provider => $service) {
            if (!is_array($service)) {
                continue;
            }

            $providerStr = trim((string)$provider);
            if ($providerStr === '') {
                $providerStr = 'restia';
            }
            $externiId = ($service['id'] ?? null);
            $externiId = ($externiId === null || $externiId === '') ? null : (string)$externiId;
            $stav = ($service['importStatus'] ?? ($service['status'] ?? null));
            $stav = ($stav === null || $stav === '') ? null : (string)$stav;
            $rawJson = cb_restia_import_a_json($service);
            $dataJson = cb_restia_import_a_json([
                'type' => $service['type'] ?? null,
                'importedAt' => $service['importedAt'] ?? null,
                'statusUpdatedAt' => $service['statusUpdatedAt'] ?? null,
                'requiredCourierId' => $service['requiredCourierId'] ?? null,
            ]);

            $stmt = $conn->prepare('
                INSERT INTO obj_sluzba (
                    id_obj, provider, externi_id, stav, raw_json, data_json, vytvoreno, zmeneno
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, NOW(3), NOW(3)
                )
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: obj_sluzba insert.');
            }

            $stmt->bind_param('isssss', $idObj, $providerStr, $externiId, $stav, $rawJson, $dataJson);
            $stmt->execute();
            $stmt->close();
            $pocet++;
        }

        return $pocet;
    }
}

if (!function_exists('cb_restia_import_a_insert_item_mods')) {
    function cb_restia_import_a_insert_item_mods(mysqli $conn, int $idObjPolozka, array $item): int
    {
        $klice = ['modifiers', 'mods', 'selectedOptions', 'options', 'extras'];
        $pocet = 0;

        foreach ($klice as $klic) {
            if (!isset($item[$klic]) || !is_array($item[$klic])) {
                continue;
            }

            foreach ($item[$klic] as $mod) {
                $restiaModId = null;
                $typ = $klic;
                $posId = null;
                $nazev = null;
                $mnozstvi = 1.0;
                $cenaKs = 0.00;
                $cenaCelk = 0.00;
                $rawJson = null;

                if (is_array($mod)) {
                    $restiaModId = isset($mod['id']) && $mod['id'] !== '' ? (string)$mod['id'] : null;
                    $typ = isset($mod['type']) && $mod['type'] !== '' ? (string)$mod['type'] : $klic;
                    $posId = isset($mod['posId']) && $mod['posId'] !== '' ? (string)$mod['posId'] : null;
                    $nazev = isset($mod['name']) && $mod['name'] !== '' ? (string)$mod['name'] : (isset($mod['label']) ? (string)$mod['label'] : null);
                    if ($nazev === null || $nazev === '') {
                        $nazev = $klic;
                    }
                    $mnozstvi = isset($mod['count']) ? (float)$mod['count'] : (isset($mod['quantity']) ? (float)$mod['quantity'] : 1.0);
                    $cenaKs = (float)cb_restia_import_a_money($mod['price'] ?? 0);
                    $cenaCelk = (float)cb_restia_import_a_money($mod['totalPrice'] ?? (($cenaKs * $mnozstvi) * 100));
                    $rawJson = cb_restia_import_a_json($mod);
                } else {
                    $nazev = (string)$mod;
                    if ($nazev === '') {
                        $nazev = $klic;
                    }
                    $rawJson = cb_restia_import_a_json($mod);
                }

                $stmt = $conn->prepare('
                    INSERT INTO obj_polozka_mod (
                        id_obj_polozka, restia_mod_id, typ, pos_id, nazev, mnozstvi, cena_ks, cena_celk, raw_json, zadano
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3)
                    )
                ');
                if ($stmt === false) {
                    throw new RuntimeException('DB prepare selhal: obj_polozka_mod insert.');
                }

                $stmt->bind_param('issssddds', $idObjPolozka, $restiaModId, $typ, $posId, $nazev, $mnozstvi, $cenaKs, $cenaCelk, $rawJson);
                $stmt->execute();
                $stmt->close();
                $pocet++;
            }
        }

        return $pocet;
    }
}

if (!function_exists('cb_restia_import_a_insert_item_tags')) {
    function cb_restia_import_a_insert_item_tags(mysqli $conn, int $idObjPolozka, array $item): int
    {
        $tags = $item['KDSTags'] ?? null;
        if (!is_array($tags) || $tags === []) {
            return 0;
        }

        $pocet = 0;
        foreach ($tags as $tag) {
            $tagStr = trim((string)$tag);
            if ($tagStr === '') {
                continue;
            }

            $stmt = $conn->prepare('
                INSERT INTO obj_polozka_kds_tag (id_obj_polozka, tag)
                VALUES (?, ?)
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: obj_polozka_kds_tag insert.');
            }

            $stmt->bind_param('is', $idObjPolozka, $tagStr);
            $stmt->execute();
            $stmt->close();
            $pocet++;
        }

        return $pocet;
    }
}

if (!function_exists('cb_restia_import_a_insert_polozky')) {
    function cb_restia_import_a_insert_polozky(mysqli $conn, int $idObj, array $order): array
    {
        $items = $order['items'] ?? null;
        if (!is_array($items)) {
            return ['polozky' => 0, 'modifikatory' => 0, 'kds_tagy' => 0];
        }

        $polozky = 0;
        $modifikatory = 0;
        $kdsTagy = 0;
        $poradi = 1;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $restiaItemId = isset($item['id']) && $item['id'] !== '' ? (string)$item['id'] : null;
            $posId = trim((string)($item['posId'] ?? ''));
            if ($posId === '') {
                $posId = 'NEZNAME_POS_ID';
            }
            $nazev = trim((string)($item['label'] ?? ''));
            if ($nazev === '') {
                $nazev = 'Neznama polozka';
            }
            $actualLabel = isset($item['actualLabel']) && $item['actualLabel'] !== '' ? (string)$item['actualLabel'] : null;
            $creatorId = isset($item['creatorId']) && $item['creatorId'] !== '' ? (string)$item['creatorId'] : null;
            $isPackaging = !empty($item['isPackging']) || !empty($item['isPackaging']) ? 1 : 0;
            $mainItemId = isset($item['mainItemId']) && $item['mainItemId'] !== '' ? (string)$item['mainItemId'] : null;
            $poznamka = isset($item['note']) && $item['note'] !== '' ? (string)$item['note'] : null;
            $mnozstvi = isset($item['count']) ? (int)$item['count'] : 1;
            if ($mnozstvi <= 0) {
                $mnozstvi = 1;
            }
            $cenaKs = (float)cb_restia_import_a_money($item['price'] ?? 0);
            $cenaCelk = $cenaKs * $mnozstvi;
            $jeExtra = !empty($item['isExtra']) ? 1 : 0;
            $rawJson = cb_restia_import_a_json($item);

            $stmt = $conn->prepare('
                INSERT INTO obj_polozky (
                    id_obj, restia_item_id, pos_id, nazev, actual_label, creator_id, is_packaging, main_item_id,
                    poznamka, poradi, mnozstvi, cena_ks, cena_celk, je_extra, raw_json, zadano
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: obj_polozky insert.');
            }

            $stmt->bind_param(
                'isssssissiiddds',
                $idObj,
                $restiaItemId,
                $posId,
                $nazev,
                $actualLabel,
                $creatorId,
                $isPackaging,
                $mainItemId,
                $poznamka,
                $poradi,
                $mnozstvi,
                $cenaKs,
                $cenaCelk,
                $jeExtra,
                $rawJson
            );
            $stmt->execute();
            $idObjPolozka = (int)$conn->insert_id;
            $stmt->close();

            $modifikatory += cb_restia_import_a_insert_item_mods($conn, $idObjPolozka, $item);
            $kdsTagy += cb_restia_import_a_insert_item_tags($conn, $idObjPolozka, $item);
            $polozky++;
            $poradi++;
        }

        return ['polozky' => $polozky, 'modifikatory' => $modifikatory, 'kds_tagy' => $kdsTagy];
    }
}

if (!function_exists('cb_restia_import_a_sync_order_children')) {
    function cb_restia_import_a_sync_order_children(mysqli $conn, int $idObj, array $order): array
    {
        cb_restia_import_a_upsert_adresa($conn, $idObj, $order);
        cb_restia_import_a_upsert_casy($conn, $idObj, $order);
        cb_restia_import_a_upsert_ceny($conn, $idObj, $order);
        cb_restia_import_a_delete_children($conn, $idObj);
        $kuryr = cb_restia_import_a_insert_kuryr($conn, $idObj, $order);
        $sluzby = cb_restia_import_a_insert_sluzby($conn, $idObj, $order);
        $polozkyInfo = cb_restia_import_a_insert_polozky($conn, $idObj, $order);

        return [
            'kuryr' => $kuryr,
            'sluzby' => $sluzby,
            'polozky' => (int)$polozkyInfo['polozky'],
            'modifikatory' => (int)$polozkyInfo['modifikatory'],
            'kds_tagy' => (int)$polozkyInfo['kds_tagy'],
        ];
    }
}

if (!function_exists('cb_restia_import_a_try_flush_api')) {
    function cb_restia_import_a_try_flush_api(?mysqli $conn, ?array $auth): void
    {
        if (!($conn instanceof mysqli) || !is_array($auth)) {
            return;
        }

        try {
            db_api_restia_flush($conn, (int)($auth['id_user'] ?? 0), (int)($auth['id_login'] ?? 0));
            cb_restia_import_a_log('API_RESTIA: flush OK');
        } catch (Throwable $e) {
            cb_restia_import_a_log('API_RESTIA: flush FAIL: ' . $e->getMessage());
        }
    }
}

$rowsInfo = [];
$summary = [
    'pocet_dni' => 0,
    'pocet_pob_kontrola' => 0,
    'pocet_pob_s_obj' => 0,
    'pocet_import_ok' => 0,
    'pocet_import_chyba' => 0,
    'pocet_skip_ok' => 0,
    'pocet_obj' => 0,
    'pocet_novych' => 0,
    'pocet_zmenenych' => 0,
    'pocet_chyb' => 0,
    'pocet_polozek' => 0,
    'pocet_modifikatoru' => 0,
    'pocet_kds_tagu' => 0,
    'pocet_kuryru' => 0,
    'pocet_sluzeb' => 0,
];

const CB_RESTIA_IMPORT_HIST_OD = '2023-07-01';
const CB_RESTIA_IMPORT_HIST_PAUZA_US = 150000;
const CB_RESTIA_IMPORT_TYP = 'historie';

if (!function_exists('cb_restia_import_a_get_pobocky_all')) {
    function cb_restia_import_a_get_pobocky_all(mysqli $conn, int $idUser): array
    {
        $sql = '
            SELECT up.id_pob, p.nazev, p.restia_activePosId
            FROM user_pobocka up
            JOIN pobocka p ON p.id_pob = up.id_pob
            WHERE up.id_user = ?
            ORDER BY up.id_pob ASC
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: seznam vsech pobocky.');
        }

        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res === false) {
            $stmt->close();
            throw new RuntimeException('DB get_result selhal: seznam vsech pobocky.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            if (!is_array($row)) {
                continue;
            }

            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => (string)($row['nazev'] ?? ''),
                'active_pos_id' => trim((string)($row['restia_activePosId'] ?? '')),
            ];
        }
        $res->free();
        $stmt->close();

        return $out;
    }
}

if (!function_exists('cb_restia_import_a_history_day_range')) {
    function cb_restia_import_a_history_day_range(string $datum): array
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $datum . ' 05:00:00', new DateTimeZone('Europe/Prague'));
        if (!($dt instanceof DateTimeImmutable)) {
            throw new RuntimeException('Nepodarilo se spocitat historicky rozsah.');
        }

        return [
            'datum' => $datum,
            'od_local' => $dt->format('Y-m-d H:i:s'),
            'do_local' => $dt->modify('+1 day')->format('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('cb_restia_import_a_import_ok_info')) {
    function cb_restia_import_a_import_ok_info(mysqli $conn, int $idPob, string $odUtc, string $doUtc): ?array
    {
        $stmt = $conn->prepare('
            SELECT id_import, pocet_obj, pocet_novych, pocet_zmenenych
            FROM obj_import
            WHERE typ_importu = ?
              AND id_pob = ?
              AND datum_od = ?
              AND datum_do = ?
              AND stav = "ok"
            ORDER BY id_import DESC
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_import ok info.');
        }

        $typImportu = CB_RESTIA_IMPORT_TYP;
        $stmt->bind_param('siss', $typImportu, $idPob, $odUtc, $doUtc);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res === false) {
            $stmt->close();
            throw new RuntimeException('DB get_result selhal: obj_import ok info.');
        }

        $row = $res->fetch_assoc();
        $res->free();
        $stmt->close();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id_import' => (int)($row['id_import'] ?? 0),
            'pocet_obj' => (int)($row['pocet_obj'] ?? 0),
            'pocet_novych' => (int)($row['pocet_novych'] ?? 0),
            'pocet_zmenenych' => (int)($row['pocet_zmenenych'] ?? 0),
        ];
    }
}

if (!function_exists('cb_restia_import_a_insert_import_typ')) {
    function cb_restia_import_a_insert_import_typ(mysqli $conn, string $typImportu, int $idPob, string $odUtc, string $doUtc): int
    {
        $stav = 'bezi';

        $stmt = $conn->prepare('
            INSERT INTO obj_import (typ_importu, id_pob, datum_od, datum_do, stav, spusteno)
            VALUES (?, ?, ?, ?, ?, NOW(3))
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_import insert typ.');
        }

        $stmt->bind_param('sisss', $typImportu, $idPob, $odUtc, $doUtc, $stav);
        $stmt->execute();
        $idImport = (int)$conn->insert_id;
        $stmt->close();

        return $idImport;
    }
}

if (!function_exists('cb_restia_import_a_render_header')) {
    function cb_restia_import_a_render_header(bool $embedMode): void
    {
        if (!$embedMode) {
            ?>
            <!doctype html>
            <html lang="cs">
            <head>
              <meta charset="utf-8">
              <title>Plneni Restia objednavky</title>
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <style>
                body { font-family: Arial, sans-serif; margin: 16px; background: #f5f7fb; color: #1f2933; }
                .wrap { width: 100%; max-width: none; margin: 0; }
                .box { background: #fff; border: 1px solid #d9e2ec; border-radius: 12px; padding: 16px; margin-bottom: 12px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; white-space: nowrap; }
                .ok { color: #166534; font-weight: 700; }
                .err { color: #b91c1c; font-weight: 700; }
                .muted { color: #52606d; }
              </style>
            </head>
            <body>
            <div class="wrap">
            <?php
        } else {
            ?>
            <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
            <?php
        }

        ?>
        <div class="box">
          <h2 style="margin:0 0 10px 0;">Plneni Restia objednavky</h2>
          <p style="margin:0 0 6px 0;">Historie od <strong><?= cb_restia_import_a_h(cb_restia_import_a_format_date_cs(CB_RESTIA_IMPORT_HIST_OD)) ?></strong> do <strong><?= cb_restia_import_a_h(cb_restia_import_a_format_date_cs(cb_restia_import_a_default_date())) ?></strong></p>
          <p class="muted" style="margin:0;">Prubeh se pripisuje dolu. Cas celkem bezi porad dal.</p>
        </div>
        <div class="box">
          <table>
            <thead>
              <tr>
                <th>Datum</th>
                <th>Pobocka</th>
                <th>Pocet obj</th>
                <th>Cas kroku</th>
                <th>Cas celkem</th>
                <th>Stav</th>
              </tr>
            </thead>
            <tbody>
        <?php

        if (function_exists('ob_implicit_flush')) {
            ob_implicit_flush(true);
        }
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        flush();
    }
}

if (!function_exists('cb_restia_import_a_render_footer')) {
    function cb_restia_import_a_render_footer(bool $embedMode, array $summary): void
    {
        ?>
            </tbody>
          </table>
        </div>
        <div class="box">
          <p style="margin:0 0 6px 0;"><strong>Hotovo.</strong></p>
          <p style="margin:0 0 4px 0;">Dni: <?= cb_restia_import_a_h((string)$summary['pocet_dni']) ?> | kontroly pob: <?= cb_restia_import_a_h((string)$summary['pocet_pob_kontrola']) ?> | pob s objednavkami: <?= cb_restia_import_a_h((string)$summary['pocet_pob_s_obj']) ?></p>
          <p style="margin:0 0 4px 0;">Import ok: <?= cb_restia_import_a_h((string)$summary['pocet_import_ok']) ?> | import chyba: <?= cb_restia_import_a_h((string)$summary['pocet_import_chyba']) ?> | skip ok: <?= cb_restia_import_a_h((string)$summary['pocet_skip_ok']) ?></p>
          <p style="margin:0;">Obj: <?= cb_restia_import_a_h((string)$summary['pocet_obj']) ?> | nove: <?= cb_restia_import_a_h((string)$summary['pocet_novych']) ?> | zmenene: <?= cb_restia_import_a_h((string)$summary['pocet_zmenenych']) ?> | chyby: <?= cb_restia_import_a_h((string)$summary['pocet_chyb']) ?></p>
        </div>
        <?php
        if (!$embedMode) {
            ?>
            </div>
            </body>
            </html>
            <?php
        } else {
            ?>
            </div>
            <?php
        }
        flush();
    }
}

if (!function_exists('cb_restia_import_a_step_row')) {
    function cb_restia_import_a_step_row(string $datum, string $pobocka, string $pocetObj, float $stepSec, float $totalSec, string $stav, bool $jeChyba = false): void
    {
        $class = $jeChyba ? 'err' : (($stav === 'OK' || $stav === 'SKIP_OK') ? 'ok' : 'muted');
        ?>
        <tr>
          <td><?= cb_restia_import_a_h(cb_restia_import_a_format_date_cs($datum)) ?></td>
          <td><?= cb_restia_import_a_h($pobocka) ?></td>
          <td><?= cb_restia_import_a_h($pocetObj) ?></td>
          <td><?= cb_restia_import_a_h(number_format($stepSec, 2, '.', '')) ?> s</td>
          <td><?= cb_restia_import_a_h(number_format($totalSec, 2, '.', '')) ?> s</td>
          <td class="<?= $class ?>"><?= cb_restia_import_a_h($stav) ?></td>
        </tr>
        <?php
        flush();
    }
}

ignore_user_abort(true);
@set_time_limit(0);

$conn = null;
$auth = null;
$startRunTs = microtime(true);

try {
    $conn = db();
    $auth = cb_restia_import_a_get_auth();
    $pobocky = cb_restia_import_a_get_pobocky_all($conn, (int)$auth['id_user']);
} catch (Throwable $e) {
    if (!$cbRestiaEmbedMode) {
        http_response_code(500);
        ?>
        <!doctype html>
        <html lang="cs">
        <head>
          <meta charset="utf-8">
          <title>Plneni Restia objednavky - chyba</title>
        </head>
        <body>
          <h1>CHYBA</h1>
          <p><?= cb_restia_import_a_h($e->getMessage()) ?></p>
        </body>
        </html>
        <?php
        exit;
    }
    ?>
    <div class="ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2>CHYBA</h2>
      <p><?= cb_restia_import_a_h($e->getMessage()) ?></p>
    </div>
    <?php
    return;
}

cb_restia_import_a_log_init();
cb_restia_import_a_log('START: ' . date('Y-m-d H:i:s'));
cb_restia_import_a_log('SCRIPT: ' . basename(__FILE__));
cb_restia_import_a_log('VERSION: ' . CB_RESTIA_IMPORT_A_VERZE);
cb_restia_import_a_log('TYP_IMPORTU: ' . CB_RESTIA_IMPORT_TYP);
cb_restia_import_a_log('HIST_OD: ' . CB_RESTIA_IMPORT_HIST_OD);
cb_restia_import_a_log('HIST_DO: ' . cb_restia_import_a_default_date());

cb_restia_import_a_render_header($cbRestiaEmbedMode);

try {
    $odHist = DateTimeImmutable::createFromFormat('Y-m-d', CB_RESTIA_IMPORT_HIST_OD, new DateTimeZone('Europe/Prague'));
    $doHist = DateTimeImmutable::createFromFormat('Y-m-d', cb_restia_import_a_default_date(), new DateTimeZone('Europe/Prague'));

    if (!($odHist instanceof DateTimeImmutable) || !($doHist instanceof DateTimeImmutable)) {
        throw new RuntimeException('Nepodarilo se pripravit rozsah historie.');
    }

    $datum = $odHist;
    while ($datum <= $doHist) {
        $datumDen = $datum->format('Y-m-d');
        $range = cb_restia_import_a_history_day_range($datumDen);
        $odLocal = $range['od_local'];
        $doLocal = $range['do_local'];
        $odUtc = cb_restia_import_a_local_to_utc_z($odLocal);
        $doUtc = cb_restia_import_a_local_to_utc_z($doLocal);

        $summary['pocet_dni']++;
        cb_restia_import_a_log('');
        cb_restia_import_a_log('DEN_START: ' . $datumDen . ' | OD_LOCAL=' . $odLocal . ' | DO_LOCAL=' . $doLocal);
        cb_restia_import_a_log('DEN_UTC: ' . $datumDen . ' | OD_UTC=' . $odUtc . ' | DO_UTC=' . $doUtc);

        foreach ($pobocky as $pob) {
            $stepStartTs = microtime(true);
            $summary['pocet_pob_kontrola']++;

            $idPob = (int)$pob['id_pob'];
            $nazevPob = (string)$pob['nazev'];
            $activePosId = trim((string)$pob['active_pos_id']);

            if ($activePosId === '') {
                $stepSec = microtime(true) - $stepStartTs;
                $totalSec = microtime(true) - $startRunTs;
                cb_restia_import_a_step_row($datumDen, $nazevPob, '0', $stepSec, $totalSec, 'BEZ_RESTIA');
                cb_restia_import_a_log('SKIP bez Restia: datum=' . $datumDen . ' | id_pob=' . $idPob . ' | pob=' . $nazevPob);
                continue;
            }

            $existingOk = cb_restia_import_a_import_ok_info($conn, $idPob, $odUtc, $doUtc);
            if (is_array($existingOk)) {
                $summary['pocet_skip_ok']++;
                $summary['pocet_pob_s_obj']++;
                $summary['pocet_obj'] += (int)$existingOk['pocet_obj'];
                $summary['pocet_novych'] += (int)$existingOk['pocet_novych'];
                $summary['pocet_zmenenych'] += (int)$existingOk['pocet_zmenenych'];

                $stepSec = microtime(true) - $stepStartTs;
                $totalSec = microtime(true) - $startRunTs;
                cb_restia_import_a_step_row($datumDen, $nazevPob, (string)$existingOk['pocet_obj'], $stepSec, $totalSec, 'SKIP_OK');
                cb_restia_import_a_log('SKIP ok: datum=' . $datumDen . ' | id_pob=' . $idPob . ' | pocet_obj=' . (string)$existingOk['pocet_obj']);
                continue;
            }

            $page = 1;
            $limit = (int)CB_RESTIA_IMPORT_A_LIMIT;
            $idImport = 0;
            $createdImport = false;
            $countOrdersTotal = 0;
            $countNew = 0;
            $countChanged = 0;
            $countErrors = 0;
            $countPolozky = 0;
            $countMod = 0;
            $countKds = 0;
            $countKuryr = 0;
            $countSluzby = 0;

            try {
                while (true) {
                    cb_restia_import_a_log('PAGE_START: datum=' . $datumDen . ' | id_pob=' . $idPob . ' | page=' . $page);
                    $res = cb_restia_get(
                        '/api/orders',
                        [
                            'page' => $page,
                            'limit' => $limit,
                            'createdFrom' => $odUtc,
                            'createdTo' => $doUtc,
                            'activePosId' => $activePosId,
                        ],
                        $activePosId,
                        'historie: id_pob=' . (string)$idPob . ' page=' . (string)$page . ' datum=' . $datumDen
                    );

                    if ((int)($res['ok'] ?? 0) !== 1) {
                        if (!$createdImport) {
                            $idImport = cb_restia_import_a_insert_import_typ($conn, CB_RESTIA_IMPORT_TYP, $idPob, $odUtc, $doUtc);
                            $createdImport = true;
                        }
                        throw new RuntimeException((string)($res['chyba'] ?? 'Restia orders vratila chybu.'));
                    }

                    $body = (string)($res['body'] ?? '');
                    $decoded = json_decode($body, true);
                    if (!is_array($decoded)) {
                        if (!$createdImport) {
                            $idImport = cb_restia_import_a_insert_import_typ($conn, CB_RESTIA_IMPORT_TYP, $idPob, $odUtc, $doUtc);
                            $createdImport = true;
                        }
                        throw new RuntimeException('Restia nevratila validni JSON.');
                    }

                    $orders = cb_restia_import_a_extract_orders($decoded);
                    $countOrders = count($orders);

                    cb_restia_import_a_log(
                        'PAGE_OK: datum=' . $datumDen .
                        ' | id_pob=' . $idPob .
                        ' | page=' . $page .
                        ' | http=' . (int)($res['http_status'] ?? 0) .
                        ' | total=' . (string)($res['total_count'] ?? '') .
                        ' | count=' . $countOrders .
                        ' | ms=' . (int)($res['ms'] ?? 0) .
                        ' | bytes=' . (int)($res['bytes_in'] ?? 0)
                    );

                    if (!$createdImport && $countOrders > 0) {
                        $idImport = cb_restia_import_a_insert_import_typ($conn, CB_RESTIA_IMPORT_TYP, $idPob, $odUtc, $doUtc);
                        $createdImport = true;
                        cb_restia_import_a_log('ID_IMPORT: ' . (string)$idImport . ' | datum=' . $datumDen . ' | id_pob=' . $idPob);
                    }

                    if ($countOrders > 0) {
                        foreach ($orders as $order) {
                            if (!is_array($order)) {
                                continue;
                            }

                            $restiaIdObj = trim((string)($order['id'] ?? ''));
                            if ($restiaIdObj === '') {
                                cb_restia_import_a_log('ERROR order ?: chybi id | datum=' . $datumDen . ' | id_pob=' . $idPob);
                                $countErrors++;
                                continue;
                            }

                            $rawJson = cb_restia_import_a_json($order);
                            if ($rawJson === null) {
                                cb_restia_import_a_log('ERROR order ' . $restiaIdObj . ': nepodarilo se vytvorit JSON');
                                $countErrors++;
                                continue;
                            }

                            $exists = cb_restia_import_a_order_exists($conn, $restiaIdObj);

                            $conn->begin_transaction();
                            try {
                                $idObj = cb_restia_import_a_upsert_order($conn, $idPob, $activePosId, $order);
                                cb_restia_import_a_insert_raw($conn, $idImport, $idPob, $restiaIdObj, $rawJson);
                                $sync = cb_restia_import_a_sync_order_children($conn, $idObj, $order);
                                $conn->commit();
                            } catch (Throwable $e) {
                                $conn->rollback();
                                cb_restia_import_a_log('ERROR order ' . $restiaIdObj . ': ' . $e->getMessage());
                                $countErrors++;
                                continue;
                            }

                            $countOrdersTotal++;
                            $countPolozky += (int)$sync['polozky'];
                            $countMod += (int)$sync['modifikatory'];
                            $countKds += (int)$sync['kds_tagy'];
                            $countKuryr += (int)$sync['kuryr'];
                            $countSluzby += (int)$sync['sluzby'];

                            if ($exists) {
                                $countChanged++;
                            } else {
                                $countNew++;
                            }

                            cb_restia_import_a_log(
                                'OK order ' . $restiaIdObj .
                                ': id_obj=' . (string)$idObj .
                                ' | datum=' . $datumDen .
                                ' | id_pob=' . $idPob .
                                ' | polozky=' . (string)$sync['polozky'] .
                                ' | mod=' . (string)$sync['modifikatory'] .
                                ' | kds=' . (string)$sync['kds_tagy'] .
                                ' | kuryr=' . (string)$sync['kuryr'] .
                                ' | sluzby=' . (string)$sync['sluzby']
                            );
                        }
                    }

                    if ($countOrders < $limit) {
                        break;
                    }

                    $totalCount = isset($res['total_count']) ? (int)$res['total_count'] : 0;
                    if ($totalCount > 0 && ($page * $limit) >= $totalCount) {
                        break;
                    }

                    if ($countOrders === 0) {
                        break;
                    }

                    $page++;
                }

                if ($createdImport) {
                    $stavImportu = ($countErrors > 0) ? 'chyba' : 'ok';
                    $poznamka = 'Historie den hotov. datum=' . $datumDen . ' id_pob=' . (string)$idPob . ' pages=' . (string)$page . ' obj=' . (string)$countOrdersTotal;

                    cb_restia_import_a_finish_import(
                        $conn,
                        $idImport,
                        $stavImportu,
                        $countOrdersTotal,
                        $countNew,
                        $countChanged,
                        $countErrors,
                        $poznamka
                    );

                    if ($stavImportu === 'ok') {
                        $summary['pocet_import_ok']++;
                    } else {
                        $summary['pocet_import_chyba']++;
                        $summary['pocet_chyb'] += $countErrors;
                    }

                    $summary['pocet_pob_s_obj']++;
                    $summary['pocet_obj'] += $countOrdersTotal;
                    $summary['pocet_novych'] += $countNew;
                    $summary['pocet_zmenenych'] += $countChanged;
                    $summary['pocet_polozek'] += $countPolozky;
                    $summary['pocet_modifikatoru'] += $countMod;
                    $summary['pocet_kds_tagu'] += $countKds;
                    $summary['pocet_kuryru'] += $countKuryr;
                    $summary['pocet_sluzeb'] += $countSluzby;

                    $stepSec = microtime(true) - $stepStartTs;
                    $totalSec = microtime(true) - $startRunTs;
                    cb_restia_import_a_step_row(
                        $datumDen,
                        $nazevPob,
                        (string)$countOrdersTotal,
                        $stepSec,
                        $totalSec,
                        ($stavImportu === 'ok') ? 'OK' : 'CHYBA',
                        ($stavImportu !== 'ok')
                    );
                } else {
                    $stepSec = microtime(true) - $stepStartTs;
                    $totalSec = microtime(true) - $startRunTs;
                    cb_restia_import_a_step_row($datumDen, $nazevPob, '0', $stepSec, $totalSec, '0_OBJ');
                    cb_restia_import_a_log('ZERO_OBJ: datum=' . $datumDen . ' | id_pob=' . $idPob . ' | pob=' . $nazevPob);
                }
            } catch (Throwable $e) {
                if ($createdImport && $idImport > 0) {
                    try {
                        cb_restia_import_a_finish_import(
                            $conn,
                            $idImport,
                            'chyba',
                            $countOrdersTotal,
                            $countNew,
                            $countChanged,
                            $countErrors + 1,
                            'STOP: ' . $e->getMessage()
                        );
                    } catch (Throwable $e2) {
                        cb_restia_import_a_log('FATAL_FINISH_IMPORT: ' . $e2->getMessage());
                    }
                }

                $summary['pocet_import_chyba']++;
                $summary['pocet_chyb']++;
                $stepSec = microtime(true) - $stepStartTs;
                $totalSec = microtime(true) - $startRunTs;
                cb_restia_import_a_step_row($datumDen, $nazevPob, ($countOrdersTotal > 0 ? (string)$countOrdersTotal : 'ERR'), $stepSec, $totalSec, 'CHYBA', true);
                cb_restia_import_a_log('FATAL_STEP: datum=' . $datumDen . ' | id_pob=' . $idPob . ' | msg=' . $e->getMessage());
            }
        }

        if ($datum < $doHist) {
            usleep(CB_RESTIA_IMPORT_HIST_PAUZA_US);
        }

        $datum = $datum->modify('+1 day');
    }

    cb_restia_import_a_try_flush_api($conn, $auth);
    cb_restia_import_a_log('TXT: ' . cb_restia_import_a_txt_path());
    cb_restia_import_a_log('END: ' . date('Y-m-d H:i:s'));
} catch (Throwable $e) {
    cb_restia_import_a_log('FATAL: ' . $e->getMessage());
    cb_restia_import_a_try_flush_api($conn, $auth);
    cb_restia_import_a_log('END: ' . date('Y-m-d H:i:s'));

    $summary['pocet_chyb']++;
    ?>
    <tr>
      <td colspan="6" class="err"><?= cb_restia_import_a_h($e->getMessage()) ?></td>
    </tr>
    <?php
}

cb_restia_import_a_render_footer($cbRestiaEmbedMode, $summary);
// inicializace/plnime_restia_objednavky.php * Verze: V3 * Aktualizace: 02.04.2026

// Konec souboru
?>