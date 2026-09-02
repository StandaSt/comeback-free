<?php
// Stažení podepsaného výsledku AI analytika jako PDF.
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';
require_once __DIR__ . '/ai_analytik_pravidla.php';
require_once __DIR__ . '/ai_analytik_export_common.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}
if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nutné přihlášení.';
    exit;
}
if (!cb_ai_analytik_ma_pravo()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'K AI analytikovi nemáte oprávnění.';
    exit;
}
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Neplatný požadavek.';
    exit;
}

$auditId = 0;
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
    $pdf = cb_ai_analytik_export_pdf($data);

    cb_user_akce_zapis([
        'id_user_akce_typ' => 16,
        'modul' => 'provoz',
        'objekt' => 'ai_analytik_pdf',
        'id_objektu' => $auditId,
        'zdroj' => 'export_ai_analytik',
        'detail' => [
            'audit_id' => $auditId,
            'soubor' => (string)$pdf['filename'],
        ],
    ]);

    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$pdf['filename']) ?: 'ai_analytik.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen((string)$pdf['content']));
    header('X-Content-Type-Options: nosniff');
    echo (string)$pdf['content'];
} catch (Throwable $error) {
    try {
        cb_user_akce_zapis([
            'id_user_akce_typ' => 20,
            'modul' => 'provoz',
            'objekt' => 'ai_analytik_pdf',
            'id_objektu' => $auditId,
            'zdroj' => 'export_ai_analytik',
            'vysledek' => 0,
            'err_msg' => $error->getMessage(),
            'detail' => ['audit_id' => $auditId],
        ]);
    } catch (Throwable $logError) {
    }
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo cb_ai_analytik_export_chyba($error);
}

