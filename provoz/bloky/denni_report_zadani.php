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
$zrMissingReportNoticeDates = array_values(array_filter(
    (array)($missingReportNoticeDates ?? []),
    static fn($date): bool => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) === 1
));
$zrWorkdayOptionValues = array_map(
    static fn(array $option): string => (string)($option['value'] ?? ''),
    (array)$workdayOptions
);
?>
<?php if ($zrMissingReportNoticeDates !== []): ?>
    <p class="card_text txt_cervena provoz_missing_report_notice" style="color:var(--clr_cervena)">
        Chybějící reporty
        <?php foreach ($zrMissingReportNoticeDates as $zrMissingReportNoticeIndex => $zrMissingReportNoticeDate): ?>
            <?php
            $zrMissingReportNoticeDateDt = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$zrMissingReportNoticeDate, $tz);
            $zrMissingReportNoticeParams = [
                'm' => 'provoz',
                'page' => 'denni_report',
                'zr_id_pob' => (string)$reportBranchId,
                'datum_reportu' => (string)$zrMissingReportNoticeDate,
            ];
            if (!in_array((string)$zrMissingReportNoticeDate, $zrWorkdayOptionValues, true)) {
                $zrMissingReportNoticeParams['zr_archive'] = '1';
                $zrMissingReportNoticeParams['zr_archive_edit'] = '1';
            }
            $zrMissingReportNoticeUrl = cb_root_url('index.php') . '?' . http_build_query($zrMissingReportNoticeParams, '', '&', PHP_QUERY_RFC3986);
            $zrMissingReportNoticeSeparator = $zrMissingReportNoticeIndex === 0
                ? ' '
                : ($zrMissingReportNoticeIndex === count($zrMissingReportNoticeDates) - 1 ? ' a ' : ', ');
            ?><?= h($zrMissingReportNoticeSeparator) ?><a href="<?= h($zrMissingReportNoticeUrl) ?>" style="color:var(--cb-text-main)"><?= h($zrMissingReportNoticeDateDt instanceof DateTimeImmutable ? $zrMissingReportNoticeDateDt->format('j.n.') : (string)$zrMissingReportNoticeDate) ?></a><?php endforeach; ?> je nutno doplnit.
    </p>
<?php endif; ?>
<section class="blok provoz_denni_report_zadani cb-zadani-reportu" data-pp-block="denni_report_zadani" data-cb-restia-needed="1">
    <?php require __DIR__ . '/../includes/denni_report_formular.php'; ?>
</section>
