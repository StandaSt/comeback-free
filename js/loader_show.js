// js/loader_show.js * Verze: V1 * Aktualizace: 17.04.2026
'use strict';

(function (w) {
  const STARTUP_ID = 'cb-startup-loader';

  function getRoot() {
    const node = document.getElementById(STARTUP_ID);
    return node instanceof HTMLElement ? node : null;
  }

  function getLoader(root) {
    if (!(root instanceof HTMLElement)) return null;
    const loader = root.querySelector('.dash_loader');
    return loader instanceof HTMLElement ? loader : null;
  }

  function getTextNode(root) {
    if (!(root instanceof HTMLElement)) return null;
    const text = root.querySelector('.dash_loader_text');
    return text instanceof HTMLElement ? text : null;
  }

  function setText(text) {
    const root = getRoot();
    const node = getTextNode(root);
    if (!node) return;
    node.textContent = String(text || '').trim();
    root.setAttribute('data-cb-startup-text', String(text || '').trim());
  }

  function setVisible(visible) {
    const root = getRoot();
    if (!root) return;
    const loader = getLoader(root);
    if (!loader) return;

    if (visible) {
      loader.classList.remove('is-hidden');
      loader.setAttribute('aria-hidden', 'false');
      loader.setAttribute('data-cb-loader-visible', '1');
      loader.style.display = 'flex';
      root.classList.add('is-dashboard-loading');
      root.setAttribute('aria-busy', 'true');
    } else {
      loader.classList.add('is-hidden');
      loader.setAttribute('aria-hidden', 'true');
      loader.removeAttribute('data-cb-loader-visible');
      loader.style.display = 'none';
      root.classList.remove('is-dashboard-loading');
      root.removeAttribute('aria-busy');
    }
  }

  function hide() {
    const root = getRoot();
    if (root && root.parentNode) {
      root.parentNode.removeChild(root);
    }
  }

  function detectDefaultText() {
    const root = getRoot();
    if (root) {
      const attr = String(root.getAttribute('data-cb-startup-text') || '').trim();
      if (attr !== '') {
        return attr;
      }
    }

    const perf = w.performance || null;
    const nav = perf && typeof perf.getEntriesByType === 'function'
      ? perf.getEntriesByType('navigation')[0]
      : null;
    const isReload = nav && String(nav.type || '') === 'reload';
    return isReload ? 'Probíhá refresh ...' : '';
  }

  function bootstrap() {
    const root = getRoot();
    if (!root) return;

    const text = detectDefaultText();
    if (text !== '') {
      setText(text);
      setVisible(true);
    }

    w.addEventListener('load', hide, { once: true });
  }

  w.CB_LOADER_SHOW = {
    bootstrap,
    hide,
    setText,
    setVisible
  };

  if (!getRoot()) {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
  } else {
    bootstrap();
  }
})(window);

// js/loader_show.js * Verze: V1 * Aktualizace: 17.04.2026 * Konec souboru
