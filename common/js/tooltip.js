// js/tooltip.js * Verze: V1
'use strict';

(function (w, d) {
  const CB_TOOLTIP = w.CB_TOOLTIP || (w.CB_TOOLTIP = {});

  function init(root) {
    const scope = root instanceof HTMLElement ? root : d;
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

  d.addEventListener('cb:main-swapped', () => init(d));
  d.addEventListener('cb:gn-block-refreshed', (event) => {
    const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
    init(detail && detail.block instanceof HTMLElement ? detail.block : d);
  });

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', () => init(d), { once: true });
  } else {
    init(d);
  }
})(window, document);
