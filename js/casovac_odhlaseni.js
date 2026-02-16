// lib/casovac_odhlaseni.js * Verze: V4 * Aktualizace: 16.2.2026
'use strict';

/*
 * Časovač neaktivity + živé přepočítávání v login bloku
 *
 * Co dělá:
 * - každou minutu přepočítá a přepíše jen 2 čísla v login bloku:
 *     běží X min  (od startu seance)
 *     zbývá Y min (timeout - neaktivita)
 * - aktivita: klik, klávesa, scroll → resetuje neaktivitu
 * - při vypršení (zbývá <= 0):
 *     smaže sessionStorage (v tomhle tabu)
 *     přesměruje na lib/logout.php
 *
 * Důležité:
 * - start/last/timeout čte z HTML atributů na .login-grid (renderuje includes/login_form.php)
 * - ošetřuje chybné hodnoty (0/nesmysl), aby se uživatel neodhlásil hned po přihlášení
 */

(function (w, d) {

  function qs(sel) {
    return d.querySelector(sel);
  }

  function toInt(v, def) {
    const n = parseInt(String(v || ''), 10);
    return Number.isFinite(n) ? n : def;
  }

  function nowTs() {
    return Math.floor(Date.now() / 1000);
  }

  const grid = qs('.login-grid[data-timeout-min]');
  if (!grid) return;

  const runEl = grid.querySelector('.cb-run-min');
  const remEl = grid.querySelector('.cb-remain-min');
  if (!runEl || !remEl) return;

  let timeoutMin = toInt(grid.getAttribute('data-timeout-min'), 20);
  let startTs = toInt(grid.getAttribute('data-start-ts'), 0);
  let lastTs = toInt(grid.getAttribute('data-last-ts'), 0);
  const logoutUrl = String(grid.getAttribute('data-logout-url') || '').trim();

  // Normalizace vstupů z HTML (hlavně kvůli okamžitému odhlášení po loginu)
  const now0 = nowTs();

  // timeout: musí být kladné celé číslo (minimálně 1)
  if (!Number.isFinite(timeoutMin) || timeoutMin <= 0) {
    timeoutMin = 20;
  }

  // start: když je 0/nesmysl → bereme "teď"
  if (!Number.isFinite(startTs) || startTs <= 0 || startTs > now0) {
    startTs = now0;
  }

  // last: když je 0/nesmysl, nebo je mimo rozsah → bereme "teď"
  if (!Number.isFinite(lastTs) || lastTs <= 0 || lastTs > now0 || lastTs < startTs) {
    lastTs = now0;
  }

  function render() {
    const ts = nowTs();

    let runMin = Math.floor((ts - startTs) / 60);
    if (runMin < 0) runMin = 0;

    let idleMin = Math.floor((ts - lastTs) / 60);
    if (idleMin < 0) idleMin = 0;

    let remain = timeoutMin - idleMin;
    if (remain < 0) remain = 0;

    runEl.textContent = String(runMin);
    remEl.textContent = String(remain);

    if (remain <= 0 && logoutUrl) {
      try {
        sessionStorage.clear();
      } catch (e) {}
      w.location.href = logoutUrl;
    }
  }

  function activity() {
    lastTs = nowTs();
    render();
  }

  d.addEventListener('click', activity, true);
  d.addEventListener('keydown', activity, true);

  // scroll chytáme na dokumentu (včetně scrollu v mainu)
  d.addEventListener('scroll', activity, true);

  render();
  w.setInterval(render, 60 * 1000);

})(window, document);

/* lib/casovac_odhlaseni.js * Verze: V4 * Aktualizace: 16.2.2026 * Počet řádků: 106 */
// Konec souboru