<?php
// includes/menu_s.php * Verze: V14 * Aktualizace: 28.1.2026 * Počet řádků: 430
declare(strict_types=1);

if (defined('COMEBACK_MENU_S_RENDERED')) return;
define('COMEBACK_MENU_S_RENDERED', true);

/*
 * Sidebar menu (až 3 úrovně) – router verze
 * - VŠECHNY kliky jdou přes: index.php?page=...
 * - L1 hover otevře L2 overlay, jen když existuje
 * - L1 bez L2 je klikací (page)
 * - L2 hover otevře L3 overlay, jen když existuje
 * - L2 bez L3 je klikací (page)
 *
 * Sjednocení SVG ikon:
 * - KAŽDÉ tlačítko, které obsahuje img/svg ikonu, má JEDINOU třídu: .ikona-svg
 * - Přepínač režimu = .ikona-svg (bez dalších tříd).
 *
 * Chování jako dropdown:
 * - při přejezdu z L1 na L2 se L2 nezavře hned (má krátké zpoždění)
 * - zavření se ruší, pokud myš přejede na L2 overlay
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

  // Normalizace dat menu
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
    .filter(sec => sec.label !== '');

  const sidebarEl = document.getElementById('sidebar');
  if (!sidebarEl) return;

  const btnToDropdown = document.getElementById('cbMenuToDropdown');
  const btnHome = document.getElementById('cbMenuHome');

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

  if (btnToDropdown) {
    btnToDropdown.addEventListener('click', (e) => {
      e.preventDefault();
      setMenuModeInUrl('dropdown');
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

  if (btnHome) {
    btnHome.addEventListener('click', (e) => {
      e.preventDefault();
      goPage('uvod');
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

  let l3Overlay = document.getElementById('comeback-l3overlay');
  if (!l3Overlay) {
    l3Overlay = document.createElement('div');
    l3Overlay.id = 'comeback-l3overlay';
    l3Overlay.className = 'dd-l3panel';
    l3Overlay.style.position = 'fixed';
    l3Overlay.style.left = '0';
    l3Overlay.style.top = '0';
    l3Overlay.style.zIndex = '999';
    document.body.appendChild(l3Overlay);
  }

  function closeSidebarL3() {
    l3Overlay.classList.remove('open');
    l3Overlay.innerHTML = '';
    l3Overlay.style.left = '0';
    l3Overlay.style.top = '0';
  }

  function closeSidebarL2() {
    l2Overlay.classList.remove('open');
    l2Overlay.innerHTML = '';
    l2Overlay.style.left = '0';
    l2Overlay.style.top = '0';
    closeSidebarL3();
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
  let closeTimer = null;
  let currentL1 = null;

  function cancelClose() {
    if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
  }

  function scheduleClose() {
    cancelClose();
    closeTimer = setTimeout(() => {
      closeAllSidebarL1();
      currentL1 = null;
    }, CLOSE_DELAY_MS);
  }

  function openSidebarL3(anchorBtnEl, items) {
    const safeItems = (Array.isArray(items) ? items : [])
      .map(x => ({ label: String(x.label || '').trim(), page: String(x.page || '').trim() }))
      .filter(x => x.label !== '');

    if (!safeItems.length) {
      closeSidebarL3();
      return;
    }

    const anchorRect = anchorBtnEl.getBoundingClientRect();

    l3Overlay.innerHTML = '';
    safeItems.forEach(item => {
      const it = document.createElement('button');
      it.type = 'button';
      it.className = 'dd-l3';
      it.textContent = item.label;

      it.addEventListener('click', (ev) => {
        ev.preventDefault();
        ev.stopPropagation();
        goPage(item.page);
      });

      it.addEventListener('mousedown', (ev) => ev.preventDefault());
      it.addEventListener('pointerdown', (ev) => ev.preventDefault());

      l3Overlay.appendChild(it);
    });

    const l2r = l2Overlay.getBoundingClientRect();
    const left = Math.round(l2r.right + GAP);

    const padTopL3 = getPadTop(l3Overlay);
    const top = Math.round(anchorRect.top - padTopL3);

    l3Overlay.style.left = left + 'px';
    l3Overlay.style.top = top + 'px';
    l3Overlay.classList.add('open');

    requestAnimationFrame(() => {
      const r = l3Overlay.getBoundingClientRect();
      let nx = r.left, ny = r.top;

      if (r.right > window.innerWidth - 6) nx = Math.max(6, window.innerWidth - r.width - 6);
      if (r.bottom > window.innerHeight - 6) ny = Math.max(6, window.innerHeight - r.height - 6);

      l3Overlay.style.left = Math.round(nx) + 'px';
      l3Overlay.style.top = Math.round(ny) + 'px';
    });
  }

  function openSidebarL2(anchorBtnEl, sec) {
    const anchorRect = anchorBtnEl.getBoundingClientRect();
    const level2 = Array.isArray(sec.level2) ? sec.level2 : [];
    if (!level2.length) return;

    l2Overlay.innerHTML = '';

    level2.forEach((g) => {
      const wrap = document.createElement('div');
      wrap.className = 'sb-l2';

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = g.label;

      const hasL3 = Array.isArray(g.level3) && g.level3.length > 0;

      if (hasL3) {
        btn.addEventListener('mouseenter', () => {
          cancelClose();
          clearL2Active();
          btn.classList.add('active');
          openSidebarL3(btn, g.level3);
        });

        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          cancelClose();
          clearL2Active();
          btn.classList.add('active');
          openSidebarL3(btn, g.level3);
          btn.blur();
        });
      } else {
        btn.addEventListener('mouseenter', () => {
          cancelClose();
          clearL2Active();
          btn.classList.add('active');
          closeSidebarL3();
        });

        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          goPage(g.page);
          btn.blur();
        });
      }

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
      const hasL2 = Array.isArray(sec.level2) && sec.level2.length > 0;

      const l1 = document.createElement('div');
      l1.className = 'sb-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.textContent = sec.label;

      l1btn.addEventListener('mousedown', (e) => e.preventDefault());
      l1btn.addEventListener('pointerdown', (e) => e.preventDefault());

      if (hasL2) {
        l1.addEventListener('mouseenter', () => {
          cancelClose();
          closeAllSidebarL1(l1);
          l1.classList.add('open');
          l1.classList.add('active');
          currentL1 = l1;
          openSidebarL2(l1btn, sec);
        });

        l1.addEventListener('mouseleave', () => {
          // stejné jako dropdown: neshodit hned, dej šanci dojet myší na L2 overlay
          scheduleClose();
        });

        l1btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          cancelClose();
          const willOpen = !l1.classList.contains('open');
          closeAllSidebarL1(l1);
          if (willOpen) {
            l1.classList.add('open');
            l1.classList.add('active');
            currentL1 = l1;
            openSidebarL2(l1btn, sec);
          } else {
            l1.classList.remove('open');
            l1.classList.remove('active');
            closeSidebarL2();
            currentL1 = null;
          }
          l1btn.blur();
        });
      } else {
        l1btn.addEventListener('mouseenter', cancelClose);

        l1btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          goPage(sec.page);
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

    // Když je myš nad L2 overlay, nikdy nezavírej (ruší timer).
    l2Overlay.addEventListener('mouseenter', cancelClose);
    l2Overlay.addEventListener('mouseleave', scheduleClose);

    // Stejné pro L3 overlay (když by bylo zapnuté).
    l3Overlay.addEventListener('mouseenter', cancelClose);
    l3Overlay.addEventListener('mouseleave', scheduleClose);
  }

  function wireGlobalCloseOnce() {
    if (window.__COMEBACK_SIDEBAR_WIRED__) return;
    window.__COMEBACK_SIDEBAR_WIRED__ = true;

    document.addEventListener('click', () => closeAllSidebarL1());
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeAllSidebarL1();
    });
    window.addEventListener('resize', () => closeAllSidebarL1());
    window.addEventListener('scroll', () => closeAllSidebarL1(), true);
  }

  renderSidebar();
  wireHoverCloseOnce();
  wireGlobalCloseOnce();
})();
</script>

<?php
// includes/menu_s.php * Verze: V14 * Aktualizace: 28.1.2026 * Počet řádků: 430
?>
