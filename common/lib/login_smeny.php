<?php
// lib/login_smeny.php * Verze: V26 * Aktualizace: 09.09.2026
declare(strict_types=1);

/*
 * PRIHLASENI PRES SMENY NEBO LOKALNI HESLO + 2FA
 *
 * Ucel souboru:
 * - overit email a heslo proti lokalnimu uctu nebo API Smeny
 * - predat push endpoint aktualniho prohlizece lokalnimu login toku
 * - presmerovat na prime mobilni schvaleni, cekaci 2FA nebo cilovy modul
 *
 * Dulezite:
 * - bez platneho hesla se zarizeni nikdy nevyhodnocuje
 * - lokalni vyvoj preskakuje 2FA podle stavajiciho pravidla prostredi
 */
require_once __DIR__ . '/session_boot.php';

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/smeny_graphql.php';
require_once __DIR__ . '/user_bad_login.php';
require_once __DIR__ . '/prvni_vstup.php';

require_once __DIR__ . '/../notifikace/notifikace_2fa.php';

require_once __DIR__ . '/../db/db_api_smeny.php';
require_once __DIR__ . '/../db/db_user.php';

/* Vrati orezanou textovou hodnotu z prihlasovaciho formulare. */
function post_str(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

/* Vrati povoleny cilovy modul po uspesnem prihlaseni. */
function post_module(): string
{
    $module = strtolower(post_str('module'));
    if ($module === 'is') {
        return 'provoz';
    }
    if (!in_array($module, ['provoz', 'hr', 'smeny'], true)) {
        return 'provoz';
    }

    return $module;
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('Neplatný požadavek.');
    }

    $email = post_str('email');
    $heslo = post_str('heslo');
    $deviceEndpoint = post_str('device_endpoint');
    $_SESSION['cb_login_target_module'] = post_module();

    if ($email === '' || $heslo === '') {
        throw new RuntimeException('Vyplň email a heslo.');
    }


    if (cb_user_bad_login_is_blocked($email, 5, 15)) {
        throw new RuntimeException('Přihlášení se nezdařilo.');
    }

    $stmtLocal = db()->prepare('SELECT id_user, jmeno, prijmeni, email, telefon, aktivni, schvalen, heslo_hash FROM user WHERE email=? LIMIT 1');
    $stmtLocal->bind_param('s', $email);
    $stmtLocal->execute();
    $localUser = $stmtLocal->get_result()->fetch_assoc();
    $stmtLocal->close();
    if (is_array($localUser) && trim((string)($localUser['heslo_hash'] ?? '')) !== '') {
        if ((int)$localUser['aktivni'] !== 1 || !password_verify($heslo, (string)$localUser['heslo_hash'])) {
            cb_user_bad_login_log($email, $heslo);
            throw new RuntimeException('Neplatné přihlašovací údaje.');
        }
        $primePresmerovani = cb_lokalni_login_zahaj(db(), $localUser, $deviceEndpoint);
        header('Location: ' . ($primePresmerovani ?? cb_login_target_url()));
        exit;
    }

    try {
        $login = cb_smeny_graphql(
            CB_SMENY_GQL_URL,
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
        CB_SMENY_GQL_URL,
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

    $legacyUser = cb_prvni_vstup_user(db(), $idUser);
    if (!is_array($legacyUser) || strcasecmp((string)$legacyUser['email'], $email) !== 0 || trim((string)$legacyUser['heslo_hash']) !== '') {
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException('Neplatné přihlašovací údaje.');
    }
    cb_prvni_vstup_priprav($legacyUser);
    header('Location: ' . cb_login_url());
    exit;

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

    // LOCAL: 2FA se preskoci jen kdyz je vypnuto v set_system.on_2fa
    cb_login_load_settings_to_session($idUser);
    $on2fa = (int)cb_system_setting('on_2fa', 1);

    if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL' || $on2fa !== 1) {
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_auth_ok']);
        unset($_SESSION['cb_2fa_token']);

        cb_login_finalize_after_ok($token);
        header('Location: ' . cb_login_target_url());
        exit;
    }

    // SERVER: bez aktivniho zarizeni je to prvni login a pokracuje parovani
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


        header('Location: ' . cb_login_url());
        exit;
    }

    // 2FA: vytvor vyzvu a cekej na schvaleni
    $limitSec = 60;
    if (defined('CB_2FA_LIMIT_SEC')) {
        $limitSec = (int)CB_2FA_LIMIT_SEC;
        if ($limitSec <= 0) {
            $limitSec = 60;
        }
    }

    // Token ma 64 hex znaku, tedy 32 nahodnych bajtu
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

    // Do session se uklada jen identifikator aktualni 2FA vyzvy
    $_SESSION['cb_2fa_token'] = $token2fa;

    // login_ok pred schvalenim neexistuje
    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_auth_ok']);

    // Odeslani Web Push notifikace

    $sent = cb_push_send_2fa($idUser, $token2fa);


    $_SESSION['cb_flash'] = 'Čekám na schválení přihlášení na mobilu';


    header('Location: ' . cb_login_url());
    exit;

} catch (Throwable $e) {

    /*
         * Neuspesny login nebo chyba:
         * - zapiseme log volani Smen i bez id_user a id_login
         * - logovani nesmi zmenit presmerovani ani chovani loginu
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
    unset($_SESSION['prava']);
    unset($_SESSION['cb_2fa_token']);
    unset($_SESSION['cb_auth_ok']);
    unset($_SESSION['cb_system']);
    unset($_SESSION['cb_user_settings']);

    unset($_SESSION['cb_timeout_min']);
    unset($_SESSION['cb_session_start_ts']);
    unset($_SESSION['cb_last_activity_ts']);

    $_SESSION['cb_flash'] = $e->getMessage();


    header('Location: ' . cb_login_url());
    exit;
}

// lib/login_smeny.php * Verze: V25 * Aktualizace: 30.03.2026
// Pocet radku: 359
// Konec souboru
