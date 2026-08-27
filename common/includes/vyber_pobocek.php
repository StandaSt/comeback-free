<?php
/*
 * Ucel souboru: Vykresli ovladac globalniho vyberu pobocek v hlavicce aplikace.
 * Komponenta si nacte povolene pobocky prihlaseneho uzivatele a pripravi stav ovladace.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/pobocky_vyber.php';

// Nacte povolene pobocky a aktualni globalni vyber pro tento ovladac.
$cbPobocky = [];
$cbSelectedPobocky = get_selected_pobocky();
$cbSelectedMode = trim((string)($_SESSION['selected_pobocky_mode'] ?? ''));
$cbPobockaMultiFromCard = in_array($cbSelectedMode, ['area', 'custom'], true);
$cbPobockaId = !$cbPobockaMultiFromCard && $cbSelectedPobocky ? (int)$cbSelectedPobocky[0] : 0;
$cbPobockySelectUser = $_SESSION['cb_user'] ?? [];
if (!empty($_SESSION['login_ok']) && is_array($cbPobockySelectUser)) {
    try {
        $cbPobocky = cb_pobocky_get_allowed_rows_for_user((int)($cbPobockySelectUser['id_user'] ?? 0));
    } catch (Throwable $e) {
        $cbPobocky = [];
    }
}

// Zajisti platny vychozi vyber, pokud uzivatel nema zvolenou povolenou pobocku.
if ($cbPobocky && !$cbPobockaMultiFromCard) {
    $cbPobockaExists = false;
    foreach ($cbPobocky as $cbPobocka) {
        if ((int)$cbPobocka['id_pob'] === $cbPobockaId) {
            $cbPobockaExists = true;
            break;
        }
    }
    if (!$cbPobockaExists) {
        cb_pobocky_set_selected([(int)$cbPobocky[0]['id_pob']]);
        $cbSelectedPobocky = get_selected_pobocky();
    }
}

// Pripravi seznam oblasti, vybrane hodnoty a text zobrazeny na tlacitku.
$cbAllowedPobocky = $cbPobocky;
$cbAllowedCount = count($cbAllowedPobocky);
$cbAllowedById = [];
$cbAllowedOblasti = [];
$cbAllowedPobockyById = $cbAllowedPobocky;
usort($cbAllowedPobockyById, static function (array $a, array $b): int {
    return (int)($a['id_pob'] ?? 0) <=> (int)($b['id_pob'] ?? 0);
});

foreach ($cbAllowedPobocky as $cbPobocka) {
    $id = (int)($cbPobocka['id_pob'] ?? 0);
    $nazev = trim((string)($cbPobocka['nazev'] ?? ''));
    $oblast = trim((string)($cbPobocka['oblast'] ?? ''));
    if ($id <= 0 || $nazev === '') {
        continue;
    }
    $oblast = $oblast !== '' ? $oblast : 'Nezarazeno';
    $cbAllowedById[$id] = ['nazev' => $nazev, 'oblast' => $oblast];
    $cbAllowedOblasti[$oblast][] = $id;
}
ksort($cbAllowedOblasti);

$cbSelectedIdMap = [];
foreach ($cbSelectedPobocky as $sid) {
    $sid = (int)$sid;
    if ($sid > 0 && isset($cbAllowedById[$sid])) {
        $cbSelectedIdMap[$sid] = true;
    }
}
$cbSelectedIds = array_keys($cbSelectedIdMap);
sort($cbSelectedIds);

$cbSelectedOblasti = [];
foreach ((array)($_SESSION['selected_oblasti'] ?? []) as $oblast) {
    $oblast = trim((string)$oblast);
    if ($oblast !== '' && isset($cbAllowedOblasti[$oblast])) {
        $cbSelectedOblasti[$oblast] = true;
    }
}
$cbSelectedOblasti = array_keys($cbSelectedOblasti);
sort($cbSelectedOblasti);

$cbSelectedModeLocal = in_array($cbSelectedMode, ['single', 'area', 'custom'], true)
    ? $cbSelectedMode
    : ($cbSelectedIds ? 'single' : '');
$cbSelectedNames = [];
foreach ($cbAllowedPobockyById as $cbPobocka) {
    $id = (int)($cbPobocka['id_pob'] ?? 0);
    if ($id > 0 && isset($cbSelectedIdMap[$id])) {
        $cbSelectedNames[] = trim((string)($cbPobocka['nazev'] ?? ''));
    }
}

$cbCanUseArea = ($cbAllowedCount > 1);
$cbPobLabel = 'Pobocka';
$cbPobTitle = 'Vyberte pobocky';
$cbSelectedCount = count($cbSelectedNames);
$cbAllSelected = ($cbAllowedCount > 1 && $cbSelectedCount === $cbAllowedCount);
if ($cbAllSelected) {
    $cbPobLabel = 'zvoleny všechny';
    $cbPobTitle = implode(', ', $cbSelectedNames);
} elseif ($cbSelectedCount === 1) {
    $cbPobLabel = $cbSelectedNames[0];
    $cbPobTitle = $cbSelectedNames[0];
} elseif ($cbSelectedCount > 1) {
    $cbPobTitle = implode(', ', $cbSelectedNames);
    $cbPobLabel = $cbPobTitle;
    if (mb_strlen($cbPobTitle, 'UTF-8') > 68) {
        $cbPobLabel = implode(', ', array_slice($cbSelectedNames, 0, 3)) . ' + ' . count(array_slice($cbSelectedNames, 3)) . ' dalsi';
    }
}
$cbPobockySelectSaveUrl = cb_root_url('index.php');
?>
<!-- Globalni ovladac pro vyber pobocek. -->
<div
  class="head_select head_select--branch"
  aria-label="Výběr poboček"
  data-cb-select-pobocky-root="1"
  data-save-url="<?= h($cbPobockySelectSaveUrl) ?>"
  data-cb-pob-header="1"
>
  <?php if ($cbAllowedCount <= 1): ?>
    <span class="head_branch_btn_static" title="<?= h($cbPobTitle) ?>">
      <span class="head_block_text head_block_text_inline">
        <span class="head_block_label head_block_label_inline">Pobočky:</span>
        <span class="head_block_value"><?= h($cbPobLabel) ?></span>
      </span>
      <span class="head_block_chev" aria-hidden="true">⌄</span>
    </span>
  <?php else: ?>
    <button
      type="button"
      class="head_branch_btn"
      data-cb-pob-toggle="1"
      title="<?= h($cbPobTitle) ?>"
      aria-label="<?= h($cbPobTitle) ?>"
    >
      <span class="head_block_text head_block_text_inline">
        <span class="head_block_label head_block_label_inline">Pobočky:</span>
        <span class="head_block_value"><?= h($cbPobLabel) ?></span>
      </span>
      <span class="head_block_chev" aria-hidden="true">⌄</span>
    </button>
    <div class="head_branch_panel ram_normal bg_bila zaobleni_10 odstup_vnitrni_10 is-hidden" data-cb-pob-panel="1">
      <div class="head_branch_panel_grid<?= $cbCanUseArea ? '' : ' is-single-col' ?> displ_grid">
        <?php if ($cbCanUseArea): ?>
          <section class="head_branch_section">
            <h4 class="head_branch_section_title txt_seda text_12 odstup_vnejsi_0">Výběr podle oblasti</h4>
            <div class="head_branch_area_list">
              <?php foreach ($cbAllowedOblasti as $oblast => $oblastIds): ?>
                <label class="head_branch_field text_11 gap_4 displ_flex">
                  <span>
                    <input type="checkbox" class="cb-pob-area" value="<?= h($oblast) ?>"<?= $cbSelectedModeLocal === 'area' && in_array($oblast, $cbSelectedOblasti, true) ? ' checked' : '' ?>>
                    <?= h($oblast) ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
            <label class="head_branch_field head_branch_field_select_all text_11 gap_4 displ_flex">
              <span class="head_branch_select_all_text"><input type="checkbox" class="cb-pob-area-all" value="1"> Vybrat vše</span>
            </label>
          </section>
        <?php endif; ?>
        <section class="head_branch_section">
          <h4 class="head_branch_section_title txt_seda text_12 odstup_vnejsi_0">Výběr jednotlivých poboček</h4>
          <?php foreach ($cbAllowedPobockyById as $cbPobocka): ?>
            <?php
            $id = (int)($cbPobocka['id_pob'] ?? 0);
            if ($id <= 0 || !isset($cbAllowedById[$id])) {
                continue;
            }
            $checked = isset($cbSelectedIdMap[$id]) && in_array($cbSelectedModeLocal, ['single', 'custom'], true);
            ?>
            <label class="head_branch_field text_11 gap_4 displ_flex">
              <span>
                <input
                  type="checkbox"
                  class="cb-pob-branch"
                  value="<?= $id ?>"
                  data-cb-name="<?= h((string)$cbAllowedById[$id]['nazev']) ?>"
                  data-cb-oblast="<?= h((string)$cbAllowedById[$id]['oblast']) ?>"
                  <?= $checked ? ' checked' : '' ?>
                >
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
