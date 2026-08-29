// js/casovac_odhlaseni.js * Verze: V11 * Aktualizace: 12.08.2026
'use strict';

/*
 * Casovac pevne platnosti session v user bloku menu.
 *
 * Co dela:
 * - neposila prubezne zadne touch requesty
 * - nesleduje aktivitu uzivatele
 * - po konci serverove platnosti session presmeruje na logout
 */

(function (w, d) {
  // CB_LOGIN_TRACE_TEMP_START
  const LOGOUT_TRACE_KEY = 'cb_login_trace_logout';
  function traceLogin(eventName, data) {
    if (w.CB_AJAX && typeof w.CB_AJAX.trace === 'function') {
      w.CB_AJAX.trace(eventName, data || {});
    }
  }
  function rememberLogoutStart(reason) {
    try {
      if (!w.sessionStorage) return;
      w.sessionStorage.setItem(LOGOUT_TRACE_KEY, JSON.stringify({
        reason: String(reason || ''),
        at_ms: Date.now(),
        perf_ms: (w.performance && typeof w.performance.now === 'function') ? Math.round(w.performance.now()) : 0
      }));
    } catch (e) {}
  }
  // CB_LOGIN_TRACE_TEMP_END

  function toInt(v) {
    const n = parseInt(String(v || ''), 10);
    return Number.isFinite(n) ? n : 0;
  }

  function nowTs() {
    return Math.floor(Date.now() / 1000);
  }

  const box = d.querySelector('.blok_menu_user[data-session-end-ts]');
  if (!box) return;

  const sessionEndTs = toInt(box.getAttribute('data-session-end-ts'));
  const logoutUrl = String(box.getAttribute('data-logout-url') || '').trim();
  const logoutLink = box.querySelector('.blok_menu_user_logout');

  if (sessionEndTs <= 0 || !logoutUrl) {
    return;
  }

  // CB_LOGIN_TRACE_TEMP_START
  if (logoutLink) {
    logoutLink.addEventListener('click', function () {
      rememberLogoutStart('logout_click');
      traceLogin('login_trace_logout_click', {
        href: logoutLink.href || logoutUrl
      });
    }, true);
  }
  // CB_LOGIN_TRACE_TEMP_END

  w.setTimeout(function () {
    // CB_LOGIN_TRACE_TEMP_START
    rememberLogoutStart('session_expired');
    traceLogin('login_trace_logout_auto', {
      session_end_ts: sessionEndTs
    });
    // CB_LOGIN_TRACE_TEMP_END
    w.location.href = logoutUrl;
  }, Math.max(0, (sessionEndTs - nowTs()) * 1000));
})(window, document);

// js/casovac_odhlaseni.js * Verze: V11 * Aktualizace: 12.08.2026
