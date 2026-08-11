// js/restia_online.js * Verze: V1
'use strict';

(function (w) {
  const d = w.document;
  const CB_RESTIA = w.CB_RESTIA || (w.CB_RESTIA = {});

  function requestUrl() {
    return String(w.location.href || (w.CB_ENDPOINT || 'index.php'));
  }

  function fetchState() {
    return fetch(requestUrl(), {
      method: 'GET',
      headers: { 'X-Comeback-Restia-State': '1' },
      credentials: 'same-origin'
    }).then((res) => {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.json();
    });
  }

  function triggerCheck(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const activeModule = String(w.CB_ACTIVE_MAIN_MODULE || '');
    if (activeModule !== 'provoz') {
      return Promise.resolve({
        ok: true,
        started: 0,
        active: 0,
        skipped_module: 1
      });
    }

    const headers = {
      'X-Comeback-Restia-Trigger': '1',
      'X-Comeback-Module': activeModule,
      'Accept': 'application/json'
    };
    if (opts.forceRestia === true) {
      headers['X-Comeback-Restia-Force'] = '1';
    }

    return fetch(requestUrl(), {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin'
    }).then((res) => {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.json();
    });
  }

  function waitForFinish(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const timeoutMs = Math.max(1000, Number(opts.timeoutMs) || 180000);
    const pollMs = Math.max(200, Number(opts.pollMs) || 500);
    const startedAt = Date.now();

    return new Promise((resolve, reject) => {
      function check() {
        fetchState()
          .then((state) => {
            const running = !!(state && Number(state.active || 0) === 1);
            if (!running) {
              resolve(state || {});
              return;
            }
            if ((Date.now() - startedAt) >= timeoutMs) {
              reject(new Error('Aktualizace Restie běží příliš dlouho.'));
              return;
            }
            w.setTimeout(check, pollMs);
          })
          .catch((err) => {
            reject(err);
          });
      }

      check();
    });
  }

  function parseDbTime(value) {
    const raw = String(value || '').trim();
    if (raw === '') return 0;
    const parsed = Date.parse(raw.replace(' ', 'T'));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function isFresh(state) {
    const finishedAt = parseDbTime(state && state.konec);
    if (finishedAt <= 0) return false;
    return (Date.now() - finishedAt) < 120000;
  }

  function reloadRestiaScope() {
    if (typeof w.CB_LOAD_MODULE !== 'function') return;
    if (String(w.CB_ACTIVE_MAIN_MODULE || '') !== 'provoz') return;

    const params = new URLSearchParams();
    const form = d.querySelector('[data-zr-form]');
    if (form instanceof HTMLFormElement) {
      const idPobInput = form.querySelector('input[name="id_pob"]');
      const datumInput = form.querySelector('[name="datum_reportu"]');
      const idPob = idPobInput instanceof HTMLInputElement ? String(idPobInput.value || '') : '';
      const datum = datumInput instanceof HTMLInputElement || datumInput instanceof HTMLSelectElement ? String(datumInput.value || '') : '';
      params.set('page', 'denni_report');
      if (idPob !== '') params.set('zr_id_pob', idPob);
      if (datum !== '') params.set('datum_reportu', datum);
    }

    w.CB_LOAD_MODULE('provoz', false, params);
  }

  CB_RESTIA.fetchState = fetchState;
  CB_RESTIA.run = function run(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const triggerRestia = opts.triggerRestia !== false;
    const loaderText = String(opts.loaderText || '').trim();
    const useLoader = loaderText !== '' && w.CB_LOADER && typeof w.CB_LOADER.show === 'function' && typeof w.CB_LOADER.hide === 'function';
    const stateJob = triggerRestia ? triggerCheck(opts) : fetchState();

    if (useLoader) {
      w.CB_LOADER.show(loaderText);
    }

    return stateJob.then((state) => {
      const running = !!(state && Number(state.active || 0) === 1);
      return running ? waitForFinish(opts) : state;
    }).finally(() => {
      if (useLoader) {
        w.CB_LOADER.hide();
      }
    });
  };

  function hasRestiaData(root) {
    const scope = root && root.querySelector ? root : d;
    return !!(scope && scope.querySelector && scope.querySelector('[data-cb-restia-needed="1"]'));
  }

  function autoRunForRestiaData() {
    const main = d.querySelector('[data-obal-main="1"]') || d.body;
    if (!hasRestiaData(main)) return;
    if (main.getAttribute('data-cb-restia-auto-running') === '1') return;

    main.setAttribute('data-cb-restia-auto-running', '1');
    fetchState()
      .then((beforeState) => {
        if (isFresh(beforeState)) return null;
        return CB_RESTIA.run({
          loaderText: 'Aktualizuji data z Restie ...'
        }).then((afterState) => {
          if (String((afterState && afterState.konec) || '') !== String((beforeState && beforeState.konec) || '')) {
            reloadRestiaScope();
          }
          return afterState;
        });
      })
      .catch((err) => {
        if (w.console && w.console.warn) w.console.warn(err);
      })
      .finally(() => {
        main.removeAttribute('data-cb-restia-auto-running');
      });
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', autoRunForRestiaData);
  } else {
    autoRunForRestiaData();
  }
  d.addEventListener('cb:main-swapped', autoRunForRestiaData);
})(window);
