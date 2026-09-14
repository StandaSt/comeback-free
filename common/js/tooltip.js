// js/tooltip.js * Verze: V2
'use strict';

(function (w, d) {
  const CB_TOOLTIP = w.CB_TOOLTIP || (w.CB_TOOLTIP = {});
  let activeTarget = null;
  let floatingPanel = null;

  function getFloatingPanel() {
    if (floatingPanel instanceof HTMLElement) return floatingPanel;

    floatingPanel = d.createElement('div');
    floatingPanel.className = 'cb_tooltip';
    floatingPanel.id = 'cb-global-tooltip';
    floatingPanel.setAttribute('role', 'tooltip');
    d.body.appendChild(floatingPanel);
    return floatingPanel;
  }

  function positionFloatingPanel(target, panel) {
    const targetRect = target.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    const edgeGap = 8;
    const targetGap = 10;
    let left = targetRect.left + ((targetRect.width - panelRect.width) / 2);
    let top = targetRect.top - panelRect.height - targetGap;
    let placement = 'top';

    left = Math.max(edgeGap, Math.min(left, w.innerWidth - panelRect.width - edgeGap));
    if (top < edgeGap) {
      top = targetRect.bottom + targetGap;
      placement = 'bottom';
    }

    panel.style.left = `${Math.round(left)}px`;
    panel.style.top = `${Math.max(edgeGap, Math.round(top))}px`;
    panel.dataset.placement = placement;
  }

  function showFloatingPanel(target) {
    const text = target.dataset.cbTooltipText || '';
    if (text === '') return;

    const panel = getFloatingPanel();
    const moduleRoot = target.closest('.obal_main') || d.querySelector('.obal_main');
    const accent = target.dataset.cbTooltipWarning === '1'
      ? '#dc2626'
      : (moduleRoot instanceof HTMLElement
        ? w.getComputedStyle(moduleRoot).getPropertyValue('--cb-module-accent').trim()
        : '');

    activeTarget = target;
    panel.textContent = text;
    panel.classList.toggle('cb_tooltip_multiline', target.dataset.cbTooltipMultiline === '1');
    panel.style.setProperty('--cb-tooltip-accent', accent || '#2563eb');
    panel.classList.add('cb_tooltip_visible');
    positionFloatingPanel(target, panel);
  }

  function hideFloatingPanel(target) {
    if (target && activeTarget !== target) return;
    if (floatingPanel instanceof HTMLElement) floatingPanel.classList.remove('cb_tooltip_visible');
    activeTarget = null;
  }

  function hideAllTooltips() {
    const focused = d.activeElement;
    hideFloatingPanel();
    d.querySelectorAll('.provoz_tooltip_panel_visible').forEach((panel) => {
      panel.classList.remove('provoz_tooltip_panel_visible');
    });
    if (
      focused instanceof HTMLElement
      && (
        focused.dataset.cbTooltipReady === '1'
        || focused.matches('[data-tooltip="1"], .provoz_tooltip')
      )
    ) {
      focused.blur();
    }
  }

  function initNativeTitles(scope) {
    const titles = Array.from(scope.querySelectorAll('[title]')).filter((node) => node instanceof HTMLElement);
    if (scope instanceof HTMLElement && scope.hasAttribute('title')) titles.unshift(scope);

    titles.forEach((target) => {
      if (target.dataset.cbTooltipReady === '1') return;
      const text = (target.getAttribute('title') || '').trim();
      if (text === '') return;

      target.dataset.cbTooltipReady = '1';
      target.dataset.cbTooltipText = text;
      target.removeAttribute('title');
      target.addEventListener('mouseenter', () => showFloatingPanel(target));
      target.addEventListener('focus', () => showFloatingPanel(target));
      target.addEventListener('mouseleave', () => hideFloatingPanel(target));
      target.addEventListener('blur', () => hideFloatingPanel(target));
    });
  }

  function init(root) {
    const scope = root instanceof HTMLElement ? root : d;
    initNativeTitles(scope);
    const tooltips = Array.from(scope.querySelectorAll('[data-tooltip="1"], .provoz_tooltip')).filter((node) => node instanceof HTMLElement);
    if (scope instanceof HTMLElement && (scope.matches('[data-tooltip="1"]') || scope.classList.contains('provoz_tooltip'))) {
      tooltips.unshift(scope);
    }
    tooltips.forEach((tooltip) => {
      if (tooltip.dataset.tooltipReady === '1') return;
      tooltip.dataset.tooltipReady = '1';
      const panel = tooltip.querySelector('[data-tooltip-panel="1"], .provoz_tooltip_panel');
      if (!(panel instanceof HTMLElement)) return;

      const showPanel = () => panel.classList.add('provoz_tooltip_panel_visible');
      const hidePanel = () => panel.classList.remove('provoz_tooltip_panel_visible');

      tooltip.addEventListener('mouseenter', showPanel);
      tooltip.addEventListener('focusin', showPanel);
      tooltip.addEventListener('mouseleave', hidePanel);
      tooltip.addEventListener('focusout', hidePanel);
    });
  }

  CB_TOOLTIP.init = init;
  CB_TOOLTIP.hideAll = hideAllTooltips;

  d.addEventListener('pointerdown', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target || !target.closest('[data-cb-tooltip-ready="1"], [data-tooltip="1"], .provoz_tooltip')) {
      hideAllTooltips();
    }
  }, true);
  d.addEventListener('cb:main-swapped', () => {
    hideAllTooltips();
    init(d);
  });
  d.addEventListener('cb:gn-block-refreshed', (event) => {
    const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
    hideAllTooltips();
    init(detail && detail.block instanceof HTMLElement ? detail.block : d);
  });
  w.addEventListener('scroll', hideAllTooltips, true);
  w.addEventListener('resize', hideAllTooltips);
  w.addEventListener('pagehide', hideAllTooltips);

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', () => init(d), { once: true });
  } else {
    init(d);
  }
})(window, document);
