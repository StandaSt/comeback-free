// lib/menu_zaklad.js * Verze: V1 * Aktualizace: 28.1.2026 * Počet řádků: 153
'use strict';

/*
 * CB_MENU – společné funkce pro obě varianty menu (dropdown i sidebar).
 *
 * CÍL:
 * - mít opakované části kódu (router + zavírání) zapsané jen jednou
 * - menu_d.php i menu_s.php si ponechají jen render a své specifické chování
 *
 * DŮLEŽITÉ (podle domluvy):
 * - neřešíme "kontroly správnosti" menu_data.js (žádná normalizace / čištění dat)
 * - pokud budou data špatně, klidně to spadne → opraví se menu_data.js
 */

(function (w) {
  // Vytvoř (nebo použij) globální objekt.
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});

  /* ---------------------------------------------------------
   * 1) Router: přepnutí stránky přes index.php?page=...
   * --------------------------------------------------------- */

  /**
   * Nastaví (nebo přepíše) query parametr v aktuální URL a provede redirect.
   * - zachová ostatní parametry (např. menu=...)
   * - používá URL API, když jde; jinak fallback
   */
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
      // Fallback: přepíš jen daný parametr "natvrdo"
      // (v praxi by to mělo stačit i na starších prohlížečích).
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

  /**
   * Jít na stránku (router) – vždy přes parametr page.
   * Příklad: CB_MENU.goPage('uvod')
   */
  CB_MENU.goPage = function goPage(page) {
    const p = String(page || '').trim();
    if (!p) return;
    setParamAndGo('page', p);
  };

  /**
   * Přepnout režim menu (dropdown/sidebar) přes parametr menu.
   * Příklad: CB_MENU.setMenuMode('sidebar')
   */
  CB_MENU.setMenuMode = function setMenuMode(mode) {
    const m = String(mode || '').trim();
    if (!m) return;
    setParamAndGo('menu', m);
  };

  /* ---------------------------------------------------------
   * 2) Jednorázové "globální" zavírání panelů (click/Esc/resize/scroll)
   * --------------------------------------------------------- */

  /**
   * Naváže globální posluchače jen jednou.
   * - každá varianta menu si předá vlastní closeAll()
   * - "key" umožní mít oddělené dráty pro dropdown a sidebar
   */
  CB_MENU.wireGlobalCloseOnce = function wireGlobalCloseOnce(opts) {
    const o = opts && typeof opts === 'object' ? opts : {};
    const key = String(o.key || 'default');

    if (!w.__CB_MENU_WIRED__) w.__CB_MENU_WIRED__ = {};
    if (w.__CB_MENU_WIRED__[key]) return;
    w.__CB_MENU_WIRED__[key] = true;

    const closeAll = (typeof o.closeAll === 'function') ? o.closeAll : function () {};

    // Klik mimo: zavřít.
    // Pozn.: když chceš ignorovat klik "uvnitř menu", pošli ignoreSelector.
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

    // Escape: zavřít.
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape') closeAll();
    });

    // Resize: zavřít (typicky kvůli přepočtu pozic overlayů).
    w.addEventListener('resize', () => closeAll());

    // Scroll: zavřít (užitečné hlavně pro overlaye ve fixed pozici).
    // Pozn.: používáme capture=true, aby to chytlo i scroll v kontejnerech.
    w.addEventListener('scroll', () => closeAll(), true);
  };

  /* ---------------------------------------------------------
   * 3) Timer pro "zpožděné zavření" (stejné chování jako dropdown)
   * --------------------------------------------------------- */

  /**
   * Vytvoří jednoduchý "close timer".
   * Použití v menu_s.php:
   *   const t = CB_MENU.makeCloseTimer(() => closeAllSidebarL1(), 180);
   *   t.cancel();   // při najetí myší
   *   t.schedule(); // při odjetí myší
   */
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

})(window);

// lib/menu_zaklad.js * Verze: V1 * Aktualizace: 28.1.2026 * Počet řádků: 153
