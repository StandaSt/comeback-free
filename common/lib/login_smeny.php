<?php
// lib/login_smeny.php * Verze: V28 * Aktualizace: 12.09.2026
declare(strict_types=1);
// Udaje a zobrazovane jmeno osoby vcetne titulu sdili centralni cteci zdroj.
require_once __DIR__ . '/osoba_jmeno.php';

/*
 * PRIHLASENI UCTEM IS + 2FA
 *
 * Ucel souboru:
 * - overit evidenci e-mailu a heslo vyhradne proti uctu IS
 * - ucet bez hesla navest na jeho nastaveni e-mailem bez pocitani chybnych pokusu
 * - predat push endpoint aktualniho prohlizece lokalnimu login toku
 * - presmerovat na prime mobilni schvaleni, cekaci 2FA nebo cilovy modul
 * - na vyzadani odeslat po overeni lokalniho hesla prihlasovaci e-mail
 * - při chybném hesle sdělit zbývající pokusy a při blokaci skutečný čas jejího konce
 * - po platnem hesle nabidnout PIN podle nastaveni uctu; nouzovy e-mail zustava samostatnou cestou
 *
 * Dulezite:
 * - bez platneho hesla se zarizeni nikdy nevyhodnocuje
 * - lokalni vyvoj preskakuje 2FA podle stavajiciho pravidla prostredi
 */
require_once __DIR__ . '/session_boot.php';

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/user_bad_login.php';
require_once __DIR__ . '/prvni_vstup.php';
require_once __DIR__ . '/ochrana_crf.php';

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
    return 'Váš účet má zablokovaný přístup do IS, protože je vaše osoba v systému vedena jako neaktivní. Obraťte se na vedoucího nebo správce IS.';
}

try {

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new CbUserVisibleException('Neplatný požadavek.');
    }

    // Nova prihlasovaci zadost nesmi ponechat vyzvu PIN po predchozim overeni jineho hesla.
    unset($_SESSION['cb_pin_ceka'], $_SESSION['cb_pin_zprava'], $_SESSION['cb_login_nastavit_heslo']);

    $email = post_str('email');
    $heslo = post_str('heslo');
    $deviceEndpoint = post_str('device_endpoint');
    $emailLogin = post_str('login_metoda') === 'email' || post_str('login_volba') === 'email';
    if ($emailLogin && !cb_crf_platny()) {
        throw new CbUserVisibleException('Platnost formuláře vypršela. Obnovte stránku a přihlášení zopakujte.');
    }
    unset($_SESSION['cb_password_reset_email_prefill']);
    $_SESSION['cb_login_target_module'] = post_module();

    if ($email === '' || $heslo === '') {
        throw new CbUserVisibleException('Vyplň email a heslo.');
    }


    // LEFT JOIN zachova existujici ucet i bez pouzitelne HR identity; takovy ucet nesmi projit loginem.
    $stmtLocal = db()->prepare('
        SELECT
            u.id_user,
            osobni.jmeno,
            osobni.prijmeni,osobni.druhe_jmeno,osobni.titul_za,
            u.email,
            (SELECT t.telefon FROM hr_telefon t WHERE t.id_person = p.id_person AND t.platny = 1 AND t.hlavni = 1 ORDER BY t.id_telefon DESC LIMIT 1) AS telefon,
            p.aktivni,
            p.id_person,
            osobni.id_osobni_udaje,
            u.heslo_hash
        FROM user u
        LEFT JOIN hr_person p ON p.id_person = u.id_user AND p.id_user = u.id_user
        LEFT JOIN ' . cb_osoby_zdroj_sql() . ' osobni ON osobni.id_person = p.id_person AND osobni.platny = 1
        WHERE u.email = ?
        LIMIT 1
    ');
    $stmtLocal->bind_param('s', $email);
    $stmtLocal->execute();
    $localUser = $stmtLocal->get_result()->fetch_assoc();
    $stmtLocal->close();
    if (!is_array($localUser)) {
        cb_user_bad_login_log($email, $heslo);
        throw new CbUserVisibleException('Tento e-mail není v systému evidován. Zkontrolujte, zda je napsaný správně. Pokud ano, obraťte se na svého nadřízeného kvůli založení účtu.');
    }
    // Chybejici heslo neni chybny pokus. Ani starsi blokace nesmi skryt cestu k jeho nastaveni.
    if (trim((string)($localUser['heslo_hash'] ?? '')) === '') {
        if ((int)($localUser['aktivni'] ?? 0) !== 1 || empty($localUser['id_person']) || empty($localUser['id_osobni_udaje'])) {
            throw new CbUserVisibleException(cb_login_neaktivni_zprava($localUser));
        }
        $_SESSION['cb_login_nastavit_heslo'] = 1;
        throw new CbUserVisibleException('Pro tento účet ještě není nastavené heslo do IS. Klikněte na „Nastavit nové heslo“. Na váš evidovaný e-mail vám pošleme odkaz, přes který si heslo vytvoříte.');
    }

    // Limit se vztahuje na overovani existujiciho hesla, nikoli na pripravu prvniho vstupu.
    $blokaceZprava = cb_user_bad_login_zprava($email, '', true);
    if ($blokaceZprava !== '') {
        throw new CbUserVisibleException($blokaceZprava);
    }
    if (!password_verify($heslo, (string)$localUser['heslo_hash'])) {
        cb_user_bad_login_log($email, $heslo);
        throw new CbUserVisibleException(cb_user_bad_login_zprava($email, 'Heslo není správné.'));
    }
    if ((int)($localUser['aktivni'] ?? 0) !== 1 || empty($localUser['id_person']) || empty($localUser['id_osobni_udaje'])) {
        throw new CbUserVisibleException(cb_login_neaktivni_zprava($localUser));
    }
    if ($emailLogin) {
        require_once __DIR__ . '/login_email.php';
        cb_login_email_odeslat(db(), $localUser, post_module());
        cb_session_forget_auth();
        unset($_SESSION['cb_local_login_user_id'], $_SESSION['cb_prvni_vstup_user_id'], $_SESSION['cb_obnoveni_hesla_user_id']);
        // Uspech zobrazi samostatny informacni modal, nikoli stav pod prihlasovacim formularem.
        unset($_SESSION['cb_flash']);
        $_SESSION['cb_login_email_odeslan'] = 1;
        header('Location: ' . cb_login_url(), true, 303);
        exit;
    }
    $primePresmerovani = cb_lokalni_login_zahaj(db(), $localUser, $deviceEndpoint);
    header('Location: ' . ($primePresmerovani ?? cb_login_target_url()));
    exit;

} catch (Throwable $e) {

    $cbLoginFailedEmail = post_str('email');
    $cbLoginErrorMessage = cb_chyba_uzivatel($e, [
        'module' => 'LOGIN',
        'action' => 'Přihlášení',
        'actor' => filter_var($cbLoginFailedEmail, FILTER_VALIDATE_EMAIL) !== false ? $cbLoginFailedEmail : '',
        'table' => 'user',
    ]);

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
