<?php
// lib/login_smeny.php * Verze: V28 * Aktualizace: 12.09.2026
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

require_once __DIR__ . '/../db/db_api_smeny.php';

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

/* Vrátí uživateli konkrétní a pravdivé vysvětlení neaktivního lokálního účtu. */
function cb_login_neaktivni_zprava(array $user): string
{
    $duvod = (string)($user['duvod_neaktivni'] ?? '');
    if ($duvod === 'nenalezen_aktivni_ve_smenach') {
        return 'Váš účet je v IS neaktivní, protože při poslední synchronizaci nebyl ve Směnách nalezen mezi aktivními uživateli. Obraťte se prosím na vedoucího.';
    }
    if ($duvod === 'rucni_deaktivace') {
        return 'Váš účet v IS deaktivoval administrátor. Obraťte se prosím na vedoucího.';
    }
    return 'Váš účet v IS není aktivní. Obraťte se prosím na vedoucího.';
}

/* Vysvětlí selhání prvního vstupu účtu, který se ještě ověřuje ve Směnách. */
function cb_login_smeny_selhalo_zprava(?array $user): string
{
    if (is_array($user) && (int)($user['zdroj'] ?? 0) === 1 && trim((string)($user['heslo_hash'] ?? '')) === '') {
        return 'Účet je v IS aktivní, ale přihlášení přes Směny se nezdařilo. Zkontrolujte heslo a ověřte, že je váš účet ve Směnách veden jako aktivní.';
    }
    return 'Neplatné přihlašovací údaje.';
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('Neplatný požadavek.');
    }

    $email = post_str('email');
    $heslo = post_str('heslo');
    $deviceEndpoint = post_str('device_endpoint');
    unset($_SESSION['cb_password_reset_email_prefill']);
    $_SESSION['cb_login_target_module'] = post_module();

    if ($email === '' || $heslo === '') {
        throw new RuntimeException('Vyplň email a heslo.');
    }


    if (cb_user_bad_login_is_blocked($email, 5, 15)) {
        throw new RuntimeException('Přihlášení se nezdařilo.');
    }

    $stmtLocal = db()->prepare('SELECT id_user, jmeno, prijmeni, email, telefon, aktivni, duvod_neaktivni, schvalen, heslo_hash, zdroj FROM user WHERE email=? LIMIT 1');
    $stmtLocal->bind_param('s', $email);
    $stmtLocal->execute();
    $localUser = $stmtLocal->get_result()->fetch_assoc();
    $stmtLocal->close();
    if (is_array($localUser) && trim((string)($localUser['heslo_hash'] ?? '')) !== '') {
        if (!password_verify($heslo, (string)$localUser['heslo_hash'])) {
            cb_user_bad_login_log($email, $heslo);
            throw new RuntimeException('Neplatné přihlašovací údaje.');
        }
        if ((int)$localUser['aktivni'] !== 1) {
            throw new RuntimeException(cb_login_neaktivni_zprava($localUser));
        }
        $primePresmerovani = cb_lokalni_login_zahaj(db(), $localUser, $deviceEndpoint);
        header('Location: ' . ($primePresmerovani ?? cb_login_target_url()));
        exit;
    }

    if (is_array($localUser) && (int)$localUser['aktivni'] !== 1) {
        throw new RuntimeException(cb_login_neaktivni_zprava($localUser));
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
        throw new RuntimeException(cb_login_smeny_selhalo_zprava($localUser));
    }

    $token = $login['userLogin']['accessToken'] ?? null;
    if (!is_string($token) || $token === '') {
        cb_user_bad_login_log($email, $heslo);
        throw new RuntimeException(cb_login_smeny_selhalo_zprava($localUser));
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
    $cbLoginFailedEmail = post_str('email');
    if (filter_var($cbLoginFailedEmail, FILTER_VALIDATE_EMAIL) !== false) {
        $_SESSION['cb_password_reset_email_prefill'] = $cbLoginFailedEmail;
    }


    header('Location: ' . cb_login_url());
    exit;
}

// lib/login_smeny.php * Verze: V28 * Aktualizace: 12.09.2026
// Konec souboru
