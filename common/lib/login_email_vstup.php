<?php
declare(strict_types=1);

/*
 * Ucel souboru: Dokonci prihlaseni e-mailovym odkazem. GET pouze ulozi token
 * do session a odstrani jej z URL. Az POST s CRF tokenem ho jednorazove spotrebuje;
 * bezny nahled odkazu v poste tak prihlasovaci odkaz nezneplatni.
 */
require_once __DIR__ . '/session_boot.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/ochrana_crf.php';
require_once __DIR__ . '/login_email.php';
require_once __DIR__ . '/prvni_vstup.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$nonce = base64_encode(random_bytes(24));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-" . $nonce . "'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

try {
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET' && isset($_GET['t'])) {
        $token = is_string($_GET['t']) ? $_GET['t'] : '';
        if (!cb_login_email_format($token)) {
            throw new CbUserVisibleException('Přihlašovací odkaz je neplatný. Vyžádejte si nový.');
        }
        $_SESSION['cb_login_email_odkaz'] = $token;
        header('Location: ' . cb_root_url('common/lib/login_email_vstup.php'), true, 303);
        exit;
    }
    if ($method === 'POST') {
        if (!cb_crf_platny()) {
            throw new CbUserVisibleException('Platnost stránky vypršela. Otevřete přihlašovací odkaz znovu.');
        }
        $token = (string)($_SESSION['cb_login_email_odkaz'] ?? '');
        unset($_SESSION['cb_login_email_odkaz']);
        $vstup = cb_login_email_spotrebuj(db(), $token);
        if ($vstup === null) {
            throw new CbUserVisibleException('Přihlašovací odkaz vypršel, byl již použit nebo již neplatí. Vyžádejte si nový přes Přihlásit bez mobilu.');
        }
        // Vsechny cesty pouzivaji stejne prava, evidenci loginu a obnovu session ID.
        cb_session_forget_auth();
        unset($_SESSION['cb_prvni_vstup_user_id'], $_SESSION['cb_obnoveni_hesla_user_id']);
        $_SESSION['cb_login_target_module'] = $vstup['module'];
        cb_prvni_vstup_dokonci_login(db(), $vstup['user']);
        header('Location: ' . cb_login_target_url(), true, 303);
        exit;
    }
    if ($method !== 'GET' || empty($_SESSION['cb_login_email_odkaz'])) {
        throw new CbUserVisibleException('Chybí přihlašovací odkaz. Použijte odkaz ze svého e-mailu.');
    }
} catch (Throwable $e) {
    unset($_SESSION['cb_login_email_odkaz']);
    // Pri selhani dokonceni nesmi zustat castecnymi kroky pripravena identita.
    if (isset($vstup) && is_array($vstup)) {
        cb_session_forget_auth();
    }
    $_SESSION['cb_flash'] = cb_chyba_uzivatel($e, ['module' => 'LOGIN', 'action' => 'Přihlášení e-mailem']);
    header('Location: ' . cb_login_url(), true, 303);
    exit;
}
?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Přihlášení do IS Comeback</title></head>
<body><p>Přihlašuji do IS Comeback…</p>
<form method="post" action="<?= h(cb_root_url('common/lib/login_email_vstup.php')) ?>" id="emailLogin">
  <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
  <button type="submit">Vstoupit do IS Comeback</button>
</form>
<script nonce="<?= h($nonce) ?>">
/* Prohlizec dokonci vstup automaticky; bez JavaScriptu zustava funkcni tlacitko. */
document.getElementById('emailLogin').requestSubmit();
</script></body></html>
