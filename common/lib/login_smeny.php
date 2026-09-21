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

/* Vysvetli, ze o pristupu do IS rozhoduje aktivni osoba v HR. */
function cb_login_neaktivni_zprava(array $user): string
{
    return 'Vaše osoba je v HR neaktivní, proto se nemůžete přihlásit do IS. Obraťte se prosím na vedoucího.';
}

/* Vysvetli selhani jednorazoveho overeni prvniho vstupu ve Smenach. */
function cb_login_smeny_selhalo_zprava(?array $user): string
{
    if (is_array($user) && trim((string)($user['heslo_hash'] ?? '')) === '') {
        return 'První vstup se nepodařilo ověřit. Zkontrolujte heslo používané ve Směnách.';
    }
    return 'Neplatné přihlašovací údaje.';
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new CbUserVisibleException('Neplatný požadavek.');
    }

    $email = post_str('email');
    $heslo = post_str('heslo');
    $deviceEndpoint = post_str('device_endpoint');
    unset($_SESSION['cb_password_reset_email_prefill']);
    $_SESSION['cb_login_target_module'] = post_module();

    if ($email === '' || $heslo === '') {
        throw new CbUserVisibleException('Vyplň email a heslo.');
    }


    if (cb_user_bad_login_is_blocked($email, 5, 15)) {
        throw new CbUserVisibleException('Přihlášení se nezdařilo.');
    }

    $stmtLocal = db()->prepare('
        SELECT
            u.id_user,
            u.jmeno,
            u.prijmeni,
            u.email,
            u.telefon,
            p.aktivni,
            u.schvalen,
            u.heslo_hash
        FROM user u
        INNER JOIN hr_person p ON p.id_user = u.id_user
        WHERE u.email = ?
        LIMIT 1
    ');
    $stmtLocal->bind_param('s', $email);
    $stmtLocal->execute();
    $localUser = $stmtLocal->get_result()->fetch_assoc();
    $stmtLocal->close();
    if (!is_array($localUser)) {
        cb_user_bad_login_log($email, $heslo);
        throw new CbUserVisibleException('Neplatné přihlašovací údaje.');
    }
    if (is_array($localUser) && trim((string)($localUser['heslo_hash'] ?? '')) !== '') {
        if (!password_verify($heslo, (string)$localUser['heslo_hash'])) {
            cb_user_bad_login_log($email, $heslo);
            throw new CbUserVisibleException('Neplatné přihlašovací údaje.');
        }
        if ((int)$localUser['aktivni'] !== 1) {
            throw new CbUserVisibleException(cb_login_neaktivni_zprava($localUser));
        }
        $primePresmerovani = cb_lokalni_login_zahaj(db(), $localUser, $deviceEndpoint);
        header('Location: ' . ($primePresmerovani ?? cb_login_target_url()));
        exit;
    }

    if ((int)$localUser['aktivni'] !== 1) {
        throw new CbUserVisibleException(cb_login_neaktivni_zprava($localUser));
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
        throw new CbUserVisibleException(cb_login_smeny_selhalo_zprava($localUser));
    }

    $overeniToken = $login['userLogin']['accessToken'] ?? null;
    if (!is_string($overeniToken) || $overeniToken === '') {
        cb_user_bad_login_log($email, $heslo);
        throw new CbUserVisibleException(cb_login_smeny_selhalo_zprava($localUser));
    }
    cb_prvni_vstup_priprav($localUser);
    header('Location: ' . cb_login_url());
    exit;

} catch (Throwable $e) {

    $cbLoginFailedEmail = post_str('email');
    $cbLoginErrorMessage = cb_chyba_uzivatel($e, [
        'module' => 'LOGIN',
        'action' => 'Přihlášení',
        'actor' => filter_var($cbLoginFailedEmail, FILTER_VALIDATE_EMAIL) !== false ? $cbLoginFailedEmail : '',
        'table' => 'user',
    ]);

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

    $_SESSION['cb_flash'] = $cbLoginErrorMessage;
    if (filter_var($cbLoginFailedEmail, FILTER_VALIDATE_EMAIL) !== false) {
        $_SESSION['cb_password_reset_email_prefill'] = $cbLoginFailedEmail;
    }


    header('Location: ' . cb_login_url());
    exit;
}

// lib/login_smeny.php * Verze: V28 * Aktualizace: 12.09.2026
// Konec souboru
