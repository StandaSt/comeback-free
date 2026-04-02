<?php
// includes/hlavicka/head_pobocka.php * Verze: V3 * Aktualizace: 02.04.2026
declare(strict_types=1);

$cbAllowedPobocky = is_array($cbPobocky ?? null) ? $cbPobocky : [];
$cbAllowedCount = count($cbAllowedPobocky);
$cbAllowedById = [];
$cbAllowedOblasti = [];
$cbAllowedPobockyById = $cbAllowedPobocky;
usort($cbAllowedPobockyById, static function (array $a, array $b): int {
    return (int)($a['id_pob'] ?? 0) <=> (int)($b['id_pob'] ?? 0);
});

foreach ($cbAllowedPobocky as $p) {
    $id = (int)($p['id_pob'] ?? 0);
    $nazev = trim((string)($p['nazev'] ?? ''));
    $oblast = trim((string)($p['oblast'] ?? ''));
    if ($id <= 0 || $nazev === '') {
        continue;
    }
    if ($oblast === '') {
        $oblast = 'Nezarazeno';
    }
    $cbAllowedById[$id] = ['nazev' => $nazev, 'oblast' => $oblast];
    if (!isset($cbAllowedOblasti[$oblast])) {
        $cbAllowedOblasti[$oblast] = [];
    }
    $cbAllowedOblasti[$oblast][] = $id;
}
ksort($cbAllowedOblasti);

$cbSelectedIdMap = [];
foreach ((array)($cbSelectedPobocky ?? []) as $sid) {
    $sid = (int)$sid;
    if ($sid > 0 && isset($cbAllowedById[$sid])) {
        $cbSelectedIdMap[$sid] = true;
    }
}
$cbSelectedIds = array_keys($cbSelectedIdMap);
sort($cbSelectedIds);

$cbSelectedOblasti = [];
$rawSelectedOblasti = $_SESSION['selected_oblasti'] ?? [];
if (is_array($rawSelectedOblasti)) {
    foreach ($rawSelectedOblasti as $oblast) {
        $oblast = trim((string)$oblast);
        if ($oblast !== '' && isset($cbAllowedOblasti[$oblast])) {
            $cbSelectedOblasti[$oblast] = true;
        }
    }
}
$cbSelectedOblasti = array_keys($cbSelectedOblasti);
sort($cbSelectedOblasti);

$cbSelectedModeLocal = trim((string)($_SESSION['selected_pobocky_mode'] ?? ''));
if (!in_array($cbSelectedModeLocal, ['single', 'area', 'custom'], true)) {
    $cbSelectedModeLocal = ($cbSelectedIds ? 'single' : '');
}

// Poradi na tlacitku stejny jako poradi v panelu (id_pob ASC).
$cbSelectedNames = [];
foreach ($cbAllowedPobockyById as $p) {
    $id = (int)($p['id_pob'] ?? 0);
    if ($id > 0 && isset($cbSelectedIdMap[$id])) {
        $cbSelectedNames[] = trim((string)($p['nazev'] ?? ''));
    }
}

$cbCanUseArea = ((int)($cbUserRoleId ?? 0) !== 3);

$cbPobLabel = 'Pobocka';
$cbPobTitle = 'Vyberte pobocky';
$cbSelectedCount = count($cbSelectedNames);
$cbAllSelected = ($cbAllowedCount > 1 && $cbSelectedCount === $cbAllowedCount);

if ($cbAllSelected) {
    $cbPobLabel = 'Pobočky: zvoleny všechny';
    $cbPobTitle = implode(', ', $cbSelectedNames);
} elseif ($cbSelectedCount === 1) {
    $cbPobLabel = $cbSelectedNames[0];
    $cbPobTitle = $cbSelectedNames[0];
} elseif ($cbSelectedCount > 1) {
    $full = implode(', ', $cbSelectedNames);
    $cbPobLabel = $full;
    $cbPobTitle = $full;

    if (mb_strlen($full, 'UTF-8') > 68) {
        $keep = array_slice($cbSelectedNames, 0, 3);
        $more = array_slice($cbSelectedNames, 3);
        $cbPobLabel = implode(', ', $keep) . ' + ' . count($more) . ' dalsi';
        $cbPobTitle = implode(', ', $more);
    }
}
?>
<div
  class="head_branch_wrap zaobleni_10 displ_flex"
  aria-label="Výběr poboček"
  data-cb-select-pobocky-root="1"
  data-save-url="<?= h(cb_url('index.php')) ?>"
  data-cb-pob-header="1"
>
  <?php if ($cbAllowedCount <= 1): ?>
    <span class="head_branch_btn_static ram_ovladace txt_seda bg_bila zaobleni_8 text_12 vyska_24" title="<?= h($cbPobTitle) ?>"><?= h($cbPobLabel) ?></span>
  <?php else: ?>
    <button
      type="button"
      class="head_branch_btn ram_ovladace txt_seda bg_bila zaobleni_8 text_12 vyska_24"
      data-cb-pob-toggle="1"
      title="<?= h($cbPobTitle) ?>"
      aria-label="<?= h($cbPobTitle) ?>"
    >
      <?= h($cbPobLabel) ?>
    </button>

      <div class="head_branch_panel ram_normal bg_bila zaobleni_10 odstup_vnitrni_10 is-hidden" data-cb-pob-panel="1">
      <div class="head_branch_panel_grid<?= $cbCanUseArea ? '' : ' is-single-col' ?> displ_grid">
        <?php if ($cbCanUseArea): ?>
          <section class="head_branch_section">
            <h4 class="head_branch_section_title txt_seda text_12 odstup_vnejsi_0">Výběr podle oblasti</h4>
            <div class="head_branch_area_list">
              <?php foreach ($cbAllowedOblasti as $oblast => $oblastIds): ?>
                <?php $checked = ($cbSelectedModeLocal === 'area' && in_array($oblast, $cbSelectedOblasti, true)); ?>
                <label class="head_branch_field text_11 gap_4 displ_flex">
                  <span>
                    <input type="checkbox" class="cb-pob-area" value="<?= h($oblast) ?>"<?= $checked ? ' checked' : '' ?>>
                    <?= h($oblast) ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <label class="head_branch_field head_branch_field_select_all text_11 gap_4 displ_flex">
              <span>
                <input type="checkbox" class="cb-pob-area-all" value="1">
                Vybrat vše
              </span>
            </label>
          </section>
        <?php endif; ?>

        <section class="head_branch_section">
          <h4 class="head_branch_section_title txt_seda text_12 odstup_vnejsi_0">Výběr jednotlivých poboček</h4>
          <?php foreach ($cbAllowedPobockyById as $p): ?>
            <?php
            $id = (int)($p['id_pob'] ?? 0);
            if ($id <= 0 || !isset($cbAllowedById[$id])) {
                continue;
            }
            $checked = ($cbSelectedModeLocal === 'custom' && isset($cbSelectedIdMap[$id]))
                || ($cbSelectedModeLocal === 'single' && isset($cbSelectedIdMap[$id]));
            ?>
            <label class="head_branch_field text_11 gap_4 displ_flex">
              <span>
                <input type="checkbox" class="cb-pob-branch" value="<?= $id ?>"<?= $checked ? ' checked' : '' ?>>
                <?= h((string)$cbAllowedById[$id]['nazev']) ?> (<?= h((string)$cbAllowedById[$id]['oblast']) ?>)
              </span>
            </label>
          <?php endforeach; ?>
        </section>
      </div>

      <div class="head_branch_actions displ_flex">
        <button type="button" class="head_branch_save_btn card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-pob-save="1">Uložit výběr</button>
      </div>
    </div>
  <?php endif; ?>
</div>
