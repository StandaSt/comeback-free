// js/denni_report_restia.js * Verze: V1
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

    return fetch(window.CB_ENDPOINT || 'index.php', {
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
      const wrap = d.createElement('div');
      wrap.innerHTML = String(html || '').trim();
      const nextSide = wrap.querySelector('.zr_side');
      const currentSide = form.querySelector('.zr_side');
      if (nextSide instanceof HTMLElement && currentSide instanceof HTMLElement) {
        currentSide.replaceWith(nextSide);
        if (typeof w.cbPrepocetReportValues === 'function') {
          w.cbPrepocetReportValues(form).catch((err) => {
            if (w.console && w.console.warn) w.console.warn(err);
          });
        }
      } else {
        throw new Error('Nová data Restie nemají platný obsah.');
      }
    });
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
        .then(() => refreshRightSide(form))
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
        });
    });
  }

  function init(event) {
    bindRestiaRefresh(d);
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
  d.addEventListener('cb:main-swapped', init);
})(window, document);
