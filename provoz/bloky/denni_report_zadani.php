<?php
// bloky/denni_report_zadani.php * Blok zadani denniho reportu
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../lib/vypocty_report.php';
require_once __DIR__ . '/../lib/denni_report_data.php';
require_once __DIR__ . '/../db/db_dr_pracovni.php';
require_once __DIR__ . '/../db/db_dr_pracovni_osoby.php';

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$zrRozvozSazba = 0;
$resRozvozSazba = $conn->query('SELECT rozvoz_sazba FROM set_system WHERE id_set = 1 LIMIT 1');
if ($resRozvozSazba instanceof mysqli_result) {
    $rowRozvozSazba = $resRozvozSazba->fetch_assoc();
    $zrRozvozSazba = (int)($rowRozvozSazba['rozvoz_sazba'] ?? 0);
    $resRozvozSazba->free();
}

$denniReportData = cb_denni_report_zadani_data($conn);
extract($denniReportData, EXTR_SKIP);
?>
<section class="blok provoz_denni_report_zadani cb-zadani-reportu" data-pp-block="denni_report_zadani">
    <?php require __DIR__ . '/../includes/denni_report_formular.php'; ?>
</section>
