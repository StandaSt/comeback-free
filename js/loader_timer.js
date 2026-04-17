// js/loader_timer.js * Verze: V1 * Aktualizace: 17.04.2026
'use strict';

(function (w) {
  const STARTUP_ID = 'cb-startup-loader';
  const TICK_MS = 50;
  let timerId = null;
  let startedAt = 0;

  function getRoot() {
    const node = document.getElementById(STARTUP_ID);
    return node instanceof HTMLElement ? node : null;
  }

  function getTimerNode(root) {
    if (!(root instanceof HTMLElement)) return null;
    const node = root.querySelector('[data-cb-loader-time]');
    return node instanceof HTMLElement ? node : null;
  }

  function formatTime(ms) {
    const sec = Math.max(0, Number(ms) || 0) / 1000;
    return sec.toFixed(2) + ' s';
  }

  function stop() {
    if (timerId !== null) {
      w.clearInterval(timerId);
      timerId = null;
    }
    startedAt = 0;
  }

  function tick() {
    const root = getRoot();
    const timerNode = getTimerNode(root);
    if (!root || !timerNode) {
      stop();
      return;
    }

    const now = (w.performance && typeof w.performance.now === 'function')
      ? w.performance.now()
      : Date.now();
    timerNode.textContent = formatTime(now - startedAt);
  }

  function start() {
    const root = getRoot();
    const timerNode = getTimerNode(root);
    if (!root || !timerNode) {
      return;
    }

    stop();
    startedAt = (w.performance && typeof w.performance.now === 'function')
      ? w.performance.now()
      : Date.now();
    tick();
    timerId = w.setInterval(tick, TICK_MS);
  }

  w.CB_LOADER_TIMER = {
    start,
    stop
  };

  if (!getRoot()) {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})(window);

// js/loader_timer.js * Verze: V1 * Aktualizace: 17.04.2026 * Konec souboru
