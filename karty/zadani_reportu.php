<?php
// K10
// karty/zadani_reportu.php * Verze: V3 * Aktualizace: 12.05.2026
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

$denniReportData = cb_denni_report_prepare_data($conn, isset($cbDashboardRenderMode) ? (string)$cbDashboardRenderMode : '');
extract($denniReportData, EXTR_SKIP);

ob_start();
?>
<p class="card_mini_text txt_cervena"><span class="text_tucny">Nezadané reporty</span></p>
<table class="card_mini_table">
  <tbody>
    <?php foreach (($miniMissingReports ?? []) as $miniMissingReport): ?>
      <?php
      $labelParts = preg_split('/\s+/', trim((string)($miniMissingReport['label'] ?? '')), 2);
      $weekday = (string)($labelParts[0] ?? '');
      $dateLabel = (string)($labelParts[1] ?? '');
      ?>
      <tr>
        <td class="txt_seda" ><?= h($weekday) ?></td>
        <td class="txt_seda" ><?= h($dateLabel) ?></td>
        <td class="txt_seda" ><?= h((string)($miniMissingReport['branches_text'] ?? 'Žádné')) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p class="card_mini_text">&nbsp;</p>
<p class="card_mini_text txt_cervena">Vypisujte prosím i tento report, je třeba odladit případné chyby. Díky</p>
<?php
$card_min_html = (string)ob_get_clean();

ob_start();
require __DIR__ . '/../includes/denni_report_formular.php';
$card_max_html = (string)ob_get_clean();
/* karty/zadani_reportu.php * Verze: V3 * Aktualizace: 12.05.2026 */
