// lib/menu_core.js * Verze: V3 * Aktualizace: 14.2.2026
'use strict';

/*
 * menu_core.js
 * - CB_MENU namespace (window.CB_MENU)
 * - router (goPage / setMenuMode)
 * - globální zavírání (click mimo / ESC / resize / scroll)
 * - timer pro zpožděné zavření
 * - detekce touch/desktop pro chování kliků na U1 s U2
 *
 * V3:
 * - URL se nemění nikdy: page i menu se řeší přes session + AJAX headers
 * - ochrana proti zbytečnému AJAX reloadu: pokud kliknu na stejnou stránku, nic se neděje
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});

  function isTouchLike() {
    const mtp = Number(w.navigator && w.navigator.maxTouchPoints) || 0;
    if (mtp > 0) return true;

    try {
      if (w.matchMedia && w.matchMedia('(pointer: coarse)').matches) return true;
    } catch (e) {
      // nic
    }
    return false;
  }

  CB_MENU.isTouchLike = isTouchLike;

  function goPageInternal(pageKey) {
    const p = String(pageKey || '').trim() || 'uvod';

    // když už je stránka otevřená, nic nedělej
    const cur = String(CB_MENU._currentPage || '').trim() || 'uvod';
    if (cur === p) {
      return;
    }

    if (CB_MENU._ajaxWirePopstateOnce) CB_MENU._ajaxWirePopstateOnce();
    if (CB_MENU._ajaxFetchMainAndSwap) {
      CB_MENU._ajaxFetchMainAndSwap(p);
      return;
    }

    // fallback
    w.location.href = w.location.href;
  }

  CB_MENU.goPage = function goPage(page) {
    goPageInternal(page);
  };

  CB_MENU.setMenuMode = function setMenuMode(mode) {
    const m = (String(mode || '').trim() === 'sidebar') ? 'sidebar' : 'dropdown';

    fetch(w.location.href, {
      method: 'POST',
      headers: {
        'X-Comeback-Set-Menu': m
      }
    }).then(() => {
      w.location.href = w.location.href;
    }).catch(() => {
      w.location.href = w.location.href;
    });
  };

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

  CB_MENU.safeMenu = function safeMenu() {
    const m = w.MENU;
    return Array.isArray(m) ? m : [];
  };

  CB_MENU.hasL2 = function hasL2(sec) {
    return !!(sec && sec.level2 && sec.level2.length > 0);
  };

  // výchozí (FULL load vždy uvod)
  if (!CB_MENU._currentPage) {
    CB_MENU._currentPage = 'uvod';
  }

})(window);

// lib/menu_core.js * Verze: V3 * Aktualizace: 14.2.2026 * počet řádků 144
// konec souboru