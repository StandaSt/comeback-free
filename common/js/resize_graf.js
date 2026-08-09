// js/resize_graf.js * Verze: V1
'use strict';

(function (w, d) {
  function resizeAll() {
    const echarts = w.echarts;
    if (!echarts || typeof echarts.getInstanceByDom !== 'function') return;

    d.querySelectorAll('[data-graf-canvas="1"]').forEach((node) => {
      if (!(node instanceof HTMLElement)) return;
      const chart = echarts.getInstanceByDom(node);
      if (chart) {
        chart.resize();
      }
    });
  }

  w.CB_GRAF_RESIZE = {
    resizeAll: resizeAll
  };

  w.addEventListener('resize', resizeAll);
})(window, document);
