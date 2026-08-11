// js/loader.js * Spolecny loader
'use strict';

(function (w, d) {
  const SELECTOR = '[data-cb-loader="1"]';
  const TICK_MS = 50;
  let timerId = null;
  let startedAt = 0;

  function root() {
    const node = d.querySelector(SELECTOR);
    return node instanceof HTMLElement ? node : null;
  }

  function textNode() {
    const node = root();
    if (!node) return null;
    const text = node.querySelector('[data-cb-loader-text]');
    return text instanceof HTMLElement ? text : null;
  }

  function timeNode() {
    const node = root();
    if (!node) return null;
    const time = node.querySelector('[data-cb-loader-time]');
    return time instanceof HTMLElement ? time : null;
  }

  function now() {
    return (w.performance && typeof w.performance.now === 'function') ? w.performance.now() : Date.now();
  }

  function formatTime(ms) {
    return (Math.max(0, Number(ms) || 0) / 1000).toFixed(2) + ' s';
  }

  function tick() {
    const node = timeNode();
    if (!node || startedAt <= 0) {
      stopTimer();
      return;
    }
    node.textContent = formatTime(now() - startedAt);
  }

  function startTimer() {
    stopTimer();
    startedAt = now();
    tick();
    timerId = w.setInterval(tick, TICK_MS);
  }

  function stopTimer() {
    if (timerId !== null) {
      w.clearInterval(timerId);
      timerId = null;
    }
    startedAt = 0;
  }

  function setText(text) {
    const node = textNode();
    if (!node) return;
    node.textContent = String(text || 'Načítám ...').trim() || 'Načítám ...';
  }

  function show(text) {
    const node = root();
    if (!node) return;
    setText(text);
    node.setAttribute('data-cb-loader-visible', '1');
    node.setAttribute('aria-hidden', 'false');
    startTimer();
  }

  function hide() {
    const node = root();
    if (!node) return;
    node.removeAttribute('data-cb-loader-visible');
    node.setAttribute('aria-hidden', 'true');
    stopTimer();
  }

  function bindAutoForms() {
    d.addEventListener('submit', function (event) {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      const text = String(form.getAttribute('data-cb-loader-text') || '').trim();
      if (text === '') return;
      show(text);
    }, true);
  }

  w.CB_LOADER = {
    show,
    hide,
    setText
  };

  bindAutoForms();
})(window, document);
