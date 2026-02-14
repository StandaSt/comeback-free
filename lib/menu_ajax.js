// lib/menu_ajax.js * Verze: V1 * Aktualizace: 13.2.2026
'use strict';

/*
 * menu_ajax.js
 * - AJAX navigace pro změnu ?page=... (mění se jen obsah v <main>, bez reloadu celé stránky)
 * - Zpět/Vpřed (popstate) načítá obsah znovu přes AJAX
 *
 * Pozn.:
 * - očekává, že server při hlavičce X-Comeback-Partial: 1 vrátí jen HTML do <main>
 * - pokud AJAX selže, udělá se fallback na klasický reload
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

  function fetchMainAndSwap(url, push) {
    const mainEl = getMainEl();
    if (!mainEl) {
      w.location.href = url;
      return;
    }

    if (activeController) {
      activeController.abort();
      activeController = null;
    }

    const ctrl = new AbortController();
    activeController = ctrl;

    setMainLoading(mainEl);

    fetch(url, {
      method: 'GET',
      headers: { 'X-Comeback-Partial': '1' },
      signal: ctrl.signal
    }).then((res) => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    }).then((html) => {
      if (activeController === ctrl) activeController = null;

      mainEl.innerHTML = html;

      if (push) {
        try {
          w.history.pushState({ comeback: 1 }, '', url);
        } catch (e) {
          // ignorujeme – obsah už je zobrazen
        }
      }
    }).catch((err) => {
      if (err && err.name === 'AbortError') return;
      w.location.href = url;
    });
  }

  function wirePopstateOnce() {
    if (w.__CB_AJAX_POPSTATE_WIRED__) return;
    w.__CB_AJAX_POPSTATE_WIRED__ = true;

    w.addEventListener('popstate', () => {
      fetchMainAndSwap(w.location.href, false);
    });
  }

  // veřejné (použije router v menu_core.js)
  CB_MENU._ajaxFetchMainAndSwap = fetchMainAndSwap;
  CB_MENU._ajaxWirePopstateOnce = wirePopstateOnce;

})(window);

// lib/menu_ajax.js * Verze: V1 * počet řádků 86 * Aktualizace: 13.2.2026
// konec souboru