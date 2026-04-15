<?php
// funkce/zobraz_kartu.php * Verze: V1 * Aktualizace: 15.04.2026
declare(strict_types=1);


function cb_zobraz_kartu(array $pripravenaKarta): string
{
    // Rozlišení režimu
    $isNano = isset($pripravenaKarta['col'], $pripravenaKarta['line'])
        && (int)$pripravenaKarta['col'] === 1
        && (int)$pripravenaKarta['line'] > 0
        && empty($pripravenaKarta['minHtml']);
    $cardId = (int)($pripravenaKarta['cardId'] ?? 0);
    $cardPoradi = (int)($pripravenaKarta['cardPoradi'] ?? 0);
    $title = (string)($pripravenaKarta['title'] ?? '');
    $role = (int)($pripravenaKarta['role'] ?? 3);
    $color = (string)($pripravenaKarta['color'] ?? '');
    $iconFile = (string)($pripravenaKarta['iconFile'] ?? '');
    $subtitleMin = !$isNano ? (string)($pripravenaKarta['subtitleMin'] ?? '') : '';
    $subtitleMax = !$isNano ? (string)($pripravenaKarta['subtitleMax'] ?? '') : '';
    $minHtml = !$isNano ? (string)($pripravenaKarta['minHtml'] ?? '') : '';
    $col = isset($pripravenaKarta['col']) ? (int)$pripravenaKarta['col'] : 1;
    $line = isset($pripravenaKarta['line']) ? (int)$pripravenaKarta['line'] : 1;

    $hasCardIcon = ($iconFile !== '');
    $cardIconSrc = $hasCardIcon ? cb_url('/img/card_icons/' . ltrim($iconFile, '/')) : '';
    $cardTopStyle = $color !== '' ? 'background:' . h($color) . ';' : '';
    $cardTopRoleClass = ($role === 1) ? ' card_top_role_1' : (($role === 2) ? ' card_top_role_2' : '');
    $cardClass = 'dash_card bg_bila card_blue zaobleni_12' . ($isNano ? ' card_mode_nano' : '');
    $cardLineHeightClass = $isNano ? ' radek_1_1' : ' radek_1_15';
    $gridStyle = $isNano
        ? ('grid-column:1;grid-row:' . $line . ';')
        : ($col > 0 && $line > 0 ? 'grid-column:' . $col . ';grid-row:' . $line . ';' : '');
    ob_start();
?>
<section class="<?= h($cardClass) ?>" data-cb-dash-card="1" style="<?= h($gridStyle) ?>">
  <article class="card_shell<?= h($cardLineHeightClass) ?> odstup_vnitrni_0"
    data-card-id="<?= h((string)$cardId) ?>"
    data-card-title="<?= h($title) ?>"
    data-card-mode="<?= $isNano ? 'nano' : 'mini' ?>"
    data-card-col="<?= h((string)$col) ?>"
    data-card-line="<?= h((string)$line) ?>">
    <div class="card_top<?= h($cardTopRoleClass) ?> gap_10 odstup_vnitrni_10 displ_flex jc_mezi"<?= $cardTopStyle !== '' ? ' style="' . h($cardTopStyle) . '"' : '' ?>>
      <div class="card_head_left displ_flex">
        <div class="card_pref_wrap" data-card-pref-wrap="1">
          <button type="button" class="card_pref_toggle cursor_ruka bg_bila" data-card-pref-toggle="1" aria-haspopup="true" aria-expanded="false" title="Nastavení karty">
            <?php if ($hasCardIcon): ?>
              <span class="card_pref_icon"><img src="<?= h((string)$cardIconSrc) ?>" class="card_pref_icon_img" alt=""></span>
            <?php elseif ($isNano): ?>
              <span class="card_pref_empty" aria-hidden="true"></span>
            <?php else: ?>
              <span class="card_pref_dots txt_seda">&#8942;</span>
            <?php endif; ?>
            <span class="card_pref_kid">K<?= h((string)$cardId) ?>/<?= h((string)$cardPoradi) ?></span>
          </button>
        </div>
        <div class="card_head_text">
          <h3 class="card_title txt_seda text_15 odstup_vnejsi_0"><?= h($title) ?></h3>
          <?php if (!$isNano): ?>
            <p
              class="card_subtitle text_12"
              data-card-subtitle="1"
              data-subtitle-min="<?= h($subtitleMin) ?>"
              data-subtitle-max="<?= h($subtitleMax) ?>"
            ><?= h($subtitleMin) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <div class="card_tools gap_4 displ_flex flex_sloupec">
        <?php if ($isNano): ?>
          <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-nano-target="mini" title="Prepnout na mini">&#8722;</button>
        <?php else: ?>
          <button type="button" class="card_tool_btn cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-toggle="1" aria-expanded="false" title="Prepnout na maxi/mini">&#10530;</button>
          <button type="button" class="card_tool_btn only-mini cursor_ruka txt_seda bg_bila zaobleni_8 text_14 card_mode_btn odstup_vnitrni_0 displ_inline_flex" data-card-to-nano="1" title="Prepnout na nano">&bull;</button>
        <?php endif; ?>
      </div>
    </div>
    <div class="card_body odstup_vnejsi_0">
      <div class="card_min card_compact odstup_vnitrni_10" data-card-compact>
        <?= $minHtml ?>
      </div>
    </div>
  </article>
</section>
<?php
    return (string)ob_get_clean();
}

