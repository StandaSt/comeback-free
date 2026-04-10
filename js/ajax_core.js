// js/ajax_core.js * Verze: V1 * Aktualizace: 19.2.2026
'use strict';

/*
 * AJAX core – společné minimum (100% identický kus)
 *
 * Cíl:
 * - žádné režimy, žádné rozhodování "menu vs tabulky"
 * - jen pomocník pro fetch → text + jednotné ošetření HTTP/neplatné odpovědi
 *
 * Použití:
 * - ostatní skripty si řeší samy:
 *   - odkud berou URL (menu = w.location.href, tabulky = href z odkazu/form)
 *   - co udělají po úspěchu (např. keep clean URL)
 *   - jak renderují loader/error (karta apod.)
 */

(function (w) {
  const CB_AJAX = w.CB_AJAX || (w.CB_AJAX = {});
  let dashboardLoading = false;
  let dashboardLoadingTimer = null;
  let dashboardLoadingStartedAt = 0;
  let restiaProgressPollTimer = null;
  let restiaProgressPollUrl = '';

  CB_AJAX.fetchText = function fetchText(url, headers, signal) {
    const u = String(url || '');
    const h = (headers && typeof headers === 'object') ? headers : {};

    return fetch(u, {
      method: 'GET',
      headers: h,
      signal: signal
    }).then((res) => {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    });
  };

  function getDashParts() {
    const box = document.querySelector('.dash_box');
    if (!(box instanceof HTMLElement)) return null;
    const content = box.querySelector('[data-cb-dash-content="1"]');
    if (!(content instanceof HTMLElement)) return null;
    const loader = box.querySelector('[data-cb-dash-loader="1"]');
    const timer = box.querySelector('[data-cb-dash-loader-time]');
    const step = box.querySelector('[data-cb-dash-loader-step]');
    return {
      box: box,
      content: content,
      loader: (loader instanceof HTMLElement) ? loader : null,
      timer: (timer instanceof HTMLElement) ? timer : null,
      step: (step instanceof HTMLElement) ? step : null
    };
  }

  function formatLoadingTime(ms) {
    const sec = Math.max(0, Number(ms) || 0) / 1000;
    return sec.toFixed(2) + ' s';
  }

  function stopDashboardLoadingTimer(parts) {
    if (dashboardLoadingTimer !== null) {
      w.clearInterval(dashboardLoadingTimer);
      dashboardLoadingTimer = null;
    }
    dashboardLoadingStartedAt = 0;
    if (parts && parts.timer) {
      parts.timer.textContent = '0.00 s';
    }
  }

  function startDashboardLoadingTimer(parts) {
    stopDashboardLoadingTimer(parts);
    dashboardLoadingStartedAt = (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();
    const tick = () => {
      if (!parts || !parts.timer) return;
      const now = (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();
      parts.timer.textContent = formatLoadingTime(now - dashboardLoadingStartedAt);
    };
    tick();
    dashboardLoadingTimer = w.setInterval(tick, 50);
  }

  function getDashboardLoadingElapsedMs() {
    if (!dashboardLoadingStartedAt) return 0;
    const now = (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();
    return Math.max(0, now - dashboardLoadingStartedAt);
  }

  function setDashboardLoading(on) {
    const parts = getDashParts();
    if (!parts) return;
    if (on) {
      if (dashboardLoadingTimer === null) {
        startDashboardLoadingTimer(parts);
      }
      dashboardLoading = true;
      if (restiaProgressPollTimer === null) {
        setLoaderStepText(0, 0, 0);
      }
      parts.box.classList.add('is-dashboard-loading');
      parts.box.setAttribute('aria-busy', 'true');
      if (parts.loader) {
        parts.loader.classList.remove('is-hidden');
        parts.loader.setAttribute('aria-hidden', 'false');
      }
      return;
    }
    parts.box.classList.remove('is-dashboard-loading');
    parts.box.removeAttribute('aria-busy');
    if (parts.loader) {
      parts.loader.classList.add('is-hidden');
      parts.loader.setAttribute('aria-hidden', 'true');
    }
    stopDashboardLoadingTimer(parts);
    dashboardLoading = false;
  }

  function setLoaderStepText(done, total, saved) {
    const parts = getDashParts();
    if (!parts || !parts.step) return;
    const doneNum = Math.max(0, parseInt(String(done || 0), 10) || 0);
    const totalNum = Math.max(0, parseInt(String(total || 0), 10) || 0);
    const savedNum = Math.max(0, parseInt(String(saved || 0), 10) || 0);
    parts.step.textContent = String(doneNum) + ' / ' + String(totalNum) + ' uloženo: ' + String(savedNum);
  }

  function stopRestiaProgressPolling() {
    if (restiaProgressPollTimer !== null) {
      w.clearInterval(restiaProgressPollTimer);
      restiaProgressPollTimer = null;
    }
    restiaProgressPollUrl = '';
  }

  function readRestiaProgress(url) {
    const reqUrl = String(url || '').trim();
    if (reqUrl === '') {
      return Promise.resolve(null);
    }
    return fetch(reqUrl, {
      method: 'GET',
      cache: 'no-store',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then((res) => {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      return res.json();
    }).catch(() => null);
  }

  CB_AJAX.setDashboardLoading = function setDashboardLoadingPublic(on) {
    setDashboardLoading(!!on);
  };

  CB_AJAX.setLoaderStepText = function setLoaderStepTextPublic(done, total, saved) {
    setLoaderStepText(done, total, saved);
  };

  CB_AJAX.stopRestiaProgressPolling = function stopRestiaProgressPollingPublic() {
    stopRestiaProgressPolling();
  };

  CB_AJAX.startRestiaProgressPolling = function startRestiaProgressPollingPublic(url, initialSaved, initialDone, initialTotal) {
    const reqUrl = String(url || '').trim();
    if (reqUrl === '') {
      stopRestiaProgressPolling();
      return;
    }
    stopRestiaProgressPolling();
    restiaProgressPollUrl = reqUrl;
    setLoaderStepText(initialDone, initialTotal, initialSaved);
    const tick = function () {
      if (restiaProgressPollUrl === '') {
        return;
      }
      readRestiaProgress(restiaProgressPollUrl).then((data) => {
        if (!data || typeof data !== 'object') {
          return;
        }
        if (Object.prototype.hasOwnProperty.call(data, 'saved_step')) {
          const requested = parseInt(String(data.requested_days || 0), 10) || 0;
          const remaining = parseInt(String(data.remaining_days || 0), 10) || 0;
          const done = Math.max(0, requested - remaining);
          setLoaderStepText(done, requested, data.saved_step);
        }
        if (parseInt(String(data.active || 0), 10) === 0) {
          stopRestiaProgressPolling();
        }
      });
    };
    tick();
    restiaProgressPollTimer = w.setInterval(tick, 3000);
  };

  CB_AJAX.runAfterDashboardLoading = function runAfterDashboardLoading(action, minVisibleMs) {
    if (typeof action !== 'function') return;
    const minMs = Math.max(0, Number(minVisibleMs) || 0);
    if (!dashboardLoading || minMs <= 0) {
      action();
      return;
    }
    const waitMs = Math.max(0, minMs - getDashboardLoadingElapsedMs());
    w.setTimeout(action, waitMs);
  };

  CB_AJAX.refreshDashboard = function refreshDashboard(options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const force = !!opts.force;
    if (dashboardLoading && !force) {
      return Promise.resolve({ ok: false, busy: true });
    }
    const parts = getDashParts();
    if (!parts) {
      return Promise.reject(new Error('Dashboard container nebyl nalezen.'));
    }
    if (!force) {
      setDashboardLoading(true);
    }

    const reqUrl = String(w.location.href || 'index.php');
    return CB_AJAX.fetchText(reqUrl, { 'X-Comeback-Partial': '1' })
      .then((html) => {
        parts.content.innerHTML = String(html || '');
        document.dispatchEvent(new CustomEvent('cb:main-swapped'));
        return { ok: true };
      })
      .finally(() => {
        setDashboardLoading(false);
      });
  };

})(window);

// js/ajax_core.js * Verze: V1 * Aktualizace: 19.2.2026 * Počet řádků: 38
// Konec souboru
