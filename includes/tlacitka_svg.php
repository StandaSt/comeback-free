<?php
// includes/tlacitka_svg.php * Verze: V10 * Aktualizace: 12.2.2026
declare(strict_types=1);

/*
 * CO JE TOHLE ZA SOUBOR
 * - Šablona (PHP include) pro vykreslení ikonových tlačítek menu.
 * - Tlačítka jsou technicky "mimo menu data" (window.MENU) – nejsou to položky menu,
 *   ale samostatné ovládací prvky (HOME + přepnutí režimu menu).
 *
 * KDE SE POUŽÍVÁ
 * - v includes/menu_d.php (dropdown varianta)
 * - v includes/menu_s.php (sidebar varianta)
 *
 * CO TENTO SOUBOR NEDĚLÁ
 * - neřeší rozložení (to je v CSS)
 * - neřeší klikání (to řeší JS, typicky lib/menu_obsluha.js + event listenery v menu_*.php)
 * - neřeší, která ikona je zrovna vidět (to obvykle řeší CSS podle režimu menu)
 */

/* ----------------------------------------------------------------
 * VSTUPNÍ PROMĚNNÉ (předávají se z include souborů)
 * ----------------------------------------------------------------
 *
 * $CB_MENU_VARIANTA
 * - očekávané hodnoty: 'dropdown' | 'sidebar'
 * - odkud přichází: nastavuje ji volající soubor před require:
 *   - menu_d.php typicky nastaví $CB_MENU_VARIANTA = 'dropdown'
 *   - menu_s.php typicky nastaví $CB_MENU_VARIANTA = 'sidebar'
 * - proč existuje: aby jeden soubor uměl vykreslit tlačítka ve dvou různých HTML strukturách.
 *
 * $CB_TLACITKA_SLOT (jen pro dropdown)
 * - očekávané hodnoty: 'home' | 'switch' | 'all'
 * - odkud přichází: nastavuje ji menu_d.php podle toho, do jakého "slotu" gridu se renderuje:
 *   - levý slot 80px → 'home'
 *   - pravý slot 80px → 'switch'
 *   - pokud se neřeší sloty, lze dát 'all' a vykreslit vše najednou
 * - proč existuje: dropdown má "rám" 80px | 1fr | 80px a ikony se mají vkládat zvlášť vlevo a vpravo.
 */

/*
 * POZNÁMKA K PROMĚNNÝM:
 * - V tomhle projektu se předpokládá, že volající soubor nastaví potřebné proměnné vždy.
 * - Pokud je nenastaví, PHP vypíše "notice" (upozornění) – a to je záměrně vidět, aby se chyba našla.
 */

/* ----------------------------------------------------------------
 * 1) REŽIM (dropdown / sidebar)
 * ---------------------------------------------------------------- */
$variant = (string)$CB_MENU_VARIANTA;

/* ----------------------------------------------------------------
 * 2) U DROPDOWNU: CO SE MÁ VYKRESLIT (HOME / SWITCH / OBOJÍ)
 * ---------------------------------------------------------------- */
$wantHome = false;
$wantSwitch = false;

if ($variant === 'dropdown') {

  $slot = (string)$CB_TLACITKA_SLOT;

  if ($slot === 'all') {
    $wantHome = true;
    $wantSwitch = true;
  } elseif ($slot === 'home') {
    $wantHome = true;
  } elseif ($slot === 'switch') {
    $wantSwitch = true;
  }

}
?>

<?php if ($variant === 'dropdown'): ?>

  <?php if ($wantHome): ?>
  <!-- DROPDOWN / HOME: samostatný obal do levého 80px slotu -->
  <div class="ikona_home">
    <!-- type="button" → aby se to v případném formuláři nechovalo jako submit -->
    <button type="button" class="ikona-svg" id="cbMenuHome" aria-label="Home">
      <!-- alt prázdné: ikonka je dekorace, přístupnost řeší aria-label na <button> -->
      <img src="<?= h(cb_url('img/icons/home.svg')) ?>" alt="">
    </button>
  </div>
  <?php endif; ?>

  <?php if ($wantSwitch): ?>
  <!-- DROPDOWN / SWITCH: obal do pravého 80px slotu -->
  <div class="ikona_switch">
    <!-- V dropdown režimu chceme jen přepnutí na sidebar -->
    <button type="button" class="ikona-svg" id="menuToSidebar" aria-label="Přepnout na sidebar">
      <img src="<?= h(cb_url('img/icons/sidebar.svg')) ?>" alt="">
    </button>
  </div>
  <?php endif; ?>

<?php else: ?>

  <!-- SIDEBAR: beze změn (původní struktura a třídy) -->
  <div class="menu-switch">
    <!-- HOME v sidebar režimu -->
    <div class="menu-home">
      <button type="button" class="ikona-svg" id="cbMenuHome" aria-label="Home">
        <img src="<?= h(cb_url('img/icons/home.svg')) ?>" alt="">
      </button>
    </div>

    <!-- Přepínače režimu menu v sidebar režimu -->
    <div class="menu-toggle">
      <!-- Přepnout na sidebar (v sidebar režimu se typicky skrývá přes CSS) -->
      <button type="button" class="ikona-svg" id="menuToSidebar" aria-label="Přepnout na sidebar">
        <img src="<?= h(cb_url('img/icons/sidebar.svg')) ?>" alt="">
      </button>

      <!-- Přepnout na dropdown (v sidebar režimu se typicky zobrazuje jen toto tlačítko přes CSS) -->
      <button type="button" class="ikona-svg" id="menuToDropdown" aria-label="Přepnout na dropdown">
        <img src="<?= h(cb_url('img/icons/dropdown.svg')) ?>" alt="">
      </button>
    </div>
  </div>

<?php endif; ?>

<?php
/*
 * ----------------------------------------------------------------
 * ID TLAČÍTEK (JS kotvy)
 * ----------------------------------------------------------------
 * cbMenuHome
 * - používá JS pro přechod na stránku 'uvod' (router)
 *
 * menuToSidebar
 * - používá JS pro přepnutí režimu menu na sidebar (typicky nastaví ?menu=sidebar)
 *
 * menuToDropdown
 * - používá JS pro přepnutí režimu menu na dropdown (typicky nastaví ?menu=dropdown)
 * - v dropdown režimu se nerenderuje (tam má být jen přepnutí na sidebar)
 *
 * ----------------------------------------------------------------
 * TŘÍDY (CSS)
 * ----------------------------------------------------------------
 * .ikona-svg
 * - jednotná třída pro vzhled ikonového tlačítka (šířka, výška, border, hover, focus…)
 * - definovaná ve style/?/ikony_svg.css
 *
 * Dropdown obaly:
 * - .ikona_home   → obal pro HOME v dropdownu (v levém 80px slotu)
 * - .ikona_switch → obal pro přepínače v dropdownu (v pravém 80px slotu)
 *   Tyto obaly jsou záměrně "mimo menu1.css", protože ikony nejsou samotné menu.
 *
 * Sidebar obaly (ponecháno beze změn):
 * - .menu-switch, .menu-home, .menu-toggle
 *   Tady je struktura historicky jiná a teď do ní nezasahujeme.
 */
?>

<?php
/* includes/tlacitka_svg.php * Verze: V10 * Aktualizace: 12.2.2026 * Počet řádků: 159 */
// Konec souboru