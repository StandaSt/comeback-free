// lib/menu_ajax.js * Verze: V2 * Aktualizace: 14.2.2026
'use strict';

/*
 * menu_ajax.js
 * - AJAX navigace: URL v prohlížeči se nemění
 * - server dostane cílovou stránku v hlavičce X-Comeback-Page
 * - server při hlavičce X-Comeback-Partial: 1 vrátí jen HTML do <main>
 * - pokud AJAX selže, udělá se fallback na klasický reload (/comeback/)
 */

(function (w) {
  const CB_MENU = w.CB_MENU || (w.CB_MENU = {});

  function getMainEl() {
    return document.querySelector('.central-content main') || document.querySelector('main');
  }

  function setMainLoading(mainEl) {
    if (!mainEl) return;
    mainEl.innerHTML = '<section class="card"><p>Načítám…</p></section>';
  }

  let activeController = null;

  function fetchMainAndSwap(pageKey) {
    const mainEl = getMainEl();
    if (!mainEl) {
      w.location.href = w.location.href;
      return;
    }

    const p = String(pageKey || '').trim() || 'uvod';

    if (activeController) {
      activeController.abort();
      activeController = null;
    }

    const ctrl = new AbortController();
    activeController = ctrl;

    setMainLoading(mainEl);

    fetch(w.location.href, {
      method: 'GET',
      headers: {
        'X-Comeback-Partial': '1',
        'X-Comeback-Page': p
      },
      signal: ctrl.signal
    }).then((res) => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    }).then((html) => {
      if (activeController === ctrl) activeController = null;

      mainEl.innerHTML = html;
      CB_MENU._currentPage = p;
    }).catch((err) => {
      if (err && err.name === 'AbortError') return;
      w.location.href = w.location.href;
    });
  }

  // popstate nepoužíváme (URL se nemění), ale necháme stub kvůli core
  function wirePopstateOnce() {
    if (w.__CB_AJAX_POPSTATE_WIRED__) return;
    w.__CB_AJAX_POPSTATE_WIRED__ = true;
  }

  // veřejné (použije router v menu_core.js)
  CB_MENU._ajaxFetchMainAndSwap = fetchMainAndSwap;
  CB_MENU._ajaxWirePopstateOnce = wirePopstateOnce;

})(window);

// lib/menu_ajax.js * Verze: V2 * Aktualizace: 14.2.2026 * počet řádků 79
// konec souboru