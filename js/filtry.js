// js/filtry.js * Verze: V3 * Aktualizace: 19.2.2026
'use strict';

/*
 * filtry.js
 * - filtry v tabulkách (řádek s inputy + Enter + X)
 * - automatické filtrování při psaní (debounce)
 * - běží jen uvnitř <main> (tzn. nemíchá se do menu)
 * - udrží čistou URL (bez ?page=...)
 *
 * V3:
 * - při psaní NEpřekreslí celé <main>, ale jen <tbody> + .list-bottom
 *   → řádek filtrů zůstane, fokus v inputu se neztratí
 * - fallback: když nejde “tělový swap”, udělá původní full swap
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

  function parseHtml(html) {
    const s = String(html || '');
    const p = new DOMParser();
    return p.parseFromString(s, 'text/html');
  }

  function swapTableOnly(mainEl, html) {
    // Aktuální DOM
    const curTable = mainEl.querySelector('table.table');
    const curTbody = curTable ? curTable.querySelector('tbody') : null;
    const curBottom = mainEl.querySelector('.list-bottom');

    if (!curTable || !curTbody || !curBottom) return false;

    // Nový DOM z odpovědi
    const doc = parseHtml(html);
    const newTable = doc.querySelector('table.table');
    const newTbody = newTable ? newTable.querySelector('tbody') : null;
    const newBottom = doc.querySelector('.list-bottom');

    if (!newTable || !newTbody || !newBottom) return false;

    // Swap jen “dat”
    curTbody.innerHTML = newTbody.innerHTML;
    curBottom.innerHTML = newBottom.innerHTML;

    return true;
  }

  let activeController = null;

  function ajaxSwapByUrl(targetUrl, mode) {
    const mainEl = getMainEl();
    if (!mainEl) return;

    if (!CB_AJAX || typeof CB_AJAX.fetchText !== 'function') {
      console.error('[CB_FILTRY] Chybí CB_AJAX.fetchText (js/ajax_core.js).');
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

    // při psaní nesmíme přepsat celý <main> (kvůli fokusu)
    if (mode !== 'table-only') {
      setMainLoading(mainEl);
    }

    CB_AJAX.fetchText(targetUrl, {
      'X-Comeback-Partial': '1',
      'X-Comeback-Page': p
    }, ctrl.signal).then((html) => {
      if (activeController === ctrl) activeController = null;

      if (mode === 'table-only') {
        const ok = swapTableOnly(mainEl, html);
        if (!ok) {
          // fallback: když stránka nemá očekávanou strukturu
          mainEl.innerHTML = html;
        }
      } else {
        mainEl.innerHTML = html;
      }

      keepBrowserUrlClean();
    }).catch((err) => {
      if (err && err.name === 'AbortError') return;
      if (activeController === ctrl) activeController = null;

      console.error('[CB_FILTRY] AJAX chyba', err);
      setMainError(mainEl, (err && err.message) ? err.message : 'Neznámá chyba');
      keepBrowserUrlClean();
    });
  }

  function buildGetUrlFromForm(form) {
    const fd = new FormData(form);
    const sp = new URLSearchParams();
    for (const pair of fd.entries()) sp.append(pair[0], String(pair[1]));

    const url = new URL(w.location.href);
    url.search = sp.toString();
    return url.toString();
  }

  document.addEventListener('submit', (ev) => {
    const mainEl = getMainEl();
    if (!mainEl) return;

    const form = ev.target;
    if (!form || !form.tagName) return;
    if (String(form.tagName).toLowerCase() !== 'form') return;
    if (!mainEl.contains(form)) return;

    const method = String(form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'get') return;

    if (!form.querySelector('.filter-input') && !form.querySelector('.filter-row')) return;

    ev.preventDefault();

    const pInput = form.querySelector('input[name="p"]');
    if (pInput) pInput.value = '1';

    // submit (Enter) může klidně full swap (nevadí fokus)
    ajaxSwapByUrl(buildGetUrlFromForm(form), 'full');
  }, true);

  document.addEventListener('click', (ev) => {
    const mainEl = getMainEl();
    if (!mainEl) return;

    const t = ev.target;
    if (!t || !t.closest) return;

    const a = t.closest('a');
    if (!a) return;
    if (!mainEl.contains(a)) return;

    if (!a.classList.contains('icon-x')) return;

    const href = a.getAttribute('href');
    if (!href) return;

    const abs = new URL(href, w.location.href).toString();
    const p = extractPageFromUrl(abs);
    if (!p) return;

    ev.preventDefault();

    // X – full swap je OK
    ajaxSwapByUrl(abs, 'full');
  }, true);

  let timer = null;

  document.addEventListener('input', (ev) => {
    const mainEl = getMainEl();
    if (!mainEl) return;

    const el = ev.target;
    if (!el || !el.classList) return;
    if (!el.classList.contains('filter-input')) return;
    if (!mainEl.contains(el)) return;

    const form = el.closest('form');
    if (!form) return;

    if (el.tagName && String(el.tagName).toLowerCase() !== 'input') return;

    if (timer) w.clearTimeout(timer);

    timer = w.setTimeout(() => {
      timer = null;

      const pInput = form.querySelector('input[name="p"]');
      if (pInput) pInput.value = '1';

      // při psaní: jen tbody + list-bottom (fokus zůstane)
      ajaxSwapByUrl(buildGetUrlFromForm(form), 'table-only');
    }, 250);
  }, true);

})(window);

// js/filtry.js * Verze: V3 * Aktualizace: 19.2.2026 * Počet řádků: 239
// Konec souboru