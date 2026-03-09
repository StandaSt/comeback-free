// js/casovac_odhlaseni.js * Verze: V9 * Aktualizace: 08.03.2026
'use strict';

/*
 * Casovac neaktivity v user bloku hlavicky.
 *
 * Co dela:
 * - zobrazuje "Seance/zbyva" (delka aktualni session / zbyvajici cas)
 * - pri aktivite uzivatele resetuje neaktivitu na plny timeout
 * - prubezne updatuje teplomer neaktivity (odkryva barevny pas)
 * - periodicky posila "touch" na server
 */

(function (w, d) {
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
    let remainSec = timeoutSec - idleSec;
    if (remainSec < 0) remainSec = 0;

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
    if (remainSec <= 0 && logoutUrl) {
      w.location.href = logoutUrl;
    }
  }

  function activity() {
    lastTs = nowTs();
    render();
    touchServer(false);
  }

  d.addEventListener('click', activity, true);
  d.addEventListener('keydown', activity, true);
  d.addEventListener('scroll', activity, true);
  d.addEventListener('pointerdown', activity, true);

  render();
  touchServer(true);
  w.setInterval(render, 1000);
})(window, document);

// js/casovac_odhlaseni.js * Verze: V9 * Aktualizace: 08.03.2026
