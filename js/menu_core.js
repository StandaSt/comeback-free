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

  function normalizeSekce(raw) {
    const v = String(raw || '').trim();
    if (v === '1' || v === '2' || v === '3') return v;
    if (v === 'home') return '3';
    if (v === 'manager') return '2';
    if (v === 'admin' || v === 'admin_dashboard') return '1';
    return '3';
  }

  CB_MENU.normalizeSekce = normalizeSekce;

  function buildSekceUrl(sekce) {
    const u = new URL(w.location.href);
    u.searchParams.set('sekce', normalizeSekce(sekce));
    return u.toString();
  }

  CB_MENU.buildSekceUrl = buildSekceUrl;

  function syncActiveTopMenu(sekce) {
    const s = normalizeSekce(sekce);
    const btns = document.querySelectorAll('.head_menu .head_menu_btn[data-sekce]');
    if (!btns || btns.length === 0) return;

    btns.forEach((btn) => {
      const key = normalizeSekce(btn.getAttribute('data-sekce') || '3');
      btn.classList.toggle('is-on', key === s);
    });
  }

  CB_MENU.syncActiveTopMenu = syncActiveTopMenu;

  function goSekceInternal(sekce) {
    const s = normalizeSekce(sekce);

    syncActiveTopMenu(s);

    const cur = normalizeSekce(CB_MENU._currentPage || '3');
    if (cur === s) {
      return;
    }

    if (CB_MENU._ajaxWirePopstateOnce) CB_MENU._ajaxWirePopstateOnce();
    if (CB_MENU._ajaxFetchMainAndSwap) {
      CB_MENU._ajaxFetchMainAndSwap(s);
      return;
    }

    w.location.href = buildSekceUrl(s);
  }

  CB_MENU.goPage = function goPage(page) {
    goSekceInternal(page);
  };

  CB_MENU.goSekce = function goSekce(sekce) {
    goSekceInternal(sekce);
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
    const u = new URL(w.location.href);
    CB_MENU._currentPage = normalizeSekce(u.searchParams.get('sekce') || '3');
  }

  syncActiveTopMenu(CB_MENU._currentPage);

  document.addEventListener('cb:main-swapped', (ev) => {
    const sekce = normalizeSekce(ev?.detail?.sekce || ev?.detail?.page || CB_MENU._currentPage || '3');
    syncActiveTopMenu(sekce);
  });

})(window);

// js/menu_core.js * Verze: V5 * Aktualizace: 08.03.2026
// konec souboru
