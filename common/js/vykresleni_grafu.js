// js/vykresleni_grafu.js * Verze: V1
'use strict';

(function (w, d) {
  const CB_GRAFY = w.CB_GRAFY || null;

  function renderOne(root, attempt) {
    if (!CB_GRAFY || !(root instanceof HTMLElement)) return;

    const currentAttempt = Number.isFinite(attempt) ? attempt : 0;
    const maxAttempts = 12;
    const delay = currentAttempt === 0 ? 0 : 120;

    w.setTimeout(() => {
      const payload = CB_GRAFY.getPayload(root);
      const canvases = CB_GRAFY.getCanvases(root);
      const echarts = w.echarts;

      if (canvases.length === 0 || !echarts || typeof echarts.init !== 'function') {
        if (currentAttempt < maxAttempts) {
          renderOne(root, currentAttempt + 1);
        }
        return;
      }

      let rendered = 0;
      canvases.forEach((canvas) => {
        const rect = canvas.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return;

        const option = CB_GRAFY.build(payload, canvas);
        if (!option) return;

        const existing = typeof echarts.getInstanceByDom === 'function' ? echarts.getInstanceByDom(canvas) : null;
        if (existing) {
          existing.dispose();
        }

        const chart = echarts.init(canvas);
        chart.setOption(option, true);
        rendered += 1;
      });

      if (rendered === 0 && currentAttempt < maxAttempts) {
        renderOne(root, currentAttempt + 1);
      }
    }, delay);
  }

  function renderAll(root) {
    if (!CB_GRAFY) return;
    CB_GRAFY.getRoots(root).forEach((node) => renderOne(node, 0));
  }

  w.CB_GRAFY_RENDER = {
    renderAll: renderAll,
    renderOne: renderOne
  };

  d.addEventListener('cb:main-swapped', () => renderAll(d));
  d.addEventListener('cb:gn-block-refreshed', (event) => {
    const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
    renderAll(detail && detail.block instanceof HTMLElement ? detail.block : d);
  });

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', () => renderAll(d), { once: true });
  } else {
    renderAll(d);
  }
})(window, document);
