// js/menu_core.js * Verze: V5 * Aktualizace: 08.03.2026
'use strict';

/*
 * menu_core.js
 * - CB_MENU namespace (window.CB_MENU)
 * - router (goPage / setMenuMode)
 * - globalni zavirani (click mimo / ESC / resize / scroll)
 * - timer pro zpozdene zavreni
 * - detekce touch/desktop
 *
 * V5:
 * - synchronizace aktivniho tlacitka v horni liste menu
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

  function normalizeMenuPage(pageKey) {
    const p = String(pageKey || '').trim() || 'home';
    if (p === 'admin') return 'admin_dashboard';
    return p;
  }

  function syncActiveTopMenu(pageKey) {
    const p = normalizeMenuPage(pageKey);
    const btns = document.querySelectorAll('.head_menu .head_menu_btn[data-page]');
    if (!btns || btns.length === 0) return;

    btns.forEach((btn) => {
      const key = String(btn.getAttribute('data-page') || '').trim();
      btn.classList.toggle('is-on', key === p);
    });
  }

  CB_MENU.syncActiveTopMenu = syncActiveTopMenu;

  function goPageInternal(pageKey) {
    const p = normalizeMenuPage(pageKey);

    syncActiveTopMenu(p);

    const cur = normalizeMenuPage(CB_MENU._currentPage || 'home');
    if (cur === p) {
      return;
    }

    if (CB_MENU._ajaxWirePopstateOnce) CB_MENU._ajaxWirePopstateOnce();
    if (CB_MENU._ajaxFetchMainAndSwap) {
      CB_MENU._ajaxFetchMainAndSwap(p);
      return;
    }

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

  if (!CB_MENU._currentPage) {
    CB_MENU._currentPage = 'home';
  }

  syncActiveTopMenu(CB_MENU._currentPage);

  document.addEventListener('cb:main-swapped', (ev) => {
    const page = String(ev?.detail?.page || CB_MENU._currentPage || 'home');
    syncActiveTopMenu(page);
  });

})(window);

// js/menu_core.js * Verze: V5 * Aktualizace: 08.03.2026
// konec souboru