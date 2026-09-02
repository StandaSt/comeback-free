<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Odesle e-mail pres SMTP konfiguraci ulozenou v secrets.php.
 */
function cb_mail_send(
    string $profile,
    string $to,
    string $subject,
    string $body,
    string $altBody = '',
    array $attachments = [],
    array $sender = []
): void
{
    $to = trim($to);
    if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Chybí platný příjemce e-mailu.');
    }

    $secrets = $GLOBALS['SECRETS'] ?? null;
    $cfg = is_array($secrets) ? ($secrets['mail'][$profile] ?? null) : null;
    if (!is_array($cfg)) {
        throw new RuntimeException('Chybí SMTP konfigurace.');
    }

    $autoload = __DIR__ . '/../../../vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Chybí Composer autoload pro odeslání e-mailu.');
    }
    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
        // Nastavi SMTP pripojeni podle konfigurace vybraneho profilu.
        $mail->isSMTP();
        $mail->Host = trim((string)($cfg['host'] ?? ''));
        $mail->Port = (int)($cfg['port'] ?? 0);
        $mail->SMTPAuth = true;
        $mail->Username = trim((string)($cfg['user'] ?? ''));
        $mail->Password = (string)($cfg['pass'] ?? '');
        $mail->SMTPSecure = strtolower(trim((string)($cfg['secure'] ?? 'ssl'))) === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $from = trim((string)($cfg['from'] ?? $mail->Username));
        $fromName = trim((string)($sender['name'] ?? $cfg['from_name'] ?? ''));
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);

        $replyTo = trim((string)($sender['email'] ?? ''));
        if ($replyTo !== '') {
            if (filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
                throw new RuntimeException('E-mail odesílatele není platný.');
            }
            $mail->addReplyTo($replyTo, trim((string)($sender['name'] ?? '')));
        }

        // Odesle HTML e-mail, pokud je predana textova alternativa.
        $mail->isHTML($altBody !== '');
        $mail->Subject = $subject;
        $mail->Body = $body;
        if ($altBody !== '') {
            $mail->AltBody = $altBody;
        }

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            $content = $attachment['content'] ?? null;
            $name = trim((string)($attachment['name'] ?? ''));
            $type = trim((string)($attachment['type'] ?? 'application/octet-stream'));
            if (!is_string($content) || $name === '') {
                throw new RuntimeException('E-mailová příloha není platná.');
            }
            $mail->addStringAttachment($content, $name, 'base64', $type);
        }

        $mail->send();
    } catch (PHPMailerException $e) {
        throw new RuntimeException('E-mail se nepodařilo odeslat: ' . $mail->ErrorInfo);
    }
}
