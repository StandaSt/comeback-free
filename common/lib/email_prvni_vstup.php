<?php
declare(strict_types=1);

/*
 * Účel souboru: Sestaví a odešle pozvánku pro první vstup do IS.
 * Token ani databázové změny sem nepatří.
 */

require_once __DIR__ . '/mailer.php';

function cb_email_prvni_vstup_odeslat(string $email, string $jmeno, string $odkaz): void
{
    $safeName = htmlspecialchars($jmeno, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeLink = htmlspecialchars($odkaz, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $body = '<!doctype html><html lang="cs"><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#1e293b;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="620" cellspacing="0" cellpadding="0" style="width:100%;max-width:620px;background:#ffffff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="padding:22px 30px;background:#e30613;color:#ffffff;font-size:22px;font-weight:bold;">IS Comeback</td></tr>'
        . '<tr><td style="padding:30px;font-size:16px;line-height:1.55;"><p style="margin:0 0 18px;">Dobrý den, ' . $safeName . ',</p>'
        . '<p style="margin:0 0 22px;">byl vám vytvořen přístup do IS Comeback. Nastavte si heslo pro první přihlášení.</p>'
        . '<p style="margin:0 0 22px;text-align:center;"><a href="' . $safeLink . '" style="display:inline-block;padding:13px 24px;background:#e30613;border-radius:6px;color:#ffffff;font-weight:bold;text-decoration:none;">Nastavit heslo a vstoupit</a></p>'
        . '<p style="margin:0 0 22px;color:#475569;font-size:14px;">Odkaz platí 3 dny.</p><p style="margin:0;">admin IS Comeback</p>'
        . '</td></tr></table></td></tr></table></body></html>';
    $altBody = "Dobrý den, {$jmeno},\n\nbyl vám vytvořen přístup do IS Comeback. Nastavte si heslo pro první přihlášení:\n\n{$odkaz}\n\nOdkaz platí 3 dny.\n\nadmin IS Comeback";

    cb_mail_send('hr', $email, 'První vstup do IS Comeback', $body, $altBody);
}
