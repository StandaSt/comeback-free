// js/karty_min_max.js * Verze: V5 * Aktualizace: 15.04.2026
'use strict';

(function (w) {
  const ICON_MAX = '\u2922';
  const ICON_MIN = '\u2212';
  const MAXI_STATE_KEY = 'cb_maxi_state_v1';
  let activeMaxi = null;
  let suppressNextRestore = false;

  const CARD_ROOT_SELECTOR = '.card_shell';
  const CARD_TOGGLE_SELECTOR = '[data-card-toggle]';
  const CARD_COMPACT_SELECTOR = '[data-card-compact]';
  const CARD_EXPANDED_SELECTOR = '[data-card-expanded]';

  function getCurrentLoginId() {
    const grid = document.querySelector('.dash_grid[data-login-id]');
    if (!(grid instanceof HTMLElement)) return '0';
    return String(grid.getAttribute('data-login-id') || '0').trim() || '0';
  }

  function clearMaxiState() {
    try { w.sessionStorage.removeItem(MAXI_STATE_KEY); } catch (e) {}
  }

  function saveMaxiState(cardId) {
    const cid = String(cardId || '').trim();
    if (cid === '') return;
    const payload = {
      cardId: cid,
      loginId: getCurrentLoginId()
    };
    try { w.sessionStorage.setItem(MAXI_STATE_KEY, JSON.stringify(payload)); } catch (e) {}
  }

  function loadMaxiState() {
    try {
      const raw = String(w.sessionStorage.getItem(MAXI_STATE_KEY) || '');
      if (raw === '') return null;
      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object') return null;
      const cardId = String(parsed.cardId || '').trim();
      if (cardId === '') return null;
      const loginId = String(parsed.loginId || '0').trim() || '0';
      return { cardId, loginId };
    } catch (e) {
      return null;
    }
  }

  function getDashCard(root) {
    if (!root) return null;
    return root.closest('[data-cb-dash-card="1"]');
  }

  function getDashBox(root) {
    if (!root) return null;
    return root.closest('.dash_box') || document.querySelector('.dash_box');
  }

  function getCardHead(root) {
    if (!root) return null;

    const toggle = root.querySelector(CARD_TOGGLE_SELECTOR);
    if (toggle instanceof HTMLElement) {
      const head = toggle.closest('.card_top');
      if (head instanceof HTMLElement) {
        return head;
      }
    }

    const fallback = root.querySelector('.card_top');
    return fallback instanceof HTMLElement ? fallback : null;
  }

  function getCardRoots() {
    return Array.from(document.querySelectorAll(CARD_ROOT_SELECTOR)).filter((el) => el instanceof HTMLElement);
  }

  function getPorovnaniPayload(root) {
    const node = document.querySelector('[data-cb-porovnani-data]');
    if (!(node instanceof HTMLElement)) return null;
    const raw = String(node.textContent || '').trim();
    if (raw === '') return null;
    try {
      const parsed = JSON.parse(raw);
      return (parsed && typeof parsed === 'object') ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function restoreActiveMaxi() {
    if (suppressNextRestore) {
      suppressNextRestore = false;
      clearMaxiState();
      return;
    }

    const state = loadMaxiState();
    if (!state) return;

    const currentLoginId = getCurrentLoginId();
    if (String(state.loginId || '0').trim() !== currentLoginId) {
      clearMaxiState();
      return;
    }

    const cardId = String(state.cardId || '').trim();
    if (cardId === '') return;

    const root = document.querySelector('.card_shell[data-card-id="' + cardId.replace(/"/g, '') + '"]');
    if (!(root instanceof HTMLElement)) return;

    openMaxi(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR);
  }

  function getPorovnaniChartNode(root, chartId) {
    if (!(root instanceof HTMLElement)) return null;
    const id = String(chartId || '').trim();
    if (id === '') return null;
    const node = root.querySelector('[data-cb-chart-id="' + id.replace(/"/g, '') + '"]');
    return node instanceof HTMLElement ? node : null;
  }

  function wireEchartsResize() {
    if (w.__CB_ECHARTS_RESIZE_WIRED__) return;
    w.__CB_ECHARTS_RESIZE_WIRED__ = true;

    w.addEventListener('resize', () => {
      const echarts = w.echarts;
      if (!echarts || typeof echarts.getInstanceByDom !== 'function') return;
      document.querySelectorAll('[data-cb-chart-id]').forEach((node) => {
        if (!(node instanceof HTMLElement)) return;
        const inst = echarts.getInstanceByDom(node);
        if (inst) {
          inst.resize();
        }
      });
    });
  }

  function buildEchartsOption(def) {
    if (!def || typeof def !== 'object') return null;

    const kind = String(def.kind || '').trim();
    const labels = Array.isArray(def.labels) ? def.labels.map((item) => String(item)) : [];
    const series = Array.isArray(def.series) ? def.series : [];

    if (kind === 'bar') {
      const s = series[0] || {};
      const values = Array.isArray(s.data) ? s.data : [];
      const colors = Array.isArray(s.colors) ? s.colors : [];

      return {
        grid: { left: 36, right: 18, top: 20, bottom: 42, containLabel: true },
        tooltip: { trigger: 'axis' },
        xAxis: {
          type: 'category',
          data: labels,
          axisLabel: { interval: 0, rotate: labels.length > 6 ? 20 : 0 }
        },
        yAxis: { type: 'value' },
        series: [{
          type: 'bar',
          name: String(s.name || ''),
          barMaxWidth: 42,
          data: values.map((value, index) => ({
            value: value,
            itemStyle: {
              color: colors[index] || s.color || '#60a5fa'
            }
          }))
        }]
      };
    }

    if (kind === 'line') {
      const s = series[0] || {};
      const values = Array.isArray(s.data) ? s.data : [];

      return {
        grid: { left: 36, right: 18, top: 20, bottom: 42, containLabel: true },
        tooltip: { trigger: 'axis' },
        xAxis: {
          type: 'category',
          data: labels,
          axisLabel: { interval: 0, rotate: labels.length > 6 ? 20 : 0 }
        },
        yAxis: { type: 'value' },
        series: [{
          type: 'line',
          smooth: true,
          name: String(s.name || ''),
          data: values,
          lineStyle: { color: s.color || '#60a5fa' },
          itemStyle: { color: s.color || '#60a5fa' },
          areaStyle: { color: s.color ? 'rgba(96,165,250,0.18)' : 'rgba(96,165,250,0.18)' }
        }]
      };
    }

    if (kind === 'radar') {
      const s = series[0] || {};
      const values = Array.isArray(s.data) ? s.data : [];
      const maxValue = Math.max(1, ...values.map((value) => Number(value) || 0));
      const indicator = labels.map((label) => ({
        name: label,
        max: Math.ceil(maxValue * 1.2)
      }));

      return {
        tooltip: {},
        radar: {
          indicator: indicator
        },
        series: [{
          type: 'radar',
          data: [{
            value: values,
            name: String(s.name || ''),
            itemStyle: { color: s.color || '#f472b6' },
            areaStyle: { color: 'rgba(244,114,182,0.20)' }
          }]
        }]
      };
    }

    if (kind === 'pie') {
      const values = Array.isArray(def.values) ? def.values : [];
      const colors = Array.isArray(def.colors) ? def.colors : [];

      return {
        tooltip: { trigger: 'item' },
        legend: { bottom: 0, type: 'scroll' },
        series: [{
          type: 'pie',
          radius: ['38%', '72%'],
          data: labels.map((label, index) => ({
            name: label,
            value: values[index] || 0,
            itemStyle: { color: colors[index] || undefined }
          }))
        }]
      };
    }

    if (kind === 'scatter') {
      const s = series[0] || {};
      const data = Array.isArray(s.data) ? s.data : [];

      return {
        grid: { left: 42, right: 18, top: 20, bottom: 42, containLabel: true },
        tooltip: {
          trigger: 'item',
          formatter: (params) => {
            const value = Array.isArray(params.value) ? params.value : [];
            const name = String(params.name || '');
            return name + ': objednavky ' + (value[0] ?? 0) + ', doruceni ' + (value[1] ?? 0) + ' min';
          }
        },
        xAxis: {
          type: 'value',
          name: 'Objednavky'
        },
        yAxis: {
          type: 'value',
          name: 'Prumer doruceni (min)'
        },
        series: [{
          type: 'scatter',
          name: String(s.name || ''),
          symbolSize: 12,
          data: data.map((point) => ({
            name: String(point.name || ''),
            value: Array.isArray(point.value) ? point.value : [0, 0],
            itemStyle: { color: point.color || '#60a5fa' }
          }))
        }]
      };
    }

    return null;
  }

  function schedulePorovnaniCharts(root, attempt) {
    if (!(root instanceof HTMLElement)) return;

    const currentAttempt = Number.isFinite(attempt) ? attempt : 0;
    const maxAttempts = 12;
    const delay = currentAttempt === 0 ? 0 : 120;

    w.setTimeout(() => {
      const payload = getPorovnaniPayload(root);
      if (!payload || typeof payload !== 'object') return;

      const echarts = w.echarts;
      if (!echarts || typeof echarts.init !== 'function') {
        if (currentAttempt < maxAttempts) {
          schedulePorovnaniCharts(root, currentAttempt + 1);
        }
        return;
      }

      const mode = String(payload.mode || 'mini');
      const chartDefs = [];
      if (mode === 'mini' && payload.mini) {
        chartDefs.push(payload.mini);
      } else if (payload.charts && typeof payload.charts === 'object') {
        Object.keys(payload.charts).forEach((key) => {
          const def = payload.charts[key];
          if (def && typeof def === 'object') {
            chartDefs.push(Object.assign({ id: key }, def));
          }
        });
      }

      let rendered = 0;
      chartDefs.forEach((def) => {
        const node = getPorovnaniChartNode(root, def.id);
        if (!(node instanceof HTMLElement)) return;

        const rect = node.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) return;

        const option = buildEchartsOption(def);
        if (!option) return;

        const existing = typeof echarts.getInstanceByDom === 'function' ? echarts.getInstanceByDom(node) : null;
        if (existing) {
          existing.dispose();
        }

        const chart = echarts.init(node);
        chart.setOption(option, true);
        rendered += 1;
      });

      if (rendered === 0 && currentAttempt < maxAttempts) {
        schedulePorovnaniCharts(root, currentAttempt + 1);
      }
    }, delay);
  }

  function renderPorovnaniCharts(root) {
    if (!(root instanceof HTMLElement)) return;
    if (String(root.getAttribute('data-card-mode') || 'mini').trim() === 'nano') return;
    if (!w.echarts || typeof w.echarts.init !== 'function') return;
    wireEchartsResize();
    schedulePorovnaniCharts(root, 0);
  }

  function updateToggle(toggle, isOn) {
    if (!(toggle instanceof HTMLElement)) return;
    toggle.textContent = isOn ? ICON_MIN : ICON_MAX;
    toggle.setAttribute('aria-expanded', isOn ? 'true' : 'false');
    toggle.setAttribute('title', isOn ? 'Přepnout do mini' : 'Přepnout do max');
  }

  function updateSubtitle(root, isExpanded) {
    if (!(root instanceof HTMLElement)) return;
    const subtitle = root.querySelector('[data-card-subtitle]');
    if (!(subtitle instanceof HTMLElement)) return;

    const minText = String(subtitle.getAttribute('data-subtitle-min') || '');
    const maxText = String(subtitle.getAttribute('data-subtitle-max') || '');
    subtitle.textContent = isExpanded ? maxText : minText;
  }

  function toggleNanoBtn(root, show) {
    if (!(root instanceof HTMLElement)) return;
    const btn = root.querySelector('[data-card-to-nano]');
    if (!(btn instanceof HTMLElement)) return;
    btn.style.display = show ? '' : 'none';
  }

  function makeHeadInteractive(head) {
    if (!(head instanceof HTMLElement)) return;
    head.style.cursor = 'pointer';
    head.style.userSelect = 'none';
    head.style.webkitUserSelect = 'none';
    head.style.msUserSelect = 'none';
    head.setAttribute('draggable', 'false');
  }

  function clearSelection() {
    if (typeof w.getSelection !== 'function') return;
    const selection = w.getSelection();
    if (selection && typeof selection.removeAllRanges === 'function') {
      selection.removeAllRanges();
    }
  }

  function blockHeadSelection(event) {
    if (!(event instanceof MouseEvent)) return;
    if (event.target instanceof Element && event.target.closest(CARD_TOGGLE_SELECTOR)) {
      return;
    }
    event.preventDefault();
    clearSelection();
  }

  function hasLoadedMax(root) {
    if (!(root instanceof HTMLElement)) return false;
    return String(root.getAttribute('data-card-max-loaded') || '0') === '1';
  }

  function loadMaxContent(root) {
    const cardId = String(root.getAttribute('data-card-id') || '').trim();
    if (cardId === '') {
      return Promise.reject(new Error('ID karty nebylo nalezeno.'));
    }

    if (!(w.CB_AJAX && typeof w.CB_AJAX.refreshCard === 'function')) {
      return Promise.reject(new Error('Nacteni karty neni dostupne.'));
    }

    return w.CB_AJAX.refreshCard(cardId, {
      force: true,
      keepLoading: true,
      loaderMode: 'cards',
      loadMax: true
    }).then((result) => {
      const nextCard = result && result.card instanceof HTMLElement ? result.card : null;
      if (!(nextCard instanceof HTMLElement)) {
        throw new Error('Nova karta nema shell.');
      }

      const nextRoot = nextCard.querySelector(CARD_ROOT_SELECTOR);
      if (!(nextRoot instanceof HTMLElement)) {
        throw new Error('Nova karta nema shell.');
      }

      return nextRoot;
    });
  }

  function closeActiveMaxi(opts) {
    if (!activeMaxi) return;

    const options = (opts && typeof opts === 'object') ? opts : {};
    const preserveState = !!options.preserveState;

    const item = activeMaxi;
    const {
      root,
      compact,
      expanded,
      toggle,
      dashCard,
      dashBox
    } = item;

    if (expanded) expanded.classList.add('is-hidden');
    if (compact) compact.classList.remove('is-hidden');
    if (toggle) updateToggle(toggle, false);
    updateSubtitle(root, false);
    toggleNanoBtn(root, true);

    if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
      w.cbSetBranchSelectDisabledForRoot(root, false);
    }

    if (dashCard) {
      dashCard.classList.remove('is-expanded');
      dashCard.classList.remove('is-maxi-overlay');
      dashCard.style.top = '';
      dashCard.style.right = '';
      dashCard.style.bottom = '';
      dashCard.style.left = '';
      dashCard.style.transform = '';
      dashCard.style.width = '';
      dashCard.style.height = '';
      dashCard.style.maxWidth = '';
      dashCard.style.maxHeight = '';
    }

    if (dashBox) {
      dashBox.classList.remove('has-maxi');
    }

    activeMaxi = null;

    if (!preserveState) {
      clearMaxiState();
    }

    const closedCard = item.dashCard instanceof HTMLElement ? item.dashCard : null;
    if (closedCard instanceof HTMLElement) {
      w.setTimeout(() => {
        document.dispatchEvent(new CustomEvent('cb:card-swapped', {
          detail: {
            cardId: parseInt(String(root.getAttribute('data-card-id') || '0'), 10) || 0,
            card: closedCard
          }
        }));
      }, 0);
    }
  }

  function finishOpenMaxi(root, compactSel, expandedSel, toggleSel) {
    const compact = root.querySelector(compactSel);
    const expanded = root.querySelector(expandedSel);
    const toggle = root.querySelector(toggleSel);
    const dashCard = getDashCard(root);
    const dashBox = getDashBox(root);

    if (!(compact instanceof HTMLElement) || !(expanded instanceof HTMLElement) || !(toggle instanceof HTMLElement) || !(dashCard instanceof HTMLElement)) {
      return;
    }

    const overlayTop = (dashBox instanceof HTMLElement) ? dashBox.scrollTop : 0;
    const forceFill = root.getAttribute('data-card-max-fill') === '1';

    updateSubtitle(root, true);
    toggleNanoBtn(root, false);
    expanded.classList.remove('is-hidden');
    compact.classList.add('is-hidden');
    dashCard.classList.add('is-expanded');
    dashCard.classList.add('is-maxi-overlay');
    if (forceFill) {
      dashCard.style.left = '0';
      dashCard.style.right = '0';
      dashCard.style.top = String(overlayTop) + 'px';
      dashCard.style.bottom = '0';
      dashCard.style.transform = 'none';
      dashCard.style.width = 'auto';
      dashCard.style.height = 'auto';
      dashCard.style.maxWidth = 'none';
      dashCard.style.maxHeight = 'none';
    } else {
      dashCard.style.top = String(overlayTop) + 'px';
    }

    if (dashBox) {
      dashBox.classList.add('has-maxi');
    }

    updateToggle(toggle, true);

    if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
      w.cbSetBranchSelectDisabledForRoot(root, true);
    }

    activeMaxi = {
      root,
      compact,
      expanded,
      toggle,
      dashCard,
      dashBox,
      forceFill
    };

    renderPorovnaniCharts(root);

    saveMaxiState(String(root.getAttribute('data-card-id') || ''));
  }

  function openMaxi(root, compactSel, expandedSel, toggleSel) {
    if (!(root instanceof HTMLElement)) return;

    if (activeMaxi && activeMaxi.root === root) {
      closeActiveMaxi();
      return;
    }

    closeActiveMaxi();

    if (hasLoadedMax(root)) {
      finishOpenMaxi(root, compactSel, expandedSel, toggleSel);
      return;
    }

    if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
      w.CB_AJAX.setDashboardLoading(true, 'cards');
    }

    loadMaxContent(root).then((nextRoot) => {
      initCard(nextRoot);
      finishOpenMaxi(nextRoot, compactSel, expandedSel, toggleSel);
    }).catch(() => {
      if (w.alert) {
        w.alert('Otevreni max karty selhalo.');
      }
    }).finally(() => {
      if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
        w.CB_AJAX.setDashboardLoading(false, 'cards');
      }
    });
  }

  function setExpanded(root, compactSel, expandedSel, toggleSel, on) {
    if (!(root instanceof HTMLElement)) return;

    const compact = root.querySelector(compactSel);
    const expanded = root.querySelector(expandedSel);
    const toggle = root.querySelector(toggleSel);
    const dashCard = getDashCard(root);
    const isOn = !!on;

    if (isOn) {
      openMaxi(root, compactSel, expandedSel, toggleSel);
      return;
    }

    if (activeMaxi && activeMaxi.root === root) {
      closeActiveMaxi();
      return;
    }

    if (compact instanceof HTMLElement) compact.classList.remove('is-hidden');
    if (expanded instanceof HTMLElement) expanded.classList.add('is-hidden');
    if (dashCard instanceof HTMLElement) {
      dashCard.classList.remove('is-expanded');
      dashCard.classList.remove('is-maxi-overlay');
      dashCard.style.top = '';
    }
    updateToggle(toggle, false);
    updateSubtitle(root, false);
    toggleNanoBtn(root, true);
  }

  function initCard(root) {
    if (!(root instanceof HTMLElement) || root.getAttribute('data-card-init-max') === '1') return;
    if (String(root.getAttribute('data-card-mode') || 'mini').trim() === 'nano') return;

    root.setAttribute('data-card-init-max', '1');

    const toggle = root.querySelector(CARD_TOGGLE_SELECTOR);
    const head = getCardHead(root);
    const compact = root.querySelector(CARD_COMPACT_SELECTOR);
    const expanded = root.querySelector(CARD_EXPANDED_SELECTOR);

    if (!(toggle instanceof HTMLElement) || !(head instanceof HTMLElement) || !(compact instanceof HTMLElement) || !(expanded instanceof HTMLElement)) {
      return;
    }

    makeHeadInteractive(head);
    setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, false);

    toggle.addEventListener('click', () => {
      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, !isExpanded);
    });

    head.addEventListener('mousedown', blockHeadSelection);
    head.addEventListener('dblclick', (event) => {
      if (event.target instanceof Element && event.target.closest(CARD_TOGGLE_SELECTOR)) {
        return;
      }
      clearSelection();
      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, !isExpanded);
    });

    renderPorovnaniCharts(root);
  }

  function initKartyMinMax() {
    const roots = getCardRoots();
    roots.forEach(initCard);
  }

  function wireOnce() {
    if (w.__CB_KARTY_MINMAX_WIRED__) return;
    w.__CB_KARTY_MINMAX_WIRED__ = true;

    document.addEventListener('cb:maxi-close-request', () => {
      suppressNextRestore = true;
      clearMaxiState();
      closeActiveMaxi({ preserveState: false });
    });

    document.addEventListener('cb:main-swapped', () => {
      if (suppressNextRestore) {
        closeActiveMaxi({ preserveState: false });
      } else {
        closeActiveMaxi({ preserveState: true });
      }
      initKartyMinMax();
      if (suppressNextRestore) {
        suppressNextRestore = false;
      } else {
        w.setTimeout(restoreActiveMaxi, 0);
      }
    });

    document.addEventListener('cb:dashboard-layout-changed', () => {
      initKartyMinMax();
      if (activeMaxi && activeMaxi.root instanceof HTMLElement) {
        finishOpenMaxi(activeMaxi.root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR);
      }
    });

    document.addEventListener('cb:card-swapped', (event) => {
      const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
      const cardId = detail ? String(detail.cardId || '').trim() : '';
      const card = detail && detail.card instanceof HTMLElement ? detail.card : null;
      if (cardId === '' || !(card instanceof HTMLElement)) {
        return;
      }

      const nextRoot = card.querySelector(CARD_ROOT_SELECTOR);
      if (!(nextRoot instanceof HTMLElement)) {
        return;
      }

      initCard(nextRoot);

      if (activeMaxi && String(activeMaxi.root.getAttribute('data-card-id') || '').trim() === cardId) {
        activeMaxi = null;
        finishOpenMaxi(nextRoot, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeActiveMaxi();
      }
    });
  }

  wireOnce();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyMinMax, { once: true });
  } else {
    initKartyMinMax();
  }
})(window);

// js/karty_min_max.js * Verze: V5 * Aktualizace: 15.04.2026
// Konec souboru
