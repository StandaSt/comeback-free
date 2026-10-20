<?php
declare(strict_types=1);

/*
 * Účel souboru: Sestaví a odešle oznámení o čekající změně přihlašovacího e-mailu.
 * Token ani databázové změny sem nepatří.
 * Potvrzovací odkaz dostává pouze nová adresa, původní adresa dostává oznámení.
 */

require_once __DIR__ . '/mailer.php';

// Novou adresu je nutné ověřit přímo v její schránce; odkaz se neposílá na původní adresu.
function cb_email_zmena_oznameni_odeslat(string $staryEmail, string $novyEmail, string $odkaz): void
{
    $safeStaryEmail = htmlspecialchars($staryEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeNovyEmail = htmlspecialchars($novyEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeOdkaz = htmlspecialchars($odkaz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $body = '<!doctype html><html lang="cs"><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#1e293b;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="620" cellspacing="0" cellpadding="0" style="width:100%;max-width:620px;background:#ffffff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="padding:22px 30px;background:#e30613;color:#ffffff;font-size:22px;font-weight:bold;">IS Comeback</td></tr>'
        . '<tr><td style="padding:30px;font-size:16px;line-height:1.55;">'
        . '<p style="margin:0 0 22px;">Byla vyžádána změna přihlašovacího e-mailu IS Comeback z <strong>' . $safeStaryEmail . '</strong> na <strong>' . $safeNovyEmail . '</strong>.</p>'
        . '<p style="margin:0 0 22px;">Použitím níže uvedeného odkazu berete změnu na vědomí a váš přihlašovací e-mail bude změněn na <strong>' . $safeNovyEmail . '</strong>.</p>'
        . '<p style="margin:0 0 22px;text-align:center;"><a href="' . $safeOdkaz . '" style="display:inline-block;padding:13px 24px;background:#e30613;border-radius:6px;color:#ffffff;font-weight:bold;text-decoration:none;">Potvrdit změnu</a></p>'
        . '<p style="margin:0 0 22px;color:#475569;font-size:14px;">Odkaz platí 3 dny. Do potvrzení se nadále přihlašujte původním e-mailem.</p>'
        . '<p style="margin:0;">admin IS Comeback</p></td></tr></table></td></tr></table></body></html>';
    $altBody = "Byla vyžádána změna přihlašovacího e-mailu IS Comeback z {$staryEmail} na {$novyEmail}.\n\nPotvrdit změnu: {$odkaz}\n\nOdkaz platí 3 dny. Do potvrzení se nadále přihlašujte původním e-mailem. Kontaktní e-mail v HR se nemění.\n\nadmin IS Comeback";

    // Název transportu HR označuje existující SMTP konfiguraci, nikoli personální kontakt.
    try {
        cb_mail_send('hr', $novyEmail, 'Potvrzení změny e-mailu v IS Comeback', $body, $altBody);
    } catch (Throwable $e) {
        throw new RuntimeException('Potvrzení se nepodařilo odeslat na nový e-mail. Změnu zkuste znovu.');
    }
    $notice = 'Byla vyžádána změna přihlašovacího e-mailu IS Comeback z ' . $staryEmail . ' na ' . $novyEmail . '. Do potvrzení odkazu v nové schránce zůstává původní e-mail platný. Kontaktní e-mail v HR se nemění. Pokud jste změnu nežádali, kontaktujte správce IS.';
    try {
        cb_mail_send('hr', $staryEmail, 'Žádost o změnu přihlašovacího e-mailu IS Comeback', '<p>' . nl2br(htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>', $notice);
    } catch (Throwable $e) {
        // Selhání informativní kopie nesmí zneplatnit již odeslaný potvrzovací odkaz.
        error_log('Změna e-mailu IS: nepodařilo se odeslat informaci na původní adresu.');
    }
}
