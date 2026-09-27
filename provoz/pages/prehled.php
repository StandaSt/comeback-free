<?php
// Sestavi hlavni prehled Provozu do rastru a pripravi export nezadanych reportu.
declare(strict_types=1);

require_once __DIR__ . '/../lib/nezadane_reporty_export_data.php';

$nezadaneExportAllowed = cb_nezadane_reporty_export_ma_pravo();
$nezadaneExportRecipients = [];
if ($nezadaneExportAllowed) {
    try {
        $nezadaneExportRecipients = cb_nezadane_reporty_export_recipients(db());
    } catch (Throwable $e) {
        $nezadaneExportRecipients = [];
    }
    if (empty($_SESSION['nezadane_reporty_export_csrf'])) {
        $_SESSION['nezadane_reporty_export_csrf'] = bin2hex(random_bytes(32));
    }
}
$nezadaneExportCsrf = $nezadaneExportAllowed ? (string)($_SESSION['nezadane_reporty_export_csrf'] ?? '') : '';
$nezadaneExportUrl = $nezadaneExportAllowed ? cb_root_url('provoz/lib/odeslat_nezadane_reporty_pdf.php') : '';
?>
<div class="provoz_prehled_grid" aria-label="Přehled Provozu">
    <div class="provoz_prehled_cell" data-pp-block="objednavky_online" data-gn="1"><?php require __DIR__ . '/../bloky/objednavky_online.php'; ?></div>
    <div class="provoz_prehled_cell provoz_prehled_cell_stack">
        <div class="provoz_prehled_stack_item provoz_prehled_stack_reports" data-pp-block="denni_report_prehled"><?php require __DIR__ . '/../bloky/denni_report_prehled.php'; ?></div>
        <div class="provoz_prehled_stack_item provoz_prehled_stack_users" data-pp-block="uzivatele_online" data-gn="1"><?php require __DIR__ . '/../bloky/uzivatele_online.php'; ?></div>
    </div>
    <div class="provoz_prehled_cell" data-pp-block="top_report" data-gn="1"><?php require __DIR__ . '/../bloky/top_report.php'; ?></div>
    <?php // Rezervovane misto pro budouci ctvrty blok prehledu. ?>
    <div class="provoz_prehled_cell provoz_prehled_cell_reserved" aria-hidden="true"></div>
</div>
<?php if ($nezadaneExportAllowed): ?>
    <?php require __DIR__ . '/../modaly/modal_nezadane_reporty_export.php'; ?>
<?php endif; ?>
