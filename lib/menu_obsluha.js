// lib/menu_obsluha.js * Verze: V2 * Aktualizace: 29.1.2026
'use strict';

/*
 * CB_MENU – společné funkce a obsluha pro obě varianty menu (dropdown i sidebar).
 *
 * CÍL:
 * - mít obsluhu menu (otevírání/zavírání, timer, router, přepínání režimu) zapsanou jen jednou
 * - menu_d.php a menu_s.php řeší jen umístění (horizontální vs vertikální) a CSS třídy
 *
 * DOMLUVA:
 * - menu_data.js je zdroj pravdy (window.MENU) a je vždy OK (bez normalizace/čištění dat)
 * - HOME + přepínače režimu NEJSOU součástí window.MENU (řeší se v includes/tlacitka_svg.php)
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});

  /* =========================================================
     1) Router a přepínání režimu (URL parametry)
     ========================================================= */

  function setParamAndGo(paramName, paramValue) {
    const name = String(paramName || '').trim();
    const value = String(paramValue || '').trim();
    if (!name) return;

    try {
      const u = new URL(w.location.href);
      u.searchParams.set(name, value);
      w.location.href = u.toString();
      return;
    } catch (e) {
      const base = 'index.php';
      if (name === 'page') {
        w.location.href = base + '?page=' + encodeURIComponent(value);
      } else if (name === 'menu') {
        w.location.href = base + '?menu=' + encodeURIComponent(value);
      } else {
        w.location.href = base + '?' + encodeURIComponent(name) + '=' + encodeURIComponent(value);
      }
    }
  }

  CB_MENU.goPage = function goPage(page) {
    const p = String(page || '').trim();
    if (!p) return;
    setParamAndGo('page', p);
  };

  CB_MENU.setMenuMode = function setMenuMode(mode) {
    const m = String(mode || '').trim();
    if (!m) return;
    setParamAndGo('menu', m);
  };

  /* =========================================================
     2) Globální zavírání (jednou)
     ========================================================= */

  CB_MENU.wireGlobalCloseOnce = function wireGlobalCloseOnce(opts) {
    const o = (opts && typeof opts === 'object') ? opts : {};
    const key = String(o.key || 'default');

    if (!w.__CB_MENU_WIRED__) w.__CB_MENU_WIRED__ = {};
    if (w.__CB_MENU_WIRED__[key]) return;
    w.__CB_MENU_WIRED__[key] = true;

    const closeAll = (typeof o.closeAll === 'function') ? o.closeAll : function () {};
    const ignoreSelector = (typeof o.ignoreSelector === 'string' && o.ignoreSelector.trim() !== '')
      ? o.ignoreSelector.trim()
      : '';

    document.addEventListener('click', (ev) => {
      if (ignoreSelector) {
        const t = ev.target;
        if (t && t.closest && t.closest(ignoreSelector)) return;
      }
      closeAll();
    });

    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') closeAll();
    });

    if (o.onResize) {
      w.addEventListener('resize', () => closeAll());
    }

    if (o.onScroll) {
      w.addEventListener('scroll', () => closeAll(), true);
    }
  };

  /* =========================================================
     3) Timer pro zpožděné zavření (jeden styl pro obě menu)
     ========================================================= */

  CB_MENU.makeCloseTimer = function makeCloseTimer(closeFn, ms) {
    const fn = (typeof closeFn === 'function') ? closeFn : function () {};
    const delay = Number.isFinite(ms) ? ms : 180;

    let timer = null;

    function cancel() {
      if (timer) { clearTimeout(timer); timer = null; }
    }

    function schedule() {
      cancel();
      timer = setTimeout(() => {
        timer = null;
        fn();
      }, delay);
    }

    return { cancel, schedule };
  };

  /* =========================================================
     4) Jednotná obsluha menu (L1 + L2)
     ========================================================= */

  function safeMenu() {
    const m = w.MENU;
    return Array.isArray(m) ? m : [];
  }

  function hasL2(sec) {
    return !!(sec && sec.level2 && sec.level2.length > 0);
  }

  function closeAllDropdown(rootEl) {
    Array.from(rootEl.querySelectorAll('.dd-l1')).forEach((l1) => {
      l1.classList.remove('open');
      l1.classList.remove('active');
      const panel = l1.querySelector('.dd-panel');
      if (panel) {
        Array.from(panel.querySelectorAll('.dd-l2btn')).forEach(b => b.classList.remove('active'));
      }
    });
  }

  function renderDropdown(rootEl, opts) {
    const MENU = safeMenu();
    const closeDelay = opts.closeDelay;

    rootEl.innerHTML = '';

    MENU.forEach((sec) => {
      const l1 = document.createElement('div');
      l1.className = 'dd-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.innerHTML =
        '<span>' + sec.label + '</span>' +
        (hasL2(sec) ? '<span class="chev">▾</span>' : '');

      l1.appendChild(l1btn);

      const panel = document.createElement('div');
      panel.className = 'dd-panel';

      const col2 = document.createElement('div');
      col2.className = 'dd-col2';
      panel.appendChild(col2);

      // L2
      if (hasL2(sec)) {
        sec.level2.forEach((g) => {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'dd-l2btn';
          b.textContent = g.label;
          col2.appendChild(b);

          b.addEventListener('mouseenter', () => {
            timer.cancel();
            Array.from(col2.querySelectorAll('.dd-l2btn')).forEach(x => x.classList.remove('active'));
            b.classList.add('active');
          });

          b.addEventListener('click', (e) => {
            e.stopPropagation();
            CB_MENU.goPage(g.page);
          });
        });

        l1.appendChild(panel);
      }

      // ===== jednotné chování (timer + otevření/zavření) =====
      const timer = CB_MENU.makeCloseTimer(() => {
        l1.classList.remove('open');
        l1.classList.remove('active');
      }, closeDelay);

      function openThis() {
        closeAllDropdown(rootEl);
        if (!hasL2(sec)) return;
        l1.classList.add('open');
        l1.classList.add('active');
        Array.from(col2.querySelectorAll('.dd-l2btn')).forEach(x => x.classList.remove('active'));
      }

      // hover nad L1
      l1.addEventListener('mouseenter', () => {
        timer.cancel();
        openThis();
      });

      l1.addEventListener('mouseleave', () => {
        if (!hasL2(sec)) return;
        timer.schedule();
      });

      // hover do panelu (neshodit hned)
      if (hasL2(sec)) {
        col2.addEventListener('mouseenter', () => timer.cancel());
        col2.addEventListener('mouseleave', () => timer.schedule());
      }

      // klik na L1
      l1btn.addEventListener('click', (e) => {
        e.stopPropagation();

        if (!hasL2(sec)) {
          closeAllDropdown(rootEl);
          CB_MENU.goPage(sec.page);
          return;
        }

        const willOpen = !l1.classList.contains('open');
        closeAllDropdown(rootEl);
        if (willOpen) openThis();
      });

      rootEl.appendChild(l1);
    });

    return {
      closeAll: () => closeAllDropdown(rootEl)
    };
  }

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
    Array.from(rootEl.querySelectorAll('.sb-l1')).forEach((l1) => {
      l1.classList.remove('open');
      l1.classList.remove('active');
    });
    l2Overlay.classList.remove('open');
    l2Overlay.innerHTML = '';
    l2Overlay.style.left = '0';
    l2Overlay.style.top = '0';
  }

  function renderSidebar(rootEl, opts) {
    const MENU = safeMenu();
    const closeDelay = opts.closeDelay;
    const gap = opts.gap;

    const l2Overlay = ensureOverlay('comeback-l2overlay', 'sb-l2panel', 998);

    function getPadTop(el) {
      const v = parseFloat(getComputedStyle(el).paddingTop);
      return Number.isFinite(v) ? v : 0;
    }

    function clearL2Active() {
      Array.from(l2Overlay.querySelectorAll('.sb-l2 > button')).forEach(b => b.classList.remove('active'));
    }

    const timer = CB_MENU.makeCloseTimer(() => closeSidebar(rootEl, l2Overlay), closeDelay);

    function openL2(anchorBtnEl, sec) {
      const level2 = sec.level2;
      if (!level2 || !level2.length) return;

      const anchorRect = anchorBtnEl.getBoundingClientRect();
      l2Overlay.innerHTML = '';

      level2.forEach((g) => {
        const wrap = document.createElement('div');
        wrap.className = 'sb-l2';

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
      Array.from(rootEl.querySelectorAll('.sb-l1')).forEach((l1) => {
        if (l1 !== exceptL1El) {
          l1.classList.remove('open');
          l1.classList.remove('active');
        }
      });
      // overlay vždy patří jen k jedné položce → při změně L1 overlay zavřít a znovu otevřít
      l2Overlay.classList.remove('open');
      l2Overlay.innerHTML = '';
    }

    // overlay – ruší zavření při přejezdu myši (stejné jako dropdown panel)
    if (!w.__COMEBACK_SIDEBAR_HOVER_WIRED__) {
      w.__COMEBACK_SIDEBAR_HOVER_WIRED__ = true;
      l2Overlay.addEventListener('mouseenter', () => timer.cancel());
      l2Overlay.addEventListener('mouseleave', () => timer.schedule());
    }

    rootEl.innerHTML = '';

    MENU.forEach((sec) => {
      const l1 = document.createElement('div');
      l1.className = 'sb-l1';

      const l1btn = document.createElement('button');
      l1btn.type = 'button';
      l1btn.textContent = sec.label;

      l1btn.addEventListener('mousedown', (e) => e.preventDefault());
      l1btn.addEventListener('pointerdown', (e) => e.preventDefault());

      // Hover nad L1 (sjednoceno s dropdownem):
      // - vždy zruš timer
      // - při najetí na L1 bez L2 zavři všechno hned
      l1.addEventListener('mouseenter', () => {
        timer.cancel();

        if (!hasL2(sec)) {
          closeSidebar(rootEl, l2Overlay);
          return;
        }

        closeAllExcept(l1);
        l1.classList.add('open');
        l1.classList.add('active');
        openL2(l1btn, sec);
      });

      l1.addEventListener('mouseleave', () => {
        if (!hasL2(sec)) return;
        timer.schedule();
      });

      // Klik na L1
      l1btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        timer.cancel();

        if (!hasL2(sec)) {
          closeSidebar(rootEl, l2Overlay);
          CB_MENU.goPage(sec.page);
          l1btn.blur();
          return;
        }

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

  /* =========================================================
     5) Veřejné init funkce (volá menu_d.php / menu_s.php)
     ========================================================= */

  CB_MENU.initDropdown = function initDropdown(rootEl, opts) {
    if (!rootEl) return;
    const o = (opts && typeof opts === 'object') ? opts : {};
    const closeDelay = Number.isFinite(o.closeDelay) ? o.closeDelay : 180;

    const api = renderDropdown(rootEl, { closeDelay: closeDelay });

    CB_MENU.wireGlobalCloseOnce({
      key: '__COMEBACK_DROPDOWN_WIRED__',
      closeAll: api.closeAll,
      ignoreSelector: '.cb-menu--dropdown'
    });
  };

  CB_MENU.initSidebar = function initSidebar(rootEl, opts) {
    if (!rootEl) return;
    const o = (opts && typeof opts === 'object') ? opts : {};
    const closeDelay = Number.isFinite(o.closeDelay) ? o.closeDelay : 180;
    const gap = Number.isFinite(o.gap) ? o.gap : 8;

    const api = renderSidebar(rootEl, { closeDelay: closeDelay, gap: gap });

    CB_MENU.wireGlobalCloseOnce({
      key: '__COMEBACK_SIDEBAR_WIRED__',
      closeAll: api.closeAll,
      ignoreSelector: '.cb-menu--sidebar',
      onResize: true,
      onScroll: true
    });
  };

})(window);

// lib/menu_obsluha.js * Verze: V2 * Aktualizace: 29.1.2026
// konec souboru
