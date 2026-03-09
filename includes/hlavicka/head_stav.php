<?php
// includes/hlavicka/head_stav.php * Verze: V3 * Aktualizace: 08.03.2026
declare(strict_types=1);
?>
<div class="head_sys" aria-label="Stav syst&eacute;mu">
  <div class="head_sys_title">Stav syst&eacute;mu</div>

  <div class="head_sys_line">
    <span class="head_sys_item"><span class="head_sys_lab">DB</span><span class="head_led is-<?= h($sysDb) ?>" aria-hidden="true"></span></span>
    <span class="head_sys_item"><span class="head_sys_lab">Sm&#283;ny</span><span class="head_led is-<?= h($sysSmeny) ?>" aria-hidden="true"></span></span>
    <span class="head_sys_item"><span class="head_sys_lab">Restia</span><span class="head_led is-<?= h($sysRestia) ?>" aria-hidden="true"></span></span>
  </div>

  <button type="button" class="head_tech tip_box tip_box_head" data-tip="TECH" aria-label="TECH">
    <span aria-hidden="true">&#9881;</span>
  </button>
</div>