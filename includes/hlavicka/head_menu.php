<?php
// includes/hlavicka/head_menu.php * Verze: V4 * Aktualizace: 08.03.2026
?>
<div class="head_menu" role="navigation" aria-label="Hlavni menu">
  <!-- Tri hlavni sekce menu volane pres AJAX router CB_MENU -->
  <button type="button" class="head_menu_btn is-on" data-page="home" onclick="if(window.CB_MENU){CB_MENU.goPage('home');}">Home</button>
  <button type="button" class="head_menu_btn" data-page="manager" onclick="if(window.CB_MENU){CB_MENU.goPage('manager');}">manager</button>
  <button type="button" class="head_menu_btn" data-page="admin_dashboard" onclick="if(window.CB_MENU){CB_MENU.goPage('admin_dashboard');}">admin</button>
</div>
