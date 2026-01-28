<?php
// includes/menu_d.php * Verze: V12 * Aktualizace: 28.1.2026 * Počet řádků: 205
declare(strict_types=1);

if (defined('COMEBACK_MENU_D_RENDERED')) return;
define('COMEBACK_MENU_D_RENDERED', true);

/*
 * Dropdown menu (2 úrovně) – router verze
 * - VŠECHNY kliky jdou přes: index.php?page=...
 * - L1 hover otevře L2 jen když existuje
 * - L1 bez L2 je klikací (page)
 * - Home ikona je jen SVG tlačítko (není to „L1 tlačítko“ se stylem)
 *
 * Sjednocení SVG ikon:
 * - KAŽDÉ tlačítko, které obsahuje img/svg ikonu, má JEDINOU třídu: .ikona-svg
 * - Styly L1/L2 tlačítek se na .ikona-svg NESMÍ aplikovat (řeší menu.css přes :not(.ikona-svg)).
 *
 * Sdílený základ (společný kód):
 * - lib/menu_zaklad.js
 *   - CB_MENU.goPage(page)
 *   - CB_MENU.setMenuMode(mode)
 *   - CB_MENU.wireGlobalCloseOnce(...)
 */
?>
<div class="cb-menu cb-menu--dropdown">
  <div class="cb-dropdown-bar">
    <div class="cb-menu-top">
      <div class="dd-row" id="dropdown"></div>

      <div class="cb-menu-switch">
        <!-- Přepínač režimu: JEDINÁ třída pro SVG tlačítko -->
        <button type="button" class="ikona-svg" id="cbMenuToSidebar" aria-label="Přepnout na sidebar">
          <img src="<?= h(cb_url('img/icons/sidebar.svg')) ?>" alt="">
        </button>
      </div>
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

// menu_zaklad.js – společné funkce pro obě menu
if (!defined('COMEBACK_MENU_ZAKLAD_JS_INCLUDED')) {
  define('COMEBACK_MENU_ZAKLAD_JS_INCLUDED', true);
  ?>
  <script src="<?= h(cb_url('lib/menu_zaklad.js')) ?>"></script>
  <?php
}
?>

<script>
(function () {
  // Domluva: menu_data.js je vždy OK → bereme data přímo bez „čištění“
  const MENU = window.MENU;

  const dropdownEl = document.getElementById('dropdown');
  if (!dropdownEl) return;

  const btnToSidebar = document.getElementById('cbMenuToSidebar');
  if (btnToSidebar && window.CB_MENU && window.CB_MENU.setMenuMode) {
    btnToSidebar.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      window.CB_MENU.setMenuMode('sidebar');
    });
  }

  function clearDropdownL2Active(panelEl) {
    Array.from(panelEl.querySelectorAll('.dd-l2btn')).forEach(b => b.classList.remove('active'));
  }

  function closeOneL1(l1) {
    l1.classList.remove('open');
    l1.classList.remove('active');
    const p = l1.querySelector('.dd-panel');
    if (p) clearDropdownL2Active(p);
  }

  function closeAllDropdownL1(exceptEl) {
    Array.from(dropdownEl.querySelectorAll('.dd-l1')).forEach(x => {
      if (x !== exceptEl) closeOneL1(x);
    });
  }

  function openL1(l1, panel, hasL2) {
    closeAllDropdownL1(l1);
    if (!hasL2) return;
    l1.classList.add('open');
    l1.classList.add('active');
    clearDropdownL2Active(panel);
  }

  function renderDropdown() {
    dropdownEl.innerHTML = '';

    MENU.forEach((sec) => {
      const hasL2 = (sec.level2 && sec.level2.length > 0);

      const l1 = document.createElement('div');
      l1.className = 'dd-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';

      // HOME (ikona): stejné SVG tlačítko jako ostatní ikony v menu (JEDINÁ třída .ikona-svg)
      if (sec.icon) {
        l1.classList.add('dd-l1--icon'); // jen pro šířku wrapperu (aby ikona nebyla „půl metru“)
        l1btn.classList.add('ikona-svg');
        l1btn.innerHTML = '<img src="' + sec.icon + '" alt="">';
        l1btn.addEventListener('click', (e) => {
          e.stopPropagation();
          window.CB_MENU.goPage(sec.page);
        });
      } else {
        l1btn.innerHTML =
          '<span>' + sec.label + '</span>' +
          (hasL2 ? '<span class="chev">▾</span>' : '');
      }

      l1.appendChild(l1btn);

      const panel = document.createElement('div');
      panel.className = 'dd-panel';

      const col2 = document.createElement('div');
      col2.className = 'dd-col2';

      let closeTimer = null;
      function cancelClose() {
        if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
      }
      function scheduleClose() {
        cancelClose();
        closeTimer = setTimeout(() => closeOneL1(l1), 180);
      }

      if (hasL2) {
        sec.level2.forEach((g) => {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'dd-l2btn';
          b.textContent = g.label;
          col2.appendChild(b);

          b.addEventListener('mouseenter', () => {
            if (!l1.classList.contains('open')) return;
            cancelClose();
            clearDropdownL2Active(panel);
            b.classList.add('active');
          });

          b.addEventListener('click', (e) => {
            e.stopPropagation();
            window.CB_MENU.goPage(g.page);
          });
        });

        col2.addEventListener('mouseenter', cancelClose);
        col2.addEventListener('mouseleave', scheduleClose);

        panel.appendChild(col2);
        l1.appendChild(panel);

        l1.addEventListener('mouseenter', () => openL1(l1, panel, true));
        l1.addEventListener('mouseleave', scheduleClose);

        // klik na L1: když má L2, jen otevřít / zavřít
        l1btn.addEventListener('click', (e) => {
          if (sec.icon) return; // HOME ikona má vlastní click výše
          e.stopPropagation();
          const willOpen = !l1.classList.contains('open');
          closeAllDropdownL1(l1);
          if (willOpen) openL1(l1, panel, true);
          else closeOneL1(l1);
        });
      } else if (!sec.icon) {
        // L1 bez L2: klik = page
        l1btn.addEventListener('click', (e) => {
          e.stopPropagation();
          window.CB_MENU.goPage(sec.page);
        });
      }

      dropdownEl.appendChild(l1);
    });
  }

  renderDropdown();

  if (window.CB_MENU) {
    window.CB_MENU.wireGlobalCloseOnce({
      key: '__COMEBACK_DROPDOWN_WIRED__',
      closeAll: closeAllDropdownL1
    });
  }
})();
</script>

<?php
// includes/menu_d.php * Verze: V12 * Aktualizace: 28.1.2026 * Počet řádků: 205
?>
