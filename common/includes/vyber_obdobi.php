<?php
/*
 * Ucel souboru: Vykresli ovladac globalniho vyberu obdobi v hlavicce aplikace.
 * Ocekava pripraveny model obdobi z common/lib/obdobi_vyber.php.
 */
declare(strict_types=1);

// Pripravi datumove hodnoty a volby casu pouze pro zobrazeni ovladace.
$cbObdobiOdInput = '';
$cbObdobiDoInput = '';
try {
    $cbObdobiOdInput = (new DateTimeImmutable((string)$cbObdobiOd))->format('Y-m-d');
    $cbObdobiDoInput = (new DateTimeImmutable((string)$cbObdobiDo))->format('Y-m-d');
} catch (Throwable $e) {
    $cbObdobiOdInput = '';
    $cbObdobiDoInput = '';
}
$cbObdobiCasOptions = [];
for ($index = 0; $index < 48; $index++) {
    $totalMinutes = (6 * 60) + ($index * 30);
    $hour = intdiv($totalMinutes, 60) % 24;
    $minute = $totalMinutes % 60;
    $value = sprintf('%02d:%02d', $hour, $minute);
    $cbObdobiCasOptions[] = ['value' => $value, 'label' => (string)$hour . ':' . sprintf('%02d', $minute)];
}
$cbManualSaveDelaySec = intdiv((int)$cbProdlevaMs, 1000);

// Vytvori kratky souhrn aktualne zvoleneho obdobi pro tlacitko.
$cbObdobiSummary = 'Období není zvoleno';
try {
    $summaryOd = new DateTimeImmutable((string)$cbObdobiOd);
    $summaryDo = new DateTimeImmutable((string)$cbObdobiDo);
    $cbObdobiSummary = $summaryOd->format('j.n.Y H:i') . ' - ' . $summaryDo->format('j.n.Y H:i');
} catch (Throwable $e) {
    $cbObdobiSummary = 'Období není zvoleno';
}
$cbVyberObdobiSaveUrl = cb_root_url('index.php');
?>
<!-- Globalni ovladac pro vyber obdobi. -->
<div
  class="head_select head_select--period"
  data-cb-period-root="1"
  data-save-url="<?= h($cbVyberObdobiSaveUrl) ?>"
  data-active-mode="<?= h($cbObdobiMode) ?>"
  data-manual-save-delay-ms="<?= (int)$cbProdlevaMs ?>"
>
  <button type="button" class="head_period_btn" data-cb-period-toggle="1" aria-expanded="false">
    <span class="head_block_text head_block_text_inline">
      <span class="head_block_label head_block_label_inline">Období:</span>
      <span class="head_block_value" data-cb-period-summary="1"><?= h($cbObdobiSummary) ?></span>
    </span>
    <span class="head_block_chev" aria-hidden="true">⌄</span>
  </button>
  <div class="head_period_panel ram_normal bg_bila zaobleni_10 odstup_vnitrni_10 is-hidden" data-cb-period-panel="1">
    <div class="head_interval gap_4 displ_flex flex_sloupec jc_stred" aria-label="Nastavení období">
      <div class="head_int_row displ_grid">
        <label class="head_date text_11 gap_6 displ_flex">
          <span class="head_date_label">Od</span>
          <input class="head_date_input text_11 zaobleni_8 ram_ovladace" type="date" id="cbObdobiOd" value="<?= h($cbObdobiOdInput) ?>">
          <select class="head_time_select text_11 zaobleni_8 ram_ovladace" id="cbObdobiOdCas" aria-label="Čas od">
            <?php foreach ($cbObdobiCasOptions as $option): ?>
              <option value="<?= h($option['value']) ?>"<?= $option['value'] === '06:00' ? ' selected' : '' ?>><?= h($option['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="head_quick gap_4 displ_grid">
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="dnes">Dnes</button>
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="vcera">Včera</button>
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="tyden">Týden</button>
        </div>
      </div>
      <div class="head_int_row displ_grid">
        <label class="head_date text_11 gap_6 displ_flex">
          <span class="head_date_label">Do</span>
          <input class="head_date_input text_11 zaobleni_8 ram_ovladace" type="date" id="cbObdobiDo" value="<?= h($cbObdobiDoInput) ?>">
          <select class="head_time_select text_11 zaobleni_8 ram_ovladace" id="cbObdobiDoCas" aria-label="Čas do">
            <?php foreach ($cbObdobiCasOptions as $option): ?>
              <option value="<?= h($option['value']) ?>"<?= $option['value'] === '06:00' ? ' selected' : '' ?>><?= h($option['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="head_quick gap_4 displ_grid">
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="mesic">Měsíc</button>
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="rok">Rok</button>
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_bila zaobleni_8 text_11" data-range="vse">Vše</button>
        </div>
      </div>
      <div class="head_interval_meter" aria-hidden="true"><span class="head_interval_meter_bar"></span></div>
      <div class="head_prodleva" data-cb-prodleva-root="1">
        <div class="head_prodleva_top">
          <span class="head_prodleva_label">Prodleva uložení</span>
          <span class="head_prodleva_value" data-cb-prodleva-value="1"><?= h((string)$cbManualSaveDelaySec) ?> sec.</span>
        </div>
        <label class="head_prodleva_slider">
          <span>1 sec.</span>
          <input type="range" min="1" max="10" step="1" value="<?= h((string)$cbManualSaveDelaySec) ?>" data-cb-prodleva-range="1" aria-label="Prodleva uložení období">
          <span>10 sec.</span>
        </label>
      </div>
    </div>
  </div>
</div>
