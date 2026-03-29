<?php
// karty/admin_inicializace.php * Verze: V11 * Aktualizace: 29.03.2026

declare(strict_types=1);

$cbRestiaCount = 0;
$cbSmenyCount = 0;
$cbReportCount = 0;

$cbRestiaDate = 'Ne';
$cbSmenyDate = 'Ne';
$cbReportDate = 'Ne';

$cbSmenyPlanMaData = false;
$cbReportMaData = false;

$qRestia = db()->query('SELECT COALESCE(MAX(id_obj), 0) AS cnt, MAX(`import`) AS dt FROM res_objednavky');
if ($qRestia instanceof mysqli_result) {
    $r = $qRestia->fetch_assoc();
    $cbRestiaCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbRestiaDate = ($dt !== '') ? $dt : 'Ne';
    $qRestia->free();
}

$qSmeny = db()->query('SELECT COUNT(*) AS cnt, MAX(created_at) AS dt FROM smeny_plan');
if ($qSmeny instanceof mysqli_result) {
    $r = $qSmeny->fetch_assoc();
    $cbSmenyCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbSmenyDate = ($dt !== '') ? $dt : 'Ne';
    $cbSmenyPlanMaData = ($cbSmenyCount > 0);
    $qSmeny->free();
}

$qReport = db()->query('SELECT COUNT(*) AS cnt, MAX(created_at) AS dt FROM smeny_report');
if ($qReport instanceof mysqli_result) {
    $r = $qReport->fetch_assoc();
    $cbReportCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbReportDate = ($dt !== '') ? $dt : 'Ne';
    $cbReportMaData = ($cbReportCount > 0);
    $qReport->free();
}

$card_min_html = ''
    . '<div class="table-wrap">'
    . '  <table class="table">'
    . '    <thead>'
    . '      <tr>'
    . '        <th class="text_vlevo">Zdroj</th>'
    . '        <th class="text_vpravo">záznamů</th>'
    . '        <th class="text_vpravo">aktualizace</th>'
    . '      </tr>'
    . '    </thead>'
    . '    <tbody>'
    . '      <tr>'
    . '        <td>Restia</td>'
    . '        <td class="text_vpravo"><strong>' . h((string)$cbRestiaCount) . '</strong></td>'
    . '        <td class="text_vpravo">' . h($cbRestiaDate) . '</td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>Směny</td>'
    . '        <td class="text_vpravo"><strong>' . h((string)$cbSmenyCount) . '</strong></td>'
    . '        <td class="text_vpravo">' . h($cbSmenyDate) . '</td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>Reporty</td>'
    . '        <td class="text_vpravo"><strong>' . h((string)$cbReportCount) . '</strong></td>'
    . '        <td class="text_vpravo">' . h($cbReportDate) . '</td>'
    . '      </tr>'
    . '    </tbody>'
    . '  </table>'
    . '</div>';

ob_start();
?>
<table class="table">
  <thead>
    <tr>
      <th class="text_vlevo">script</th>
      <th class="text_vlevo">popis</th>
      <th class="text_vlevo">akce</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>inicializace/plnime_smeny_plan.php</td>
      <td>stáhne naplánované směny</td>
      <td>
        <?php if ($cbSmenyPlanMaData): ?>
          <span class="text_barva_cervena text_tucny">DATA!</span>
        <?php else: ?>
          <form method="get" action="<?= h(cb_url('/inicializace/plnime_smeny_plan.php')) ?>" class="odstup_vnejsi_0">
            <button type="submit" class="card_btn card_btn_primary">Spustit</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td>inicializace/google_data.php</td>
      <td>stáhne směny z reportů</td>
      <td>
        <?php if ($cbReportMaData): ?>
          <span class="text_barva_cervena text_tucny">DATA!</span>
        <?php else: ?>
          <form method="get" action="<?= h(cb_url('/inicializace/google_data.php')) ?>" class="odstup_vnejsi_0">
            <button type="submit" class="card_btn card_btn_primary">Spustit</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  </tbody>
</table>
<?php
$card_max_html = (string)ob_get_clean();

// karty/admin_inicializace.php * Verze: V11 * Aktualizace: 29.03.2026
// počet řádků 96
?>
