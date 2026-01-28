<?php
// includes/menu_s.php * Verze: V16 * Aktualizace: 28.1.2026 * Počet řádků: 285
declare(strict_types=1);

if (defined('COMEBACK_MENU_S_RENDERED')) return;
define('COMEBACK_MENU_S_RENDERED', true);

/*
 * Sidebar menu (2 úrovně) – router verze
 * - VŠECHNY kliky jdou přes: index.php?page=...
 * - L1 hover otevře L2 overlay, jen když existuje
 * - L1 bez L2 je klikací (page) + zmodrá na hover stejně jako dropdown
 * - L2 je klikací (page)
 *
 * Sjednocení SVG ikon:
 * - KAŽDÉ tlačítko, které obsahuje img/svg ikonu, má JEDINOU třídu: .ikona-svg
 * - Přepínač režimu = .ikona-svg (bez dalších tříd).
 *
 * Chování jako dropdown:
 * - při přejezdu z L1 na L2 se L2 nezavře hned (má krátké zpoždění)
 * - zavření se ruší, pokud myš přejede na L2 overlay
 *
 * Sdílený základ (společný kód):
 * - lib/menu_zaklad.js
 *   - CB_MENU.goPage(page)
 *   - CB_MENU.setMenuMode(mode)
 *   - CB_MENU.wireGlobalCloseOnce(...)
 */
?>
<div class="cb-menu cb-menu--sidebar">
  <div class="cb-sidebar-area">
    <div id="sidebar"></div>

    <div class="cb-menu-switch">
      <!-- HOME: JEDINÁ třída pro SVG tlačítko -->
      <button type="button" class="ikona-svg" id="cbMenuHome" aria-label="Home">
        <img src="<?= h(cb_url('img/icons/home.svg')) ?>" alt="">
      </button>

      <!-- Přepínač režimu: JEDINÁ třída pro SVG tlačítko -->
      <button type="button" class="ikona-svg" id="cbMenuToDropdown" aria-label="Přepnout na dropdown">
        <img src="<?= h(cb_url('img/icons/dropdown.svg')) ?>" alt="">
      </button>
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

  const sidebarEl = document.getElementById('sidebar');
  if (!sidebarEl) return;

  const btnToDropdown = document.getElementById('cbMenuToDropdown');
  const btnHome = document.getElementById('cbMenuHome');

  if (btnToDropdown && window.CB_MENU && window.CB_MENU.setMenuMode) {
    btnToDropdown.addEventListener('click', (e) => {
      e.preventDefault();
      window.CB_MENU.setMenuMode('dropdown');
    });
  }

  if (btnHome) {
    btnHome.addEventListener('click', (e) => {
      e.preventDefault();
      window.CB_MENU.goPage('uvod');
    });
  }

  const GAP = 8;

  function getPadTop(el) {
    const v = parseFloat(getComputedStyle(el).paddingTop);
    return Number.isFinite(v) ? v : 0;
  }

  let l2Overlay = document.getElementById('comeback-l2overlay');
  if (!l2Overlay) {
    l2Overlay = document.createElement('div');
    l2Overlay.id = 'comeback-l2overlay';
    l2Overlay.className = 'sb-l2panel';
    l2Overlay.style.position = 'fixed';
    l2Overlay.style.left = '0';
    l2Overlay.style.top = '0';
    l2Overlay.style.zIndex = '998';
    document.body.appendChild(l2Overlay);
  }

  function closeSidebarL2() {
    l2Overlay.classList.remove('open');
    l2Overlay.innerHTML = '';
    l2Overlay.style.left = '0';
    l2Overlay.style.top = '0';
  }

  function closeAllSidebarL1(exceptEl) {
    Array.from(sidebarEl.querySelectorAll('.sb-l1')).forEach(x => {
      if (x !== exceptEl) {
        x.classList.remove('open');
        x.classList.remove('active');
      }
    });
    closeSidebarL2();
  }

  function clearL2Active() {
    Array.from(l2Overlay.querySelectorAll('.sb-l2 > button')).forEach(b => b.classList.remove('active'));
  }

  // ===== Chování jako dropdown: krátké zpoždění zavření =====
  const CLOSE_DELAY_MS = 180;
  const closeTimer = (window.CB_MENU && window.CB_MENU.makeCloseTimer)
    ? window.CB_MENU.makeCloseTimer(() => closeAllSidebarL1(), CLOSE_DELAY_MS)
    : (function () {
        let t = null;
        return {
          cancel: function () { if (t) { clearTimeout(t); t = null; } },
          schedule: function () { if (t) clearTimeout(t); t = setTimeout(() => closeAllSidebarL1(), CLOSE_DELAY_MS); }
        };
      })();

  function openSidebarL2(anchorBtnEl, sec) {
    const anchorRect = anchorBtnEl.getBoundingClientRect();
    const level2 = sec.level2;
    if (!level2.length) return;

    l2Overlay.innerHTML = '';

    level2.forEach((g) => {
      const wrap = document.createElement('div');
      wrap.className = 'sb-l2';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = g.label;

      btn.addEventListener('mouseenter', () => {
        closeTimer.cancel();
        clearL2Active();
        btn.classList.add('active');
      });

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        window.CB_MENU.goPage(g.page);
        btn.blur();
      });

      btn.addEventListener('mousedown', (e) => e.preventDefault());
      btn.addEventListener('pointerdown', (e) => e.preventDefault());

      wrap.appendChild(btn);
      l2Overlay.appendChild(wrap);
    });

    const left = Math.round(anchorRect.right + GAP);
    const padTopL2 = getPadTop(l2Overlay);
    const top = Math.round(anchorRect.top - padTopL2);

    l2Overlay.style.left = left + 'px';
    l2Overlay.style.top = top + 'px';
    l2Overlay.classList.add('open');

    requestAnimationFrame(() => {
      const r = l2Overlay.getBoundingClientRect();
      let nx = r.left, ny = r.top;

      if (r.right > window.innerWidth - 6) nx = Math.max(6, window.innerWidth - r.width - 6);
      if (r.bottom > window.innerHeight - 6) ny = Math.max(6, window.innerHeight - r.height - 6);

      l2Overlay.style.left = Math.round(nx) + 'px';
      l2Overlay.style.top = Math.round(ny) + 'px';
    });
  }

  function renderSidebar() {
    sidebarEl.innerHTML = '';

    MENU.forEach((sec) => {
      const hasL2 = (sec.level2 && sec.level2.length > 0);

      const l1 = document.createElement('div');
      l1.className = 'sb-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.textContent = sec.label;

      l1btn.addEventListener('mousedown', (e) => e.preventDefault());
      l1btn.addEventListener('pointerdown', (e) => e.preventDefault());

      if (hasL2) {
        l1.addEventListener('mouseenter', () => {
          closeTimer.cancel();
          closeAllSidebarL1(l1);
          l1.classList.add('open');
          l1.classList.add('active');
          openSidebarL2(l1btn, sec);
        });

        l1.addEventListener('mouseleave', () => {
          closeTimer.schedule();
        });

        l1btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          closeTimer.cancel();
          const willOpen = !l1.classList.contains('open');
          closeAllSidebarL1(l1);
          if (willOpen) {
            l1.classList.add('open');
            l1.classList.add('active');
            openSidebarL2(l1btn, sec);
          } else {
            l1.classList.remove('open');
            l1.classList.remove('active');
            closeSidebarL2();
          }
          l1btn.blur();
        });
      } else {
        // L1 bez L2: klik = page (a modrání řeší CSS hover na samotném tlačítku)
        l1btn.addEventListener('mouseenter', () => closeTimer.cancel());

        l1btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          window.CB_MENU.goPage(sec.page);
          l1btn.blur();
        });
      }

      l1.appendChild(l1btn);
      sidebarEl.appendChild(l1);
    });
  }

  function wireHoverCloseOnce() {
    if (window.__COMEBACK_SIDEBAR_HOVER_WIRED__) return;
    window.__COMEBACK_SIDEBAR_HOVER_WIRED__ = true;

    l2Overlay.addEventListener('mouseenter', () => closeTimer.cancel());
    l2Overlay.addEventListener('mouseleave', () => closeTimer.schedule());
  }

  renderSidebar();
  wireHoverCloseOnce();

  if (window.CB_MENU) {
    window.CB_MENU.wireGlobalCloseOnce({
      key: '__COMEBACK_SIDEBAR_WIRED__',
      closeAll: closeAllSidebarL1,
      onResize: true,
      onScroll: true
    });
  }
})();
</script>

<?php
// includes/menu_s.php * Verze: V16 * Aktualizace: 28.1.2026 * Počet řádků: 285
?>
