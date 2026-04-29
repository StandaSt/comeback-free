// js/karty_grafy.js * Verze: V5 * Aktualizace: 29.04.2026
'use strict';

(function (w) {
  const WRAPPER_SELECTOR = '[data-cb-prehledy-grafy="1"]';
  const CHART_SELECTOR = '[data-cb-prehledy-grafy-chart="1"]';
  const DATA_SELECTOR = '[data-cb-prehledy-grafy-data]';
  const CHART_DATA_SELECTOR = '[data-cb-prehledy-grafy-chart-data]';
  const MINI_SLOUPEC_GRID = {
    left: 10,
    right: 10,
    top: 20,
    bottom: 25,
    containLabel: true
  };
  const MINI_SLOUPEC_BAR_MAX_WIDTH = 44;

  function formatInt(value) {
    const intValue = Math.round(Number(value) || 0);
    return String(intValue).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function lightenColor(color, amount) {
    const raw = String(color || '').trim();
    const match = raw.match(/^#([0-9a-f]{6})$/i);
    if (!match) return '#cbd5e1';

    const hex = match[1];
    const ratio = Math.max(0, Math.min(1, Number(amount) || 0));
    const mix = (offset) => {
      const base = parseInt(hex.slice(offset, offset + 2), 16);
      const next = Math.round(base + ((255 - base) * ratio));
      return String(next.toString(16)).padStart(2, '0');
    };

    return '#' + mix(0) + mix(2) + mix(4);
  }

  function buildValueMarks(labels, values, formatter) {
    return labels.map((label, index) => {
      const value = Number(values[index] || 0) || 0;
      return {
        coord: [label, value],
        value: value,
        symbolSize: 1,
        itemStyle: { color: 'transparent' },
        label: {
          show: true,
          color: '#334155',
          fontSize: 10,
          position: value >= 0 ? 'top' : 'bottom',
          formatter: () => formatter(value)
        }
      };
    });
  }

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
    if (kind === 'online_stavy') {
      const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
      const dokonceno = Array.isArray(payload.dokonceno) ? payload.dokonceno.map((item) => Number(item) || 0) : [];
      const naCeste = Array.isArray(payload.na_ceste) ? payload.na_ceste.map((item) => Number(item) || 0) : [];
      const vyrabiSe = Array.isArray(payload.vyrabi_se) ? payload.vyrabi_se.map((item) => Number(item) || 0) : [];
      const colors = Array.isArray(payload.colors) ? payload.colors.map((item) => String(item || '')) : [];

      return {
        grid: MINI_SLOUPEC_GRID,
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: { show: false },
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
          axisLabel: { show: false },
          axisTick: { show: false },
          splitLine: { show: false }
        },
        series: [
          {
            name: 'Dokončeno',
            type: 'bar',
            stack: 'online',
            barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
            data: labels.map((label, index) => ({
              value: dokonceno[index] ?? 0,
              itemStyle: { color: colors[index] || '#16a34a' }
            }))
          },
          {
            name: 'Na cestě',
            type: 'bar',
            stack: 'online',
            barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
            itemStyle: { color: '#f59e0b' },
            data: naCeste
          },
          {
            name: 'Vyrábí se',
            type: 'bar',
            stack: 'online',
            barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
            itemStyle: { color: '#dc2626' },
            data: vyrabiSe
          }
        ]
      };
    }

    if (kind === 'bar_dual') {
      const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
      const orders = Array.isArray(payload.orders) ? payload.orders.map((item) => Number(item) || 0) : [];
      const sales = Array.isArray(payload.sales) ? payload.sales.map((item) => Number(item) || 0) : [];
      const avgPrice = Array.isArray(payload.avg_price) ? payload.avg_price.map((item) => Number(item) || 0) : [];
      const colors = Array.isArray(payload.colors) ? payload.colors.map((item) => String(item || '')) : [];

      return {
        grid: {
          left: 10,
          right: 10,
          top: 32,
          bottom: 25,
          containLabel: true
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          formatter: (params) => {
            const items = Array.isArray(params) ? params : [];
            const name = items.length > 0 ? String(items[0].axisValue || '') : '';
            const lines = [name];
            items.forEach((item) => {
              const seriesName = String(item.seriesName || '');
              const value = Number(item.value || 0) || 0;
              if (seriesName === 'Tržba') {
                lines.push(seriesName + ': ' + formatInt(value) + ' Kč');
                return;
              }
              lines.push(seriesName + ': ' + formatInt(value));
            });
            return lines.join('<br>');
          }
        },
        legend: { show: false },
        xAxis: {
          type: 'category',
          data: labels,
          axisTick: { show: false },
          axisLine: { show: false },
          axisLabel: {
            interval: 0,
            rotate: 0,
            lineHeight: 16,
            formatter: (value, index) => {
              const avg = avgPrice[index] ?? 0;
              return String(value) + '\nø ' + formatInt(avg) + ' Kč';
            }
          }
        },
        yAxis: [
          {
            type: 'value',
            axisLabel: { show: false },
            axisTick: { show: false },
            axisLine: { show: false },
            splitLine: { show: false }
          },
          {
            type: 'value',
            axisLabel: { show: false },
            axisTick: { show: false },
            axisLine: { show: false },
            splitLine: { show: false }
          }
        ],
        series: [
          {
            name: 'Objednávky',
            type: 'bar',
            yAxisIndex: 0,
            barGap: '15%',
            barMaxWidth: 34,
            data: labels.map((label, index) => ({
              value: orders[index] ?? 0,
              itemStyle: {
                color: colors[index] || '#64748b'
              }
            })),
            label: {
              show: true,
              position: 'top',
              fontSize: 10,
              formatter: (params) => formatInt(params && typeof params.value !== 'undefined' ? params.value : 0)
            }
          },
          {
            name: 'Tržba',
            type: 'bar',
            yAxisIndex: 1,
            barMaxWidth: 34,
            data: labels.map((label, index) => ({
              value: sales[index] ?? 0,
              itemStyle: {
                color: lightenColor(colors[index] || '#16a34a', 0.45),
                borderColor: colors[index] || '#16a34a',
                borderWidth: 1
              }
            })),
            label: {
              show: true,
              position: 'top',
              fontSize: 10,
              formatter: (params) => formatInt(params && typeof params.value !== 'undefined' ? params.value : 0)
            }
          }
        ]
      };
    }

    if (kind === 'bar_diff') {
      const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
      const values = Array.isArray(payload.values) ? payload.values.map((item) => Number(item) || 0) : [];
      const colors = Array.isArray(payload.colors) ? payload.colors.map((item) => String(item || '')) : [];

      return {
        grid: {
          left: 10,
          right: 10,
          top: 20,
          bottom: 25,
          containLabel: true
        },
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          formatter: (params) => {
            const item = Array.isArray(params) && params.length > 0 ? params[0] : null;
            if (!item) return '';
            const value = Number(item.value || 0) || 0;
            return String(item.axisValue || '') + '<br>Rozdíl: ' + (value > 0 ? '+' : '') + formatInt(value);
          }
        },
        xAxis: {
          type: 'category',
          data: labels,
          axisTick: { show: false },
          axisLabel: {
            interval: 0,
            rotate: labels.length > 6 ? 20 : 0
          },
          axisLine: {
            show: true,
            onZero: true
          }
        },
        yAxis: {
          type: 'value',
          axisLabel: { show: false },
          axisTick: { show: false },
          splitLine: { show: false }
        },
        series: [{
          name: 'Rozdíl',
          type: 'bar',
          barMaxWidth: 42,
          data: labels.map((label, index) => {
            const value = values[index] ?? 0;
            const color = colors[index] || '#64748b';
            return {
              value: value,
              itemStyle: {
                color: value >= 0 ? color : lightenColor(color, 0.45),
                borderColor: color,
                borderWidth: 1
              }
            };
          }),
          label: {
            show: true,
            position: (params) => ((Number(params && params.value) || 0) >= 0 ? 'top' : 'bottom'),
            fontSize: 10,
            formatter: (params) => {
              const value = Number(params && typeof params.value !== 'undefined' ? params.value : 0) || 0;
              return (value > 0 ? '+' : '') + formatInt(value);
            }
          }
        }]
      };
    }

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
        grid: MINI_SLOUPEC_GRID,
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
          axisLabel: { show: false },
          axisTick: { show: false },
          splitLine: { show: false }
        },
        series: [{
          type: 'bar',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          label: {
            show: true,
            position: 'top',
            fontSize: 10,
            formatter: (params) => String((params && typeof params.value !== 'undefined') ? params.value : '')
          },
          data: labels.map((label, index) => ({
            value: values[index] ?? 0,
            itemStyle: { color: colors[index] }
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
        return { name: name, color: color, data: data };
      }).filter((item) => item.name !== '');

      if (normalizedSeries.length === 0) return null;

      return {
        grid: {
          left: 10,
          right: 10,
          top: 10,
          bottom: 10,
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
        yAxis: { type: 'value' },
        series: normalizedSeries.map((item) => ({
          type: 'line',
          name: item.name,
          smooth: true,
          showSymbol: true,
          symbolSize: 6,
          lineStyle: { width: 2, color: item.color || undefined },
          itemStyle: { color: item.color || undefined },
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
        { label: 'Telefonem', index: labels.indexOf('Telefonem') },
        { label: 'V restauraci', index: labels.indexOf('V restauraci') },
        { label: 'Anonymní', index: labels.indexOf('Anonymní') }
      ].filter((item) => item.index >= 0);

      legendOrder.forEach((item, orderIndex) => {
        const color = colors[item.index] || '#94a3b8';
        const value = values[item.index] ?? 0;
        const top = 52 + (orderIndex * 28);

        graphic.push({
          type: 'circle',
          left: 50,
          top: top + 6,
          shape: { cx: 0, cy: 0, r: 5 },
          style: { fill: color }
        });
        graphic.push({
          type: 'text',
          left: 62,
          top: top,
          style: {
            text: item.label + ': ' + formatInt(value),
            fill: '#334155',
            font: '13px Arial, Helvetica, sans-serif'
          }
        });
      });

      graphic.push({
        type: 'text',
        left: '61%',
        top: '56%',
        style: {
          text: 'Celkem: ' + formatInt(total),
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
            return name + ': ' + formatInt(value) + ' (' + percent.toFixed(1) + ' %)';
          }
        },
        graphic: graphic,
        series: [{
          type: 'pie',
          radius: ['40%', '72%'],
          center: ['64%', '56%'],
          avoidLabelOverlap: true,
          label: {
            show: true,
            formatter: (params) => String(params && params.percent ? Math.round(params.percent) : 0) + ' %'
          },
          data: labels.map((label, index) => ({
            name: label,
            value: values[index] ?? 0,
            itemStyle: { color: colors[index] || undefined }
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

// js/karty_grafy.js * Verze: V5 * Aktualizace: 29.04.2026
// Konec souboru
