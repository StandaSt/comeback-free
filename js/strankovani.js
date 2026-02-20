// js/strankovani.js * Verze: V2 * Aktualizace: 19.2.2026
'use strict';

/*
 * strankovani.js
 * - obsluha spodní lišty tabulek (stránkování + selecty)
 * - běží jen uvnitř <main> (tzn. nemíchá se do menu)
 * - provede AJAX načtení partialu a udrží čistou URL (bez ?page=...)
 *
 * V2:
 * - používá společné minimum z js/ajax_core.js (CB_AJAX.fetchText)
 */

(function (w) {
  const CB_AJAX = w.CB_AJAX || null;

  function getMainEl() {
    return document.querySelector('.central-content main') || document.querySelector('main');
  }

  function getCleanBaseUrl() {
    try {
      const u = new URL(w.location.href);
      u.search = '';
      u.hash = '';
      return u.toString();
    } catch (e) {
      return w.location.href;
    }
  }

  function keepBrowserUrlClean() {
    try {
      w.history.replaceState(null, '', getCleanBaseUrl());
    } catch (e) {
      // nic
    }
  }

  function extractPageFromUrl(urlStr) {
    try {
      const u = new URL(urlStr, w.location.href);
      const p = String(u.searchParams.get('page') || '').trim();
      return p || null;
    } catch (e) {
      return null;
    }
  }

  function setMainLoading(mainEl) {
    mainEl.innerHTML = '<section class="card"><p>Načítám…</p></section>';
  }

  function setMainError(mainEl, msg) {
    mainEl.innerHTML =
      '<section class="card">' +
      '<p><strong>Načtení se nepovedlo.</strong></p>' +
      '<p>' + String(msg || 'Neznámá chyba') + '</p>' +
      '</section>';
  }

  let activeController = null;

  function ajaxSwapByUrl(targetUrl) {
    const mainEl = getMainEl();
    if (!mainEl) return;

    if (!CB_AJAX || typeof CB_AJAX.fetchText !== 'function') {
      console.error('[CB_STRANKOVANI] Chybí CB_AJAX.fetchText (js/ajax_core.js).');
      setMainError(mainEl, 'Chybí AJAX core.');
      keepBrowserUrlClean();
      return;
    }

    const p = extractPageFromUrl(targetUrl) || 'uvod';

    if (activeController) {
      activeController.abort();
      activeController = null;
    }
    const ctrl = new AbortController();
    activeController = ctrl;

    setMainLoading(mainEl);

    CB_AJAX.fetchText(targetUrl, {
      'X-Comeback-Partial': '1',
      'X-Comeback-Page': p
    }, ctrl.signal).then((html) => {
      if (activeController === ctrl) activeController = null;
      mainEl.innerHTML = html;
      keepBrowserUrlClean();
    }).catch((err) => {
      if (err && err.name === 'AbortError') return;
      if (activeController === ctrl) activeController = null;

      console.error('[CB_STRANKOVANI] AJAX chyba', err);
      setMainError(mainEl, (err && err.message) ? err.message : 'Neznámá chyba');
      keepBrowserUrlClean();
    });
  }

  function isSameOriginUrl(urlStr) {
    try {
      const u = new URL(urlStr, w.location.href);
      return u.origin === w.location.origin;
    } catch (e) {
      return false;
    }
  }

  function isModifiedClick(ev) {
    return !!(ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || (ev.button && ev.button !== 0));
  }

  document.addEventListener('click', (ev) => {
    const mainEl = getMainEl();
    if (!mainEl) return;

    const t = ev.target;
    if (!t || !t.closest) return;

    const a = t.closest('a');
    if (!a) return;
    if (!mainEl.contains(a)) return;

    const pag = a.closest('.pagination-icon');
    if (!pag) return;

    if (a.target && String(a.target).toLowerCase() === '_blank') return;
    if (a.hasAttribute('download')) return;
    if (isModifiedClick(ev)) return;

    const href = a.getAttribute('href');
    if (!href) return;
    if (!isSameOriginUrl(href)) return;

    const p = extractPageFromUrl(href);
    if (!p) return;

    ev.preventDefault();
    ajaxSwapByUrl(new URL(href, w.location.href).toString());
  }, true);

  document.addEventListener('change', (ev) => {
    const mainEl = getMainEl();
    if (!mainEl) return;

    const el = ev.target;
    if (!el || !el.closest) return;

    const sel = el.closest('select');
    if (!sel) return;
    if (!mainEl.contains(sel)) return;

    if (!sel.classList.contains('per-select') &&
        !sel.classList.contains('blk-select') &&
        !sel.classList.contains('akt-select')) return;

    const form = sel.closest('form');
    if (!form) return;

    ev.preventDefault();

    const pInput = form.querySelector('input[name="p"]');
    if (pInput) pInput.value = '1';

    const fd = new FormData(form);
    const sp = new URLSearchParams();
    for (const pair of fd.entries()) sp.append(pair[0], String(pair[1]));

    const url = new URL(w.location.href);
    url.search = sp.toString();

    ajaxSwapByUrl(url.toString());
  }, true);

})(window);

// js/strankovani.js * Verze: V2 * Aktualizace: 19.2.2026 * Počet řádků: 184
// Konec souboru