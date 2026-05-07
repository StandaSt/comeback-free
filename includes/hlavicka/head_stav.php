<?php
// includes/hlavicka/head_stav.php * Verze: V3 * Aktualizace: 08.03.2026
declare(strict_types=1);
?>
<div class="head_sys ram_hlavicka zaobleni_10 gap_6 displ_grid" aria-label="Stav syst&eacute;mu">
  <div class="head_sys_title txt_seda text_tucny text_12">Syst&eacute;m:</div>

  <div class="head_sys_list gap_4 displ_flex flex_sloupec">
    <span class="head_sys_item text_11 gap_6 displ_flex"><span class="head_sys_lab text_11 radek_1_1">DB</span><span class="head_led is-<?= h($sysDb) ?>" aria-hidden="true"></span></span>
    <span class="head_sys_item text_11 gap_6 displ_flex"><span class="head_sys_lab text_11 radek_1_1">Sm&#283;ny</span><span class="head_led is-<?= h($sysSmeny) ?>" aria-hidden="true"></span></span>
    <span class="head_sys_item text_11 gap_6 displ_flex"><span class="head_sys_lab text_11 radek_1_1">Restia</span><span class="head_led is-<?= h($sysRestia) ?>" aria-hidden="true"></span></span>
  </div>
</div>
