<?php
// includes/hlavicka/head_user.php * Verze: V3 * Aktualizace: 07.03.2026
declare(strict_types=1);
?>
<div class="head_user"
     data-timeout-min="<?= h((string)$cbTimeoutMin) ?>"
     data-start-ts="<?= h((string)$cbStartTs) ?>"
     data-last-ts="<?= h((string)$cbLastTs) ?>"
     data-logout-url="<?= h(cb_url('lib/logout.php')) ?>"
     data-touch-url="<?= h(cb_url('index.php')) ?>">

  <div class="head_user_col head_user_col--left">
    <div class="head_user_name"><strong><?= h($cbUserName) ?></strong></div>
    <div class="head_user_lab">Poslední přístup:</div>
    <div class="head_user_lab">Přihlášení:</div>
    <div class="head_user_lab">Seance/zbývá:</div>
  </div>

  <div class="head_user_col head_user_col--right">
    <div class="head_user_role"><?= h($cbUserRole) ?></div>
    <div class="head_user_val"><?= h($cbLastLoginText) ?></div>
    <div class="head_user_val"><?= h($cbLoginStatsText) ?></div>
    <div class="head_user_val cb-session-combo"><?= h($cbSessionComboText) ?></div>
    <div class="head_user_val cb-session-thermo" data-thermo="<?= h((string)$cbThermoPct) ?>" style="--thermo:<?= h((string)$cbThermoPct) ?>%">&nbsp;</div>
  </div>

  <a class="head_user_exit tip_box tip_box_head" data-tip="Odhlásit" href="<?= h(cb_url('lib/logout.php')) ?>" aria-label="Odhlásit">
    <svg class="head_user_exit_ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2" /><path d="M9 12h12l-3 -3" /><path d="M18 15l3 -3" /></svg>
  </a>
</div>
