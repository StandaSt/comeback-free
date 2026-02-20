// js/menu_ajax.js * Verze: V4 * Aktualizace: 19.2.2026
'use strict';

/*
 * menu_ajax.js
 * - AJAX navigace: URL v prohlížeči se nemění
 * - server dostane cílovou stránku v hlavičce X-Comeback-Page
 * - server při hlavičce X-Comeback-Partial: 1 vrátí jen HTML do <main>
 *
 * V4:
 * - používá společné minimum z js/ajax_core.js (CB_AJAX.fetchText)
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

  function setMainLoading(mainEl) {
    setMainCard(mainEl, '<p>Načítám…</p>');
  }

  function setMainError(mainEl, msg) {
    const safe = String(msg || 'Neznámá chyba');
    setMainCard(
      mainEl,
      '<p><strong>Načtení se nepovedlo.</strong></p>' +
      '<p>' + safe + '</p>'
    );
  }

  let activeController = null;

  function fetchMainAndSwap(pageKey) {
    const mainEl = getMainEl();
    const p = String(pageKey || '').trim() || 'uvod';

    if (!mainEl) {
      console.error('[CB_MENU AJAX] Chybí <main>, nelze provést swap. page=', p);
      return;
    }

    if (!CB_AJAX || typeof CB_AJAX.fetchText !== 'function') {
      console.error('[CB_MENU AJAX] Chybí CB_AJAX.fetchText (js/ajax_core.js).');
      setMainError(mainEl, 'Chybí AJAX core.');
      return;
    }

    if (activeController) {
      activeController.abort();
      activeController = null;
    }

    const ctrl = new AbortController();
    activeController = ctrl;

    setMainLoading(mainEl);

    CB_AJAX.fetchText(w.location.href, {
      'X-Comeback-Partial': '1',
      'X-Comeback-Page': p
    }, ctrl.signal).then((html) => {
      if (activeController === ctrl) activeController = null;

      mainEl.innerHTML = html;
      CB_MENU._currentPage = p;
    }).catch((err) => {
      if (err && err.name === 'AbortError') return;
      if (activeController === ctrl) activeController = null;

      const msg = (err && err.message) ? err.message : 'AJAX chyba';
      console.error('[CB_MENU AJAX] Chyba načtení page=', p, err);
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

// js/menu_ajax.js * Verze: V4 * Aktualizace: 19.2.2026 * Počet řádků: 98
// Konec souboru