// js/data_grafu.js * Verze: V1
'use strict';

(function (w, d) {
  const CB_GRAFY = w.CB_GRAFY || (w.CB_GRAFY = {});
  const builders = CB_GRAFY.builders || (CB_GRAFY.builders = {});

  function parseJson(raw) {
    const text = String(raw || '').trim();
    if (text === '') return null;
    try {
      const parsed = JSON.parse(text);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  CB_GRAFY.selectors = {
    root: '[data-graf="1"]',
    data: 'script[data-graf-data]',
    canvas: '[data-graf-canvas="1"]'
  };

  CB_GRAFY.register = function register(kind, builder) {
    const key = String(kind || '').trim();
    if (key === '' || typeof builder !== 'function') return;
    builders[key] = builder;
  };

  CB_GRAFY.build = function build(payload, canvas) {
    if (!payload || typeof payload !== 'object') return null;
    const key = String(payload.kind || '').trim();
    const builder = builders[key];
    return typeof builder === 'function' ? builder(payload, canvas) : null;
  };

  CB_GRAFY.getRoots = function getRoots(root) {
    const scope = root instanceof HTMLElement ? root : d;
    const roots = Array.from(scope.querySelectorAll(CB_GRAFY.selectors.root)).filter((node) => node instanceof HTMLElement);
    if (scope instanceof HTMLElement && scope.matches(CB_GRAFY.selectors.root)) {
      roots.unshift(scope);
    }
    return roots;
  };

  CB_GRAFY.getCanvases = function getCanvases(root) {
    if (!(root instanceof HTMLElement)) return [];
    return Array.from(root.querySelectorAll(CB_GRAFY.selectors.canvas)).filter((node) => node instanceof HTMLElement);
  };

  CB_GRAFY.getPayload = function getPayload(root) {
    if (!(root instanceof HTMLElement)) return null;
    const node = root.querySelector(CB_GRAFY.selectors.data);
    if (!(node instanceof HTMLElement)) return null;
    return parseJson(node.textContent || '');
  };
})(window, document);
