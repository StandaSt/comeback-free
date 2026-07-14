<?php
// lib/export_prehled_smen_pdf.php * Verze: V2 * Aktualizace: 04.06.2026
declare(strict_types=1);

require_once __DIR__ . '/session_boot.php';

require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/prehled_smen_data.php';
require_once __DIR__ . '/../../www/vendor/autoload.php';

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
$scope = (string)($_GET['ps_scope'] ?? 'summary') === 'detail' ? 'detail' : 'summary';
$filename = 'prehled_smen_' . ($scope === 'detail' ? 'detail_' : '') . $month . '.pdf';

if (!function_exists('ps_pdf_date')) {
    function ps_pdf_date(string $date): string
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $dt instanceof DateTimeImmutable ? $dt->format('j.n.Y') : $date;
    }
}

if (!function_exists('ps_pdf_num_dash')) {
    function ps_pdf_num_dash(float $value): string
    {
        return abs($value) < 0.005 ? '-' : ps_num($value);
    }
}

if (!function_exists('ps_pdf_summary_rows')) {
    function ps_pdf_summary_rows(array $rows): string
    {
        $rowsHtml = '';
        foreach ($rows as $row) {
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

        return $rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="9" class="txt_l">Žádná data</td></tr>';
    }
}

if (!function_exists('ps_pdf_detail_blocks')) {
    function ps_pdf_detail_blocks(array $rows): string
    {
        if ($rows === []) {
            return '<p>Žádná data</p>';
        }

        $html = '';
        foreach ($rows as $row) {
            $detailRows = isset($row['detail_rows']) && is_array($row['detail_rows']) ? $row['detail_rows'] : [];
            $html .= '<section class="user-block">'
                . '<table class="summary-table"><tbody><tr>'
                . '<td class="txt_l name">' . h((string)$row['cele_jmeno']) . '</td>'
                . '<td class="txt_l">' . h(ps_slot_label((int)$row['slot'])) . '</td>'
                . '<td>' . h(ps_num((float)$row['celkem'])) . '</td>'
                . '<td>' . h(ps_num((float)$row['den'])) . '</td>'
                . '<td>' . h(ps_num((float)$row['noc'])) . '</td>'
                . '<td>' . h(ps_num((float)$row['vikend'])) . '</td>'
                . '<td>' . h(ps_num((float)$row['svatek'])) . '</td>'
                . '</tr></tbody></table>'
                . '<div class="detail-title">Detail - odpracované hodiny ' . h((string)$row['cele_jmeno']) . '</div>'
                . '<table class="detail-table"><thead><tr>'
                . '<th class="txt_l">datum</th><th class="txt_l">pobočka</th><th class="txt_l">slot</th>'
                . '<th>odpracováno</th><th>6-22</th><th>22-6</th><th>So+Ne</th><th>svátek</th>'
                . '</tr></thead><tbody>';

            if ($detailRows === []) {
                $html .= '<tr><td colspan="8" class="txt_l">Žádná data</td></tr>';
            } else {
                foreach ($detailRows as $detailRow) {
                    $branchName = trim((string)($detailRow['pobocka'] ?? ''));
                    if ($branchName === '' && (int)($detailRow['id_pob'] ?? 0) > 0) {
                        $branchName = 'ID ' . (string)(int)$detailRow['id_pob'];
                    }
                    $html .= '<tr>'
                        . '<td class="txt_l">' . h(ps_pdf_date((string)($detailRow['datum'] ?? ''))) . '</td>'
                        . '<td class="txt_l">' . h($branchName !== '' ? $branchName : '-') . '</td>'
                        . '<td class="txt_l">' . h(ps_slot_label((int)($detailRow['slot'] ?? 0))) . '</td>'
                        . '<td>' . h(ps_pdf_num_dash((float)($detailRow['celkem'] ?? 0.0))) . '</td>'
                        . '<td>' . h(ps_pdf_num_dash((float)($detailRow['den'] ?? 0.0))) . '</td>'
                        . '<td>' . h(ps_pdf_num_dash((float)($detailRow['noc'] ?? 0.0))) . '</td>'
                        . '<td>' . h(ps_pdf_num_dash((float)($detailRow['vikend'] ?? 0.0))) . '</td>'
                        . '<td>' . h(ps_pdf_num_dash((float)($detailRow['svatek'] ?? 0.0))) . '</td>'
                        . '</tr>';
                }
            }

            $html .= '</tbody></table></section>';
        }

        return $html;
    }
}

$bodyHtml = '';
if ($scope === 'detail') {
    $bodyHtml = '<div class="detail-export">' . ps_pdf_detail_blocks((array)$data['filteredRows']) . '</div>';
} else {
    $bodyHtml = '<table><thead><tr>'
        . '<th>měsíc</th><th>rok</th><th class="txt_l">celé jméno</th><th class="txt_l">slot</th>'
        . '<th>odpracováno</th><th>6-22</th><th>22-6</th><th>So+Ne</th><th>svátek</th>'
        . '</tr></thead><tbody>' . ps_pdf_summary_rows((array)$data['filteredRows']) . '</tbody></table>';
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
    . '.detail-export{font-size:9px;}'
    . '.user-block{page-break-inside:avoid;break-inside:avoid;margin:0 0 7px 0;}'
    . '.summary-table td{background:#eaf4ff;font-weight:bold;padding:3px 5px;}'
    . '.summary-table .name{width:28%;}'
    . '.detail-title{background:#d9ecff;border:1px solid #d7e0ec;border-top:0;font-weight:bold;padding:3px 5px;text-align:left;}'
    . '.detail-table th,.detail-table td{padding:2px 4px;}'
    . '.detail-table th{background:#eef7ff;}'
    . '</style></head><body>'
    . '<h1>' . ($scope === 'detail' ? 'Detailní přehled za ' : 'Přehled za ') . h((string)$data['monthLabel']) . '</h1>'
    . '<p>Celkem: <strong>' . h(ps_num((float)$data['filteredHours'])) . '</strong> hodin</p>'
    . $bodyHtml
    . '</body></html>';

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($filename, ['Attachment' => true]);

/* lib/export_prehled_smen_pdf.php * Verze: V2 * Aktualizace: 04.06.2026 */
?>
