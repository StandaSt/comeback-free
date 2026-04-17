// js/karty_grafy.js * Verze: V1 * Aktualizace: 17.04.2026
'use strict';

(function (w) {
  const WRAPPER_SELECTOR = '[data-cb-prehledy-grafy="1"]';
  const CHART_SELECTOR = '[data-cb-prehledy-grafy-chart="1"]';
  const DATA_SELECTOR = '[data-cb-prehledy-grafy-data]';

  function getWrappers(root) {
    const scope = root instanceof HTMLElement ? root : document;
    return Array.from(scope.querySelectorAll(WRAPPER_SELECTOR)).filter((node) => node instanceof HTMLElement);
  }

  function parsePayload(root) {
    if (!(root instanceof HTMLElement)) return null;
    const node = root.querySelector(DATA_SELECTOR);
    if (!(node instanceof HTMLElement)) return null;
    const raw = String(node.textContent || '').trim();
    if (raw === '') return null;
    try {
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function getChartNodes(root) {
    if (!(root instanceof HTMLElement)) return [];
    return Array.from(root.querySelectorAll(CHART_SELECTOR)).filter((node) => node instanceof HTMLElement);
  }

  function buildOption(payload) {
    if (!payload || typeof payload !== 'object') return null;
    const kind = String(payload.kind || 'bar').trim();
    if (kind !== 'bar') return null;

    const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
    const values = Array.isArray(payload.values) ? payload.values.map((item) => Number(item) || 0) : [];
    const colors = Array.isArray(payload.colors) ? payload.colors.map((item) => String(item || '')) : [];

    if (colors.length !== labels.length) {
      console.error('karty_grafy: Chybi barvy pro graf pobocek:', { labels: labels.length, colors: colors.length });
      throw new Error('Chybi barvy pro graf pobocek: labels=' + labels.length + ', colors=' + colors.length + '.');
    }
    if (colors.some((color) => color.trim() === '')) {
      console.error('karty_grafy: Chybi barva pro jednu nebo vice pobocek v grafu.');
      throw new Error('Chybi barva pro jednu nebo vice pobocek v grafu.');
    }

    return {
      grid: {
        left: 10,
        right: 10,
        top: 10,
        bottom: 40,
        containLabel: true
      },
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' }
      },
      xAxis: {
        type: 'category',
        data: labels,
        axisLabel: {
          interval: 0,
          rotate: labels.length > 6 ? 20 : 0
        }
      },
      yAxis: {
        type: 'value'
      },
      series: [{
        type: 'bar',
        barMaxWidth: 44,
        data: labels.map((label, index) => ({
          value: values[index] ?? 0,
          itemStyle: {
            color: colors[index]
          }
        }))
      }]
    };
  }

  function renderOne(root, attempt) {
    if (!(root instanceof HTMLElement)) return;

    const currentAttempt = Number.isFinite(attempt) ? attempt : 0;
    const maxAttempts = 12;
    const delay = currentAttempt === 0 ? 0 : 120;

    w.setTimeout(() => {
      const payload = parsePayload(root);
      const chartNodes = getChartNodes(root);
      const echarts = w.echarts;

      if (!payload || chartNodes.length === 0 || !echarts || typeof echarts.init !== 'function') {
        if (currentAttempt < maxAttempts) {
          renderOne(root, currentAttempt + 1);
        }
        return;
      }

      const option = buildOption(payload);
      if (!option) return;

      let rendered = 0;
      chartNodes.forEach((chartNode) => {
        const rect = chartNode.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) {
          return;
        }

        const existing = typeof echarts.getInstanceByDom === 'function' ? echarts.getInstanceByDom(chartNode) : null;
        if (existing) {
          existing.dispose();
        }

        const chart = echarts.init(chartNode);
        chart.setOption(option, true);
        rendered += 1;
      });

      if (rendered === 0 && currentAttempt < maxAttempts) {
        renderOne(root, currentAttempt + 1);
      }
    }, delay);
  }

  function renderAll(root) {
    const wrappers = root instanceof HTMLElement ? getWrappers(root) : getWrappers(document);
    wrappers.forEach((node) => renderOne(node, 0));
  }

  function wireResize() {
    if (w.__CB_PREHLEDY_GRAFY_RESIZE_WIRED__) return;
    w.__CB_PREHLEDY_GRAFY_RESIZE_WIRED__ = true;

    w.addEventListener('resize', () => {
      const echarts = w.echarts;
      if (!echarts || typeof echarts.getInstanceByDom !== 'function') return;

      document.querySelectorAll(CHART_SELECTOR).forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        const inst = echarts.getInstanceByDom(node);
        if (inst) {
          inst.resize();
        }
      });
    });
  }

  function init() {
    wireResize();
    renderAll(document);
  }

  if (w.__CB_PREHLEDY_GRAFY_INITED__ === true) {
    return;
  }
  w.__CB_PREHLEDY_GRAFY_INITED__ = true;

  document.addEventListener('cb:main-swapped', () => {
    renderAll(document);
  });

  document.addEventListener('cb:card-swapped', (event) => {
    const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
    const card = detail && detail.card instanceof HTMLElement ? detail.card : null;
    if (card) {
      renderAll(card);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})(window);

// js/karty_grafy.js * Verze: V1 * Aktualizace: 17.04.2026
// Konec souboru
