<?php
// karty/admin_inicializace.php * Verze: V12 * Aktualizace: 02.04.2026

declare(strict_types=1);

$cbRestiaCount = 0;
$cbSmenyCount = 0;
$cbReportCount = 0;

$cbRestiaDate = 'Ne';
$cbSmenyDate = 'Ne';
$cbReportDate = 'Ne';

$cbSmenyPlanMaData = false;
$cbReportMaData = false;
$cbRunRestia = (
    isset($_POST['run_restia_obj']) && (string)$_POST['run_restia_obj'] === '1'
);
$cbRunRestiaMenu = (
    isset($_POST['run_restia_menu']) && (string)$_POST['run_restia_menu'] === '1'
);
$cbRestiaState = $_SESSION['cb_restia_hist_v4_state'] ?? null;
$cbKeepRestiaMax = false;
if (is_array($cbRestiaState)) {
    $cbKeepRestiaMax = (
        (int)($cbRestiaState['finished'] ?? 0) === 0
        && (
            (int)($cbRestiaState['auto_next'] ?? 0) === 1
            || (int)($cbRestiaState['waiting_continue'] ?? 0) === 1
        )
    );
}

$qRestia = db()->query('SELECT COALESCE(MAX(id_obj), 0) AS cnt, MAX(`import`) AS dt FROM objednavky_restia');
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
    . '<div class="table-wrap ram_normal bg_bila zaobleni_12">'
    . '  <table class="table ram_normal bg_bila radek_1_35">'
    . '    <thead>'
    . '      <tr>'
    . '        <th class="txt_l">Zdroj</th>'
    . '        <th class="txt_r">záznamů</th>'
    . '        <th class="txt_r">aktualizace</th>'
    . '      </tr>'
    . '    </thead>'
    . '    <tbody>'
    . '      <tr>'
    . '        <td>Restia</td>'
    . '        <td class="txt_r"><strong>' . h((string)$cbRestiaCount) . '</strong></td>'
    . '        <td class="txt_r">' . h($cbRestiaDate) . '</td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>Směny</td>'
    . '        <td class="txt_r"><strong>' . h((string)$cbSmenyCount) . '</strong></td>'
    . '        <td class="txt_r">' . h($cbSmenyDate) . '</td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>Reporty</td>'
    . '        <td class="txt_r"><strong>' . h((string)$cbReportCount) . '</strong></td>'
    . '        <td class="txt_r">' . h($cbReportDate) . '</td>'
    . '      </tr>'
    . '    </tbody>'
    . '  </table>'
    . '</div>';

ob_start();
?>

<div class="table-wrap ram_normal bg_bila zaobleni_12">
<table class="table ram_normal bg_bila radek_1_35">
  <thead>
    <tr>
      <th class="txt_l">script</th>
      <th class="txt_l">popis</th>
      <th class="txt_l">akce</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>inicializace/plnime_smeny_plan.php</td>
      <td>stáhne naplánované směny</td>
      <td>
        <?php if ($cbSmenyPlanMaData): ?>
          <span class="txt_cervena text_tucny">DATA!</span>
        <?php else: ?>
          <form method="get" action="<?= h(cb_url('/inicializace/plnime_smeny_plan.php')) ?>" class="odstup_vnejsi_0">
            <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td>inicializace/google_data.php</td>
      <td>stáhne směny z reportů</td>
      <td>
        <?php if ($cbReportMaData): ?>
          <span class="txt_cervena text_tucny">DATA!</span>
        <?php else: ?>
          <form method="get" action="<?= h(cb_url('/inicializace/google_data.php')) ?>" class="odstup_vnejsi_0">
            <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td>inicializace/plnime_restia_objednavky.php</td>
      <td>objednávky z Restie</td>
      <td>
        <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0">
          <input type="hidden" name="run_restia_obj" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit</button>
        </form>
      </td>
    </tr>
    <tr>
      <td>inicializace/plnime_restia_menu.php</td>
      <td>menu z Restie</td>
      <td>
        <form method="post" action="<?= h(cb_url('/index.php')) ?>" class="odstup_vnejsi_0">
          <input type="hidden" name="run_restia_menu" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit</button>
        </form>
      </td>
    </tr>
</tbody>
</table>
</div>
<?php
$card_max_html = (string)ob_get_clean();

if ($cbRunRestia || $cbKeepRestiaMax) {
    $startExpanded = true;
    ob_start();
    require __DIR__ . '/../inicializace/plnime_restia_objednavky.php';
    $card_max_html .= (string)ob_get_clean();
}

if ($cbRunRestiaMenu) {
    $startExpanded = true;
    ob_start();
    require __DIR__ . '/../inicializace/plnime_restia_menu.php';
    $card_max_html .= (string)ob_get_clean();
}

// karty/admin_inicializace.php * Verze: V12 * Aktualizace: 02.04.2026
// počet řádků 132
?>
