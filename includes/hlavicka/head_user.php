<?php
// includes/hlavicka/head_user.php * Verze: V3 * Aktualizace: 07.03.2026
declare(strict_types=1);
?>
<div class="head_user ram_hlavicka zaobleni_10 odstup_vnitrni_0 displ_grid"
     data-timeout-min="<?= h((string)$cbTimeoutMin) ?>"
     data-start-ts="<?= h((string)$cbStartTs) ?>"
     data-last-ts="<?= h((string)$cbLastTs) ?>"
     data-logout-url="<?= h(cb_url('index.php?action=logout')) ?>"
     data-touch-url="<?= h(cb_url('index.php')) ?>">

  <div class="head_user_col head_user_col--left txt_l gap_6 displ_flex flex_sloupec">
    <div class="head_user_name text_12"><strong><?= h($cbUserName) ?></strong></div>
    <div class="head_user_lab text_11 radek_1_15">Poslední přístup:</div>
    <div class="head_user_lab text_11 radek_1_15">Přihlášení:</div>
    <div class="head_user_lab text_11 radek_1_15">Seance/zbývá:</div>
  </div>

  <div class="head_user_col head_user_col--right txt_l gap_6 displ_flex flex_sloupec">
    <div class="head_user_role txt_seda text_12"><?= h($cbUserRole) ?></div>
    <div class="head_user_val txt_seda text_11 radek_1_15"><?= h($cbLastLoginText) ?></div>
    <div class="head_user_val txt_seda text_11 radek_1_15"><?= h($cbLoginStatsText) ?></div>
    <div class="head_user_val txt_seda text_11 radek_1_15 cb-session-combo"><?= h($cbSessionComboText) ?></div>
    <div class="head_user_val txt_seda text_11 radek_1_15 cb-session-thermo odstup_vnitrni_0" data-thermo="<?= h((string)$cbThermoPct) ?>" style="--thermo:<?= h((string)$cbThermoPct) ?>%">&nbsp;</div>
  </div>

  <a class="head_user_exit cursor_ruka bg_bila zaobleni_6 displ_flex jc_stred" href="<?= h(cb_url('index.php?action=logout')) ?>" aria-label="Odhlásit">
    <svg class="head_user_exit_ico displ_block" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
  </a>
</div>
