<?php
// includes/menu_d.php * Verze: V16 * Aktualizace: 6.2.2026 * Počet řádků: 101
declare(strict_types=1);

if (defined('COMEBACK_MENU_D_RENDERED')) return;
define('COMEBACK_MENU_D_RENDERED', true);

/*
 * Dropdown menu (2 úrovně) – router verze
 * - obsluha (otevírání/zavírání/router) je v lib/menu_obsluha.js
 * - tento soubor řeší jen umístění (horizontální) + HTML kotvy
 *
 * Ikony (HOME + přepínače režimu):
 * - jsou technicky mimo samotné menu (nejsou součást window.MENU)
 * - render řeší includes/tlacitka_svg.php
 * - zde je jen rozmístíme do 3sloupcového gridu: 80px | 1fr | 80px
 */
?>
<div class="menu menu-dropdown">

  <!-- RÁM DROPDOWNU: 80px | 1fr | 80px -->
  <div class="menu-ddbar">

    <!-- LEVÝ SLOT (80px): HOME -->
    <div class="menu-ddslot-left">
      <?php
      $CB_MENU_VARIANTA = 'dropdown';
      $CB_TLACITKA_SLOT = 'home';
      require __DIR__ . '/tlacitka_svg.php';
      ?>
    </div>

    <!-- STŘED (1fr): SEM JS VYRENDERUJE L1/L2 -->
    <div class="menu-row" id="dropdown"></div>

    <!-- PRAVÝ SLOT (80px): SWITCH -->
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

  // Obsluha dropdownu (včetně timeru)
  if (window.CB_MENU.initDropdown) {
    window.CB_MENU.initDropdown(dropdownEl, { closeDelay: 180 });
  }
})();
</script>

<?php
// includes/menu_d.php * Verze: V16 * Aktualizace: 6.2.2026 * Počet řádků: 101
// konec souboru
?>