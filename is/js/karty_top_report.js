// js/karty_top_report.js * Verze: V2 * Aktualizace: 08.05.2026
'use strict';

(function (w, d) {
  function getStateKey(root) {
    if (!(root instanceof HTMLElement)) { return 'cb_top_report_manager_state'; }
    const shell = root.closest('.card_shell');
    const cardId = shell ? String(shell.getAttribute('data-card-id') || '15') : '15';
    return 'cb_top_report_manager_state_' + cardId;
  }

  function parseMetricList(value) {
    return String(value || '')
      .split(',')
      .map((item) => String(item || '').trim())
      .filter((item, index, arr) => item !== '' && arr.indexOf(item) === index);
  }

  function serializeMetricList(metrics) {
    return parseMetricList(Array.isArray(metrics) ? metrics.join(',') : metrics).join(',');
  }

  function loadState(root) {
    try {
      const raw = w.sessionStorage ? w.sessionStorage.getItem(getStateKey(root)) : '';
      if (!raw) { return null; }
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function saveState(root, metrics, view, display) {
    if (!(root instanceof HTMLElement) || !w.sessionStorage) { return; }
    try {
      w.sessionStorage.setItem(getStateKey(root), JSON.stringify({
        metrics: parseMetricList(metrics),
        view: view,
        display: display
      }));
    } catch (e) {}
  }

  function getAvailableViews(root) {
    const script = root.querySelector('[data-top-report-available-views="1"]');
    if (!(script instanceof HTMLElement)) { return {}; }
    try {
      return JSON.parse(script.textContent || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function getMetricInputs(root) {
    return Array.from(root.querySelectorAll('[data-top-report-metric]')).filter((el) => el instanceof HTMLInputElement);
  }

  function getAllowedViews(availableViews, metrics) {
    const cleanMetrics = parseMetricList(metrics);
    if (cleanMetrics.length === 0) {
      return Array.isArray(availableViews.trzby) ? availableViews.trzby.slice() : ['pobocky'];
    }
    let allowed = null;
    cleanMetrics.forEach((metric) => {
      const metricViews = Array.isArray(availableViews[metric]) ? availableViews[metric].slice() : [];
      if (allowed === null) {
        allowed = metricViews;
        return;
      }
      allowed = allowed.filter((view) => metricViews.indexOf(view) !== -1);
    });
    return Array.isArray(allowed) && allowed.length > 0 ? allowed : (Array.isArray(availableViews.trzby) ? availableViews.trzby.slice() : ['pobocky']);
  }

  function syncMetricBlocks(root, metrics) {
    const selected = parseMetricList(metrics);
    root.querySelectorAll('[data-top-report-metric-block]').forEach((node) => {
      const metricId = String(node.getAttribute('data-top-report-metric-block') || '').trim();
      if (metricId === '') { return; }
      node.style.display = selected.indexOf(metricId) !== -1 ? '' : 'none';
    });
  }

  function syncRoot(root) {
    if (!(root instanceof HTMLElement)) { return; }

    const availableViews = getAvailableViews(root);
    const saved = loadState(root);
    const hasAppliedSavedState = root.getAttribute('data-top-report-state-applied') === '1';
    let metrics = parseMetricList(root.getAttribute('data-top-report-metrics-active') || root.getAttribute('data-top-report-metric-active') || 'trzby');
    let display = String(root.getAttribute('data-top-report-display-active') || 'graph');
    let view = String(root.getAttribute('data-top-report-view-active') || '');

    if (!hasAppliedSavedState && saved && typeof saved === 'object') {
      if (Array.isArray(saved.metrics)) {
        metrics = parseMetricList(saved.metrics.join(','));
      } else if (typeof saved.metric === 'string' && saved.metric.trim() !== '') {
        metrics = [saved.metric.trim()];
      }
      if (typeof saved.view === 'string' && saved.view.trim() !== '') {
        view = saved.view;
      }
      if (typeof saved.display === 'string' && saved.display.trim() !== '') {
        display = saved.display;
      }
      root.setAttribute('data-top-report-state-applied', '1');
    }

    const metricInputs = getMetricInputs(root);
    const knownMetrics = metricInputs
      .map((input) => String(input.getAttribute('data-top-report-metric') || '').trim())
      .filter((metric, index, arr) => metric !== '' && arr.indexOf(metric) === index);

    metrics = metrics.filter((metric) => knownMetrics.indexOf(metric) !== -1);
    if (metrics.length === 0) {
      metrics = knownMetrics.indexOf('trzby') !== -1 ? ['trzby'] : (knownMetrics[0] ? [knownMetrics[0]] : []);
    }

    const allowedViews = getAllowedViews(availableViews, metrics);
    if (allowedViews.indexOf(view) === -1) {
      view = allowedViews.length > 0 ? String(allowedViews[0]) : 'pobocky';
    }
    if (display !== 'table') {
      display = 'graph';
    }

    root.setAttribute('data-top-report-metrics-active', serializeMetricList(metrics));
    root.setAttribute('data-top-report-view-active', view);
    root.setAttribute('data-top-report-display-active', display);

    metricInputs.forEach((input) => {
      const metricId = String(input.getAttribute('data-top-report-metric') || '').trim();
      input.checked = metrics.indexOf(metricId) !== -1;
    });

    root.querySelectorAll('[data-top-report-view]').forEach((btn) => {
      const btnView = String(btn.getAttribute('data-top-report-view') || '');
      const isAllowed = allowedViews.indexOf(btnView) !== -1;
      if (btn instanceof HTMLInputElement) {
        btn.checked = (btnView === view);
        btn.disabled = !isAllowed;
      }
      const label = btn.closest('.cbtr-choice');
      if (label instanceof HTMLElement) {
        label.style.opacity = isAllowed ? '1' : '0.45';
        label.style.pointerEvents = isAllowed ? '' : 'none';
      }
    });

    root.querySelectorAll('[data-top-report-display]').forEach((btn) => {
      const isOn = String(btn.getAttribute('data-top-report-display') || '') === display;
      if (btn instanceof HTMLInputElement) {
        btn.checked = isOn;
      }
    });

    const activeKey = view + '|' + display;
    let captionText = '';
    root.querySelectorAll('[data-top-report-panel]').forEach((panel) => {
      const panelKey = String(panel.getAttribute('data-top-report-panel') || '');
      const isOn = panelKey === activeKey;
      panel.style.display = isOn ? '' : 'none';
      if (isOn) {
        captionText = String(panel.getAttribute('data-top-report-title') || '');
      }
    });

    const caption = root.querySelector('[data-top-report-caption]');
    if (caption instanceof HTMLElement) {
      caption.textContent = captionText;
    }

    syncMetricBlocks(root, metrics);
    saveState(root, metrics, view, display);
  }

  function initTopReport(card) {
    const scope = card instanceof HTMLElement ? card : d;
    scope.querySelectorAll('[data-cb-top-report-manager="1"]').forEach(syncRoot);
  }

  if (!w.__CB_TOP_REPORT_MANAGER_WIRED__) {
    w.__CB_TOP_REPORT_MANAGER_WIRED__ = true;

    d.addEventListener('change', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLInputElement)) { return; }
      const root = target.closest('[data-cb-top-report-manager="1"]');
      if (!(root instanceof HTMLElement)) { return; }

      if (target.hasAttribute('data-top-report-metric')) {
        const checkedMetrics = getMetricInputs(root)
          .filter((input) => input.checked)
          .map((input) => String(input.getAttribute('data-top-report-metric') || '').trim())
          .filter((metric, index, arr) => metric !== '' && arr.indexOf(metric) === index);

        if (checkedMetrics.length === 0) {
          target.checked = true;
          root.setAttribute('data-top-report-metrics-active', String(target.getAttribute('data-top-report-metric') || 'trzby'));
        } else {
          root.setAttribute('data-top-report-metrics-active', serializeMetricList(checkedMetrics));
        }
      }

      if (target.hasAttribute('data-top-report-view')) {
        root.setAttribute('data-top-report-view-active', String(target.getAttribute('data-top-report-view') || 'pobocky'));
      }
      if (target.hasAttribute('data-top-report-display')) {
        root.setAttribute('data-top-report-display-active', String(target.getAttribute('data-top-report-display') || 'graph'));
      }

      syncRoot(root);
    });

    d.addEventListener('cb:card-swapped', (event) => {
      const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
      const card = detail && detail.card instanceof HTMLElement ? detail.card : null;
      if (card instanceof HTMLElement) {
        initTopReport(card);
      }
    });

    d.addEventListener('cb:card-max-loaded', (event) => {
      const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
      const card = detail && detail.card instanceof HTMLElement ? detail.card : null;
      if (card instanceof HTMLElement) {
        initTopReport(card);
      }
    });
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', () => initTopReport(d), { once: true });
  } else {
    initTopReport(d);
  }
})(window, document);

// js/karty_top_report.js * Verze: V2 * Aktualizace: 08.05.2026
// Konec souboru
