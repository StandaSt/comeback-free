// js/casovac_odhlaseni.js * Verze: V10 * Aktualizace: 28.04.2026
'use strict';

/*
 * Casovac neaktivity v user bloku hlavicky.
 *
 * Co dela:
 * - zobrazuje "Seance/zbyva" (delka aktualni session / zbyvajici cas)
 * - pri aktivite uzivatele resetuje neaktivitu na plny timeout
 * - prubezne updatuje teplomer neaktivity (odkryva barevny pas)
 * - periodicky posila "touch" na server
 *
 * Zmena V10:
 * - odhlaseni se spousti podle zobrazene zbyvajici minuty
 * - jakmile Seance/zbyva ukaze 0 min, provede se logout
 */

(function (w, d) {
  // CB_LOGIN_TRACE_TEMP_START
  const LOGOUT_TRACE_KEY = 'cb_login_trace_logout';
  function traceLogin(eventName, data) {
    try {
      const payload = JSON.stringify({
        event: eventName,
        href: w.location.href,
        path: w.location.pathname,
        data: data || {}
      });
      if (navigator.sendBeacon) {
        navigator.sendBeacon('lib/ajax_trace.php', new Blob([payload], { type: 'application/json' }));
        return;
      }
      fetch('lib/ajax_trace.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: payload,
        keepalive: true
      }).catch(function(){});
    } catch (e) {}
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

  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }

  const box = d.querySelector('.head_user[data-timeout-min]');
  if (!box) return;

  const comboEl = box.querySelector('.cb-session-combo');
  const thermoEl = box.querySelector('.cb-session-thermo');
  if (!comboEl || !thermoEl) return;

  const timeoutMin = toInt(box.getAttribute('data-timeout-min'));
  const logoutUrl = String(box.getAttribute('data-logout-url') || '').trim();
  const touchUrl = String(box.getAttribute('data-touch-url') || '').trim();
  const logoutLink = box.querySelector('.head_user_exit');
  const warningThresholdMin = Math.max(1, Math.ceil(timeoutMin * 0.1));

  if (timeoutMin <= 0) {
    comboEl.textContent = '!';
    return;
  }

  let startTs = toInt(box.getAttribute('data-start-ts'));
  let lastTs = toInt(box.getAttribute('data-last-ts'));
  const now0 = nowTs();

  if (startTs <= 0 || startTs > now0) startTs = now0;
  if (lastTs <= 0 || lastTs > now0 || lastTs < startTs) lastTs = now0;

  let lastTouchSentAt = 0;

  function touchServer(force) {
    const now = nowTs();
    if (!force && (now - lastTouchSentAt) < 10) return;
    lastTouchSentAt = now;

    if (!touchUrl) return;

    fetch(touchUrl, {
      method: 'POST',
      headers: {
        'X-Comeback-Touch': '1'
      }
    }).catch(function () {
      // Tichy fail, UI bezi dal.
    });
  }

  function render() {
    const ts = nowTs();

    let runMin = Math.floor((ts - startTs) / 60);
    if (runMin < 0) runMin = 0;

    let idleSec = ts - lastTs;
    if (idleSec < 0) idleSec = 0;

    const timeoutSec = timeoutMin * 60;

    // Minuty nechavame po celych minutach jako dosud.
    let idleMin = Math.floor(idleSec / 60);
    if (idleMin < 0) idleMin = 0;

    let remainMin = timeoutMin - idleMin;
    if (remainMin < 0) remainMin = 0;

    comboEl.textContent = String(runMin) + ' min/' + String(remainMin) + ' min';

    // Teplomer = procento vycerpane neaktivity.
    let thermoPct = Math.round((idleSec / timeoutSec) * 100);
    thermoPct = clamp(thermoPct, 0, 100);

    thermoEl.setAttribute('data-thermo', String(thermoPct));
    thermoEl.style.setProperty('--thermo', String(thermoPct) + '%');

    if (logoutLink) {
      const warningOn = (remainMin > 0 && remainMin <= warningThresholdMin);
      logoutLink.classList.toggle('is-timeout-warning', warningOn);
    }

    if (remainMin <= 0 && logoutUrl) {
      // CB_LOGIN_TRACE_TEMP_START
      rememberLogoutStart('idle_timer');
      traceLogin('login_trace_logout_auto', {
        remain_min: remainMin
      });
      // CB_LOGIN_TRACE_TEMP_END
      w.location.href = logoutUrl;
    }
  }

  function activity() {
    lastTs = nowTs();
    render();
    touchServer(false);
  }

  w.cbResetIdleLogout = function () {
    activity();
  };

  d.addEventListener('click', activity, true);
  d.addEventListener('keydown', activity, true);
  d.addEventListener('scroll', activity, true);
  d.addEventListener('pointerdown', activity, true);

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

  render();
  touchServer(true);
  w.setInterval(render, 1000);
})(window, document);

// js/casovac_odhlaseni.js * Verze: V10 * Aktualizace: 28.04.2026
