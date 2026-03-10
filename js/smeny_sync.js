/*
 * js/smeny_sync.js
 * Zive zobrazeni prubehu synchronizace smen (start + kroky).
 */
(function () {
  'use strict';

  function byId(id) {
    return document.getElementById(id);
  }

  function setText(id, text) {
    var el = byId(id);
    if (el) {
      el.textContent = text;
    }
  }

  function clearLog() {
    var log = byId('cbSyncLog');
    if (!log) return;
    log.innerHTML = '';
  }

  function appendLogLine(text) {
    var log = byId('cbSyncLog');
    if (!log) return;
    var row = document.createElement('div');
    row.textContent = text;
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  function appendLogGap() {
    appendLogLine('');
  }

  function parseApiPeriodBranch(apiQuery) {
    var raw = String(apiQuery || '');
    var parts = raw.split('/').map(function (s) { return s.trim(); });
    if (parts.length >= 3) {
      return parts[1] + ' / ' + parts[2];
    }
    return '... / ...';
  }

  function showBox(show) {
    var box = byId('cbSyncInfo');
    if (!box) return;
    box.style.display = show ? 'block' : 'none';
  }

  function showCloseButton(show) {
    var btn = byId('cbSyncClose');
    if (!btn) return;
    btn.style.display = show ? 'inline-block' : 'none';
  }

  function setDoneText(text) {
    var box = byId('cbSyncInfo');
    if (!box) return;

    var done = byId('cbSyncDone');
    if (!done) {
      done = document.createElement('div');
      done.id = 'cbSyncDone';
      done.style.marginTop = '6px';
      done.style.color = '#b91c1c';
      done.style.fontWeight = '700';
      box.appendChild(done);
    }

    done.textContent = text || '';
  }

  function setTitleWithSeconds(startMs) {
    var box = byId('cbSyncInfo');
    if (!box) return;

    var title = box.querySelector('div');
    if (!title) return;

    var sec = Math.max(0, Math.floor((Date.now() - startMs) / 1000));
    title.textContent = 'Prob\u00edh\u00e1 synchronizace (' + sec + ' sec.)';
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json; charset=utf-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(payload || {})
    }).then(function (r) { return r.json(); });
  }

  function sleep(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
  }

  async function runSync() {
    if (window.CB_SYNC_SMENY_ALLOWED !== true) return;

    var url = String(window.CB_SYNC_SMENY_URL || '');
    if (!url) return;

    var startedMs = Date.now();
    var titleTimer = null;

    showBox(true);
    showCloseButton(false);
    setTitleWithSeconds(startedMs);
    titleTimer = setInterval(function () {
      setTitleWithSeconds(startedMs);
    }, 250);

    clearLog();
    setDoneText('');

    try {
      var start = await postJson(url, { mode: 'start' });
      if (!start || start.ok !== true) {
        appendLogLine('Chyba startu synchronizace.');
        setDoneText('Synchronizace dokon\u010dena');
        showCloseButton(true);
        return;
      }

      var done = false;
      var lastCount = 0;

      while (!done) {
        var next = await postJson(url, { mode: 'next' });
        if (!next || next.ok !== true) {
          appendLogLine('Chyba kroku.');
          setDoneText('Synchronizace dokon\u010dena');
          showCloseButton(true);
          return;
        }
        if (next.done === true) {
          done = true;
          break;
        }

        var stepNo = Number(next.step || 1);
        var apiRaw = String(next.api_query || '');
        var periodBranch = parseApiPeriodBranch(apiRaw);
        appendLogLine('Start API \u010d.: ' + String(stepNo));
        appendLogLine('Obdob\u00ed/pobo\u010dka: ' + periodBranch);

        var stepStarted = Date.now();
        var step = await postJson(url, { mode: 'step' });
        var stepMs = Math.max(0, Date.now() - stepStarted);
        if (!step || step.ok !== true) {
          appendLogLine('Ukon\u010den API dotaz \u010d.: ' + String(stepNo) + ' trval: ' + String(stepMs) + ' ms');
          appendLogGap();
          appendLogLine('Chyba kroku.');
          setDoneText('Synchronizace dokon\u010dena');
          showCloseButton(true);
          return;
        }

        lastCount = Number(step.received_total || lastCount || 0);
        appendLogLine('Ukon\u010den API dotaz \u010d.: ' + String(stepNo) + ' trval: ' + String(stepMs) + ' ms');
        appendLogGap();

        done = !!step.done;
        if (!done) {
          await sleep(250);
        }
      }

      appendLogLine('P\u0159ijato: ' + String(lastCount) + ' z\u00e1znam\u016f');
      if (lastCount === 0) {
        setDoneText('Synchronizace dokon\u010dena, \u017e\u00e1dn\u00e1 napl\u00e1novan\u00e1 sm\u011bna.');
      } else {
        setDoneText('Synchronizace dokon\u010dena');
      }
      showCloseButton(true);

    } catch (e) {
      appendLogLine('Chyba synchronizace.');
      setDoneText('Synchronizace dokon\u010dena');
      showCloseButton(true);
    } finally {
      if (titleTimer) {
        clearInterval(titleTimer);
      }
      setTitleWithSeconds(startedMs);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = byId('cbSyncClose');
    if (btn) {
      btn.addEventListener('click', function () {
        showBox(false);
      });
    }
    runSync();
  }, { once: true });
})();
