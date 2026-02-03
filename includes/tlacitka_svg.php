<?php
// includes/tlacitka_svg.php * Verze: V3 * Aktualizace: 29.1.2026
declare(strict_types=1);

/*
 * Společná SVG tlačítka pro obě menu (dropdown i sidebar).
 * - HOME
 * - přepnutí na sidebar
 * - přepnutí na dropdown
 *
 * Pozn.:
 * - rozložení (vlevo / vpravo / pod menu) řeší CSS
 * - která tlačítka jsou vidět řeší CSS podle .menu-dropdown / .menu-sidebar
 *
 * Varianta renderu:
 * - $CB_MENU_VARIANTA = 'dropdown' | 'sidebar'
 *   - dropdown: HOME zvlášť vlevo, switch zvlášť vpravo
 *   - sidebar: vše jako jedna lišta pod menu
 */
$variant = isset($CB_MENU_VARIANTA) ? (string)$CB_MENU_VARIANTA : '';
?>
<?php if ($variant === 'dropdown'): ?>
  <div class="menu-home">
    <button type="button" class="ikona-svg" id="cbMenuHome" aria-label="Home">
      <img src="<?= h(cb_url('img/icons/home.svg')) ?>" alt="">
    </button>
  </div>

  <div class="menu-toggle">
    <button type="button" class="ikona-svg" id="menuToSidebar" aria-label="Přepnout na sidebar">
      <img src="<?= h(cb_url('img/icons/sidebar.svg')) ?>" alt="">
    </button>

    <button type="button" class="ikona-svg" id="menuToDropdown" aria-label="Přepnout na dropdown">
      <img src="<?= h(cb_url('img/icons/dropdown.svg')) ?>" alt="">
    </button>
  </div>
<?php else: ?>
  <div class="menu-switch">
    <div class="menu-home">
      <button type="button" class="ikona-svg" id="cbMenuHome" aria-label="Home">
        <img src="<?= h(cb_url('img/icons/home.svg')) ?>" alt="">
      </button>
    </div>

    <div class="menu-toggle">
      <button type="button" class="ikona-svg" id="menuToSidebar" aria-label="Přepnout na sidebar">
        <img src="<?= h(cb_url('img/icons/sidebar.svg')) ?>" alt="">
      </button>

      <button type="button" class="ikona-svg" id="menuToDropdown" aria-label="Přepnout na dropdown">
        <img src="<?= h(cb_url('img/icons/dropdown.svg')) ?>" alt="">
      </button>
    </div>
  </div>
<?php endif; ?>

<?php
// includes/tlacitka_svg.php * Verze: V3 * Aktualizace: 29.1.2026
// konec souboru
?>
