<?php
// karty/admin_inicializace.php * Verze: V9 * Aktualizace: 26.03.2026

declare(strict_types=1);

$cbRestiaCount = 0;
$cbSmenyCount = 0;
$cbRestiaDate = 'Ne';
$cbSmenyDate = 'Ne';
$cbSmenyPlanMaData = false;

$qRestia = db()->query('SELECT COALESCE(MAX(id_obj), 0) AS cnt, MAX(`import`) AS dt FROM res_objednavky');
if ($qRestia instanceof mysqli_result) {
    $r = $qRestia->fetch_assoc();
    $cbRestiaCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbRestiaDate = ($dt !== '') ? $dt : 'Ne';
    $qRestia->free();
}

$qSmeny = db()->query('SELECT COUNT(*) AS cnt, MAX(updated_at) AS dt FROM smeny_plan');
if ($qSmeny instanceof mysqli_result) {
    $r = $qSmeny->fetch_assoc();
    $cbSmenyCount = (int)($r['cnt'] ?? 0);
    $dt = trim((string)($r['dt'] ?? ''));
    $cbSmenyDate = ($dt !== '') ? $dt : 'Ne';
    $cbSmenyPlanMaData = ($cbSmenyCount > 0);
    $qSmeny->free();
}

$card_min_html = ''
    . '<div class="table-wrap">'
    . '<table class="table">'
    . '<thead><tr style="border:0;"><th>Zdroj</th><th style="text-align:right;">záznamů</th><th style="text-align:right;">aktualizace</th></tr></thead>'
    . '<tbody style="border:0;">'
    . '<tr style="border:0;"><td style="padding:0; border:0;">Restia</td><td style="text-align:right;"><strong>' . h((string)$cbRestiaCount) . '</strong></td><td style="text-align:right;">' . h($cbRestiaDate) . '</td></tr>'
    . '<tr style="border:0;"><td style="padding:0; border:0;">Směny</td><td style="text-align:right;"><strong>' . h((string)$cbSmenyCount) . '</strong></td><td style="text-align:right;">' . h($cbSmenyDate) . '</td></tr>'
    . '<tr style="border:0;"><td style="padding:0; border:0;">Reporty</td><td style="text-align:right;"><strong>0</strong></td><td style="text-align:right;">Ne</td></tr>'
    . '</tbody>'
    . '</table>'
    . '</div>';

ob_start();
?>
<table class="table" style="width:100%; margin:0;">
  <thead>
    <tr>
      <th style="text-align:left;">script</th>
      <th style="text-align:left;">popis</th>
      <th style="text-align:left;">akce</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>inicializace/plnime_smeny_plan.php</td>
      <td>stáhne naplánované směny</td>
      <td>
        <?php if ($cbSmenyPlanMaData): ?>
          <span style="color:#c62828; font-weight:700;">DATA!</span>
        <?php else: ?>
          <form method="get" action="<?= h(cb_url('/inicializace/plnime_smeny_plan.php')) ?>" style="margin:0;">
            <button type="submit" class="btn btn-primary">Spustit</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  </tbody>
</table>
<?php
$card_max_html = (string)ob_get_clean();

// karty/admin_inicializace.php * Verze: V9 * Aktualizace: 26.03.2026
// počet řádků 74
?>
