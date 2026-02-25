<?php
// lib/restia_orders_test.php * Verze: V3 * Aktualizace: 22.2.2026
declare(strict_types=1);

/*
 * RESTIA – TEST: ORDERS (ruční test)
 *
 * Účel:
 * - spustí se ručně při přihlášeném uživateli v IS
 * - vezme id_user z $_SESSION['cb_user'] a id_login z $_SESSION['cb_id_login']
 * - načte restia_activePosId z tabulky pobocka pro zadané id_pob
 * - zavolá GET /api/orders (page=1, limit dle konfigurace, createdFrom/createdTo v UTC Z)
 * - uloží průběh + výsledek do pomocne/restia_test.txt
 * - zapíše log do api_restia přes session buffer + db_api_restia_flush()
 *
 * Pozn.:
 * - neobnovuje token (musí být platný v restia_token)
 * - do TXT zapisuje průběh krok za krokem (krůčky), abychom viděli, kam to došlo
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../db/db_api_restia.php';
require_once __DIR__ . '/restia_client.php';

/*
 * ====== KONFIGURACE TESTU (jen tady se sahá) ======
 */
const CB_RESTIA_TEST_ID_POB = 6;
const CB_RESTIA_TEST_PAGE = 1;
const CB_RESTIA_TEST_LIMIT = 10;

// UTC interval ve formátu ISO Z (Restia chce Z = UTC)
const CB_RESTIA_TEST_CREATED_FROM = '2026-02-21T00:00:00Z';
const CB_RESTIA_TEST_CREATED_TO   = '2026-02-22T00:00:00Z';
/* ================================================ */

if (!function_exists('cb_restia_test_dt_utc')) {
    function cb_restia_test_dt_utc(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_restia_test_txt_path')) {
    function cb_restia_test_txt_path(): string
    {
        return __DIR__ . '/../pomocne/restia_test.txt';
    }
}

if (!function_exists('cb_restia_test_txt_reset')) {
    function cb_restia_test_txt_reset(): void
    {
        $p = cb_restia_test_txt_path();

        $head =
            "RESTIA TEST – START\n" .
            "kdy_utc: " . cb_restia_test_dt_utc() . "\n" .
            "soubor: lib/restia_orders_test.php\n" .
            "id_pob: " . (string)CB_RESTIA_TEST_ID_POB . "\n" .
            "createdFrom: " . (string)CB_RESTIA_TEST_CREATED_FROM . "\n" .
            "createdTo: " . (string)CB_RESTIA_TEST_CREATED_TO . "\n" .
            "page: " . (string)CB_RESTIA_TEST_PAGE . "  limit: " . (string)CB_RESTIA_TEST_LIMIT . "\n" .
            "----------------------------------------\n";

        // Přepíše (truncate) – ať je tam vždy čistý poslední běh.
        @file_put_contents($p, $head, LOCK_EX);
    }
}

if (!function_exists('cb_restia_test_txt_add')) {
    function cb_restia_test_txt_add(string $msg): void
    {
        $p = cb_restia_test_txt_path();

        $line =
            "[" . cb_restia_test_dt_utc() . " UTC] " . $msg . "\n";

        @file_put_contents($p, $line, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_test_txt_add_block')) {
    function cb_restia_test_txt_add_block(string $title, string $content): void
    {
        $p = cb_restia_test_txt_path();

        $block =
            "\n=== " . $title . " ===\n" .
            $content . "\n" .
            "=== KONEC " . $title . " ===\n\n";

        @file_put_contents($p, $block, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_test_get_logged_user')) {
    function cb_restia_test_get_logged_user(): array
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

if (!function_exists('cb_restia_test_get_active_pos_id')) {
    function cb_restia_test_get_active_pos_id(mysqli $conn, int $idPob): array
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

        // Pozn.: v projektu nechceme vymýšlet fallbacky. Používám get_result() stejně jako původní test.
        $res = $stmt->get_result();
        if ($res === false) {
            $stmt->close();
            return ['ok' => 0, 'chyba' => 'DB: nelze načíst pobocka (get_result).'];
        }

        $row = $res->fetch_assoc();
        $stmt->close();

        if (!is_array($row)) {
            return ['ok' => 0, 'chyba' => 'DB: pobocka nenalezena.'];
        }

        $pos = $row['restia_activePosId'] ?? null;
        $pos = ($pos === null || $pos === '') ? null : (string)$pos;

        if ($pos === null) {
            return ['ok' => 0, 'chyba' => 'DB: pobocka.restia_activePosId je prázdné.'];
        }

        return ['ok' => 1, 'active_pos_id' => $pos];
    }
}

/*
 * ======================
 * Spuštění testu
 * ======================
 */

cb_restia_test_txt_reset();
cb_restia_test_txt_add('krůček 1: start skriptu');

try {

    cb_restia_test_txt_add('krůček 2: kontrola přihlášení (session cb_user + cb_id_login)');
    $auth = cb_restia_test_get_logged_user();

    if ((int)($auth['ok'] ?? 0) !== 1) {
        cb_restia_test_txt_add('STOP: ' . (string)($auth['chyba'] ?? 'Neznámá chyba přihlášení.'));
        return;
    }

    $idUser = (int)$auth['id_user'];
    $idLogin = (int)$auth['id_login'];

    cb_restia_test_txt_add('krůček 3: přihlášen OK (id_user=' . (string)$idUser . ', id_login=' . (string)$idLogin . ')');

    cb_restia_test_txt_add('krůček 4: db()');
    $conn = db();
    cb_restia_test_txt_add('krůček 5: db() OK');

    cb_restia_test_txt_add('krůček 6: načtu restia_activePosId z pobocka (id_pob=' . (string)CB_RESTIA_TEST_ID_POB . ')');
    $pos = cb_restia_test_get_active_pos_id($conn, (int)CB_RESTIA_TEST_ID_POB);

    if ((int)($pos['ok'] ?? 0) !== 1) {
        $msg = (string)($pos['chyba'] ?? 'Neznámá chyba pobočky.');
        cb_restia_test_txt_add('CHYBA: ' . $msg);

        // zalogovat do api_restia přes buffer + flush
        cb_restia_buffer_add([
            'kdy_start' => cb_restia_dt6_utc_now(),
            'ms' => 0,
            'metoda' => 'GET',
            'endpoint' => '/api/orders',
            'url' => null,
            'active_pos_id' => null,
            'http_status' => null,
            'bytes_out' => 0,
            'bytes_in' => 0,
            'pocet_zaznamu' => null,
            'total_count' => null,
            'chyba' => $msg,
            'poznamka' => 'test: id_pob=' . (string)CB_RESTIA_TEST_ID_POB,
            'ok' => 0
        ]);

        cb_restia_test_txt_add('krůček 7: flush api_restia (chyba pobočky)');
        db_api_restia_flush($conn, $idUser, $idLogin);
        cb_restia_test_txt_add('krůček 8: konec (chyba pobočky)');
        return;
    }

    $activePosId = (string)$pos['active_pos_id'];
    cb_restia_test_txt_add('krůček 7: restia_activePosId OK: ' . $activePosId);

    $endpoint = '/api/orders';
    $query = [
        'page' => (int)CB_RESTIA_TEST_PAGE,
        'limit' => (int)CB_RESTIA_TEST_LIMIT,
        'createdFrom' => (string)CB_RESTIA_TEST_CREATED_FROM,
        'createdTo' => (string)CB_RESTIA_TEST_CREATED_TO,
        'activePosId' => $activePosId,
    ];

    $note =
        'test: id_pob=' . (string)CB_RESTIA_TEST_ID_POB .
        ' createdFrom=' . (string)CB_RESTIA_TEST_CREATED_FROM .
        ' createdTo=' . (string)CB_RESTIA_TEST_CREATED_TO .
        ' page=' . (string)CB_RESTIA_TEST_PAGE .
        ' limit=' . (string)CB_RESTIA_TEST_LIMIT;

    cb_restia_test_txt_add(
        'krůček 8: volám Restii (GET /api/orders, createdFrom=' .
        (string)CB_RESTIA_TEST_CREATED_FROM . ', createdTo=' .
        (string)CB_RESTIA_TEST_CREATED_TO . ', page=' .
        (string)CB_RESTIA_TEST_PAGE . ', limit=' .
        (string)CB_RESTIA_TEST_LIMIT . ')'
    );

    $res = cb_restia_get(
        $endpoint,
        $query,
        $activePosId,
        $note
    );
    cb_restia_test_txt_add('krůček 9: Restia volání hotovo');

    $http = (int)($res['http_status'] ?? 0);
    $total = $res['total_count'] ?? null;

    cb_restia_test_txt_add('krůček 10: http_status=' . (string)$http);
    if ($total !== null) {
        cb_restia_test_txt_add('krůček 11: X-Total-Count=' . (string)$total);
    } else {
        cb_restia_test_txt_add('krůček 11: X-Total-Count nepřišlo / nešlo přečíst');
    }

    $body = (string)($res['body'] ?? '');

    // Shrnutí: kolik objednávek v body (pokud je to JSON pole)
    $cnt = null;
    $firstInfo = null;

    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $cnt = count($decoded);

        if ($cnt > 0 && is_array($decoded[0])) {
            $firstId = isset($decoded[0]['id']) ? (string)$decoded[0]['id'] : '';
            $firstCreatedAt = isset($decoded[0]['createdAt']) ? (string)$decoded[0]['createdAt'] : '';
            $firstOrderNumber = isset($decoded[0]['orderNumber']) ? (string)$decoded[0]['orderNumber'] : '';

            $firstInfo =
                'první_obj: id=' . $firstId .
                ' orderNumber=' . $firstOrderNumber .
                ' createdAt=' . $firstCreatedAt;
        }
    }

    cb_restia_test_txt_add('krůček 12: uložím BODY (JSON) do souboru');
    cb_restia_test_txt_add_block('BODY', $body);

    cb_restia_test_txt_add('krůček 13: shrnutí');
    if ($cnt === null) {
        cb_restia_test_txt_add('shrnutí: body není JSON pole / nešlo dekódovat');
    } else {
        cb_restia_test_txt_add('shrnutí: pocet_v_body=' . (string)$cnt);
        if ($firstInfo !== null) {
            cb_restia_test_txt_add('shrnutí: ' . $firstInfo);
        }
    }

    cb_restia_test_txt_add('krůček 14: flush api_restia');
    db_api_restia_flush($conn, $idUser, $idLogin);
    cb_restia_test_txt_add('krůček 15: konec OK');

} catch (Throwable $e) {
    cb_restia_test_txt_add('CATCH: výjimka');
    cb_restia_test_txt_add('CATCH: ' . $e->getMessage());
    cb_restia_test_txt_add('CATCH: file=' . $e->getFile() . ' line=' . (string)$e->getLine());
    return;
}

// lib/restia_orders_test.php * Verze: V3 * Aktualizace: 22.2.2026
// Počet řádků: 318
// Konec souboru