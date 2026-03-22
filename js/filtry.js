// js/filtry.js * Verze: V6 * Aktualizace: 19.03.2026
'use strict';

/*
 * Jednotny filtr pro karty:
 * - live filtrovani pri psani (debounce)
 * - submit/select bez reloadu stranky
 * - swap pouze uvnitr aktualni karty (table-wrap + list-bottom)
 */

(function (w) {
  const CB_AJAX = w.CB_AJAX || null;
  const timers = new WeakMap();
  const controllers = new WeakMap();

  function getPrefixFromName(name) {
    const s = String(name || '');
    const m = s.match(/^([a-z0-9_]+)_f\[/i);
    return m ? String(m[1] || '').toLowerCase() : '';
  }

  function getCardFilterPrefix(form) {
    if (!(form instanceof HTMLFormElement)) return '';
    const el = form.querySelector('input.filter-input[name*="_f["]');
    return el ? getPrefixFromName(el.name) : '';
  }

  function getPageInput(form, prefix) {
    if (!(form instanceof HTMLFormElement) || !prefix) return null;
    return form.querySelector('input[name="' + prefix + '_p"]');
  }

  function buildUrlFromForm(form) {
    const action = form.getAttribute('action') || w.location.href;
    const url = new URL(action, w.location.href);
    const fd = new FormData(form);
    url.search = new URLSearchParams(fd).toString();
    return url.toString();
  }

  function findResponseForm(doc, prefix) {
    if (!doc || !prefix) return null;
    const marker = doc.querySelector('form input[name="' + prefix + '_p"]');
    return marker ? marker.closest('form') : null;
  }

  function swapFormParts(curForm, newForm) {
    if (!(curForm instanceof HTMLFormElement) || !(newForm instanceof HTMLFormElement)) return false;

    const curTable = curForm.querySelector('.table-wrap');
    const curBottom = curForm.querySelector('.list-bottom');
    const newTable = newForm.querySelector('.table-wrap');
    const newBottom = newForm.querySelector('.list-bottom');

    if (!curTable || !newTable) return false;

    curTable.replaceWith(newTable);
    if (curBottom && newBottom) {
      curBottom.replaceWith(newBottom);
    } else if (curBottom && !newBottom) {
      curBottom.remove();
    } else if (!curBottom && newBottom) {
      curForm.appendChild(newBottom);
    }

    return true;
  }

  function fetchAndSwap(form, prefix, reqUrlOverride) {
    if (!(form instanceof HTMLFormElement) || !prefix) return;
    if (!CB_AJAX || typeof CB_AJAX.fetchText !== 'function') {
      form.submit();
      return;
    }

    const oldCtrl = controllers.get(form);
    if (oldCtrl) oldCtrl.abort();

    const ctrl = new AbortController();
    controllers.set(form, ctrl);

    const activeEl = document.activeElement;
    const focusName = (activeEl instanceof HTMLInputElement) ? String(activeEl.name || '') : '';
    const selStart = (activeEl instanceof HTMLInputElement && typeof activeEl.selectionStart === 'number')
      ? activeEl.selectionStart
      : null;
    const selEnd = (activeEl instanceof HTMLInputElement && typeof activeEl.selectionEnd === 'number')
      ? activeEl.selectionEnd
      : null;

    const reqUrl = String(reqUrlOverride || buildUrlFromForm(form));

    CB_AJAX.fetchText(reqUrl, { 'X-Comeback-Partial': '1' }, ctrl.signal)
      .then((html) => {
        if (controllers.get(form) !== ctrl) return;
        controllers.delete(form);

        const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
        const newForm = findResponseForm(doc, prefix);
        if (!newForm) return;
        const ok = swapFormParts(form, newForm);
        if (!ok) return;

        if (focusName !== '') {
          const nextInput = form.querySelector('input[name="' + CSS.escape(focusName) + '"]');
          if (nextInput instanceof HTMLInputElement) {
            nextInput.focus();
            if (selStart !== null && selEnd !== null) {
              const valLen = String(nextInput.value || '').length;
              const s = Math.min(selStart, valLen);
              const e = Math.min(selEnd, valLen);
              nextInput.setSelectionRange(s, e);
            }
          }
        }
      })
      .catch((err) => {
        if (err && err.name === 'AbortError') return;
        controllers.delete(form);
      });
  }

  document.addEventListener('input', (ev) => {
    const t = ev.target;
    if (!(t instanceof HTMLInputElement)) return;
    if (!t.classList.contains('filter-input')) return;

    const prefix = getPrefixFromName(t.name);
    if (!prefix) return;

    const form = t.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    const p = getPageInput(form, prefix);
    if (p) p.value = '1';

    const oldTimer = timers.get(form);
    if (oldTimer) clearTimeout(oldTimer);

    const timer = setTimeout(() => {
      timers.delete(form);
      fetchAndSwap(form, prefix);
    }, 250);
    timers.set(form, timer);
  }, true);

  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (String(form.method || 'get').toLowerCase() !== 'get') return;

    const prefix = getCardFilterPrefix(form);
    if (!prefix) return;

    ev.preventDefault();
    fetchAndSwap(form, prefix);
  }, true);

  document.addEventListener('click', (ev) => {
    const a = ev.target instanceof Element ? ev.target.closest('a') : null;
    if (!(a instanceof HTMLAnchorElement)) return;

    const form = a.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    const prefix = getCardFilterPrefix(form);
    if (!prefix) return;

    const href = String(a.getAttribute('href') || '').trim();
    if (href === '' || href === '#') return;

    ev.preventDefault();
    const absHref = new URL(href, w.location.href).toString();
    fetchAndSwap(form, prefix, absHref);
  }, true);
})(window);

// js/filtry.js * Verze: V6 * Aktualizace: 19.03.2026
// Konec souboru
