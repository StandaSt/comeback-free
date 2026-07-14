// js/karty_report_restia.js * Verze: V2 * Aktualizace: 12.05.2026
'use strict';

(function (w, d) {
  function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;
    return hours + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
  }

  function refreshRightSide(form) {
    const shell = form.closest('.card_shell[data-card-id]');
    const cardId = shell instanceof HTMLElement ? String(shell.getAttribute('data-card-id') || '') : '';
    const idPobInput = form.querySelector('input[name="id_pob"]');
    const idPob = idPobInput instanceof HTMLInputElement ? String(idPobInput.value || '') : '';
    if (cardId === '') return Promise.resolve();

    const url = 'index.php?cb_card_id=' + encodeURIComponent(cardId)
      + '&cb_load_max=1&zr_id_pob=' + encodeURIComponent(idPob);

    return fetch(url, {
      method: 'GET',
      headers: {
        'X-Comeback-Card': '1',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    }).then((res) => res.json()).then((data) => {
      if (!data || typeof data.cardHtml !== 'string') {
        throw new Error('Nová data Restie nemají platný obsah.');
      }
      const wrap = d.createElement('div');
      wrap.innerHTML = String(data.cardHtml || '').trim();
      const nextSide = wrap.querySelector('.zr_side');
      const currentSide = form.querySelector('.zr_side');
      if (nextSide instanceof HTMLElement && currentSide instanceof HTMLElement) {
        currentSide.replaceWith(nextSide);
        if (typeof w.cbPrepocetReportValues === 'function') {
          w.cbPrepocetReportValues(form).catch((err) => {
            if (w.console && w.console.warn) w.console.warn(err);
          });
        }
      }
    });
  }

  function setRefreshLocked(button, text, seconds) {
    button.disabled = true;
    button.setAttribute('aria-disabled', 'true');
    button.title = text + ' za ' + formatDuration(seconds);
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
      setRefreshLocked(button, 'Aktualizace bude možná', remaining);
      return false;
    };

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
      if (!w.CB_AJAX || typeof w.CB_AJAX.runRestiaAndRefreshOpCards !== 'function') {
        w.alert('Aktualizace Restie není dostupná.');
        button.classList.remove('is-loading');
        setRefreshReady(button);
        return;
      }
      w.CB_AJAX.runRestiaAndRefreshOpCards({
          forceRestia: true,
          refreshOpCards: false,
          loaderMode: 'dashboard',
          showLoading: false
        })
        .then(() => refreshRightSide(form))
        .catch((err) => {
          w.alert((err && err.message) ? err.message : 'Aktualizace Restie selhala.');
        })
        .finally(() => {
          button.classList.remove('is-loading');
          setRefreshReady(button);
          bindRestiaRefresh(form);
        });
    });
  }

  function init(event) {
    const root = event && event.detail && event.detail.card instanceof HTMLElement
      ? event.detail.card
      : d;
    bindRestiaRefresh(root);
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  d.addEventListener('cb:card-swapped', init);
  d.addEventListener('cb:card-max-loaded', init);
})(window, document);
