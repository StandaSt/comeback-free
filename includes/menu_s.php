<?php
// includes/menu_s.php * Verze: V19 * Aktualizace: 29.1.2026
declare(strict_types=1);

if (defined('COMEBACK_MENU_S_RENDERED')) return;
define('COMEBACK_MENU_S_RENDERED', true);

/*
 * Sidebar menu (2 úrovně) – router verze
 * - obsluha (otevírání/zavírání/router) je v lib/menu_obsluha.js
 * - tento soubor řeší jen umístění (vertikální) + HTML kotvy
 *
 * SVG tlačítka (HOME + přepínače režimu):
 * - společně v includes/tlacitka_svg.php
 * - variantu určuje $CB_MENU_VARIANTA = 'sidebar'
 */
?>
<div class="cb-menu cb-menu--sidebar">
  <div class="cb-sidebar-area">
    <div id="sidebar"></div>

    <?php
    $CB_MENU_VARIANTA = 'sidebar';
    require __DIR__ . '/tlacitka_svg.php';
    ?>
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
  const sidebarEl = document.getElementById('sidebar');
  if (!sidebarEl || !window.CB_MENU) return;

  // SVG tlačítka
  const btnHome = document.getElementById('cbMenuHome');
  const btnToDropdown = document.getElementById('cbMenuToDropdown');

  if (btnHome) {
    btnHome.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      window.CB_MENU.goPage('uvod');
    });
  }

  if (btnToDropdown) {
    btnToDropdown.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      window.CB_MENU.setMenuMode('dropdown');
    });
  }

  // Obsluha sidebaru (včetně overlay + timeru)
  if (window.CB_MENU.initSidebar) {
    window.CB_MENU.initSidebar(sidebarEl, { closeDelay: 180, gap: 8 });
  }
})();
</script>

<?php
// includes/menu_s.php * Verze: V19 * Aktualizace: 29.1.2026
// konec souboru
?>
