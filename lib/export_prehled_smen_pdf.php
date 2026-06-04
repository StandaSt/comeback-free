<?php
// lib/export_prehled_smen_pdf.php * Verze: V1 * Aktualizace: 03.06.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/prehled_smen_data.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}

if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nutné přihlášení';
    exit;
}

$data = ps_prehled_smen_data($_GET);
if ((string)$data['error'] !== '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo (string)$data['error'];
    exit;
}

$month = sprintf('%04d-%02d', (int)$data['selectedYear'], (int)$data['selectedMonth']);
$filename = 'prehled_smen_' . $month . '.pdf';

$rowsHtml = '';
foreach ((array)$data['filteredRows'] as $row) {
    $rowsHtml .= '<tr>'
        . '<td>' . h((string)$row['mesic']) . '</td>'
        . '<td>' . h((string)$row['rok']) . '</td>'
        . '<td class="txt_l">' . h((string)$row['cele_jmeno']) . '</td>'
        . '<td class="txt_l">' . h(ps_slot_label((int)$row['slot'])) . '</td>'
        . '<td>' . h(ps_num((float)$row['celkem'])) . '</td>'
        . '<td>' . h(ps_num((float)$row['den'])) . '</td>'
        . '<td>' . h(ps_num((float)$row['noc'])) . '</td>'
        . '<td>' . h(ps_num((float)$row['vikend'])) . '</td>'
        . '<td>' . h(ps_num((float)$row['svatek'])) . '</td>'
        . '</tr>';
}
if ($rowsHtml === '') {
    $rowsHtml = '<tr><td colspan="9" class="txt_l">Žádná data</td></tr>';
}

$html = '<!doctype html><html lang="cs"><head><meta charset="utf-8">'
    . '<style>'
    . 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111827;}'
    . 'h1{font-size:18px;margin:0 0 6px 0;}'
    . 'p{margin:0 0 12px 0;}'
    . 'table{width:100%;border-collapse:collapse;}'
    . 'th,td{border:1px solid #d7e0ec;padding:5px 6px;text-align:right;white-space:nowrap;}'
    . 'th{background:#eef4fb;font-weight:bold;}'
    . '.txt_l{text-align:left;}'
    . '</style></head><body>'
    . '<h1>Přehled za ' . h((string)$data['monthLabel']) . '</h1>'
    . '<p>Celkem: <strong>' . h(ps_num((float)$data['filteredHours'])) . '</strong> hodin</p>'
    . '<table><thead><tr>'
    . '<th>měsíc</th><th>rok</th><th class="txt_l">celé jméno</th><th class="txt_l">slot</th>'
    . '<th>odpracováno</th><th>6-22</th><th>22-6</th><th>So+Ne</th><th>svátek</th>'
    . '</tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
    . '</body></html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream($filename, ['Attachment' => true]);

/* lib/export_prehled_smen_pdf.php * Verze: V1 * Aktualizace: 03.06.2026 */
?>
