// js/objednavky_online_graf.js * Verze: V1
'use strict';

(function (w) {
  const CB_GRAFY = w.CB_GRAFY || null;
  if (!CB_GRAFY || typeof CB_GRAFY.register !== 'function') return;

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

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
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

  function positionTooltipOutsideBlock(canvas) {
    return (point, params, dom, rect, size) => {
      const contentSize = size && Array.isArray(size.contentSize) ? size.contentSize : [0, 0];
      const gap = 12;
      const extraRightOffset = 15;
      const width = Number(contentSize[0]) || 0;
      const height = Number(contentSize[1]) || 0;
      const viewWidth = w.innerWidth || document.documentElement.clientWidth || 0;
      const viewHeight = w.innerHeight || document.documentElement.clientHeight || 0;
      const boundary = canvas instanceof HTMLElement ? canvas.closest('[data-tooltip-boundary="1"]') : null;
      const boundaryRect = boundary instanceof HTMLElement
        ? boundary.getBoundingClientRect()
        : (canvas instanceof HTMLElement ? canvas.getBoundingClientRect() : { left: 0, right: 0, top: 0 });
      const canvasRect = canvas instanceof HTMLElement ? canvas.getBoundingClientRect() : { left: 0, top: 0 };

      let viewportX = boundaryRect.right + gap + extraRightOffset;
      if (viewportX + width + gap > viewWidth) {
        viewportX = boundaryRect.left - width - gap;
      }

      let viewportY = boundaryRect.top + gap;
      if (viewportY + height + gap > viewHeight) {
        viewportY = viewHeight - height - gap;
      }

      viewportX = Math.max(gap, Math.min(viewportX, Math.max(gap, viewWidth - width - gap)));
      viewportY = Math.max(gap, Math.min(viewportY, Math.max(gap, viewHeight - height - gap)));

      return [
        viewportX - canvasRect.left,
        viewportY - canvasRect.top
      ];
    };
  }

  function getSeriesItem(payload, seriesId, fallbackIndex) {
    const list = Array.isArray(payload && payload.series) ? payload.series : [];
    const wantedId = String(seriesId || '').trim();
    if (wantedId !== '') {
      const found = list.find((item) => String(item && item.id ? item.id : '').trim() === wantedId);
      if (found && typeof found === 'object') {
        return found;
      }
    }

    const index = Number.isInteger(fallbackIndex) ? fallbackIndex : 0;
    return list[index] && typeof list[index] === 'object' ? list[index] : null;
  }

  function getSeriesData(payload, seriesId, fallbackIndex) {
    const item = getSeriesItem(payload, seriesId, fallbackIndex);
    return item && Array.isArray(item.data) ? item.data : [];
  }

  function getSeriesColors(payload, seriesId, fallbackIndex) {
    const item = getSeriesItem(payload, seriesId, fallbackIndex);
    return item && Array.isArray(item.colors) ? item.colors.map((value) => String(value || '')) : [];
  }

  CB_GRAFY.register('online_stavy', function objednavkyOnlineGraf(payload, canvas) {
    const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
    const dokoncenoRaw = getSeriesData(payload, 'dokonceno', 0);
    const naCesteRaw = getSeriesData(payload, 'na_ceste', 1);
    const osobniOdberRaw = getSeriesData(payload, 'osobni_odber', 2);
    const vyrabiSeRaw = getSeriesData(payload, 'vyrabi_se', 3);
    const zrusenoRaw = getSeriesData(payload, 'zruseno', 4);
    const objednavkyRaw = getSeriesData(payload, 'objednavky', 5);
    const trzbaRaw = getSeriesData(payload, 'trzba', 6);
    const colorsRaw = getSeriesColors(payload, 'dokonceno', 0);
    const dokonceno = dokoncenoRaw.map((item) => Number(item) || 0);
    const naCeste = naCesteRaw.map((item) => Number(item) || 0);
    const osobniOdber = osobniOdberRaw.map((item) => Number(item) || 0);
    const vyrabiSe = vyrabiSeRaw.map((item) => Number(item) || 0);
    const zruseno = zrusenoRaw.map((item) => Number(item) || 0);
    const objednavky = labels.map((label, index) => {
      const payloadValue = Number(objednavkyRaw[index] || 0) || 0;
      const stackValue = dokonceno[index] + naCeste[index] + osobniOdber[index] + vyrabiSe[index] + zruseno[index];
      return payloadValue > 0 ? payloadValue : stackValue;
    });
    const trzba = labels.map((label, index) => Number(trzbaRaw[index] || 0) || 0);
    const colors = colorsRaw.map((item) => String(item || ''));

    return {
      grid: MINI_SLOUPEC_GRID,
      tooltip: {
        trigger: 'axis',
        axisPointer: { type: 'shadow' },
        appendToBody: true,
        showDelay: 0,
        hideDelay: 250,
        transitionDuration: 0,
        enterable: true,
        backgroundColor: 'transparent',
        borderWidth: 0,
        padding: 0,
        position: positionTooltipOutsideBlock(canvas),
        formatter: (params) => {
          const items = Array.isArray(params) ? params : [];
          const name = items.length > 0 ? String(items[0].axisValue || '') : '';
          const index = items.length > 0 ? Number(items[0].dataIndex || 0) || 0 : 0;
          return ''
            + '<div class="provoz_chart_tooltip provoz_tooltip_card">'
            + '<div class="provoz_tooltip_title">' + escapeHtml(name) + '</div>'
            + '<table class="provoz_tooltip_table">'
            + '<tbody>'
            + '<tr><td class="provoz_tooltip_table_cell">Dokončeno</td><td class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatInt(dokonceno[index] ?? 0) + '</td></tr>'
            + '<tr><td class="provoz_tooltip_table_cell">Na cestě</td><td class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatInt(naCeste[index] ?? 0) + '</td></tr>'
            + '<tr><td class="provoz_tooltip_table_cell">Osobní odběr</td><td class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatInt(osobniOdber[index] ?? 0) + '</td></tr>'
            + '<tr><td class="provoz_tooltip_table_cell">Vyrábí se</td><td class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatInt(vyrabiSe[index] ?? 0) + '</td></tr>'
            + '<tr><td class="provoz_tooltip_table_cell">Zrušeno</td><td class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatInt(zruseno[index] ?? 0) + '</td></tr>'
            + '<tr><th class="provoz_tooltip_table_cell">Objednávky</th><th class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatInt(objednavky[index] ?? 0) + '</th></tr>'
            + '<tr><th class="provoz_tooltip_table_cell">Tržba</th><th class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatInt(trzba[index] ?? 0) + ' Kč</th></tr>'
            + '</tbody>'
            + '</table>'
            + '</div>';
        }
      },
      legend: { show: false },
      xAxis: {
        type: 'category',
        data: labels,
        axisLabel: {
          interval: 0,
          rotate: labels.length > 6 ? 20 : 0
        }
      },
      yAxis: [
        {
          type: 'value',
          axisLabel: { show: false },
          axisTick: { show: false },
          splitLine: { show: false }
        },
        {
          type: 'value',
          axisLabel: { show: false },
          axisTick: { show: false },
          splitLine: { show: false }
        }
      ],
      series: [
        {
          name: 'Dokončeno',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barGap: '25%',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          data: labels.map((label, index) => ({
            value: dokonceno[index] ?? 0,
            itemStyle: { color: colors[index] || '#16a34a' }
          }))
        },
        {
          name: 'Na cestě',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#f59e0b' },
          data: naCeste
        },
        {
          name: 'Osobní odběr',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#0ea5e9' },
          data: osobniOdber
        },
        {
          name: 'Vyrábí se',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#dc2626' },
          data: vyrabiSe
        },
        {
          name: 'Zrušeno',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#64748b' },
          data: zruseno
        },
        {
          name: 'Objednávky',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          silent: true,
          tooltip: { show: false },
          itemStyle: { color: 'transparent' },
          emphasis: { disabled: true },
          label: {
            show: true,
            position: 'top',
            color: '#475569',
            fontSize: 10,
            fontWeight: 600,
            formatter: (params) => formatInt(objednavky[params.dataIndex] ?? 0)
          },
          data: labels.map(() => 0)
        },
        {
          name: 'Tržba',
          type: 'bar',
          yAxisIndex: 1,
          barWidth: 5,
          barMaxWidth: 5,
          data: labels.map((label, index) => ({
            value: trzba[index] ?? 0,
            itemStyle: {
              color: lightenColor(colors[index] || '#16a34a', 0.45),
              borderColor: colors[index] || '#16a34a',
              borderWidth: 1
            }
          })),
          label: {
            show: false
          }
        }
      ]
    };
  });
})(window);
