// Obnovuje souhrn Restie a storna; od času odevzdání hlídá nové povinné důvody bez ztráty rozepsaných polí.
'use strict';

(function (w, d) {
  const refreshRequests = new WeakMap();

  // Existující řádky ponecháme v DOM, aby průběžná obnova nepřerušila psaní ani ukládání důvodu.
  function syncStornoSection(form, wrap) {
    const current = form.querySelector('.zr_storno_section');
    const next = wrap.querySelector('.zr_storno_section');
    if (!(current instanceof HTMLElement) || !(next instanceof HTMLElement)) return;
    const currentNotes = Array.from(current.querySelectorAll('[data-zr-storno-note]'));
    const nextNotes = Array.from(next.querySelectorAll('[data-zr-storno-note]'));
    const tbody = current.querySelector('.zr_storno_table > tbody');
    if (!currentNotes.length || !nextNotes.length || !(tbody instanceof HTMLElement)) {
      current.replaceWith(next);
    } else {
      const nextIds = new Set(nextNotes.map((input) => input.getAttribute('data-id-obj')));
      const currentIds = new Set(currentNotes.map((input) => input.getAttribute('data-id-obj')));
      currentNotes.forEach((input) => {
        if (nextIds.has(input.getAttribute('data-id-obj'))) return;
        const row = input.closest('tr');
        const detail = row?.nextElementSibling;
        if (detail?.hasAttribute('data-zr-storno-detail')) detail.remove();
        row?.remove();
      });
      nextNotes.forEach((input) => {
        if (currentIds.has(input.getAttribute('data-id-obj'))) return;
        const row = input.closest('tr');
        const detail = row?.nextElementSibling;
        if (!(row instanceof HTMLElement)) return;
        tbody.append(row);
        if (detail?.hasAttribute('data-zr-storno-detail')) tbody.append(detail);
      });
    }
    form.dispatchEvent(new CustomEvent('cb:report-storno-updated', { bubbles: true }));
  }

  function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;
    return hours + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
  }

  function refreshReportParts(form) {
    if (refreshRequests.has(form)) return refreshRequests.get(form);
    const idPobInput = form.querySelector('input[name="id_pob"]');
    const datumInput = form.querySelector('[name="datum_reportu"]');
    const idPob = idPobInput instanceof HTMLInputElement ? String(idPobInput.value || '') : '';
    const datum = datumInput instanceof HTMLInputElement || datumInput instanceof HTMLSelectElement ? String(datumInput.value || '') : '';
    const body = new URLSearchParams();
    body.set('cb_shell_module', 'provoz');
    body.set('page', 'denni_report');
    if (idPob !== '') {
      body.set('zr_id_pob', idPob);
    }
    if (datum !== '') {
      body.set('datum_reportu', datum);
    }

    const pending = fetch(form.action || window.CB_ENDPOINT || 'index.php', {
      method: 'POST',
      headers: {
        'X-Comeback-Shell-Module': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then((res) => {
      if (!res.ok) throw new Error('Nová data Restie se nepodařilo načíst.');
      return res.text();
    }).then((html) => {
      if (!d.body.contains(form) || form.getAttribute('data-zr-submit-busy') === '1') return;
      const wrap = d.createElement('div');
      wrap.innerHTML = String(html || '').trim();
      const nextSide = wrap.querySelector('.zr_side');
      const currentSide = form.querySelector('.zr_side');
      if (nextSide instanceof HTMLElement && currentSide instanceof HTMLElement) {
        currentSide.replaceWith(nextSide);
        syncStornoSection(form, wrap);
        const nextWarning = wrap.querySelector('[data-zr-delivery-warning-host]');
        const currentWarning = form.querySelector('[data-zr-delivery-warning-host]');
        if (nextWarning instanceof HTMLElement && currentWarning instanceof HTMLElement) {
          currentWarning.replaceWith(nextWarning);
        }
        if (typeof w.cbPrepocetReportValues === 'function') {
          w.cbPrepocetReportValues(form).catch((err) => {
            if (w.console && w.console.warn) w.console.warn(err);
          });
        }
      } else {
        throw new Error('Nová data Restie nemají platný obsah.');
      }
    }).finally(() => refreshRequests.delete(form));
    refreshRequests.set(form, pending);
    return pending;
  }

  // Po dosažení času odevzdání načteme storna ihned a poté jednou za minutu; import Restie nespouštíme.
  function bindStornoRefresh(root) {
    const form = root instanceof HTMLFormElement ? root : root.querySelector('[data-zr-form]');
    const submit = form?.querySelector('[data-zr-submit]');
    if (!(form instanceof HTMLFormElement) || !(submit instanceof HTMLButtonElement)
      || form.getAttribute('data-zr-readonly') === '1' || form.getAttribute('data-zr-storno-refresh-bound') === '1') return;
    form.setAttribute('data-zr-storno-refresh-bound', '1');
    const submitAt = Number.parseInt(String(submit.getAttribute('data-zr-submit-at') || '0'), 10) || 0;
    let nextRefresh = 0;
    const tick = () => {
      const now = Math.floor(Date.now() / 1000);
      if ((form.getAttribute('data-zr-form-mode') !== 'final_edit' && now < submitAt)
        || now < nextRefresh || form.getAttribute('data-zr-submit-busy') === '1' || d.hidden) return;
      nextRefresh = now + 60;
      refreshReportParts(form).then(() => {
        bindRestiaRefresh(form);
        bindDeliveryWarning(form);
      }).catch((err) => {
        if (w.console && w.console.warn) w.console.warn(err);
      });
    };
    tick();
    const timer = w.setInterval(() => {
      if (!d.body.contains(form)) { w.clearInterval(timer); return; }
      tick();
    }, 1000);
  }

  function setRefreshLocked(button) {
    button.disabled = true;
    button.setAttribute('aria-disabled', 'true');
  }

  function setLockedTitle(button, refreshAt) {
    const remaining = refreshAt - Math.floor(Date.now() / 1000);
    button.title = 'Aktualizace bude možná za ' + formatDuration(remaining);
  }

  function setRefreshReady(button) {
    button.disabled = false;
    button.setAttribute('aria-disabled', 'false');
    button.title = 'Aktualizovat Restii';
  }

  function bindRestiaRefresh(root) {
    const form = root instanceof HTMLFormElement ? root : root.querySelector('[data-zr-form]');
    if (!(form instanceof HTMLFormElement)) return;

    const button = form.querySelector('[data-zr-restia-refresh]');
    if (!(button instanceof HTMLButtonElement) || button.getAttribute('data-zr-restia-bound') === '1') {
      return;
    }
    button.setAttribute('data-zr-restia-bound', '1');

    const refreshAt = Number.parseInt(String(button.getAttribute('data-zr-restia-refresh-at') || '0'), 10) || 0;
    const tick = () => {
      const remaining = refreshAt - Math.floor(Date.now() / 1000);
      if (refreshAt > 0 && remaining <= 0) {
        setRefreshReady(button);
        return true;
      }
      setRefreshLocked(button);
      return false;
    };

    const updateLockedTitle = () => {
      if (!button.disabled) return;
      setLockedTitle(button, refreshAt);
    };
    button.addEventListener('mouseenter', updateLockedTitle);
    button.addEventListener('focus', updateLockedTitle);
    updateLockedTitle();

    if (!tick()) {
      const timer = w.setInterval(() => {
        if (!d.body.contains(button) || tick()) {
          w.clearInterval(timer);
        }
      }, 1000);
    }

    button.addEventListener('click', () => {
      if (button.disabled) return;
      button.disabled = true;
      button.classList.add('is-loading');
      if (!w.CB_RESTIA || typeof w.CB_RESTIA.run !== 'function') {
        w.alert('Aktualizace Restie není dostupná.');
        button.classList.remove('is-loading');
        setRefreshReady(button);
        return;
      }
      const pageBusy = w.CB_PAGE_BUSY && typeof w.CB_PAGE_BUSY.start === 'function' && typeof w.CB_PAGE_BUSY.stop === 'function'
        ? w.CB_PAGE_BUSY
        : null;
      const pageBusyHandle = pageBusy
        ? pageBusy.start('Aktualizuji objednávky ...', 'Načítám nová data Restie')
        : null;
      w.CB_RESTIA.run({
          forceRestia: true
        })
        .then(() => refreshReportParts(form))
        .catch((err) => {
          w.alert((err && err.message) ? err.message : 'Aktualizace Restie selhala.');
        })
        .finally(() => {
          if (pageBusy) {
            pageBusy.stop(pageBusyHandle);
          }
          button.classList.remove('is-loading');
          setRefreshReady(button);
          bindRestiaRefresh(form);
          bindDeliveryWarning(form);
        });
    });
  }

  function bindDeliveryWarning(root) {
    const form = root instanceof HTMLFormElement ? root : root.querySelector('[data-zr-form]');
    if (!(form instanceof HTMLFormElement)) return;

    const host = form.querySelector('[data-zr-delivery-warning-host]');
    if (!(host instanceof HTMLElement) || host.getAttribute('data-zr-delivery-warning-bound') === '1') return;
    host.setAttribute('data-zr-delivery-warning-bound', '1');

    const warningAt = Number.parseInt(String(host.getAttribute('data-zr-delivery-warning-at') || '0'), 10) || 0;
    const ready = host.getAttribute('data-zr-delivery-warning-ready') === '1';
    const delay = (warningAt * 1000) - Date.now();
    if (ready || warningAt <= 0 || delay <= 0) return;

    w.setTimeout(() => {
      if (!d.body.contains(host)) return;
      refreshReportParts(form)
        .then(() => bindDeliveryWarning(form))
        .catch((err) => {
          if (w.console && w.console.warn) w.console.warn(err);
        });
    }, Math.min(delay, 2147483647));
  }

  function init(event) {
    bindRestiaRefresh(d);
    bindDeliveryWarning(d);
    bindStornoRefresh(d);
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  d.addEventListener('cb:main-swapped', init);
})(window, document);
