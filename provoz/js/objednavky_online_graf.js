// Graf online objednávek; všechny uživatelské číselné údaje skládá ve stejném českém formátu.
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

  function formatMoney(value) {
    return formatInt(value) + ' Kč';
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function positionTooltipOutsideBlock(canvas) {
    return (point, params, dom, rect, size) => {
      const contentSize = size && Array.isArray(size.contentSize) ? size.contentSize : [0, 0];
      const width = Number(contentSize[0]) || 0;
      const height = Number(contentSize[1]) || 0;
      const boundaryRect = canvas instanceof HTMLElement ? canvas.getBoundingClientRect() : { left: 0, right: 0, top: 0 };
      const canvasRect = canvas instanceof HTMLElement ? canvas.getBoundingClientRect() : { left: 0, top: 0 };
      const position = w.CB_TOOLTIP && typeof w.CB_TOOLTIP.positionOutsideRect === 'function'
        ? w.CB_TOOLTIP.positionOutsideRect(boundaryRect, width, height)
        : [boundaryRect.right + 27, boundaryRect.top + 12];

      return [
        position[0] - canvasRect.left,
        position[1] - canvasRect.top
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

  CB_GRAFY.register('online_stavy', function objednavkyOnlineGraf(payload, canvas) {
    const labels = Array.isArray(payload.labels) ? payload.labels.map((item) => String(item)) : [];
    const dokoncenoRaw = getSeriesData(payload, 'dokonceno', 0);
    const naCesteRaw = getSeriesData(payload, 'na_ceste', 1);
    const osobniOdberRaw = getSeriesData(payload, 'osobni_odber', 2);
    const vyrabiSeRaw = getSeriesData(payload, 'vyrabi_se', 3);
    const zrusenoRaw = getSeriesData(payload, 'zruseno', 4);
    const objednavkyRaw = getSeriesData(payload, 'objednavky', 5);
    const trzbaRaw = getSeriesData(payload, 'trzba', 6);
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
            + '<tr><th class="provoz_tooltip_table_cell">Tržba</th><th class="provoz_tooltip_table_cell provoz_tooltip_num">' + formatMoney(trzba[index] ?? 0) + '</th></tr>'
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
          itemStyle: { color: '#7bdca5' },
          emphasis: { disabled: true },
          data: dokonceno
        },
        {
          name: 'Na cestě',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#f59e0b' },
          emphasis: { disabled: true },
          data: naCeste
        },
        {
          name: 'Osobní odběr',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#0ea5e9' },
          emphasis: { disabled: true },
          data: osobniOdber
        },
        {
          name: 'Vyrábí se',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#dc2626' },
          emphasis: { disabled: true },
          data: vyrabiSe
        },
        {
          name: 'Zrušeno',
          type: 'bar',
          yAxisIndex: 0,
          stack: 'online',
          barMaxWidth: MINI_SLOUPEC_BAR_MAX_WIDTH,
          itemStyle: { color: '#64748b' },
          emphasis: { disabled: true },
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
          itemStyle: {
            color: '#cbd5e1',
            borderColor: '#64748b',
            borderWidth: 1
          },
          data: trzba,
          label: {
            show: false
          }
        }
      ]
    };
  });
})(window);
