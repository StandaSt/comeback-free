// js/restia_online.js * Verze: V1
'use strict';

(function (w) {
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

  CB_RESTIA.fetchState = fetchState;
  CB_RESTIA.run = function run(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const triggerRestia = opts.triggerRestia !== false;
    const stateJob = triggerRestia ? triggerCheck(opts) : fetchState();

    return stateJob.then((state) => {
      const running = !!(state && Number(state.active || 0) === 1);
      return running ? waitForFinish(opts) : state;
    });
  };
})(window);
