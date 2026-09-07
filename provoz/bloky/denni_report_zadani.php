<?php
// bloky/denni_report_zadani.php * Blok zadani denniho reportu
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../lib/vypocty_report.php';
require_once __DIR__ . '/../lib/denni_report_data.php';
require_once __DIR__ . '/../lib/archiv_reportu_data.php';
require_once __DIR__ . '/../lib/report_promenne.php';
require_once __DIR__ . '/../db/db_dr_pracovni.php';
require_once __DIR__ . '/../db/db_dr_pracovni_osoby.php';

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$denniReportData = cb_denni_report_zadani_data($conn);
extract($denniReportData, EXTR_SKIP);
$zrManualDifferenceRows = [];
$zrManualDifferenceDates = [];
if (empty($isGoogleArchiveView) && $reportBranchId > 0) {
    foreach ((array)$workdayOptions as $workdayOption) {
        $workdayDate = trim((string)($workdayOption['value'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $workdayDate) !== 1) {
            continue;
        }
        $zrComparisonRows = cb_archiv_reportu_comparison_rows($conn, (int)$reportBranchId, $workdayDate);
        $workdayDifferenceRows = (array)($zrComparisonRows['entry'] ?? []);
        if ($workdayDifferenceRows === []) {
            continue;
        }
        $zrManualDifferenceDates[$workdayDate] = true;
        if ($workdayDate === (string)$reportDate) {
            $zrManualDifferenceRows = $workdayDifferenceRows;
        }
    }
}
$reportPromenne = cb_report_promenne_for_date($conn, (string)$reportDate);
$zrRozvozSazba = max(0, (int)($reportPromenne['phm_soukrome'] ?? 0));
?>
<section class="blok provoz_denni_report_zadani cb-zadani-reportu" data-pp-block="denni_report_zadani" data-cb-restia-needed="1">
    <?php require __DIR__ . '/../includes/denni_report_formular.php'; ?>
</section>
