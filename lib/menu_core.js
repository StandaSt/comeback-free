// lib/menu_core.js * Verze: V2 * Aktualizace: 14.2.2026
'use strict';

/*
 * menu_core.js
 * - CB_MENU namespace (window.CB_MENU)
 * - router (goPage / setMenuMode)
 * - globální zavírání (click mimo / ESC / resize / scroll)
 * - timer pro zpožděné zavření
 * - detekce touch/desktop pro chování kliků na U1 s U2
 *
 * V2:
 * - ochrana proti zbytečnému AJAX reloadu: pokud kliknu na stejný ?page= a není p, nic se neděje
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

  function getCurrentParam(paramName) {
    const name = String(paramName || '').trim();
    if (!name) return '';

    try {
      const u = new URL(w.location.href);
      return String(u.searchParams.get(name) || '');
    } catch (e) {
      // fallback bez URL(): vezmeme z query stringu
      const qs = String(w.location.search || '');
      const re = new RegExp('(?:\\?|&)' + name.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&') + '=([^&]*)', 'i');
      const m = qs.match(re);
      if (!m) return '';
      try {
        return decodeURIComponent(m[1].replace(/\+/g, '%20'));
      } catch (e2) {
        return String(m[1] || '');
      }
    }
  }

  function hasAnyPagingParam() {
    try {
      const u = new URL(w.location.href);
      return u.searchParams.has('p') && String(u.searchParams.get('p') || '').trim() !== '';
    } catch (e) {
      const qs = String(w.location.search || '');
      return /(?:\?|&)p=/.test(qs);
    }
  }

  function buildUrlWithParam(paramName, paramValue) {
    const name = String(paramName || '').trim();
    const value = String(paramValue || '').trim();
    if (!name) return '';

    try {
      const u = new URL(w.location.href);

      if (name === 'page') {
        u.searchParams.delete('p');
      }

      u.searchParams.set(name, value);
      return u.toString();
    } catch (e) {
      const base = 'index.php';
      if (name === 'page') return base + '?page=' + encodeURIComponent(value);
      if (name === 'menu') return base + '?menu=' + encodeURIComponent(value);
      return base + '?' + encodeURIComponent(name) + '=' + encodeURIComponent(value);
    }
  }

  function setParamAndGo(paramName, paramValue) {
    const name = String(paramName || '').trim();
    const value = String(paramValue || '').trim();
    if (!name) return;

    // === V2: pokud je už stejná stránka otevřená a není stránkování, nedělej nic ===
    if (name === 'page') {
      const curPage = String(getCurrentParam('page') || '').trim() || 'uvod';
      const nextPage = value || 'uvod';

      // když je stejné page a není p, nemá smysl znovu překreslovat
      if (curPage === nextPage && !hasAnyPagingParam()) {
        return;
      }
    }

    const url = buildUrlWithParam(name, value);
    if (!url) return;

    if (name === 'page') {
      if (CB_MENU._ajaxWirePopstateOnce) CB_MENU._ajaxWirePopstateOnce();
      if (CB_MENU._ajaxFetchMainAndSwap) {
        CB_MENU._ajaxFetchMainAndSwap(url, true);
        return;
      }
    }

    w.location.href = url;
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

})(window);

// lib/menu_core.js * Verze: V2 * Aktualizace: 14.2.2026
// konec souboru