<?php
// includes/menu_d.php * Verze: V14 * Aktualizace: 29.1.2026
declare(strict_types=1);

if (defined('COMEBACK_MENU_D_RENDERED')) return;
define('COMEBACK_MENU_D_RENDERED', true);

/*
 * Dropdown menu (2 úrovně) – router verze
 * - obsluha (otevírání/zavírání/router) je v lib/menu_obsluha.js
 * - tento soubor řeší jen umístění (horizontální) + HTML kotvy
 *
 * SVG tlačítka (HOME + přepínače režimu):
 * - společně v includes/tlacitka_svg.php
 * - variantu určuje $CB_MENU_VARIANTA = 'dropdown'
 */
?>
<div class="cb-menu cb-menu--dropdown">
  <div class="cb-dropdown-bar">
    <div class="cb-menu-top">

      <?php
      $CB_MENU_VARIANTA = 'dropdown';
      require __DIR__ . '/tlacitka_svg.php';
      ?>

      <div class="dd-row" id="dropdown"></div>

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

// menu_obsluha.js – společná obsluha pro obě menu
if (!defined('COMEBACK_MENU_OBSLUHA_JS_INCLUDED')) {
  define('COMEBACK_MENU_OBSLUHA_JS_INCLUDED', true);
  ?>
  <script src="<?= h(cb_url('lib/menu_obsluha.js')) ?>"></script>
  <?php
}
?>

<script>
(function () {
  const dropdownEl = document.getElementById('dropdown');
  if (!dropdownEl || !window.CB_MENU) return;

  // SVG tlačítka
  const btnHome = document.getElementById('cbMenuHome');
  const btnToSidebar = document.getElementById('cbMenuToSidebar');

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

  // Obsluha dropdownu (včetně timeru)
  if (window.CB_MENU.initDropdown) {
    window.CB_MENU.initDropdown(dropdownEl, { closeDelay: 180 });
  }
})();
</script>

<?php
// includes/menu_d.php * Verze: V14 * Aktualizace: 29.1.2026
// konec souboru
?>
