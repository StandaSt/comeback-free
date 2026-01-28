<?php
// includes/menu_d.php * Verze: V10 * Aktualizace: 28.1.2026
declare(strict_types=1);

if (defined('COMEBACK_MENU_D_RENDERED')) return;
define('COMEBACK_MENU_D_RENDERED', true);

/*
 * Dropdown menu (až 3 úrovně) – router verze
 * - VŠECHNY kliky jdou přes: index.php?page=...
 * - L1 hover otevře L2 jen když existuje
 * - L1 bez L2 je klikací (page)
 * - L2 hover otevře L3 jen když existuje
 * - L2 bez L3 je klikací (page)
 * - Home ikona je jen SVG tlačítko (není to „L1 tlačítko“ se stylem)
 *
 * Sjednocení SVG ikon:
 * - KAŽDÉ tlačítko, které obsahuje img/svg ikonu, má JEDINOU třídu: .ikona-svg
 * - Styly L1/L2 tlačítek se na .ikona-svg NESMÍ aplikovat (řeší menu.css přes :not(.ikona-svg)).
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
if (!defined('COMEBACK_MENU_DATA_JS_INCLUDED')) {
  define('COMEBACK_MENU_DATA_JS_INCLUDED', true);
  ?>
  <script src="<?= h(cb_url('lib/menu_data.js')) ?>"></script>
  <?php
}
?>

<script>
(function () {
  const MENU_RAW = window.MENU || [];

  // Normalizace dat menu (trim, vyhození prázdných labelů apod.)
  const MENU = (Array.isArray(MENU_RAW) ? MENU_RAW : [])
    .map(sec => ({
      key: String(sec.key || '').trim(),
      label: String(sec.label || '').trim(),
      page: sec.page ? String(sec.page).trim() : '',
      icon: sec.icon ? String(sec.icon).trim() : '',
      level2: Array.isArray(sec.level2) ? sec.level2
        .filter(g => g && String(g.label || '').trim() !== '')
        .map(g => ({
          label: String(g.label || '').trim(),
          page: g.page ? String(g.page).trim() : '',
          level3: Array.isArray(g.level3)
            ? g.level3.map(x => (x && typeof x === 'object')
                ? { label: String(x.label || '').trim(), page: String(x.page || '').trim() }
                : { label: String(x || '').trim(), page: '' }
              ).filter(x => x.label !== '')
            : []
        }))
        : []
    }))
    .filter(sec => sec.label !== '' || sec.icon !== '');

  const dropdownEl = document.getElementById('dropdown');
  if (!dropdownEl) return;

  const btnToSidebar = document.getElementById('cbMenuToSidebar');

  // Přepnutí režimu menu (jen změna parametru v URL)
  function setMenuModeInUrl(mode) {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('menu', mode);
      window.location.href = u.toString();
    } catch (e) {
      window.location.search = '?menu=' + encodeURIComponent(mode);
    }
  }

  if (btnToSidebar) {
    btnToSidebar.addEventListener('click', (e) => {
      e.preventDefault();
      setMenuModeInUrl('sidebar');
    });
  }

  // Router: vždy přes index.php?page=...
  function goPage(page) {
    const p = String(page || '').trim();
    if (!p) return;

    try {
      const u = new URL(window.location.href);
      u.searchParams.set('page', p);
      window.location.href = u.toString();
    } catch (e) {
      window.location.href = 'index.php?page=' + encodeURIComponent(p);
    }
  }

  function clearDropdownL2Active(panelEl) {
    Array.from(panelEl.querySelectorAll('.dd-l2btn')).forEach(b => b.classList.remove('active'));
  }

  function closeDropdownL3(panelEl) {
    const l3p = panelEl.querySelector('.dd-l3panel');
    if (!l3p) return;
    l3p.classList.remove('open');
    l3p.innerHTML = '';
    l3p.style.top = '';
  }

  function closeOneL1(l1) {
    l1.classList.remove('open');
    l1.classList.remove('active');
    const p = l1.querySelector('.dd-panel');
    if (p) {
      clearDropdownL2Active(p);
      closeDropdownL3(p);
    }
  }

  function closeAllDropdownL1(exceptEl) {
    Array.from(dropdownEl.querySelectorAll('.dd-l1')).forEach(x => {
      if (x !== exceptEl) closeOneL1(x);
    });
  }

  function openDropdownL3(panelEl, anchorBtnEl, items) {
    const l3p = panelEl.querySelector('.dd-l3panel');
    if (!l3p) return;

    const safeItems = (Array.isArray(items) ? items : [])
      .map(x => ({ label: String(x.label || '').trim(), page: String(x.page || '').trim() }))
      .filter(x => x.label !== '');

    if (!safeItems.length) {
      closeDropdownL3(panelEl);
      return;
    }

    const panelRect = panelEl.getBoundingClientRect();
    const anchorRect = anchorBtnEl.getBoundingClientRect();
    const anchorTopInPanel = anchorRect.top - panelRect.top;

    const padTop = parseFloat(getComputedStyle(l3p).paddingTop) || 0;
    const top = Math.max(0, Math.round(anchorTopInPanel - padTop));
    l3p.style.top = top + 'px';

    l3p.innerHTML = '';
    safeItems.forEach(item => {
      const it = document.createElement('button');
      it.type = 'button';
      it.className = 'dd-l3';
      it.textContent = item.label;

      it.addEventListener('click', (ev) => {
        ev.stopPropagation();
        goPage(item.page);
      });

      l3p.appendChild(it);
    });

    l3p.classList.add('open');
  }

  function openL1(l1, panel, hasL2) {
    closeAllDropdownL1(l1);
    if (!hasL2) return;
    l1.classList.add('open');
    l1.classList.add('active');
    clearDropdownL2Active(panel);
    closeDropdownL3(panel);
  }

  function renderDropdown() {
    dropdownEl.innerHTML = '';

    MENU.forEach((sec) => {
      const hasL2 = Array.isArray(sec.level2) && sec.level2.length > 0;

      const l1 = document.createElement('div');
      l1.className = 'dd-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';

      // HOME (ikona): stejné SVG tlačítko jako ostatní ikony v menu (JEDINÁ třída .ikona-svg)
      if (sec.icon) {
        l1.classList.add('dd-l1--icon'); // jen pro šířku/pozici wrapperu (aby ikona nebyla „půl metru“)
        l1btn.classList.add('ikona-svg');
        l1btn.innerHTML = '<img src="' + sec.icon + '" alt="">';
        l1btn.addEventListener('click', (e) => {
          e.stopPropagation();
          goPage(sec.page);
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

      const l3panel = document.createElement('div');
      l3panel.className = 'dd-l3panel';

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
          const hasL3 = Array.isArray(g.level3) && g.level3.length > 0;

          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'dd-l2btn';
          b.textContent = g.label;
          col2.appendChild(b);

          if (hasL3) {
            b.addEventListener('mouseenter', () => {
              if (!l1.classList.contains('open')) return;
              cancelClose();
              clearDropdownL2Active(panel);
              b.classList.add('active');
              openDropdownL3(panel, b, g.level3);
            });

            b.addEventListener('click', (e) => {
              e.stopPropagation();
              clearDropdownL2Active(panel);
              b.classList.add('active');
              openDropdownL3(panel, b, g.level3);
            });
          } else {
            b.addEventListener('mouseenter', () => {
              if (!l1.classList.contains('open')) return;
              cancelClose();
              clearDropdownL2Active(panel);
              closeDropdownL3(panel);
              b.classList.add('active');
            });

            b.addEventListener('click', (e) => {
              e.stopPropagation();
              goPage(g.page);
            });
          }
        });

        col2.addEventListener('mouseenter', cancelClose);
        col2.addEventListener('mouseleave', scheduleClose);
        l3panel.addEventListener('mouseenter', cancelClose);
        l3panel.addEventListener('mouseleave', scheduleClose);

        panel.appendChild(col2);
        panel.appendChild(l3panel);
        l1.appendChild(panel);

        l1.addEventListener('mouseenter', () => openL1(l1, panel, true));
        l1.addEventListener('mouseleave', scheduleClose);

        // klik na L1: když má L2, jen otevřít; když nemá, jít na page
        l1btn.addEventListener('click', (e) => {
          e.stopPropagation();
          const willOpen = !l1.classList.contains('open');
          closeAllDropdownL1(l1);
          if (willOpen) openL1(l1, panel, true);
          else closeOneL1(l1);
        });
      } else if (!sec.icon) {
        // L1 bez L2: klik = page (ale ne pro HOME ikonu – ta už má klik výše)
        l1btn.addEventListener('click', (e) => {
          e.stopPropagation();
          goPage(sec.page);
        });
      }

      dropdownEl.appendChild(l1);
    });
  }

  function wireGlobalCloseOnce() {
    if (window.__COMEBACK_DROPDOWN_WIRED__) return;
    window.__COMEBACK_DROPDOWN_WIRED__ = true;

    document.addEventListener('click', () => closeAllDropdownL1());
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAllDropdownL1();
    });
  }

  renderDropdown();
  wireGlobalCloseOnce();
})();
</script>

<?php
// includes/menu_d.php * Verze: V10 * Aktualizace: 28.1.2026
?>
