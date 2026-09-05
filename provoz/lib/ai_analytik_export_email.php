<?php
// Odeslání podepsaného výsledku AI analytika jako PDF.
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/mailer.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';
require_once __DIR__ . '/ai_analytik_pravidla.php';
require_once __DIR__ . '/ai_analytik_export_common.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}
if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Nutné přihlášení.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!cb_ai_analytik_ma_pravo()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'K AI analytikovi nemáte oprávnění.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Neplatný požadavek.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$auditId = 0;
$recipientEmail = '';
try {
    $input = json_decode((string)file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new RuntimeException('Neplatná data požadavku.');
    }
    $sessionToken = (string)($_SESSION['ai_analytik_csrf'] ?? '');
    $requestToken = (string)($input['csrf'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        throw new RuntimeException('Platnost formuláře vypršela. Obnovte stránku.');
    }

    $data = cb_ai_analytik_export_overit(
        (string)($input['payload'] ?? ''),
        (string)($input['signature'] ?? ''),
        (string)($_SESSION['ai_analytik_export_secret'] ?? ''),
        (int)($_SESSION['cb_user']['id_user'] ?? 0)
    );
    $auditId = (int)$data['audit_id'];

    $recipient = cb_ai_analytik_export_prijemce(db(), (int)($input['id_recipient'] ?? 0));
    if (!is_array($recipient)) {
        throw new RuntimeException('Vyberte platného příjemce.');
    }
    $recipientEmail = (string)$recipient['email'];

    $sender = is_array($_SESSION['cb_user'] ?? null) ? $_SESSION['cb_user'] : [];
    $senderName = trim((string)($sender['name'] ?? '') . ' ' . (string)($sender['surname'] ?? ''));
    $senderEmail = trim((string)($sender['email'] ?? ''));
    if ($senderName === '') {
        $senderName = $senderEmail !== '' ? $senderEmail : 'AI analytik';
    }
    if (filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Přihlášený uživatel nemá platnou e-mailovou adresu.');
    }

    $pdf = cb_ai_analytik_export_pdf($data);
    $subject = 'Výsledek AI analytika';
    $prompt = trim((string)($data['prompt'] ?? ''));
    $yearsText = cb_ai_analytik_export_roky_text($data);
    $body = '<p>V příloze je výsledek AI analytika.</p><p><strong>Dotaz:</strong> '
        . cb_ai_analytik_export_h($prompt) . '</p><p><strong>Zpracované roky:</strong> '
        . cb_ai_analytik_export_h($yearsText) . '</p>';
    $altBody = "V příloze je výsledek AI analytika.\n\nDotaz: " . $prompt
        . "\nZpracované roky: " . $yearsText;

    cb_mail_send('ai', $recipientEmail, $subject, $body, $altBody, [[
        'content' => (string)$pdf['content'],
        'name' => (string)$pdf['filename'],
        'type' => 'application/pdf',
    ]], [
        'name' => $senderName,
        'email' => $senderEmail,
    ]);

    cb_user_akce_zapis([
        'id_user_akce_typ' => 16,
        'modul' => 'provoz',
        'objekt' => 'ai_analytik_email',
        'id_objektu' => $auditId,
        'pole' => 'email_prijemce',
        'hodnota_new' => $recipientEmail,
        'zdroj' => 'export_ai_analytik',
        'detail' => [
            'audit_id' => $auditId,
            'id_prijemce' => (int)$recipient['id_user'],
            'soubor' => (string)$pdf['filename'],
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'PDF bylo odesláno na ' . $recipientEmail . '.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    try {
        cb_user_akce_zapis([
            'id_user_akce_typ' => 20,
            'modul' => 'provoz',
            'objekt' => 'ai_analytik_email',
            'id_objektu' => $auditId,
            'pole' => 'email_prijemce',
            'hodnota_new' => $recipientEmail,
            'zdroj' => 'export_ai_analytik',
            'vysledek' => 0,
            'err_msg' => $error->getMessage(),
            'detail' => ['audit_id' => $auditId],
        ]);
    } catch (Throwable $logError) {
    }
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => cb_ai_analytik_export_chyba($error),
    ], JSON_UNESCAPED_UNICODE);
}
