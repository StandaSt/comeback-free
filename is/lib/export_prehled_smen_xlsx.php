<?php
// lib/export_prehled_smen_xlsx.php * Verze: V2 * Aktualizace: 04.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../../www/lib/session_boot.php';

require_once __DIR__ . '/../../www/config/secrets.php';
require_once __DIR__ . '/../../www/lib/app.php';
require_once __DIR__ . '/prehled_smen_data.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
$filename = 'prehled_smen_' . ($scope === 'detail' ? 'detail_' : '') . $month . '.xlsx';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle($scope === 'detail' ? 'Detail směn' : 'Přehled směn');

$sheet->setCellValue('A1', 'Přehled za ' . (string)$data['monthLabel']);
$sheet->setCellValue('A2', 'Celkem hodin');
$sheet->setCellValue('B2', (float)$data['filteredHours']);

$headers = $scope === 'detail'
    ? ['celé jméno', 'slot', 'datum', 'pobočka', 'odpracováno', '6-22', '22-6', 'So+Ne', 'svátek']
    : ['měsíc', 'rok', 'celé jméno', 'slot', 'odpracováno', '6-22', '22-6', 'So+Ne', 'svátek'];
$sheet->fromArray($headers, null, 'A4');

$rowNum = 5;
if ($scope === 'detail') {
    foreach ((array)$data['filteredRows'] as $row) {
        $detailRows = isset($row['detail_rows']) && is_array($row['detail_rows']) ? $row['detail_rows'] : [];
        foreach ($detailRows as $detailRow) {
            $branchName = trim((string)($detailRow['pobocka'] ?? ''));
            if ($branchName === '' && (int)($detailRow['id_pob'] ?? 0) > 0) {
                $branchName = 'ID ' . (string)(int)$detailRow['id_pob'];
            }
            $sheet->fromArray([
                (string)$row['cele_jmeno'],
                ps_slot_label((int)$row['slot']),
                (string)($detailRow['datum'] ?? ''),
                $branchName !== '' ? $branchName : '-',
                (float)($detailRow['celkem'] ?? 0.0),
                (float)($detailRow['den'] ?? 0.0),
                (float)($detailRow['noc'] ?? 0.0),
                (float)($detailRow['vikend'] ?? 0.0),
                (float)($detailRow['svatek'] ?? 0.0),
            ], null, 'A' . (string)$rowNum);
            $rowNum++;
        }
    }
} else {
    foreach ((array)$data['filteredRows'] as $row) {
        $sheet->fromArray([
            (int)$row['mesic'],
            (int)$row['rok'],
            (string)$row['cele_jmeno'],
            ps_slot_label((int)$row['slot']),
            (float)$row['celkem'],
            (float)$row['den'],
            (float)$row['noc'],
            (float)$row['vikend'],
            (float)$row['svatek'],
        ], null, 'A' . (string)$rowNum);
        $rowNum++;
    }
}

$lastRow = max(4, $rowNum - 1);
$sheet->mergeCells('A1:I1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A4:I4')->getFont()->setBold(true);
$sheet->getStyle('A4:I' . (string)$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$sheet->getStyle('A4:I' . (string)$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle(($scope === 'detail' ? 'A4:D' : 'C4:D') . (string)$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
$sheet->getStyle('B2')->getNumberFormat()->setFormatCode('# ##0.00');
$sheet->getStyle('E5:I' . (string)$lastRow)->getNumberFormat()->setFormatCode('# ##0.00');

foreach (range('A', 'I') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('X-Content-Type-Options: nosniff');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
$spreadsheet->disconnectWorksheets();

/* lib/export_prehled_smen_xlsx.php * Verze: V2 * Aktualizace: 04.06.2026 */
?>
