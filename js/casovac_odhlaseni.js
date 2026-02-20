// js/casovac_odhlaseni.js * Verze: V5 * Aktualizace: 17.2.2026
'use strict';

/*
 * Časovač neaktivity + živé přepočítávání v login bloku
 *
 * Pravidla:
 * - timeoutMin se NESMÍ potichu opravovat žádným fallbackem (žádné "20").
 * - když je timeoutMin neplatný / chybí, jen zobrazíme "!" a časovač nespouštíme.
 *
 * Aktivita: klik, klávesa, scroll.
 * Krok: 1 minuta.
 */

(function (w, d) {

  function toInt(v) {
    const n = parseInt(String(v || ''), 10);
    return Number.isFinite(n) ? n : 0;
  }

  function nowTs() {
    return Math.floor(Date.now() / 1000);
  }

  const grid = d.querySelector('.login-grid[data-timeout-min]');
  if (!grid) return;

  const runEl = grid.querySelector('.cb-run-min');
  const remEl = grid.querySelector('.cb-remain-min');
  if (!runEl || !remEl) return;

  const timeoutMin = toInt(grid.getAttribute('data-timeout-min'));
  const logoutUrl = String(grid.getAttribute('data-logout-url') || '').trim();

  if (timeoutMin <= 0) {
    runEl.textContent = '!';
    remEl.textContent = '!';
    return;
  }

  let startTs = toInt(grid.getAttribute('data-start-ts'));
  let lastTs = toInt(grid.getAttribute('data-last-ts'));
  const now0 = nowTs();

  if (startTs <= 0 || startTs > now0) startTs = now0;
  if (lastTs <= 0 || lastTs > now0 || lastTs < startTs) lastTs = now0;

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
      w.location.href = logoutUrl;
    }
  }

  function activity() {
    lastTs = nowTs();
    render();
  }

  d.addEventListener('click', activity, true);
  d.addEventListener('keydown', activity, true);
  d.addEventListener('scroll', activity, true);

  render();
  w.setInterval(render, 60 * 1000);

})(window, document);

// js/casovac_odhlaseni.js * Verze: V5 * Aktualizace: 17.2.2026 * Počet řádků: 84
// Konec souboru