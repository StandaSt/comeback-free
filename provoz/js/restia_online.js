// js/restia_online.js * Verze: V1
'use strict';

(function (w) {
  const d = w.document;
  const CB_RESTIA = w.CB_RESTIA || (w.CB_RESTIA = {});

  function requestUrl() {
    return String(w.location.href || (w.CB_ENDPOINT || 'index.php'));
  }

  function readJson(res) {
    if (!res.ok) {
      throw new Error('HTTP ' + res.status);
    }

    const contentType = String(res.headers.get('Content-Type') || '').toLowerCase();
    return res.text().then((text) => {
      const trimmed = String(text || '').trim();
      if (trimmed === '') {
        throw new Error('Prázdná odpověď serveru.');
      }
      if (contentType.indexOf('application/json') === -1 && trimmed.charAt(0) === '<') {
        throw new Error('Session pravděpodobně vypršela. Obnovte stránku a přihlaste se znovu.');
      }
      try {
        return JSON.parse(trimmed);
      } catch (err) {
        throw new Error('Neplatná JSON odpověď serveru.');
      }
    });
  }

  function fetchState() {
    return fetch(requestUrl(), {
      method: 'GET',
      headers: { 'X-Comeback-Restia-State': '1' },
      credentials: 'same-origin'
    }).then(readJson);
  }

  function triggerCheck(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const moduleName = String(opts.moduleName || w.CB_ACTIVE_MAIN_MODULE || '');
    if (moduleName !== 'provoz') {
      return Promise.resolve({
        ok: true,
        started: 0,
        active: 0,
        skipped_module: 1
      });
    }

    const headers = {
      'X-Comeback-Restia-Trigger': '1',
      'X-Comeback-Module': moduleName,
      'Accept': 'application/json'
    };
    if (opts.forceRestia === true) {
      headers['X-Comeback-Restia-Force'] = '1';
    }

    return fetch(requestUrl(), {
      method: 'POST',
      headers: headers,
      credentials: 'same-origin'
    }).then(readJson);
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

  CB_RESTIA.fetchState = fetchState;
  CB_RESTIA.isFresh = isFresh;
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
})(window);
