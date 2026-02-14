<?php
// includes/menu_d.php * Verze: V18 * Aktualizace: 13.2.2026
declare(strict_types=1);

if (defined('COMEBACK_MENU_D_RENDERED')) {
    return;
}
define('COMEBACK_MENU_D_RENDERED', true);

/*
 * Dropdown menu (2 úrovně) – router verze
 * - obsluha je rozdělená do více JS souborů (lib/menu_*.js), pořád pod window.CB_MENU
 * - tento soubor řeší jen umístění (horizontální) + HTML kotvy
 *
 * Ikony (HOME + přepínače režimu):
 * - jsou technicky mimo samotné menu (nejsou součást window.MENU)
 * - render řeší includes/tlacitka_svg.php
 * - zde je jen rozmístíme do 3sloupcového gridu: 80px | 1fr | 80px
 */
?>
<div class="menu menu-dropdown">

  <div class="menu-ddbar">

    <div class="menu-ddslot-left">
      <?php
      $CB_MENU_VARIANTA = 'dropdown';
      $CB_TLACITKA_SLOT = 'home';
      require __DIR__ . '/tlacitka_svg.php';
      ?>
    </div>

    <div class="menu-row" id="dropdown"></div>

    <div class="menu-ddslot-right">
      <?php
      $CB_MENU_VARIANTA = 'dropdown';
      $CB_TLACITKA_SLOT = 'switch';
      require __DIR__ . '/tlacitka_svg.php';
      ?>
    </div>

  </div>
</div>

<?php
// menu_data.js – zdroj dat pro menu (window.MENU)
if (!defined('COMEBACK_MENU_DATA_JS_INCLUDED')) {
    define('COMEBACK_MENU_DATA_JS_INCLUDED', true);
    ?>
    <script src="<?= h(cb_url('lib/menu_data.js')) ?>"></script>
    <?php
}

// nově: rozdělená obsluha menu (pořadí je důležité)
if (!defined('COMEBACK_MENU_AJAX_JS_INCLUDED')) {
    define('COMEBACK_MENU_AJAX_JS_INCLUDED', true);
    ?>
    <script src="<?= h(cb_url('lib/menu_ajax.js')) ?>"></script>
    <?php
}
if (!defined('COMEBACK_MENU_CORE_JS_INCLUDED')) {
    define('COMEBACK_MENU_CORE_JS_INCLUDED', true);
    ?>
    <script src="<?= h(cb_url('lib/menu_core.js')) ?>"></script>
    <?php
}
if (!defined('COMEBACK_MENU_DD_JS_INCLUDED')) {
    define('COMEBACK_MENU_DD_JS_INCLUDED', true);
    ?>
    <script src="<?= h(cb_url('lib/menu_dropdown.js')) ?>"></script>
    <?php
}
if (!defined('COMEBACK_MENU_SB_JS_INCLUDED')) {
    define('COMEBACK_MENU_SB_JS_INCLUDED', true);
    ?>
    <script src="<?= h(cb_url('lib/menu_sidebar.js')) ?>"></script>
    <?php
}
?>

<script>
(function () {
  const dropdownEl = document.getElementById('dropdown');
  if (!dropdownEl || !window.CB_MENU) return;

  const btnHome = document.getElementById('cbMenuHome');
  const btnToSidebar = document.getElementById('menuToSidebar');

  if (btnHome) {
    btnHome.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      window.CB_MENU.goPage('uvod');
    });
  }

  if (btnToSidebar) {
    btnToSidebar.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      window.CB_MENU.setMenuMode('sidebar');
    });
  }

  if (window.CB_MENU.initDropdown) {
    window.CB_MENU.initDropdown(dropdownEl, { closeDelay: 180 });
  }
})();
</script>

<?php
/* includes/menu_d.php * Verze: V18 * počet řádků 114 * Aktualizace: 13.2.2026 */
 // Konec souboru