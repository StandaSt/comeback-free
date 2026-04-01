<?php
// admin_testy/01_restia_import_den_a.php * Verze: V5 * Aktualizace: 02.04.2026
declare(strict_types=1);

/*
CO TENHLE SCRIPT DĚLÁ

- natvrdo stáhne 1 pobočku z Restie
- natvrdo bere období 31.3.2026 05:00 až 1.4.2026 05:00 (lokální čas Praha)
- lokální čas si sám převede na UTC pro Restii
- stáhne objednávky po stránkách přes cb_restia_get()
- uloží jen základ objednávek:
  - obj_import
  - objednavky_restia
  - obj_raw
- položky, ceny, časy, adresa, kurýr a služby zatím neukládá
- číselníky si podle dat z Restie doplní sám:
  - cis_obj_platforma
  - cis_obj_stav
  - cis_obj_platby
  - cis_doruceni
- log volání do api_restia flushne do DB
- po doběhu vypíše přehled

POZNÁMKA
- je to první ostrý test importu typu A
- cílem je ověřit, že data tečou do DB správně
- zdroj pravdy je Restia, nic nepřepočítáváme
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

const CB_RESTIA_IMPORT_A_ID_POB = 6;
const CB_RESTIA_IMPORT_A_OD_LOCAL = '2026-03-31 05:00:00';
const CB_RESTIA_IMPORT_A_DO_LOCAL = '2026-04-01 05:00:00';
const CB_RESTIA_IMPORT_A_LIMIT = 100;
const CB_RESTIA_IMPORT_A_VERZE = 'V5';

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

if (!function_exists('cb_restia_import_a_write_txt')) {
    function cb_restia_import_a_write_txt(array $lines): void
    {
        $txtPath = __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
        $payload = implode("\n", $lines) . "\n";
        @file_put_contents($txtPath, $payload);
    }
}

if (!function_exists('cb_restia_import_a_local_to_utc_z')) {
    function cb_restia_import_a_local_to_utc_z(string $local): string
    {
        $dt = new DateTimeImmutable($local, new DateTimeZone('Europe/Prague'));
        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}

if (!function_exists('cb_restia_import_a_get_auth')) {
    function cb_restia_import_a_get_auth(): array
    {
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = (int)(is_array($user) ? ($user['id_user'] ?? 0) : 0);
        $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);

        if ($idUser <= 0 || $idLogin <= 0) {
            throw new RuntimeException('Chybí přihlášený uživatel nebo id_login.');
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
            throw new RuntimeException('Pobočka nebyla nalezena.');
        }

        $activePosId = trim((string)($row['restia_activePosId'] ?? ''));
        if ($activePosId === '') {
            throw new RuntimeException('Pobočka nemá restia_activePosId.');
        }

        return [
            'id_pob' => (int)$row['id_pob'],
            'nazev' => (string)($row['nazev'] ?? ''),
            'active_pos_id' => $activePosId,
        ];
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

if (!function_exists('cb_restia_import_a_upsert_order')) {
    function cb_restia_import_a_upsert_order(mysqli $conn, int $idPob, string $activePosId, array $order): void
    {
        $restiaIdObj = trim((string)($order['id'] ?? ''));
        if ($restiaIdObj === '') {
            throw new RuntimeException('Objednávka nemá id.');
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
        $jeVyzvednuti = (int)((!empty($order['isPickup'])) ? 1 : 0);
        $jeVRestauraci = (int)((!empty($order['isInRestaurant'])) ? 1 : 0);
        $jeVlastniRozvoz = (int)((!empty($order['isSelfDelivery'])) ? 1 : 0);
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
        $rawJson = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($rawJson) || $rawJson === '') {
            throw new RuntimeException('Nepodařilo se převést objednávku na JSON.');
        }
        $rawHash = hash('sha256', $rawJson);

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
    }
}

if (!function_exists('cb_restia_import_a_insert_raw')) {
    function cb_restia_import_a_insert_raw(mysqli $conn, int $idImport, int $idPob, string $restiaIdObj, string $rawJson): void
    {
        $payloadHash = hash('sha256', $rawJson);

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

$rowsInfo = [];
$summary = [
    'id_import' => 0,
    'pocet_obj' => 0,
    'pocet_novych' => 0,
    'pocet_zmenenych' => 0,
    'pocet_chyb' => 0,
    'stav' => 'bezi',
    'poznamka' => '',
    'page_count' => 0,
];

$__cb_log = [];
$__cb_log[] = "START: " . date('Y-m-d H:i:s');
$__cb_log[] = "SCRIPT: " . basename(__FILE__);
$__cb_log[] = "VERSION: " . CB_RESTIA_IMPORT_A_VERZE;
$__cb_log[] = "ID_POB: " . (string)CB_RESTIA_IMPORT_A_ID_POB;
$__cb_log[] = "OD_LOCAL: " . (string)CB_RESTIA_IMPORT_A_OD_LOCAL;
$__cb_log[] = "DO_LOCAL: " . (string)CB_RESTIA_IMPORT_A_DO_LOCAL;

try {
    $auth = cb_restia_import_a_get_auth();
    $conn = db();
    $pob = cb_restia_import_a_get_pobocka($conn, (int)CB_RESTIA_IMPORT_A_ID_POB);

    $odUtc = cb_restia_import_a_local_to_utc_z((string)CB_RESTIA_IMPORT_A_OD_LOCAL);
    $doUtc = cb_restia_import_a_local_to_utc_z((string)CB_RESTIA_IMPORT_A_DO_LOCAL);

    $idImport = cb_restia_import_a_insert_import($conn, (int)$pob['id_pob'], $odUtc, $doUtc);
    $summary['id_import'] = $idImport;

    $page = 1;
    $limit = (int)CB_RESTIA_IMPORT_A_LIMIT;

    while (true) {
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
            'import A: id_pob=' . (string)$pob['id_pob'] . ' page=' . (string)$page
        );

        if ((int)($res['ok'] ?? 0) !== 1) {
            throw new RuntimeException((string)($res['chyba'] ?? 'Restia orders vrátila chybu.'));
        }

        $body = (string)($res['body'] ?? '');
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Restia nevrátila validní JSON.');
        }

        $orders = cb_restia_import_a_extract_orders($decoded);
        $countOrders = count($orders);

        $__cb_log[] = "PAGE " . $page . ": http=" . (int)($res['http_status'] ?? 0) . " total=" . (string)($res['total_count'] ?? '') . " count=" . $countOrders . " ms=" . (int)($res['ms'] ?? 0) . " bytes=" . (int)($res['bytes_in'] ?? 0);
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
                $__cb_log[] = 'ERROR order ?: chybi id';
                $summary['pocet_chyb']++;
                continue;
            }

            $rawJson = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($rawJson) || $rawJson === '') {
                $__cb_log[] = 'ERROR order ' . $restiaIdObj . ': nepodarilo se vytvorit JSON';
                $summary['pocet_chyb']++;
                continue;
            }

            $exists = cb_restia_import_a_order_exists($conn, $restiaIdObj);

            $conn->begin_transaction();
            try {
                cb_restia_import_a_upsert_order($conn, (int)$pob['id_pob'], (string)$pob['active_pos_id'], $order);
                cb_restia_import_a_insert_raw($conn, $idImport, (int)$pob['id_pob'], $restiaIdObj, $rawJson);
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                $__cb_log[] = "ERROR order " . $restiaIdObj . ": " . $e->getMessage();
                $summary['pocet_chyb']++;
                continue;
            }

            $__cb_log[] = 'OK order ' . $restiaIdObj . ': ulozeno';
            $summary['pocet_obj']++;
            if ($exists) {
                $summary['pocet_zmenenych']++;
            } else {
                $summary['pocet_novych']++;
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

    $summary['stav'] = ($summary['pocet_chyb'] > 0) ? 'chyba' : 'ok';
    $summary['poznamka'] =
        'Import A hotov. id_pob=' . (string)$pob['id_pob'] .
        ' od=' . (string)CB_RESTIA_IMPORT_A_OD_LOCAL .
        ' do=' . (string)CB_RESTIA_IMPORT_A_DO_LOCAL .
        ' pages=' . (string)$summary['page_count'];

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

    db_api_restia_flush($conn, (int)$auth['id_user'], (int)$auth['id_login']);

    $__cb_log[] = '';
    $__cb_log[] = 'SUMMARY:';
    $__cb_log[] = '  id_import: ' . (string)$summary['id_import'];
    $__cb_log[] = '  stav: ' . (string)$summary['stav'];
    $__cb_log[] = '  pocet_obj: ' . (string)$summary['pocet_obj'];
    $__cb_log[] = '  pocet_novych: ' . (string)$summary['pocet_novych'];
    $__cb_log[] = '  pocet_zmenenych: ' . (string)$summary['pocet_zmenenych'];
    $__cb_log[] = '  pocet_chyb: ' . (string)$summary['pocet_chyb'];
    $__cb_log[] = '  pages: ' . (string)$summary['page_count'];
    $__cb_log[] = '  poznamka: ' . (string)$summary['poznamka'];
    $__cb_log[] = '';
    $__cb_log[] = 'TXT:';
    $__txtPath = __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
    cb_restia_import_a_write_txt($__cb_log);
    $__cb_log[] = '  ulozeno: ' . $__txtPath;
    $__cb_log[] = 'END: ' . date('Y-m-d H:i:s');
    cb_restia_import_a_write_txt($__cb_log);

} catch (Throwable $e) {
    try {
        if (isset($conn) && $conn instanceof mysqli && (int)$summary['id_import'] > 0) {
            cb_restia_import_a_finish_import(
                $conn,
                (int)$summary['id_import'],
                'chyba',
                (int)$summary['pocet_obj'],
                (int)$summary['pocet_novych'],
                (int)$summary['pocet_zmenenych'],
                (int)$summary['pocet_chyb'] + 1,
                'STOP: ' . $e->getMessage()
            );

            if (isset($auth['id_user'], $auth['id_login'])) {
                db_api_restia_flush($conn, (int)$auth['id_user'], (int)$auth['id_login']);
            }
        }
    } catch (Throwable $e2) {
    }

    $__cb_log[] = 'FATAL: ' . $e->getMessage();
    $__cb_log[] = '';
    $__cb_log[] = 'SUMMARY:';
    $__cb_log[] = '  id_import: ' . (string)$summary['id_import'];
    $__cb_log[] = '  stav: chyba';
    $__cb_log[] = '  pocet_obj: ' . (string)$summary['pocet_obj'];
    $__cb_log[] = '  pocet_novych: ' . (string)$summary['pocet_novych'];
    $__cb_log[] = '  pocet_zmenenych: ' . (string)$summary['pocet_zmenenych'];
    $__cb_log[] = '  pocet_chyb: ' . (string)$summary['pocet_chyb'];
    $__cb_log[] = '  pages: ' . (string)$summary['page_count'];
    $__cb_log[] = '';
    $__cb_log[] = 'TXT:';
    $__txtPath = __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
    cb_restia_import_a_write_txt($__cb_log);
    $__cb_log[] = '  ulozeno: ' . $__txtPath;
    $__cb_log[] = 'END: ' . date('Y-m-d H:i:s');
    cb_restia_import_a_write_txt($__cb_log);

    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="cs">
    <head>
      <meta charset="utf-8">
      <title>Restia import A - chyba</title>
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
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Restia import A</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #1f2933; }
    .wrap { width: 100%; max-width: none; margin: 0; }
    .box { background: #fff; border: 1px solid #d9e2ec; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
    h1 { font-size: 24px; margin: 0 0 12px; }
    h2 { font-size: 18px; margin: 0 0 10px; }
    p { margin: 0 0 8px; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    .ok { color: #166534; font-weight: 700; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="box">
    <h1>Restia import A</h1>
    <p class="ok">Import doběhl.</p>
    <p>kdy: <?= cb_restia_import_a_h(cb_restia_import_a_now()) ?></p>
    <p>id_pob: <?= cb_restia_import_a_h((string)CB_RESTIA_IMPORT_A_ID_POB) ?></p>
    <p>od lokál: <?= cb_restia_import_a_h((string)CB_RESTIA_IMPORT_A_OD_LOCAL) ?></p>
    <p>do lokál: <?= cb_restia_import_a_h((string)CB_RESTIA_IMPORT_A_DO_LOCAL) ?></p>
    <p>od UTC: <?= cb_restia_import_a_h(cb_restia_import_a_local_to_utc_z((string)CB_RESTIA_IMPORT_A_OD_LOCAL)) ?></p>
    <p>do UTC: <?= cb_restia_import_a_h(cb_restia_import_a_local_to_utc_z((string)CB_RESTIA_IMPORT_A_DO_LOCAL)) ?></p>
  </div>

  <div class="box">
    <h2>Shrnutí</h2>
    <table>
      <tbody>
        <tr><th>id_import</th><td><?= cb_restia_import_a_h((string)$summary['id_import']) ?></td></tr>
        <tr><th>stav</th><td><?= cb_restia_import_a_h((string)$summary['stav']) ?></td></tr>
        <tr><th>pocet_obj</th><td><?= cb_restia_import_a_h((string)$summary['pocet_obj']) ?></td></tr>
        <tr><th>pocet_novych</th><td><?= cb_restia_import_a_h((string)$summary['pocet_novych']) ?></td></tr>
        <tr><th>pocet_zmenenych</th><td><?= cb_restia_import_a_h((string)$summary['pocet_zmenenych']) ?></td></tr>
        <tr><th>pocet_chyb</th><td><?= cb_restia_import_a_h((string)$summary['pocet_chyb']) ?></td></tr>
        <tr><th>poznamka</th><td><?= cb_restia_import_a_h((string)$summary['poznamka']) ?></td></tr>
      </tbody>
    </table>
  </div>

  <div class="box">
    <h2>Stránky</h2>
    <table>
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
</div>
</body>
</html>
<?php
// admin_testy/01_restia_import_den_a.php * Verze: V5 * Aktualizace: 02.04.2026
// Počet řádků: 787
// Předchozí počet řádků: 787
// Konec souboru
?>
