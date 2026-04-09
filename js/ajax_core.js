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
    return {
      box: box,
      content: content,
      loader: (loader instanceof HTMLElement) ? loader : null,
      timer: (timer instanceof HTMLElement) ? timer : null
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

  CB_AJAX.setDashboardLoading = function setDashboardLoadingPublic(on) {
    setDashboardLoading(!!on);
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
