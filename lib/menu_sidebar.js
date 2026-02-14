// lib/menu_sidebar.js * Verze: V1 * Aktualizace: 13.2.2026
'use strict';

/*
 * menu_sidebar.js
 * - render a obsluha sidebar menu (včetně overlay panelu L2)
 *
 * Změny chování:
 * 1) klik na U2 => hned zavře overlay
 * 2) klik na U1 s U2:
 *    - desktop bez touch: ignorovat (otevírá jen hoverem)
 *    - touch: klik toggle jako dřív
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});

  function ensureOverlay(id, className, zIndex) {
    let el = document.getElementById(id);
    if (el) return el;

    el = document.createElement('div');
    el.id = id;
    el.className = className;
    el.style.position = 'fixed';
    el.style.left = '0';
    el.style.top = '0';
    el.style.zIndex = String(zIndex);
    document.body.appendChild(el);
    return el;
  }

  function closeSidebar(rootEl, l2Overlay) {
    Array.from(rootEl.querySelectorAll('.menu-u1')).forEach((l1) => {
      l1.classList.remove('open');
      l1.classList.remove('active');
    });
    l2Overlay.classList.remove('open');
    l2Overlay.innerHTML = '';
    l2Overlay.style.left = '0';
    l2Overlay.style.top = '0';
  }

  function renderSidebar(rootEl, opts) {
    const MENU = (CB_MENU.safeMenu) ? CB_MENU.safeMenu() : [];
    const closeDelay = opts.closeDelay;
    const gap = opts.gap;

    const l2Overlay = ensureOverlay('comeback-l2overlay', 'menu-u2panel', 998);

    function getPadTop(el) {
      const v = parseFloat(getComputedStyle(el).paddingTop);
      return Number.isFinite(v) ? v : 0;
    }

    function clearL2Active() {
      Array.from(l2Overlay.querySelectorAll('.menu-u2 > button')).forEach(b => b.classList.remove('active'));
    }

    const timer = CB_MENU.makeCloseTimer(() => closeSidebar(rootEl, l2Overlay), closeDelay);

    function openL2(anchorBtnEl, sec) {
      const level2 = sec.level2;
      if (!level2 || !level2.length) return;

      const anchorRect = anchorBtnEl.getBoundingClientRect();
      l2Overlay.innerHTML = '';

      level2.forEach((g) => {
        const wrap = document.createElement('div');
        wrap.className = 'menu-u2';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = g.label;

        btn.addEventListener('mouseenter', () => {
          timer.cancel();
          clearL2Active();
          btn.classList.add('active');
        });

        btn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation();

          // 1) po výběru U2 hned zavřít
          closeSidebar(rootEl, l2Overlay);

          CB_MENU.goPage(g.page);
          btn.blur();
        });

        btn.addEventListener('mousedown', (e) => e.preventDefault());
        btn.addEventListener('pointerdown', (e) => e.preventDefault());

        wrap.appendChild(btn);
        l2Overlay.appendChild(wrap);
      });

      const left = Math.round(anchorRect.right + gap);
      const padTop = getPadTop(l2Overlay);
      const top = Math.round(anchorRect.top - padTop);

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

    function closeAllExcept(exceptL1El) {
      Array.from(rootEl.querySelectorAll('.menu-u1')).forEach((l1) => {
        if (l1 !== exceptL1El) {
          l1.classList.remove('open');
          l1.classList.remove('active');
        }
      });
      l2Overlay.classList.remove('open');
      l2Overlay.innerHTML = '';
    }

    if (!w.__COMEBACK_SIDEBAR_HOVER_WIRED__) {
      w.__COMEBACK_SIDEBAR_HOVER_WIRED__ = true;
      l2Overlay.addEventListener('mouseenter', () => timer.cancel());
      l2Overlay.addEventListener('mouseleave', () => timer.schedule());
    }

    rootEl.innerHTML = '';

    MENU.forEach((sec) => {
      const l1 = document.createElement('div');
      l1.className = 'menu-u1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.textContent = sec.label;

      l1btn.addEventListener('mousedown', (e) => e.preventDefault());
      l1btn.addEventListener('pointerdown', (e) => e.preventDefault());

      const hasL2 = (CB_MENU.hasL2) ? CB_MENU.hasL2(sec) : false;

      l1.addEventListener('mouseenter', () => {
        timer.cancel();

        if (!hasL2) {
          closeSidebar(rootEl, l2Overlay);
          return;
        }

        closeAllExcept(l1);
        l1.classList.add('open');
        l1.classList.add('active');
        openL2(l1btn, sec);
      });

      l1.addEventListener('mouseleave', () => {
        if (!hasL2) return;
        timer.schedule();
      });

      l1btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        timer.cancel();

        if (!hasL2) {
          closeSidebar(rootEl, l2Overlay);
          CB_MENU.goPage(sec.page);
          l1btn.blur();
          return;
        }

        // 2) U1 s U2: na desktopu bez touch ignorovat klik
        const touchLike = (CB_MENU.isTouchLike) ? CB_MENU.isTouchLike() : false;
        if (!touchLike) {
          l1btn.blur();
          return;
        }

        // touch: klik toggle jako dřív
        const willOpen = !l1.classList.contains('open');
        closeAllExcept(l1);

        if (willOpen) {
          l1.classList.add('open');
          l1.classList.add('active');
          openL2(l1btn, sec);
        } else {
          closeSidebar(rootEl, l2Overlay);
        }

        l1btn.blur();
      });

      l1.appendChild(l1btn);
      rootEl.appendChild(l1);
    });

    return {
      closeAll: () => closeSidebar(rootEl, l2Overlay)
    };
  }

  CB_MENU.initSidebar = function initSidebar(rootEl, opts) {
    if (!rootEl) return;
    const o = (opts && typeof opts === 'object') ? opts : {};
    const closeDelay = Number.isFinite(o.closeDelay) ? o.closeDelay : 180;
    const gap = Number.isFinite(o.gap) ? o.gap : 8;

    const api = renderSidebar(rootEl, { closeDelay: closeDelay, gap: gap });

    CB_MENU.wireGlobalCloseOnce({
      key: '__COMEBACK_SIDEBAR_WIRED__',
      closeAll: api.closeAll,
      ignoreSelector: '.menu-sidebar',
      onResize: true,
      onScroll: true
    });
  };

})(window);

// lib/menu_sidebar.js * Verze: V1 * počet řádků 235 * Aktualizace: 13.2.2026
// konec souboru