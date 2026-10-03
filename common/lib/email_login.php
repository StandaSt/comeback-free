<?php
declare(strict_types=1);

/* Ucel souboru: Sestavi prihlasovaci e-mail bez mobilu a preda ho spolecnemu SMTP odesilaci. */
require_once __DIR__ . '/mailer.php';

/* Odesle pouze odkaz; heslo ani technicke udaje o uctu do zpravy nepatri. */
function cb_email_login_odeslat(string $email, string $odkaz): void
{
    $safeLink = htmlspecialchars($odkaz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $text = "Přihlášení do IS Comeback bez mobilu\n\n"
        . "Pro přihlášení otevřete tento odkaz:\n" . $odkaz . "\n\n"
        . "Odkaz platí 5 minut od vyžádání a lze ho použít jen jednou.\n"
        . "Pokud jste o přihlášení nežádali, odkaz neotevírejte a nikomu jej nepřeposílejte.\n\nIS Comeback";
    $html = '<!doctype html><html lang="cs"><head><meta charset="utf-8"></head>'
        . '<body style="font-family:Arial,sans-serif;color:#1e293b;line-height:1.5">'
        . '<h2>IS Comeback — přihlášení bez mobilu</h2><p>Pro přihlášení použijte následující odkaz.</p>'
        . '<p><a style="display:inline-block;padding:12px 22px;background:#e30613;color:white;border-radius:6px;text-decoration:none" href="'
        . $safeLink . '">Přihlásit do IS Comeback</a></p>'
        . '<p>Odkaz platí <strong>5 minut od vyžádání</strong> a lze ho použít jen jednou.</p>'
        . '<p>Pokud jste o přihlášení nežádali, odkaz neotevírejte a nikomu jej nepřeposílejte.</p></body></html>';
    cb_mail_send('hr', $email, 'Přihlášení do IS Comeback bez mobilu', $html, $text);
}
