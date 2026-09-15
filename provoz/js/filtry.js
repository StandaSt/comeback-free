// js/filtry.js * Verze: V9 * Aktualizace: 15.09.2026
'use strict';

/*
 * Jednotny filtr pro karty:
 * - live filtrovani pri psani (debounce)
 * - submit/select bez reloadu stranky
 * - swap pouze uvnitr aktualni karty (table-wrap + list-bottom)
 *
 * Poznamka pro AI/Codex:
 * Nova filtrovana tabulka ma pouzit jeden prefix, napriklad "zak".
 * Povinna struktura: form method="get", hidden input "zak_p", filtry
 * "zak_f[nazev_sloupce]" s tridou filter-input, volitelne "zak_per",
 * tabulka v .table-wrap a spodni lista v .list-bottom. Souhrn filtrovaneho
 * vysledku patri do .filter-summary uvnitr stejneho formulare.
 * Nevymyslet vlastni JS pro kazdou tabulku; tento soubor je spolecny.
 */

(function (w) {
  const timers = new WeakMap();
  const controllers = new WeakMap();

  function fetchText(url, headers, signal) {
    if (w.CB_AJAX && typeof w.CB_AJAX.fetchText === 'function') {
      return w.CB_AJAX.fetchText(url, headers, signal);
    }
    if (typeof w.fetch !== 'function') {
      return null;
    }
    return w.fetch(String(url || ''), {
      method: 'GET',
      headers: headers && typeof headers === 'object' ? headers : {},
      signal: signal,
      credentials: 'same-origin'
    }).then((response) => {
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.text();
    });
  }

  function getPrefixFromName(name) {
    const s = String(name || '');
    const m = s.match(/^([a-z0-9_]+)_f\[/i);
    return m ? String(m[1] || '').toLowerCase() : '';
  }

  function getColumnFromName(name) {
    const s = String(name || '');
    const m = s.match(/^[a-z0-9_]+_f\[([^\]]+)\]/i);
    return m ? String(m[1] || '').trim() : '';
  }

  function getControlForm(control) {
    if (!(control instanceof HTMLElement)) return null;
    if ('form' in control && control.form instanceof HTMLFormElement) {
      return control.form;
    }
    const form = control.closest('form');
    return form instanceof HTMLFormElement ? form : null;
  }

  function getNamedFormElement(form, name) {
    if (!(form instanceof HTMLFormElement) || !name) return null;
    const element = form.elements.namedItem(name);
    return element instanceof HTMLElement ? element : null;
  }

  function getCardFilterPrefix(form) {
    if (!(form instanceof HTMLFormElement)) return '';
    const el = Array.from(form.elements).find((element) => (
      element instanceof HTMLElement
      && element.classList.contains('filter-input')
      && String(element.getAttribute('name') || '').indexOf('_f[') !== -1
    ));
    return el ? getPrefixFromName(el.name) : '';
  }

  function getPageInput(form, prefix) {
    if (!(form instanceof HTMLFormElement) || !prefix) return null;
    const input = getNamedFormElement(form, prefix + '_p');
    return input instanceof HTMLInputElement ? input : null;
  }

  function getCardIdFromForm(form) {
    if (!(form instanceof HTMLFormElement)) return 0;
    const shell = form.closest('.card_shell[data-card-id]');
    if (!(shell instanceof HTMLElement)) return 0;
    const id = parseInt(String(shell.getAttribute('data-card-id') || '0'), 10);
    return Number.isFinite(id) && id > 0 ? id : 0;
  }

  function logUserAction(idAkce, form, detail) {
    const actionId = parseInt(String(idAkce || '0'), 10);
    if (!Number.isFinite(actionId) || actionId <= 0) return;

    const cardId = getCardIdFromForm(form);
    const payload = {
      id_akce: actionId,
      vysledek: 1,
      zdroj: cardId > 0 ? ('K' + String(cardId)) : 'filtry'
    };
    if (cardId > 0) {
      payload.id_karta = cardId;
    }
    if (detail && typeof detail === 'object') {
      payload.detail = detail;
    }

    w.fetch((w.CB_ENDPOINT || 'index.php'), {
      method: 'POST',
      headers: {
        'X-Comeback-User-Akce': '1',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).catch(() => {});
  }

  function buildUrlFromForm(form, reqUrlOverride) {
    const action = reqUrlOverride || form.getAttribute('action') || w.location.href;
    const url = new URL(action, w.location.href);
    const prefix = getCardFilterPrefix(form);
    const cardId = getCardIdFromForm(form);
    if (reqUrlOverride) {
      if (cardId > 0) {
        url.searchParams.set('cb_card_id', String(cardId));
      }
      return url.toString();
    }

    const defaults = {};
    if (prefix) {
      defaults[prefix + '_p'] = String(form.getAttribute('data-cb-filter-default-page') || '1');
      defaults[prefix + '_per'] = String(form.getAttribute('data-cb-filter-default-per') || '20');
      defaults[prefix + '_sort'] = String(form.getAttribute('data-cb-filter-default-sort') || 'id');
      defaults[prefix + '_dir'] = String(form.getAttribute('data-cb-filter-default-dir') || 'DESC');
      if (prefix === 'uz') defaults.uz_akt = '1';
      if (prefix === 'zak') defaults.zak_blk = '0';
    }

    const fd = new FormData(form);
    const qs = new URLSearchParams();
    fd.forEach((rawValue, rawName) => {
      const name = String(rawName || '');
      const value = String(rawValue ?? '').trim();
      if (value === '') return;
      if (Object.prototype.hasOwnProperty.call(defaults, name) && value === defaults[name]) return;
      qs.append(name, value);
    });

    if (cardId > 0) {
      qs.set('cb_card_id', String(cardId));
    }

    url.search = qs.toString();
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
    const curSummary = curForm.querySelector('.card-max-summary');
    const newTable = newForm.querySelector('.table-wrap');
    const newBottom = newForm.querySelector('.list-bottom');
    const newSummary = newForm.querySelector('.card-max-summary');

    if (!curTable || !newTable) return false;

    Array.from(newForm.elements).forEach((newElement) => {
      const curElement = getNamedFormElement(curForm, newElement.name);
      if (newElement instanceof HTMLInputElement && newElement.type === 'hidden'
        && curElement instanceof HTMLInputElement && curElement.type === 'hidden') {
        curElement.value = newElement.value;
        return;
      }
      if (newElement instanceof HTMLSelectElement
        && newElement.getAttribute('data-cb-filter-refresh-options') === '1'
        && curElement instanceof HTMLSelectElement) {
        curElement.replaceChildren(...Array.from(newElement.options).map((option) => option.cloneNode(true)));
        curElement.value = newElement.value;
      }
    });

    if (curSummary && newSummary) {
      curSummary.replaceWith(newSummary);
    } else if (curSummary && !newSummary) {
      curSummary.remove();
    } else if (!curSummary && newSummary) {
      curForm.insertBefore(newSummary, curTable);
    }

    const curFilterSummaries = Array.from(curForm.querySelectorAll('.filter-summary'));
    const newFilterSummaries = Array.from(newForm.querySelectorAll('.filter-summary'));
    curFilterSummaries.forEach((summary, index) => {
      const replacement = newFilterSummaries[index];
      if (replacement) {
        summary.replaceWith(replacement);
      }
    });

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

  function fetchAndSwap(form, prefix, reqUrlOverride, logDetail, logActionId) {
    if (!(form instanceof HTMLFormElement) || !prefix) return;

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

    const reqUrl = String(buildUrlFromForm(form, reqUrlOverride));
    const requestStartedAt = performance.now();
    const cardId = getCardIdFromForm(form);
    let request;
    if (cardId > 0) {
      request = fetch(reqUrl, {
        method: 'GET',
        headers: {
          'X-Comeback-Card-Max': '1',
          'Accept': 'application/json'
        },
        credentials: 'same-origin',
        signal: ctrl.signal
      }).then((res) => res.text().then((text) => {
        const raw = String(text || '').trim();
        let data = null;
        if (raw !== '') {
          try {
            data = JSON.parse(raw);
          } catch (e) {
            data = null;
          }
        }
        if (!res.ok || !data || typeof data.maxHtml !== 'string') {
          throw new Error('Filtrovana karta vratila neplatny obsah.');
        }
        return data.maxHtml;
      }));
    } else {
      request = fetchText(reqUrl, { 'X-Comeback-Partial': '1' }, ctrl.signal);
      if (!request) {
        controllers.delete(form);
        w.location.assign(reqUrl);
        return;
      }
    }

    request.then((html) => {
        if (controllers.get(form) !== ctrl) return;
        controllers.delete(form);

        const doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
        const newForm = findResponseForm(doc, prefix);
        if (!newForm) {
          w.location.assign(reqUrl);
          return;
        }
        const ok = swapFormParts(form, newForm);
        if (!ok) {
          w.location.assign(reqUrl);
          return;
        }

        document.dispatchEvent(new CustomEvent('cb:filters-swapped', {
          detail: {
            form: form,
            prefix: prefix,
            elapsedMs: Math.round(performance.now() - requestStartedAt)
          }
        }));

        if (logDetail && typeof logDetail === 'object') {
          logUserAction(logActionId || 5, form, logDetail);
        }

        if (focusName !== '') {
          const nextInput = getNamedFormElement(form, focusName);
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
        w.location.assign(reqUrl);
      });
  }

  function isFilterControl(el, prefix) {
    if (!(el instanceof HTMLInputElement) && !(el instanceof HTMLSelectElement)) return false;
    const name = String(el.name || '');
    if (!prefix || name === '') return false;
    if (name.indexOf(prefix + '_') !== 0) return false;
    if (el instanceof HTMLInputElement && ['text', 'search'].indexOf(String(el.type || '').toLowerCase()) !== -1) {
      return false;
    }
    return true;
  }

  function getPageFromUrl(href, prefix) {
    if (!href || !prefix) return '';
    try {
      const url = new URL(String(href), w.location.href);
      return String(url.searchParams.get(prefix + '_p') || '').trim();
    } catch (e) {
      return '';
    }
  }

  document.addEventListener('input', (ev) => {
    const t = ev.target;
    if (!(t instanceof HTMLInputElement)) return;
    if (!t.classList.contains('filter-input')) return;

    const prefix = getPrefixFromName(t.name);
    if (!prefix) return;

    const form = getControlForm(t);
    if (!(form instanceof HTMLFormElement)) return;

    const p = getPageInput(form, prefix);
    if (p) p.value = '1';

    const oldTimer = timers.get(form);
    if (oldTimer) clearTimeout(oldTimer);

    const timer = setTimeout(() => {
      timers.delete(form);
      const column = getColumnFromName(t.name);
      const detail = {
        prefix: prefix
      };
      if (column !== '') {
        detail[column] = String(t.value || '').trim();
      }
      fetchAndSwap(form, prefix, null, detail);
    }, 750);
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

  document.addEventListener('change', (ev) => {
    const t = ev.target;
    if (!(t instanceof HTMLInputElement) && !(t instanceof HTMLSelectElement)) return;

    const form = getControlForm(t);
    if (!(form instanceof HTMLFormElement)) return;

    const prefix = getCardFilterPrefix(form);
    if (!prefix || !isFilterControl(t, prefix)) return;

    ev.preventDefault();
    ev.stopPropagation();

    const p = getPageInput(form, prefix);
    if (p && t !== p) p.value = '1';

    const isPerChange = String(t.name || '') === (prefix + '_per');
    const logDetail = isPerChange
      ? {
        prefix: prefix,
        pocet_radku: String(t.value || '').trim()
      }
      : null;

    fetchAndSwap(form, prefix, null, logDetail, isPerChange ? 8 : 5);
  }, true);

  document.addEventListener('click', (ev) => {
    const a = ev.target instanceof Element ? ev.target.closest('a') : null;
    if (!(a instanceof HTMLAnchorElement)) return;
    if (a.getAttribute('data-cb-filter-ignore') === '1') return;

    const form = a.closest('form');
    if (!(form instanceof HTMLFormElement)) return;

    const prefix = getCardFilterPrefix(form);
    if (!prefix) return;

    const href = String(a.getAttribute('href') || '').trim();
    if (href === '' || href === '#') return;

    ev.preventDefault();
    ev.stopPropagation();
    const isReset = a.classList.contains('filter-reset-btn') || (
      a.classList.contains('icon-x') && a.closest('.filter-actions')
    );
    const pagination = a.closest('.pagination-icon');
    const targetPage = pagination ? getPageFromUrl(href, prefix) : '';
    const isPagination = targetPage !== '';
    let logDetail = null;
    let logActionId = 0;

    if (isReset) {
      logDetail = { prefix: prefix };
      logActionId = 6;
    } else if (isPagination) {
      logDetail = {
        prefix: prefix,
        stranka: targetPage
      };
      logActionId = 7;
    }

    fetchAndSwap(form, prefix, href, logDetail, logActionId);
  }, true);

})(window);

// js/filtry.js * Verze: V8 * Aktualizace: 03.06.2026
// Konec souboru
