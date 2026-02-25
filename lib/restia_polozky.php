<?php
// lib/restia_polozky.php * Verze: V5 * Aktualizace: 22.2.2026
declare(strict_types=1);

/*
 * RESTIA – MENU → POLOŽKY (test + zápis do DB)
 *
 * Požadavek V5:
 * - do TXT vypsat PŘESNĚ:
 *     - HTTP metodu + kompletní URL (včetně querystringu)
 *     - všechny request headery tak, jak se pošlou do cURL (žádné úpravy/formatování)
 *
 * Pozn.:
 * - nevytváří nové soubory (vyžaduje existující):
 *     - pomocne/restia_menu_test.txt
 *     - pomocne/restia_menu.json
 * - token se bere z DB tabulky restia_token (id_restia_token=1)
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../db/db_api_restia.php';

const CB_RESTIA_TEST_ID_POB = 6;
const CB_RESTIA_MENU_ID = '762f8daa-ca39-4d8f-ae4a-d22b4d106e88';
const CB_RESTIA_PREVIEW_LIMIT = 10;

if (!function_exists('cb_restia_polozky_dt_utc')) {
    function cb_restia_polozky_dt_utc(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_restia_dt6_utc_now')) {
    function cb_restia_dt6_utc_now(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s.u');
    }
}

if (!function_exists('cb_restia_polozky_path_txt')) {
    function cb_restia_polozky_path_txt(): string
    {
        return __DIR__ . '/../pomocne/restia_menu_test.txt';
    }
}

if (!function_exists('cb_restia_polozky_path_json')) {
    function cb_restia_polozky_path_json(): string
    {
        return __DIR__ . '/../pomocne/restia_menu.json';
    }
}

if (!function_exists('cb_restia_polozky_require_existing_files')) {
    function cb_restia_polozky_require_existing_files(): bool
    {
        $txt = cb_restia_polozky_path_txt();
        $json = cb_restia_polozky_path_json();

        if (!is_file($txt)) {
            return false;
        }
        if (!is_file($json)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('cb_restia_polozky_txt_add')) {
    function cb_restia_polozky_txt_add(string $msg): void
    {
        $p = cb_restia_polozky_path_txt();
        $line = '[' . cb_restia_polozky_dt_utc() . " UTC] " . $msg . "\n";
        @file_put_contents($p, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_polozky_txt_reset')) {
    function cb_restia_polozky_txt_reset(int $idPob, string $menuId, int $previewLimit): void
    {
        $p = cb_restia_polozky_path_txt();

        $head =
            "RESTIA MENU TEST – START\n" .
            "kdy_utc: " . cb_restia_polozky_dt_utc() . "\n" .
            "soubor: lib/restia_polozky.php\n" .
            "id_pob: " . (string)$idPob . "\n" .
            "menuId: " . $menuId . "\n" .
            "preview_limit: " . (string)$previewLimit . "\n" .
            "----------------------------------------\n";

        @file_put_contents($p, $head, LOCK_EX);
    }
}

if (!function_exists('cb_restia_polozky_get_logged_user')) {
    function cb_restia_polozky_get_logged_user(): array
    {
        $u = $_SESSION['cb_user'] ?? null;
        $idLogin = $_SESSION['cb_id_login'] ?? null;

        if (!is_array($u)) {
            return ['ok' => 0, 'chyba' => 'Nejste přihlášen (cb_user).'];
        }

        $idUser = $u['id_user'] ?? null;
        if ($idUser === null || $idUser === '') {
            return ['ok' => 0, 'chyba' => 'Nejste přihlášen (cb_user[id_user]).'];
        }

        if ($idLogin === null || $idLogin === '') {
            return ['ok' => 0, 'chyba' => 'Chybí id_login (cb_id_login).'];
        }

        return [
            'ok' => 1,
            'id_user' => (int)$idUser,
            'id_login' => (int)$idLogin
        ];
    }
}

if (!function_exists('cb_restia_polozky_get_active_pos_id')) {
    function cb_restia_polozky_get_active_pos_id(mysqli $conn, int $idPob): array
    {
        $sql = 'SELECT restia_activePosId
                FROM pobocka
                WHERE id_pob = ?
                LIMIT 1';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            return ['ok' => 0, 'chyba' => 'DB: prepare selhal (pobocka).'];
        }

        $stmt->bind_param('i', $idPob);
        $stmt->execute();

        $restiaPos = null;
        $stmt->bind_result($restiaPos);

        if (!$stmt->fetch()) {
            $stmt->close();
            return ['ok' => 0, 'chyba' => 'DB: pobocka nenalezena.'];
        }

        $stmt->close();

        $pos = ($restiaPos === null || (string)$restiaPos === '') ? null : (string)$restiaPos;
        if ($pos === null) {
            return ['ok' => 0, 'chyba' => 'DB: pobocka.restia_activePosId je prázdné.'];
        }

        return ['ok' => 1, 'active_pos_id' => $pos];
    }
}

if (!function_exists('cb_restia_db_get_access_token')) {
    function cb_restia_db_get_access_token(mysqli $conn): array
    {
        $sql = 'SELECT access_token, expires_at
                FROM restia_token
                WHERE id_restia_token = 1
                LIMIT 1';

        $res = $conn->query($sql);
        if ($res === false) {
            return ['ok' => 0, 'chyba' => 'DB: nelze načíst restia_token.'];
        }

        $row = $res->fetch_assoc();
        if (!is_array($row)) {
            return ['ok' => 0, 'chyba' => 'DB: restia_token nenalezen.'];
        }

        $token = (string)($row['access_token'] ?? '');
        $exp = (string)($row['expires_at'] ?? '');

        if ($token === '' || $exp === '') {
            return ['ok' => 0, 'chyba' => 'DB: restia_token je prázdný (access_token nebo expires_at).'];
        }

        return ['ok' => 1, 'access_token' => $token, 'expires_at' => $exp];
    }
}

/**
 * Přidá 1 řádek do session bufferu pro api_restia (flush to uvidí).
 */
if (!function_exists('cb_api_restia_buf_add')) {
    function cb_api_restia_buf_add(array $row): void
    {
        if (!isset($_SESSION['api_restia_buffer']) || !is_array($_SESSION['api_restia_buffer'])) {
            $_SESSION['api_restia_buffer'] = [];
        }
        $_SESSION['api_restia_buffer'][] = $row;
    }
}

if (!function_exists('cb_restia_build_url')) {
    function cb_restia_build_url(string $baseUrl, string $path, array $query): string
    {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $qs = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if ($qs !== '') {
            $url .= '?' . $qs;
        }
        return $url;
    }
}

/**
 * Zavolá Restii přes cURL.
 */
if (!function_exists('cb_restia_http_get_raw')) {
    function cb_restia_http_get_raw(string $url, array $headers, int $timeoutSec): array
    {
        $ch = curl_init($url);

        $t0 = microtime(true);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => false,
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $t1 = microtime(true);
        $ms = (int)round(($t1 - $t0) * 1000);

        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return [
                'ok' => 0,
                'chyba' => 'cURL chyba: ' . $err,
                'http_status' => $http,
                'ms' => $ms,
                'bytes_out' => 0,
                'bytes_in' => 0,
                'body' => ''
            ];
        }

        curl_close($ch);

        return [
            'ok' => 1,
            'chyba' => null,
            'http_status' => $http,
            'ms' => $ms,
            'bytes_out' => 0,
            'bytes_in' => strlen((string)$body),
            'body' => (string)$body
        ];
    }
}

if (!function_exists('cb_restia_menu_collect_items')) {
    function cb_restia_menu_collect_items(mixed $node, array &$out): void
    {
        if (!is_array($node)) {
            return;
        }

        $id = null;
        if (array_key_exists('posId', $node)) {
            $id = $node['posId'];
        } elseif (array_key_exists('id', $node)) {
            $id = $node['id'];
        }

        $name = null;
        if (array_key_exists('label', $node)) {
            $name = $node['label'];
        } elseif (array_key_exists('name', $node)) {
            $name = $node['name'];
        } elseif (array_key_exists('title', $node)) {
            $name = $node['title'];
        }

        $idStr = ($id === null) ? '' : trim((string)$id);
        $nameStr = ($name === null) ? '' : trim((string)$name);

        if ($idStr !== '' && $nameStr !== '') {
            $out[] = [
                'pos_id' => $idStr,
                'nazev' => $nameStr,
            ];
        }

        foreach ($node as $v) {
            cb_restia_menu_collect_items($v, $out);
        }
    }
}

if (!function_exists('cb_restia_menu_extract_items')) {
    function cb_restia_menu_extract_items(array $json): array
    {
        $items = [];
        cb_restia_menu_collect_items($json, $items);

        $dedup = [];
        foreach ($items as $it) {
            $k = (string)($it['pos_id'] ?? '');
            if ($k === '') {
                continue;
            }
            $dedup[$k] = $it;
        }

        return array_values($dedup);
    }
}

if (!function_exists('cb_restia_db_upsert_polozky')) {
    function cb_restia_db_upsert_polozky(mysqli $conn, array $items): int
    {
        if (count($items) === 0) {
            return 0;
        }

        $sql = '
            INSERT INTO cis_polozka (pos_id, pol_nazev, id_polozka_kat, aktivni)
            VALUES (?, ?, NULL, 1)
            ON DUPLICATE KEY UPDATE
                pol_nazev = VALUES(pol_nazev),
                aktivni = 1
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (cis_polozka).');
        }

        $cnt = 0;
        foreach ($items as $it) {
            $posId = trim((string)($it['pos_id'] ?? ''));
            $nazev = trim((string)($it['nazev'] ?? ''));

            if ($posId === '' || $nazev === '') {
                continue;
            }

            $stmt->bind_param('ss', $posId, $nazev);
            $stmt->execute();
            $cnt++;
        }

        $stmt->close();
        return $cnt;
    }
}

/*
 * ======================
 * Spuštění
 * ======================
 */

$idPob = CB_RESTIA_TEST_ID_POB;
$menuId = CB_RESTIA_MENU_ID;
$previewLimit = CB_RESTIA_PREVIEW_LIMIT;

if (!cb_restia_polozky_require_existing_files()) {
    return;
}

cb_restia_polozky_txt_reset($idPob, $menuId, $previewLimit);
cb_restia_polozky_txt_add('krůček 1: start');

try {

    cb_restia_polozky_txt_add('krůček 2: kontrola přihlášení (cb_user + cb_id_login)');
    $auth = cb_restia_polozky_get_logged_user();

    if ((int)($auth['ok'] ?? 0) !== 1) {
        cb_restia_polozky_txt_add('STOP: ' . (string)($auth['chyba'] ?? 'Neznámá chyba přihlášení.'));
        return;
    }

    $idUser = (int)$auth['id_user'];
    $idLogin = (int)$auth['id_login'];

    cb_restia_polozky_txt_add('krůček 3: přihlášen OK (id_user=' . (string)$idUser . ', id_login=' . (string)$idLogin . ')');

    cb_restia_polozky_txt_add('krůček 4: db()');
    $conn = db();
    cb_restia_polozky_txt_add('krůček 5: db() OK');

    cb_restia_polozky_txt_add('krůček 6: načtu restia_activePosId z pobocka (id_pob=' . (string)$idPob . ')');
    $pos = cb_restia_polozky_get_active_pos_id($conn, $idPob);

    if ((int)($pos['ok'] ?? 0) !== 1) {
        $msg = (string)($pos['chyba'] ?? 'Neznámá chyba pobočky.');
        cb_restia_polozky_txt_add('CHYBA: ' . $msg);

        cb_api_restia_buf_add([
            'kdy_start' => cb_restia_dt6_utc_now(),
            'ms' => 0,
            'metoda' => 'GET',
            'endpoint' => '/api/menu/' . $menuId,
            'url' => null,
            'active_pos_id' => null,
            'http_status' => null,
            'bytes_out' => 0,
            'bytes_in' => 0,
            'pocet_zaznamu' => null,
            'total_count' => null,
            'chyba' => $msg,
            'poznamka' => 'menu: id_pob=' . (string)$idPob,
            'ok' => 0
        ]);

        db_api_restia_flush($conn, $idUser, $idLogin);
        return;
    }

    $activePosId = (string)$pos['active_pos_id'];
    cb_restia_polozky_txt_add('krůček 7: restia_activePosId OK: ' . $activePosId);

    $tok = cb_restia_db_get_access_token($conn);
    if ((int)($tok['ok'] ?? 0) !== 1) {
        $msg = (string)($tok['chyba'] ?? 'Neznámá chyba tokenu.');
        cb_restia_polozky_txt_add('CHYBA: ' . $msg);
        return;
    }

    $accessToken = (string)$tok['access_token'];

    $baseUrl = 'https://apilite.restia.cz';
    $path = '/api/menu/' . $menuId;

    $query = [
        'activePosId' => $activePosId,
    ];

    $url = cb_restia_build_url($baseUrl, $path, $query);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
    ];

    cb_restia_polozky_txt_add('--- REQUEST (přesně jak se posílá) ---');
    cb_restia_polozky_txt_add('HTTP GET ' . $url);
    cb_restia_polozky_txt_add('Headers:');
    foreach ($headers as $h) {
        cb_restia_polozky_txt_add($h);
    }
    cb_restia_polozky_txt_add('--- KONEC REQUESTU ---');

    cb_restia_polozky_txt_add('krůček 8: volám Restii');
    $res = cb_restia_http_get_raw($url, $headers, 20);
    cb_restia_polozky_txt_add('krůček 9: Restia volání hotovo');

    $http = (int)($res['http_status'] ?? 0);
    cb_restia_polozky_txt_add('krůček 10: http_status=' . (string)$http);

    $body = (string)($res['body'] ?? '');

    @file_put_contents(cb_restia_polozky_path_json(), $body, LOCK_EX);
    cb_restia_polozky_txt_add('krůček 11: JSON uložen do pomocne/restia_menu.json');

    $json = json_decode($body, true);
    if (!is_array($json)) {
        cb_restia_polozky_txt_add('CHYBA: Neplatný JSON (json_decode).');
    }

    $items = is_array($json) ? cb_restia_menu_extract_items($json) : [];
    cb_restia_polozky_txt_add('krůček 12: nalezeno položek=' . (string)count($items));

    if (count($items) > 0) {
        $n = 0;
        foreach ($items as $it) {
            $n++;
            if ($n > $previewLimit) {
                break;
            }
            cb_restia_polozky_txt_add('preview ' . (string)$n . ': pos_id=' . (string)$it['pos_id'] . ' | nazev=' . (string)$it['nazev']);
        }
    } else {
        cb_restia_polozky_txt_add('krůček 13: preview prázdný (menu bez id+name / jiný formát)');
    }

    $inserted = is_array($json) ? cb_restia_db_upsert_polozky($conn, $items) : 0;
    cb_restia_polozky_txt_add('krůček 13b: zapsáno do DB (cis_polozka) řádků=' . (string)$inserted);

    cb_api_restia_buf_add([
        'kdy_start' => cb_restia_dt6_utc_now(),
        'ms' => (int)($res['ms'] ?? 0),
        'metoda' => 'GET',
        'endpoint' => $path,
        'url' => $url,
        'active_pos_id' => $activePosId,
        'http_status' => $http,
        'bytes_out' => (int)($res['bytes_out'] ?? 0),
        'bytes_in' => (int)($res['bytes_in'] ?? 0),
        'pocet_zaznamu' => (int)count($items),
        'total_count' => null,
        'chyba' => ((int)($res['ok'] ?? 0) === 1) ? null : (string)($res['chyba'] ?? 'Neznámá chyba'),
        'poznamka' => 'menu: id_pob=' . (string)$idPob . ', menuId=' . $menuId . ', db_rows=' . (string)$inserted,
        'ok' => (int)($res['ok'] ?? 0)
    ]);

    cb_restia_polozky_txt_add('krůček 14: flush api_restia');
    db_api_restia_flush($conn, $idUser, $idLogin);

    cb_restia_polozky_txt_add('konec OK');

} catch (Throwable $e) {
    cb_restia_polozky_txt_add('CATCH: výjimka');
    cb_restia_polozky_txt_add('CATCH: ' . $e->getMessage());
    cb_restia_polozky_txt_add('CATCH: file=' . $e->getFile() . ' line=' . (string)$e->getLine());
    return;
}

// lib/restia_polozky.php * Verze: V5 * Aktualizace: 22.2.2026
// Počet řádků: 533
// Konec souboru