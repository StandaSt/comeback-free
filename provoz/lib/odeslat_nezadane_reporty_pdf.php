<?php
// lib/odeslat_nezadane_reporty_pdf.php * Vytvori PDF v pameti a odesle je e-mailem
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/mailer.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';
require_once __DIR__ . '/nezadane_reporty_export_data.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json; charset=utf-8');

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}
if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Nutné přihlášení.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!cb_nezadane_reporty_export_ma_pravo()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Nemáte právo exportovat nezadané reporty.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Neplatný požadavek.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$logScope = '';
$logRecipient = '';
try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new RuntimeException('Neplatná data požadavku.');
    }

    $sessionToken = (string)($_SESSION['nezadane_reporty_export_csrf'] ?? '');
    $requestToken = (string)($input['csrf'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $requestToken)) {
        throw new RuntimeException('Platnost formuláře vypršela. Obnovte stránku.');
    }

    $scope = (string)($input['scope'] ?? '');
    $logScope = $scope;
    $idRecipient = (int)($input['id_recipient'] ?? 0);
    $conn = db();
    $recipient = cb_nezadane_reporty_export_recipient($conn, $idRecipient);
    if (!is_array($recipient)) {
        throw new RuntimeException('Vyberte platnou e-mailovou adresu.');
    }
    $logRecipient = (string)$recipient['email'];

    $export = cb_nezadane_reporty_export_rows($conn, $scope);
    $period = is_array($export['period'] ?? null) ? $export['period'] : [];
    $rows = is_array($export['rows'] ?? null) ? $export['rows'] : [];
    $periodLabel = (string)($period['label'] ?? '');

    $tableRows = '';
    foreach ($rows as $row) {
        $tableRows .= '<tr>'
            . '<td>' . h((string)($row['date_label'] ?? '')) . '</td>'
            . '<td>' . h((string)($row['weekday'] ?? '')) . '</td>'
            . '<td>' . h((string)($row['branch'] ?? '')) . '</td>'
            . '<td>' . h((string)($row['closer'] ?? '—')) . '</td>'
            . '</tr>';
    }
    if ($tableRows === '') {
        $tableRows = '<tr><td colspan="4" class="empty">V tomto období nechybí žádný denní report.</td></tr>';
    }

    $html = '<!doctype html><html lang="cs"><head><meta charset="utf-8"><style>'
        . 'body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111827;}'
        . 'h1{font-size:17px;margin:0 0 5px;}'
        . 'p{margin:0 0 12px;color:#4b5563;}'
        . 'table{width:100%;border-collapse:collapse;}'
        . 'th,td{border:1px solid #cbd5e1;padding:5px 6px;text-align:left;vertical-align:top;}'
        . 'th{background:#e7eef6;font-weight:bold;}'
        . '.empty{text-align:center;color:#64748b;padding:12px;}'
        . '</style></head><body>'
        . '<h1>Nezadané denní reporty</h1>'
        . '<p>Období: ' . h((string)($period['from'] ?? '')) . ' – ' . h((string)($period['to'] ?? '')) . ' (' . h($periodLabel) . ')</p>'
        . '<table><thead><tr><th>Datum</th><th>Den v týdnu</th><th>Pobočka</th><th>Kdo zavíral</th></tr></thead>'
        . '<tbody>' . $tableRows . '</tbody></table>'
        . '</body></html>';

    $options = new Options();
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf = $dompdf->output();

    $filename = 'nezadane_denni_reporty_' . substr((string)($period['from'] ?? date('Y-m-d')), 0, 7) . '.pdf';
    $subject = 'Nezadané denní reporty – ' . $periodLabel;
    $body = '<p>V příloze je přehled nezadaných denních reportů za období <strong>' . h($periodLabel) . '</strong>.</p>';
    $altBody = 'V příloze je přehled nezadaných denních reportů za období ' . $periodLabel . '.';
    cb_mail_send('hr', (string)$recipient['email'], $subject, $body, $altBody, [[
        'content' => $pdf,
        'name' => $filename,
        'type' => 'application/pdf',
    ]]);

    cb_user_akce_zapis([
        'id_user_akce_typ' => 16,
        'modul' => 'provoz',
        'objekt' => 'nezadane_reporty_pdf',
        'pole' => 'email_prijemce',
        'hodnota_new' => (string)$recipient['email'],
        'zdroj' => 'export_nezadane_reporty',
        'detail' => [
            'scope' => $scope,
            'obdobi_od' => (string)($period['from'] ?? ''),
            'obdobi_do' => (string)($period['to'] ?? ''),
            'obdobi' => $periodLabel,
            'pocet_radku' => count($rows),
            'soubor' => $filename,
        ],
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'PDF bylo odesláno na ' . (string)$recipient['email'] . '.',
        'recipient_email' => (string)$recipient['email'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    try {
        cb_user_akce_zapis([
            'id_user_akce_typ' => 20,
            'modul' => 'provoz',
            'objekt' => 'nezadane_reporty_pdf',
            'pole' => 'email_prijemce',
            'hodnota_new' => $logRecipient,
            'zdroj' => 'export_nezadane_reporty',
            'vysledek' => 0,
            'err_msg' => $e->getMessage(),
            'detail' => ['scope' => $logScope],
        ]);
    } catch (Throwable $logError) {
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
