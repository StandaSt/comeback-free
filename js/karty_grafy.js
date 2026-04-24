// js/karty_grafy.js * Verze: V1 * Aktualizace: 17.04.2026
'use strict';

(function (w) {
  const WRAPPER_SELECTOR = '[data-cb-prehledy-grafy="1"]';
  const CHART_SELECTOR = '[data-cb-prehledy-grafy-chart="1"]';
  const DATA_SELECTOR = '[data-cb-prehledy-grafy-data]';
  const CHART_DATA_SELECTOR = '[data-cb-prehledy-grafy-chart-data]';

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

  function parseChartPayload(node) {
    if (!(node instanceof HTMLElement)) return null;
    const rawAttr = String(node.getAttribute('data-cb-prehledy-grafy-chart-data') || '').trim();
    if (rawAttr !== '') {
      try {
        const parsed = JSON.parse(rawAttr);
        return parsed && typeof parsed === 'object' ? parsed : null;
      } catch (e) {
        return null;
      }
    }

    const dataNode = node.querySelector(CHART_DATA_SELECTOR);
    if (!(dataNode instanceof HTMLElement)) return null;
    const raw = String(dataNode.textContent || '').trim();
    if (raw === '') return null;
    try {
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function buildOption(payload) {
    if (!payload || typeof payload !== 'object') return null;
    const kind = String(payload.kind || 'bar').trim();
    const title = String(payload.title || '').trim();
    if (kind === 'bar') {
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
          top: 20,
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
          type: 'value',
          axisLabel: {
            show: false
          },
          axisTick: {
            show: false
          },
          splitLine: {
            show: false
          }
        },
        series: [{
          type: 'bar',
          barMaxWidth: 44,
          label: {
            show: true,
            position: 'top',
            fontSize: 10,
            formatter: (params) => String((params && typeof params.value !== 'undefined') ? params.value : '')
          },
          data: labels.map((label, index) => ({
            value: values[index] ?? 0,
            itemStyle: {
              color: colors[index]
            }
          }))
        }]
      };
    }

    if (kind === 'line') {
      const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
      const series = Array.isArray(payload.series) ? payload.series : [];

      if (series.length === 0) return null;

      const normalizedSeries = series.map((item) => {
        const data = item && Array.isArray(item.data) ? item.data.map((value) => Number(value) || 0) : [];
        const name = String(item && item.name ? item.name : '').trim();
        const color = String(item && item.color ? item.color : '').trim();
        return {
          name: name,
          color: color,
          data: data
        };
      }).filter((item) => item.name !== '');

      if (normalizedSeries.length === 0) return null;

      return {
        grid: {
          left: 10,
          right: 10,
          top: 10,
          bottom: 24,
          containLabel: true
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'line' }
        },
        xAxis: {
          type: 'category',
          boundaryGap: false,
          data: labels
        },
        yAxis: {
          type: 'value'
        },
        series: normalizedSeries.map((item) => ({
          type: 'line',
          name: item.name,
          smooth: true,
          showSymbol: true,
          symbolSize: 6,
          lineStyle: {
            width: 2,
            color: item.color || undefined
          },
          itemStyle: {
            color: item.color || undefined
          },
          data: item.data
        }))
      };
    }

    if (kind === 'pie') {
      const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
      const values = Array.isArray(payload.values) ? payload.values.map((item) => Number(item) || 0) : [];
      const colors = Array.isArray(payload.colors) ? payload.colors.map((item) => String(item || '')) : [];
      const total = Number(payload.total || 0) || values.reduce((sum, value) => sum + value, 0);

      if (labels.length === 0 || values.length !== labels.length) return null;

      const graphic = [];
      const legendOrder = [
        { label: 'Registrovany', index: labels.indexOf('Registrovany zakaznik') },
        { label: 'V restauraci', index: labels.indexOf('V restauraci') },
        { label: 'Anonymni', index: labels.indexOf('Anonymni') }
      ].filter((item) => item.index >= 0);

      legendOrder.forEach((item, orderIndex) => {
        const color = colors[item.index] || '#94a3b8';
        const value = values[item.index] ?? 0;
        const top = 52 + (orderIndex * 28);

        graphic.push({
          type: 'circle',
          left: 18,
          top: top + 6,
          shape: { cx: 0, cy: 0, r: 5 },
          style: { fill: color }
        });
        graphic.push({
          type: 'text',
          left: 30,
          top: top,
          style: {
            text: item.label + ': ' + value,
            fill: '#334155',
            font: '13px Arial, Helvetica, sans-serif'
          }
        });
      });

      graphic.push({
        type: 'text',
        left: '70%',
        top: '39%',
        style: {
          text: 'Celkem:\n' + total,
          textAlign: 'center',
          textVerticalAlign: 'middle',
          fill: '#0f172a',
          font: '700 16px Arial, Helvetica, sans-serif'
        }
      });

      return {
        tooltip: {
          trigger: 'item',
          formatter: (params) => {
            const name = String(params && params.name ? params.name : '');
            const value = Number(params && typeof params.value !== 'undefined' ? params.value : 0) || 0;
            const percent = Number(params && typeof params.percent !== 'undefined' ? params.percent : 0) || 0;
            return name + ': ' + value + ' (' + percent.toFixed(1) + ' %)';
          }
        },
        graphic: graphic,
        series: [{
          type: 'pie',
          radius: ['40%', '72%'],
          center: ['72%', '44%'],
          avoidLabelOverlap: true,
          label: {
            show: true,
            formatter: (params) => String(params && params.percent ? Math.round(params.percent) : 0) + ' %'
          },
          data: labels.map((label, index) => ({
            name: label,
            value: values[index] ?? 0,
            itemStyle: {
              color: colors[index] || undefined
            }
          }))
        }]
      };
    }

    return null;
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

        const payloadNode = parseChartPayload(chartNode);
        const option = buildOption(payloadNode || payload);
        if (!option) {
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

  document.addEventListener('cb:dashboard-mini-swapped', (event) => {
    const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
    const cards = detail && Array.isArray(detail.cards) ? detail.cards : [];
    if (cards.length > 0) {
      cards.forEach((card) => {
        if (card instanceof HTMLElement) {
          renderAll(card);
        }
      });
      return;
    }
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
