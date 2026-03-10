// js/menu_ajax.js * Verze: V13 * Aktualizace: 09.03.2026
'use strict';

/*
 * menu_ajax.js
 * - AJAX prepinani sekci dashboardu
 * - vymena obsahu v <main>
 * - vyvolani udalosti cb:main-swapped
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});
  const CB_AJAX = w.CB_AJAX || null;

  function getMainEl() {
    return document.querySelector('.central-content main') || document.querySelector('main');
  }

  function setMainCard(mainEl, html) {
    if (!mainEl) return;
    mainEl.innerHTML = '<section class="card">' + html + '</section>';
  }

  function setMainError(mainEl, msg) {
    const safe = String(msg || 'Neznama chyba');
    setMainCard(
      mainEl,
      '<p><strong>Nacteni se nepovedlo.</strong></p>' +
      '<p>' + safe + '</p>'
    );
  }

  function notifyMainSwap(sekce) {
    document.dispatchEvent(new CustomEvent('cb:main-swapped', {
      detail: { sekce: String(sekce || ''), page: String(sekce || '') }
    }));
  }

  let activeController = null;

  function fetchMainAndSwap(sekceKey) {
    const mainEl = getMainEl();
    const normalizeSekce = (typeof CB_MENU.normalizeSekce === 'function')
      ? CB_MENU.normalizeSekce
      : function (v) { return String(v || '3').trim() || '3'; };
    const s = normalizeSekce(sekceKey || '3');
    const reqUrl = (typeof CB_MENU.buildSekceUrl === 'function')
      ? CB_MENU.buildSekceUrl(s)
      : w.location.href;

    if (!mainEl) {
      console.error('[CB_MENU AJAX] Chybi <main>, nelze provest swap. sekce=', s);
      return;
    }

    if (!CB_AJAX || typeof CB_AJAX.fetchText !== 'function') {
      console.error('[CB_MENU AJAX] Chybi CB_AJAX.fetchText (js/ajax_core.js).');
      setMainError(mainEl, 'Chybi AJAX core.');
      return;
    }

    if (activeController) {
      activeController.abort();
      activeController = null;
    }

    const ctrl = new AbortController();
    activeController = ctrl;

    CB_AJAX.fetchText(reqUrl, {
      'X-Comeback-Partial': '1',
      'X-Comeback-Sekce': s
    }, ctrl.signal).then((html) => {
      if (activeController === ctrl) activeController = null;

      mainEl.innerHTML = html;
      CB_MENU._currentPage = s;
      if (w.history && typeof w.history.replaceState === 'function') {
        w.history.replaceState(null, '', reqUrl);
      }
      notifyMainSwap(s);
    }).catch((err) => {
      if (err && err.name === 'AbortError') return;
      if (activeController === ctrl) activeController = null;

      const msg = (err && err.message) ? err.message : 'AJAX chyba';
      console.error('[CB_MENU AJAX] Chyba nacteni sekce=', s, err);
      setMainError(mainEl, msg);
    });
  }

  function wirePopstateOnce() {
    if (w.__CB_AJAX_POPSTATE_WIRED__) return;
    w.__CB_AJAX_POPSTATE_WIRED__ = true;
  }

  CB_MENU._ajaxFetchMainAndSwap = fetchMainAndSwap;
  CB_MENU._ajaxWirePopstateOnce = wirePopstateOnce;
})(window);

// js/menu_ajax.js * Verze: V13 * Aktualizace: 09.03.2026
// Konec souboru
