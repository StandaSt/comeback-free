<?php
// includes/hlavicka/head_menu.php * Verze: V5 * Aktualizace: 09.03.2026
declare(strict_types=1);

$sekceAkt = (int)($cb_dashboard_sekce ?? 3);
if (!in_array($sekceAkt, [1, 2, 3], true)) {
    $sekceAkt = 3;
}

$roleId = (int)($cbUserRoleId ?? 9);
$showManager = ($roleId > 0 && $roleId <= 2);
$showAdmin = ($roleId === 1);
?>
<div class="head_menu" role="navigation" aria-label="Hlavni menu">
  <button type="button" class="head_menu_btn<?= $sekceAkt === 3 ? ' is-on' : '' ?>" data-sekce="3" onclick="if(window.CB_MENU){CB_MENU.goSekce('3');}">home</button>
  <?php if ($showManager): ?>
    <button type="button" class="head_menu_btn<?= $sekceAkt === 2 ? ' is-on' : '' ?>" data-sekce="2" onclick="if(window.CB_MENU){CB_MENU.goSekce('2');}">manager</button>
  <?php endif; ?>
  <?php if ($showAdmin): ?>
    <button type="button" class="head_menu_btn<?= $sekceAkt === 1 ? ' is-on' : '' ?>" data-sekce="1" onclick="if(window.CB_MENU){CB_MENU.goSekce('1');}">admin</button>
  <?php endif; ?>
</div>

