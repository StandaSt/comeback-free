<?php
// admin_testy/01_restia_import_den.php * Verze: V8 * Aktualizace: 01.04.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DELA

- po spusteni zobrazi formular pro vyber pobocky a dne
- zvoleny den bere jako interval predchozi den 05:00 az zvoleny den 05:00 (lokalni cas Praha)
- lokalni cas si sam prevede na UTC pro Restii
- stahne objednavky po strankach pres cb_restia_get()
- uklada hlavicku objednavky + raw payload + adresu + casy + ceny + kuryra + sluzby + polozky + modifikatory + KDS tagy
- prubezne zapisuje technicky log do TXT po celou dobu behu
- log volani do api_restia zkusi flushnout az nakonec, ale nesmi shodit TXT log ani import

POZNAMKA
- je to testovaci import pro overeni mapovani dat z Restie
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
require_once __DIR__ . '/../db/zapis_log_chyby.php';

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
if (!function_exists('cb_restia_import_a_lookup_res_polozka_id')) {
    function cb_restia_import_a_lookup_res_polozka_id(mysqli $conn, int $idPob, string $restiaItemId): int
    {
        $restiaItemId = trim($restiaItemId);
        if ($idPob <= 0 || $restiaItemId === '') {
            return 0;
        }

        $stmtSel = $conn->prepare('
            SELECT id_res_polozka AS id
            FROM res_polozky
            WHERE id_pob = ? AND pos_code = ?
            ORDER BY id_res_polozka DESC
            LIMIT 1
        ');
        if ($stmtSel === false) {
            throw new RuntimeException('DB prepare selhal: res_polozky lookup.');
        }

        $stmtSel->bind_param('is', $idPob, $restiaItemId);
        $stmtSel->execute();
        $resSel = $stmtSel->get_result();
        $row = ($resSel instanceof mysqli_result) ? $resSel->fetch_assoc() : null;
        if ($resSel instanceof mysqli_result) {
            $resSel->free();
        }
        $stmtSel->close();

        return (int)($row['id'] ?? 0);
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
        $zpozdeniMin = isset($order['cookingTimeMinutes']) ? (int)$order['cookingTimeMinutes'] : null;
        $objPozn = ($order['note'] ?? null);
        $objPozn = ($objPozn === null || $objPozn === '') ? null : (string)$objPozn;
        $importTs = cb_restia_import_a_now();
        $sql = '
            INSERT INTO objednavky_restia (
                id_pob, id_platforma, restia_id_obj, restia_order_number, restia_token,
                profil_typ, profil_klic, profil_nazev, profil_menu_id, profil_pos_id, profil_url,
                je_vyzvednuti, je_v_restauraci, je_vlastni_rozvoz, kuryr_poradi, pos_import_stav,
                rest_obj, short_code, seriove_cislo, id_stav, id_platba, id_doruceni,
                zpozdeni_min, obj_pozn, restia_imported_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                id_pob = VALUES(id_pob),
                id_platforma = VALUES(id_platforma),
                restia_order_number = VALUES(restia_order_number),
                restia_token = VALUES(restia_token),
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
                zpozdeni_min = VALUES(zpozdeni_min),
                obj_pozn = VALUES(obj_pozn),
                restia_imported_at = VALUES(restia_imported_at)
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: objednavky_restia upsert.');
        }

        $stmt->bind_param(
            'iisssssssssiiiissssiiiiss',
            $idPob,
            $idPlatforma,
            $restiaIdObj,
            $restiaOrderNumber,
            $restiaToken,
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
            $zpozdeniMin,
            $objPozn,
            $importTs
        );

        $stmt->execute();
        $stmt->close();

        return cb_restia_import_a_get_obj_id($conn, $restiaIdObj);
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
        $stmt = $conn->prepare('
            INSERT INTO obj_adresa (
                id_obj, ulice, cislo_domovni, mesto, psc, stat, lat, lng, vzdalenost_m, cas_jizdy_s, vytvoreno, zmeneno
            ) VALUES (
                ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, NOW(3), NOW(3)
            )
            ON DUPLICATE KEY UPDATE
                ulice = VALUES(ulice),
                mesto = VALUES(mesto),
                psc = VALUES(psc),
                stat = VALUES(stat),
                lat = VALUES(lat),
                lng = VALUES(lng),
                vzdalenost_m = VALUES(vzdalenost_m),
                cas_jizdy_s = VALUES(cas_jizdy_s),
                zmeneno = NOW(3)
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_adresa upsert.');
        }

        $stmt->bind_param('issssddii', $idObj, $street, $city, $zip, $country, $lat, $lng, $distance, $duration);
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
        $stmt = $conn->prepare('
            INSERT INTO obj_kuryr (
                id_obj, provider, externi_id, poradi, jmeno, telefon, vytvoreno, zmeneno
            ) VALUES (
                ?, ?, ?, ?, ?, ?, NOW(3), NOW(3)
            )
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: obj_kuryr insert.');
        }

        $stmt->bind_param('ississ', $idObj, $provider, $externiId, $poradi, $jmeno, $telefon);
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
            $stmt = $conn->prepare('
                INSERT INTO obj_sluzba (
                    id_obj, provider, externi_id, stav, vytvoreno, zmeneno
                ) VALUES (
                    ?, ?, ?, ?, NOW(3), NOW(3)
                )
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: obj_sluzba insert.');
            }

            $stmt->bind_param('isss', $idObj, $providerStr, $externiId, $stav);
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
                } else {
                    $nazev = (string)$mod;
                    if ($nazev === '') {
                        $nazev = $klic;
                    }
                }

                $stmt = $conn->prepare('
                    INSERT INTO obj_polozka_mod (
                        id_obj_polozka, restia_mod_id, typ, pos_id, nazev, mnozstvi, cena_ks, cena_celk, zadano
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, NOW(3)
                    )
                ');
                if ($stmt === false) {
                    throw new RuntimeException('DB prepare selhal: obj_polozka_mod insert.');
                }

                $stmt->bind_param('issssddd', $idObjPolozka, $restiaModId, $typ, $posId, $nazev, $mnozstvi, $cenaKs, $cenaCelk);
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
    function cb_restia_import_a_insert_polozky(mysqli $conn, int $idObj, int $idPob, array $order): array
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

            $poznamka = isset($item['note']) && $item['note'] !== '' ? (string)$item['note'] : null;
            $mnozstvi = isset($item['count']) ? (int)$item['count'] : 1;
            if ($mnozstvi <= 0) {
                $mnozstvi = 1;
            }
            $cenaKs = (float)cb_restia_import_a_money($item['price'] ?? 0);
            $cenaCelk = $cenaKs * $mnozstvi;
            $jeExtra = !empty($item['isExtra']) ? 1 : 0;
            $restiaItemId = isset($item['posId']) && $item['posId'] !== ''
                ? (string)$item['posId']
                : (isset($item['id']) && $item['id'] !== '' ? (string)$item['id'] : '');
            $idResPolozka = cb_restia_import_a_lookup_res_polozka_id($conn, $idPob, $restiaItemId);
            if ($idResPolozka <= 0) {
                throw new RuntimeException('Chybi res_polozky mapovani pro restia item ' . $restiaItemId . ' id_pob=' . (string)$idPob);
            }

            $stmt = $conn->prepare('
                INSERT INTO obj_polozky (
                    id_obj, id_res_polozka, poznamka, poradi, mnozstvi, cena_ks, cena_celk, je_extra, zadano
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: obj_polozky insert.');
            }

            $stmt->bind_param(
                'iisiiddi',
                $idObj,
                $idResPolozka,
                $poznamka,
                $poradi,
                $mnozstvi,
                $cenaKs,
                $cenaCelk,
                $jeExtra
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
    function cb_restia_import_a_sync_order_children(mysqli $conn, int $idObj, int $idPob, array $order): array
    {
        cb_restia_import_a_upsert_adresa($conn, $idObj, $order);
        cb_restia_import_a_upsert_casy($conn, $idObj, $order);
        cb_restia_import_a_upsert_ceny($conn, $idObj, $order);
        cb_restia_import_a_delete_children($conn, $idObj);
        $kuryr = cb_restia_import_a_insert_kuryr($conn, $idObj, $order);
        $sluzby = cb_restia_import_a_insert_sluzby($conn, $idObj, $order);
        $polozkyInfo = cb_restia_import_a_insert_polozky($conn, $idObj, $idPob, $order);

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
    'id_import' => 0,
    'pocet_obj' => 0,
    'pocet_novych' => 0,
    'pocet_zmenenych' => 0,
    'pocet_chyb' => 0,
    'stav' => 'ceka',
    'poznamka' => '',
    'page_count' => 0,
    'pocet_polozek' => 0,
    'pocet_modifikatoru' => 0,
    'pocet_kds_tagu' => 0,
    'pocet_kuryru' => 0,
    'pocet_sluzeb' => 0,
];

$cbFlashKey = 'cb_restia_import_a_flash';
$cbFlash = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' && isset($_SESSION[$cbFlashKey]) && is_array($_SESSION[$cbFlashKey])) {
    $cbFlash = $_SESSION[$cbFlashKey];
    unset($_SESSION[$cbFlashKey]);
}

$formError = '';
$formMode = 'form';
$selectedIdPob = 0;
$selectedDate = cb_restia_import_a_default_date();
$selectedRange = null;
$pob = null;
$conn = null;
$auth = null;

try {
    $conn = db();
    $auth = cb_restia_import_a_get_auth();
    $pobocky = cb_restia_import_a_get_pobocky($conn, (int)$auth['id_user']);
} catch (Throwable $e) {
    if (!$cbRestiaEmbedMode) {
        http_response_code(500);
        ?>
        <!doctype html>
        <html lang="cs">
        <head>
          <meta charset="utf-8">
          <title>Restia import den - chyba</title>
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $akce = cb_restia_import_a_post_string('akce');

    if ($akce === 'zpet') {
        $selectedIdPob = (int)cb_restia_import_a_post_string('id_pob');
        $selectedDate = cb_restia_import_a_post_string('datum');
        if ($selectedDate === '') {
            $selectedDate = cb_restia_import_a_default_date();
        }
        $formMode = 'form';
    } elseif ($akce === 'potvrdit' || $akce === 'spustit') {
        try {
            $selectedIdPob = (int)cb_restia_import_a_post_string('id_pob');
            $selectedDate = cb_restia_import_a_normalize_date(cb_restia_import_a_post_string('datum'));
            if ($selectedIdPob <= 0) {
                throw new RuntimeException('Vyber pobocku.');
            }

            $pob = cb_restia_import_a_get_pobocka($conn, $selectedIdPob);
            $selectedRange = cb_restia_import_a_selected_day_range($selectedDate);

            if ($akce === 'potvrdit') {
                $formMode = 'confirm';
            } else {
                $formMode = 'run';
            }
        } catch (Throwable $e) {
            $formError = $e->getMessage();
            $formMode = 'form';
        }
    }
}

if ($cbFlash !== null) {
    $rowsInfo = (isset($cbFlash['rows_info']) && is_array($cbFlash['rows_info'])) ? $cbFlash['rows_info'] : [];
    $summary = (isset($cbFlash['summary']) && is_array($cbFlash['summary'])) ? $cbFlash['summary'] : $summary;
    $pob = (isset($cbFlash['pob']) && is_array($cbFlash['pob'])) ? $cbFlash['pob'] : null;
    $selectedIdPob = (int)($cbFlash['selected_id_pob'] ?? 0);
    $selectedDate = (string)($cbFlash['selected_date'] ?? $selectedDate);
    $odLocal = (string)($cbFlash['od_local'] ?? '');
    $doLocal = (string)($cbFlash['do_local'] ?? '');
}

if ($formMode !== 'run' && $cbFlash === null) {
    ?>
    <?php if (!$cbRestiaEmbedMode): ?>
      <!doctype html>
      <html lang="cs">
      <head>
        <meta charset="utf-8">
        <title>Restia import den</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
      </head>
      <body>
    <?php endif; ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
        <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Restia import den</h2>
        <?php if ($formError !== ''): ?>
          <p class="card_text txt_cervena text_tucny odstup_horni_10"><?= cb_restia_import_a_h($formError) ?></p>
        <?php endif; ?>

        <?php if ($formMode === 'confirm' && is_array($selectedRange) && is_array($pob)): ?>
          <h3 class="text_24 txt_seda text_tucny odstup_horni_10 odstup_vnejsi_0">Potvrzeni</h3>
          <table class="table ram_normal bg_bila radek_1_35 sirka100 odstup_horni_10">
            <tbody>
              <tr><td class="text_tucny">Vybrana pobocka</td><td><strong><?= cb_restia_import_a_h((string)$pob['nazev']) ?></strong></td></tr>
              <tr><td class="text_tucny">Zvolene datum</td><td><strong><?= cb_restia_import_a_h(cb_restia_import_a_format_date_cs($selectedRange['datum'])) ?></strong></td></tr>
              <tr><td class="text_tucny">Rozsah</td><td><strong><?= cb_restia_import_a_h(cb_restia_import_a_format_datetime_cs_short($selectedRange['od_local'])) ?></strong> az <strong><?= cb_restia_import_a_h(cb_restia_import_a_format_datetime_cs_short($selectedRange['do_local'])) ?></strong></td></tr>
            </tbody>
          </table>
          <div class="card_actions gap_8 displ_flex jc_konec">
            <form method="post">
              <input type="hidden" name="akce" value="spustit">
              <input type="hidden" name="id_pob" value="<?= cb_restia_import_a_h((string)$selectedIdPob) ?>">
              <input type="hidden" name="datum" value="<?= cb_restia_import_a_h($selectedDate) ?>">
              <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Provest stazeni</button>
            </form>
            <form method="post">
              <input type="hidden" name="akce" value="zpet">
              <input type="hidden" name="id_pob" value="<?= cb_restia_import_a_h((string)$selectedIdPob) ?>">
              <input type="hidden" name="datum" value="<?= cb_restia_import_a_h($selectedDate) ?>">
              <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Zpet</button>
            </form>
          </div>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="akce" value="potvrdit">
            <div class="card_stack gap_10 displ_flex flex_sloupec">
              <div>
                <label for="id_pob" class="card_text text_12 text_tucny">Pobocka</label>
                <select class="card_select ram_sedy txt_seda bg_bila zaobleni_8 vyska_32 sirka100" name="id_pob" id="id_pob" required>
                  <option value="0">Vyber pobocku</option>
                  <?php foreach ($pobocky as $pobocka): ?>
                    <option value="<?= cb_restia_import_a_h((string)$pobocka['id_pob']) ?>"<?= ((int)$selectedIdPob === (int)$pobocka['id_pob']) ? ' selected' : '' ?>><?= cb_restia_import_a_h((string)$pobocka['nazev']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label for="datum" class="card_text text_12 text_tucny">Datum</label>
                <input class="card_input ram_sedy txt_seda bg_bila zaobleni_8 vyska_32 sirka100" type="date" name="datum" id="datum" value="<?= cb_restia_import_a_h($selectedDate) ?>" required>
                <p class="card_text txt_seda">Vybrany den = predchozi den 05:00 az vybrany den 05:00.</p>
              </div>
            </div>
            <div class="card_actions gap_8 displ_flex jc_konec">
              <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Pokracovat</button>
            </div>
          </form>
        <?php endif; ?>
    </div>
    <?php if (!$cbRestiaEmbedMode): ?>
      </body>
      </html>
    <?php endif; ?>
    <?php
    if ($cbRestiaEmbedMode) {
        return;
    }
    exit;
}

if ($cbFlash === null) {
    $summary['stav'] = 'bezi';
    $odLocal = (string)$selectedRange['od_local'];
    $doLocal = (string)$selectedRange['do_local'];

    cb_restia_import_a_log_init();
    cb_restia_import_a_log('START: ' . date('Y-m-d H:i:s'));
    cb_restia_import_a_log('SCRIPT: ' . basename(__FILE__));
    cb_restia_import_a_log('VERSION: ' . CB_RESTIA_IMPORT_A_VERZE);
    cb_restia_import_a_log('ID_POB: ' . (string)$selectedIdPob);
    cb_restia_import_a_log('DATUM: ' . $selectedDate);
    cb_restia_import_a_log('OD_LOCAL: ' . $odLocal);
    cb_restia_import_a_log('DO_LOCAL: ' . $doLocal);

    try {
    $auth = cb_restia_import_a_get_auth();
    if (!is_array($pob)) {
        $pob = cb_restia_import_a_get_pobocka($conn, $selectedIdPob);
    }

    $odUtc = cb_restia_import_a_local_to_utc_z($odLocal);
    $doUtc = cb_restia_import_a_local_to_utc_z($doLocal);

    cb_restia_import_a_log('POBOCKA: ' . (string)$pob['nazev'] . ' | activePosId=' . (string)$pob['active_pos_id']);
    cb_restia_import_a_log('OD_UTC: ' . $odUtc);
    cb_restia_import_a_log('DO_UTC: ' . $doUtc);

    $idImport = cb_restia_import_a_insert_import($conn, (int)$pob['id_pob'], $odUtc, $doUtc);
    $summary['id_import'] = $idImport;
    cb_restia_import_a_log('ID_IMPORT: ' . (string)$idImport);

    $page = 1;
    $limit = (int)CB_RESTIA_IMPORT_A_LIMIT;

    while (true) {
        cb_restia_import_a_log('PAGE_START: ' . (string)$page);
        $res = cb_restia_get(
            '/api/orders',
            [
                'page' => $page,
                'limit' => $limit,
                'createdFrom' => $odUtc,
                'createdTo' => $doUtc,
                'activePosId' => (string)$pob['active_pos_id'],
            ],
            (string)$pob['active_pos_id'],
            'import den: id_pob=' . (string)$pob['id_pob'] . ' page=' . (string)$page . ' datum=' . $selectedDate
        );

        if ((int)($res['ok'] ?? 0) !== 1) {
            throw new RuntimeException((string)($res['chyba'] ?? 'Restia orders vratila chybu.'));
        }

        $body = (string)($res['body'] ?? '');
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Restia nevratila validni JSON.');
        }

        $orders = cb_restia_import_a_extract_orders($decoded);
        $countOrders = count($orders);

        cb_restia_import_a_log('PAGE_OK: page=' . $page . ' http=' . (int)($res['http_status'] ?? 0) . ' total=' . (string)($res['total_count'] ?? '') . ' count=' . $countOrders . ' ms=' . (int)($res['ms'] ?? 0) . ' bytes=' . (int)($res['bytes_in'] ?? 0));
        $rowsInfo[] = [
            'page' => $page,
            'http' => (int)($res['http_status'] ?? 0),
            'total' => (string)($res['total_count'] ?? ''),
            'count' => $countOrders,
            'ms' => (int)($res['ms'] ?? 0),
            'bytes_in' => (int)($res['bytes_in'] ?? 0),
        ];
        $summary['page_count']++;

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }

            $restiaIdObj = trim((string)($order['id'] ?? ''));
            if ($restiaIdObj === '') {
                $summary['pocet_chyb']++;
                continue;
            }

            $exists = cb_restia_import_a_order_exists($conn, $restiaIdObj);

            $conn->begin_transaction();
            try {
                $idObj = cb_restia_import_a_upsert_order($conn, (int)$pob['id_pob'], (string)$pob['active_pos_id'], $order);
                $sync = cb_restia_import_a_sync_order_children($conn, $idObj, (int)$pob['id_pob'], $order);
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                $summary['pocet_chyb']++;
                continue;
            }

            $summary['pocet_obj']++;
            $summary['pocet_polozek'] += (int)$sync['polozky'];
            $summary['pocet_modifikatoru'] += (int)$sync['modifikatory'];
            $summary['pocet_kds_tagu'] += (int)$sync['kds_tagy'];
            $summary['pocet_kuryru'] += (int)$sync['kuryr'];
            $summary['pocet_sluzeb'] += (int)$sync['sluzby'];

            if ($exists) {
                $summary['pocet_zmenenych']++;
            } else {
                $summary['pocet_novych']++;
            }

            cb_restia_import_a_log(
                'OK order ' . $restiaIdObj .
                ': id_obj=' . (string)$idObj .
                ' polozky=' . (string)$sync['polozky'] .
                ' mod=' . (string)$sync['modifikatory'] .
                ' kds=' . (string)$sync['kds_tagy'] .
                ' kuryr=' . (string)$sync['kuryr'] .
                ' sluzby=' . (string)$sync['sluzby']
            );
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

    $summary['stav'] = ($summary['pocet_chyb'] > 0) ? 'chyba' : 'ok';
    $summary['poznamka'] =
        'Import den hotov. id_pob=' . (string)$pob['id_pob'] .
        ' datum=' . $selectedDate .
        ' od=' . $odLocal .
        ' do=' . $doLocal .
        ' pages=' . (string)$summary['page_count'] .
        ' polozky=' . (string)$summary['pocet_polozek'];

    cb_restia_import_a_finish_import(
        $conn,
        $idImport,
        $summary['stav'],
        $summary['pocet_obj'],
        $summary['pocet_novych'],
        $summary['pocet_zmenenych'],
        $summary['pocet_chyb'],
        $summary['poznamka']
    );

    cb_restia_import_a_log('');
    cb_restia_import_a_log('SUMMARY:');
    cb_restia_import_a_log('  id_import: ' . (string)$summary['id_import']);
    cb_restia_import_a_log('  stav: ' . (string)$summary['stav']);
    cb_restia_import_a_log('  pocet_obj: ' . (string)$summary['pocet_obj']);
    cb_restia_import_a_log('  pocet_novych: ' . (string)$summary['pocet_novych']);
    cb_restia_import_a_log('  pocet_zmenenych: ' . (string)$summary['pocet_zmenenych']);
    cb_restia_import_a_log('  pocet_chyb: ' . (string)$summary['pocet_chyb']);
    cb_restia_import_a_log('  pages: ' . (string)$summary['page_count']);
    cb_restia_import_a_log('  polozky: ' . (string)$summary['pocet_polozek']);
    cb_restia_import_a_log('  modifikatory: ' . (string)$summary['pocet_modifikatoru']);
    cb_restia_import_a_log('  kds_tagy: ' . (string)$summary['pocet_kds_tagu']);
    cb_restia_import_a_log('  kuryri: ' . (string)$summary['pocet_kuryru']);
    cb_restia_import_a_log('  sluzby: ' . (string)$summary['pocet_sluzeb']);
    cb_restia_import_a_log('  poznamka: ' . (string)$summary['poznamka']);

    cb_restia_import_a_try_flush_api($conn, $auth);
    cb_restia_import_a_log('TXT: ' . cb_restia_import_a_txt_path());
    cb_restia_import_a_log('END: ' . date('Y-m-d H:i:s'));

    $_SESSION[$cbFlashKey] = [
        'rows_info' => $rowsInfo,
        'summary' => $summary,
        'pob' => $pob,
        'selected_id_pob' => $selectedIdPob,
        'selected_date' => $selectedDate,
        'od_local' => $odLocal,
        'do_local' => $doLocal,
    ];
    if (!headers_sent()) {
        $cbRedirectUrl = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($cbRedirectUrl === '') {
            $cbRedirectUrl = '/admin_testy/' . basename(__FILE__);
        }
        header('Location: ' . $cbRedirectUrl, true, 303);
        if ($cbRestiaEmbedMode) {
            return;
        }
        exit;
    }

    } catch (Throwable $e) {
    cb_restia_import_a_log('FATAL: ' . $e->getMessage());
    if ($conn instanceof mysqli) {
        try {
            db_zapis_log_chyby(
                $conn,
                null,
                'RESTIA',
                'IMPORT_DN',
                'FATAL_STEP',
                $e->getMessage(),
                'FATAL: ' . $e->getMessage(),
                __FILE__,
                __LINE__,
                null,
                null,
                0,
                'id_import=' . (string)($summary['id_import'] ?? 0)
            );
        } catch (Throwable $logErr) {
            cb_restia_import_a_log('WARN: log_chyby insert selhal: ' . $logErr->getMessage());
        }
    }

    try {
    } catch (Throwable $e2) {
        cb_restia_import_a_log('FATAL_FINISH_IMPORT: ' . $e2->getMessage());
    }

    cb_restia_import_a_try_flush_api($conn, $auth);
    cb_restia_import_a_log('END: ' . date('Y-m-d H:i:s'));

    if (!$cbRestiaEmbedMode) {
        http_response_code(500);
        ?>
        <!doctype html>
        <html lang="cs">
        <head>
          <meta charset="utf-8">
          <title>Restia import den - chyba</title>
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
}
?>
<?php if (!$cbRestiaEmbedMode): ?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Restia import den</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<?php endif; ?>
<div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Restia import den</h2>
  <p class="card_text text_tucny <?= ((string)$summary['stav'] === 'ok') ? 'txt_zelena' : 'txt_cervena' ?>">Stav: <?= cb_restia_import_a_h((string)$summary['stav']) ?></p>

  <table class="table ram_normal bg_bila radek_1_35 sirka100 odstup_horni_10">
    <tbody>
      <tr><td class="text_tucny">Kdy</td><td><?= cb_restia_import_a_h(cb_restia_import_a_format_datetime_cs_short(cb_restia_import_a_now())) ?></td></tr>
      <tr><td class="text_tucny">Pobocka</td><td><?= cb_restia_import_a_h((string)($pob['nazev'] ?? '')) ?></td></tr>
      <tr><td class="text_tucny">ID pob</td><td><?= cb_restia_import_a_h((string)$selectedIdPob) ?></td></tr>
      <tr><td class="text_tucny">Datum</td><td><?= cb_restia_import_a_h(cb_restia_import_a_format_date_cs($selectedDate)) ?></td></tr>
      <tr><td class="text_tucny">Od lokal</td><td><?= cb_restia_import_a_h(cb_restia_import_a_format_datetime_cs_short($odLocal)) ?></td></tr>
      <tr><td class="text_tucny">Do lokal</td><td><?= cb_restia_import_a_h(cb_restia_import_a_format_datetime_cs_short($doLocal)) ?></td></tr>
      <tr><td class="text_tucny">Od UTC</td><td><?= cb_restia_import_a_h(cb_restia_import_a_local_to_utc_z($odLocal)) ?></td></tr>
      <tr><td class="text_tucny">Do UTC</td><td><?= cb_restia_import_a_h(cb_restia_import_a_local_to_utc_z($doLocal)) ?></td></tr>
    </tbody>
  </table>

  <h3 class="text_24 txt_seda text_tucny odstup_horni_10 odstup_vnejsi_0">Shrnuti</h3>
  <table class="table ram_normal bg_bila radek_1_35 sirka100 odstup_horni_10">
    <tbody>
      <tr><th>ID import</th><td><?= cb_restia_import_a_h((string)$summary['id_import']) ?></td></tr>
      <tr><th>Pocet obj</th><td><?= cb_restia_import_a_h((string)$summary['pocet_obj']) ?></td></tr>
      <tr><th>Pocet novych</th><td><?= cb_restia_import_a_h((string)$summary['pocet_novych']) ?></td></tr>
      <tr><th>Pocet zmenenych</th><td><?= cb_restia_import_a_h((string)$summary['pocet_zmenenych']) ?></td></tr>
      <tr><th>Pocet chyb</th><td><?= cb_restia_import_a_h((string)$summary['pocet_chyb']) ?></td></tr>
      <tr><th>Pocet polozek</th><td><?= cb_restia_import_a_h((string)$summary['pocet_polozek']) ?></td></tr>
      <tr><th>Pocet modifikatoru</th><td><?= cb_restia_import_a_h((string)$summary['pocet_modifikatoru']) ?></td></tr>
      <tr><th>Pocet kds tagu</th><td><?= cb_restia_import_a_h((string)$summary['pocet_kds_tagu']) ?></td></tr>
      <tr><th>Pocet kuryru</th><td><?= cb_restia_import_a_h((string)$summary['pocet_kuryru']) ?></td></tr>
      <tr><th>Pocet sluzeb</th><td><?= cb_restia_import_a_h((string)$summary['pocet_sluzeb']) ?></td></tr>
    </tbody>
  </table>

  <h3 class="text_24 txt_seda text_tucny odstup_horni_10 odstup_vnejsi_0">Stranky</h3>
  <table class="table ram_normal bg_bila radek_1_35 sirka100 odstup_horni_10">
    <thead>
      <tr>
        <th>page</th>
        <th>http</th>
        <th>total_count</th>
        <th>pocet</th>
        <th>ms</th>
        <th>bytes_in</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rowsInfo as $row): ?>
        <tr>
          <td><?= cb_restia_import_a_h((string)$row['page']) ?></td>
          <td><?= cb_restia_import_a_h((string)$row['http']) ?></td>
          <td><?= cb_restia_import_a_h((string)$row['total']) ?></td>
          <td><?= cb_restia_import_a_h((string)$row['count']) ?></td>
          <td><?= cb_restia_import_a_h((string)$row['ms']) ?></td>
          <td><?= cb_restia_import_a_h((string)$row['bytes_in']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rowsInfo === []): ?>
        <tr><td colspan="6">Bez dat.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (!$cbRestiaEmbedMode): ?>
</body>
</html>
<?php endif; ?>
<?php
// admin_testy/01_restia_import_den.php * Verze: V8 * Aktualizace: 01.04.2026
// Pocet radku: 1718
// Predchozi pocet radku: 1682
// Konec souboru
?>
