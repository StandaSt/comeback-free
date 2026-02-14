<?php
// includes/menu_s.php * Verze: V21 * Aktualizace: 13.2.2026
declare(strict_types=1);

if (defined('COMEBACK_MENU_S_RENDERED')) {
    return;
}
define('COMEBACK_MENU_S_RENDERED', true);

/*
 * Sidebar menu (2 úrovně) – router verze
 * - obsluha je rozdělená do více JS souborů (lib/menu_*.js), pořád pod window.CB_MENU
 * - tento soubor řeší jen umístění (vertikální) + HTML kotvy
 *
 * SVG tlačítka (HOME + přepínače režimu):
 * - společně v includes/tlacitka_svg.php
 * - variantu určuje $CB_MENU_VARIANTA = 'sidebar'
 */
?>
<div class="menu menu-sidebar">
  <div class="menu-area">
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
  const sidebarEl = document.getElementById('sidebar');
  if (!sidebarEl || !window.CB_MENU) return;

  const btnHome = document.getElementById('cbMenuHome');
  const btnToDropdown = document.getElementById('menuToDropdown');

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

  if (window.CB_MENU.initSidebar) {
    window.CB_MENU.initSidebar(sidebarEl, { closeDelay: 180, gap: 8 });
  }
})();
</script>

<?php
/* includes/menu_s.php * Verze: V21 * počet řádků 99 * Aktualizace: 13.2.2026 */
 // Konec souboru