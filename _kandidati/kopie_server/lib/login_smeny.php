<?php
// lib/login_smeny.php * Verze: V25 * Aktualizace: 31.03.2026
declare(strict_types=1);

/*
 * PŘIHLÁŠENÍ PŘES SMĚNY (GraphQL API) + 2FA (schválení na mobilu)
 *
 * Co to dělá:
 * - ověří email/heslo přes Směny (GraphQL)
 * - načte jen základní profil pro modál registrace / 2FA
 * - uloží minimální data do session (bez login_ok)
 * - při prvním loginu bez aktivního zařízení přeskočí 2FA a pustí uživatele do párování mobilu
 * - při dalším loginu připraví 2FA výzvu do DB (push_login_2fa) a odešle notifikaci na spárované zařízení (push_zarizeni)
 * - redirect na úvod (index.php zobrazí čekací modál nebo modál párování)
 *
 * Důležité:
 * - login_ok se nastaví AŽ po schválení 2FA (mobil), nebo hned při LOCAL / prvním loginu bez zařízení
 * - LOCAL: 2FA lze vypnout přes set_system.on_2fa (notifikace z LOCAL nechodí)
 */
require_once __DIR__ . '/session_boot.php';

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/smeny_graphql.php';
require_once __DIR__ . '/user_bad_login.php';

require_once __DIR__ . '/../notifikace/notifikace_2fa.php';

require_once __DIR__ . '/../db/db_api_smeny.php';
require_once __DIR__ . '/../db/db_user.php';

$GQL_URL = 'https://smeny.pizzacomeback.cz/graphql';

/*
 * Timeout neaktivity (minuty) – JEDINÝ zdroj hodnoty.
 */

function post_str(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('Neplatný požadavek.');
    }

    $email = post_str('email');
    $heslo = post_str('heslo');

    if ($email === '' || $heslo === '') {
        throw new RuntimeException('Vyplň email a heslo.');
    }


    if (cb_user_bad_login_is_blocked($email, 5, 15)) {
        throw new RuntimeException('Přihlášení se nezdařilo.');
    }

    try {
        $login = cb_smeny_graphql(
            $GQL_URL,
            'query($email:String!,$password:String!){
                userLogin(email:$email,password:$password){
                    accessToken
                }
            }',
            ['email' => $email, 'password' => $heslo]
        );
    } catch (Throwable $e) {
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('Neplatné přihlašovací údaje.');
    }

    $token = $login['userLogin']['accessToken'] ?? null;
    if (!is_string($token) || $token === '') {
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('Neplatné přihlašovací údaje.');
    }



    $me = cb_smeny_graphql(
        $GQL_URL,
        'query{
            userGetLogged{
                id
                name
                surname
                email
                phoneNumber
                active
                approved
            }
        }',
        [],
        $token
    );

    $u = $me['userGetLogged'] ?? null;
    if (!is_array($u) || empty($u['id']) || empty($u['email'])) {
        throw new RuntimeException('Nepodařilo se načíst profil uživatele.');
    }

    $idUser = (int)$u['id'];

    cb_db_ensure_user_set(db(), $idUser);

    $_SESSION['cb_token'] = $token;

    $_SESSION['cb_user'] = [
        'id_user'   => $idUser,
        'name'      => (string)($u['name'] ?? ''),
        'surname'   => (string)($u['surname'] ?? ''),
        'email'     => (string)($u['email'] ?? ''),
        'telefon'   => (string)($u['phoneNumber'] ?? ''),
        'active'    => (bool)($u['active'] ?? false),
        'approved'  => (bool)($u['approved'] ?? false),
        'roles'     => [],
        'sloty'     => [],
    ];

    $_SESSION['cb_auth_ok'] = 1;

    // LOCAL: 2FA se přeskočí jen když je vypnuto v set_system.on_2fa
    cb_login_load_settings_to_session($idUser);
    $on2fa = (int)cb_system_setting('on_2fa', 1);

    if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL' || $on2fa !== 1) {
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_auth_ok']);
        unset($_SESSION['cb_2fa_token']);

        cb_login_finalize_after_ok($token);
        $_SESSION['cb_initial_loader_text'] = 'Inicializace systému ...';

        header('Location: ' . cb_url(''));
        exit;
    }

    // SERVER: bez aktivního zařízení je to první login => přeskoč 2FA a pusť párování
    $maAktivniZarizeni = false;

    $stmtDevice = db()->prepare('
        SELECT id
        FROM push_zarizeni
        WHERE id_user=? AND aktivni=1
        LIMIT 1
    ');

    if ($stmtDevice) {
        $stmtDevice->bind_param('i', $idUser);
        $stmtDevice->execute();
        $stmtDevice->store_result();
        $maAktivniZarizeni = ($stmtDevice->num_rows > 0);
        $stmtDevice->close();
    }

    if (!$maAktivniZarizeni) {
        unset($_SESSION['login_ok']);
        unset($_SESSION['cb_2fa_token']);


        header('Location: ' . cb_url(''));
        exit;
    }

    // ====== 2FA: vytvoř výzvu a čekej na schválení ======
    $limitSec = 300;
    if (defined('CB_2FA_LIMIT_SEC')) {
        $limitSec = (int)CB_2FA_LIMIT_SEC;
        if ($limitSec <= 0) {
            $limitSec = 300;
        }
    }

    // token je 64 hex znaků (32 bytes)
    $token2fa = bin2hex(random_bytes(32));

    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '') {
        $ip = 'UNKNOWN';
    }

    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ua = trim($ua);
    if ($ua === '') {
        $ua = null;
    }

    $conn = db();
    $stmt = $conn->prepare('
        INSERT INTO push_login_2fa
        (id_user, token, stav, ip, prohlizec, vytvoreno, vyprsi, rozhodnuto, id_zarizeni)
        VALUES
        (?, ?, \'ceka\', ?, ?, NOW(), (NOW() + INTERVAL ? SECOND), NULL, NULL)
    ');
    if (!$stmt) {
        throw new RuntimeException('2FA: DB prepare selhal.');
    }

    $stmt->bind_param('isssi', $idUser, $token2fa, $ip, $ua, $limitSec);
    $stmt->execute();
    $stmt->close();

    // Ulož do session jen identifikátor aktuální 2FA výzvy
    $_SESSION['cb_2fa_token'] = $token2fa;

    // login_ok zatím NEEXISTUJE
    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_auth_ok']);

    // ====== Odeslání Web Push notifikace ======

    $sent = cb_push_send_2fa($idUser, $token2fa);


    $_SESSION['cb_flash'] = 'Čekám na schválení přihlášení na mobilu';


    header('Location: ' . cb_url(''));
    exit;

} catch (Throwable $e) {

    /*
     * Neúspěšný login / chyba:
     * - zapíšeme log volání Směn i bez id_user a id_login (NULL)
     * - nic z toho nesmí shodit redirect ani chování loginu
     */
    try {
        db_api_smeny_flush(db(), null, null);
    } catch (Throwable $eLog) {
        error_log('api_smeny flush (fail) selhal: ' . $eLog->getMessage());
    }


    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_user']);
    unset($_SESSION['cb_token']);
    unset($_SESSION['cb_user_profile']);
    unset($_SESSION['cb_user_branches']);
    unset($_SESSION['cb_2fa_token']);
    unset($_SESSION['cb_auth_ok']);
    unset($_SESSION['cb_system']);
    unset($_SESSION['cb_user_settings']);

    unset($_SESSION['cb_timeout_min']);
    unset($_SESSION['cb_session_start_ts']);
    unset($_SESSION['cb_last_activity_ts']);

    $_SESSION['cb_flash'] = $e->getMessage();


    header('Location: ' . cb_url(''));
    exit;
}

// lib/login_smeny.php * Verze: V25 * Aktualizace: 30.03.2026
// Počet řádků: 359
// Konec souboru
