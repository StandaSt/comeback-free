/* Účel: Společné tooltipy IS; text i tabulky se vejdou do okna, graf objednávek ukazuje podrobnosti pod celým blokem. */
'use strict';

(function (w, d) {
  const CB_TOOLTIP = w.CB_TOOLTIP || (w.CB_TOOLTIP = {});
  let activeTarget = null;
  let floatingPanel = null;
  let hideTimer = null;

  function getFloatingPanel() {
    if (floatingPanel instanceof HTMLElement) return floatingPanel;

    floatingPanel = d.createElement('div');
    floatingPanel.className = 'cb_tooltip';
    floatingPanel.id = 'cb-global-tooltip';
    floatingPanel.setAttribute('role', 'tooltip');
    d.body.appendChild(floatingPanel);
    // Velkou tabulku lze prohlížet myší i rolovat bez zmizení při přechodu ze štítku.
    floatingPanel.addEventListener('mouseenter', () => clearTimeout(hideTimer));
    floatingPanel.addEventListener('mouseleave', () => hideFloatingPanel());
    return floatingPanel;
  }

  function positionOutsideRect(boundaryRect, panelWidth, panelHeight) {
    const gap = 12;
    const extraRightOffset = 15;
    const width = Number(panelWidth) || 0;
    const height = Number(panelHeight) || 0;
    const viewWidth = w.innerWidth || d.documentElement.clientWidth || 0;
    const viewHeight = w.innerHeight || d.documentElement.clientHeight || 0;
    let left = boundaryRect.right + gap + extraRightOffset;
    if (left + width + gap > viewWidth) {
      left = boundaryRect.left - width - gap;
    }

    let top = boundaryRect.top + gap;
    if (top + height + gap > viewHeight) {
      top = viewHeight - height - gap;
    }

    return [
      Math.max(gap, Math.min(left, Math.max(gap, viewWidth - width - gap))),
      Math.max(gap, Math.min(top, Math.max(gap, viewHeight - height - gap)))
    ];
  }

  // Oba tooltipy objednavek sdileji polohu pod blokem; velka tabulka se posouva uvnitr dostupne plochy.
  function positionBelowRect(boundaryRect, panel) {
    const left = Math.max(8, boundaryRect.left);
    const top = boundaryRect.bottom + 6;
    panel.style.boxSizing = 'border-box';
    panel.style.maxWidth = `${Math.max(0, Math.min(boundaryRect.width, w.innerWidth - left - 8))}px`;
    panel.style.maxHeight = `${Math.max(80, w.innerHeight - top - 8)}px`;
    panel.style.overflow = 'auto';
    panel.dataset.tooltipScroll = '1';
    return [left, top];
  }

  // Souhrn pouzije vyslovne umisteni grafu; ostatnim tooltipum se pravidla nemeni.
  function positionChartPanel(target, panel) {
    const below = target.dataset.tooltipPosition === 'chart-bottom';
    if (!below && target.dataset.tooltipPosition !== 'chart-right') return;

    const root = target.closest('.provoz_prehled_online_root');
    const canvas = root instanceof HTMLElement ? root.querySelector('[data-graf-canvas="1"]') : null;
    if (!(canvas instanceof HTMLElement)) return;

    panel.style.position = 'fixed';
    panel.style.right = 'auto';
    const panelRect = panel.getBoundingClientRect();
    const block = target.closest('.blok_online');
    const position = below && block instanceof HTMLElement
      ? positionBelowRect(block.getBoundingClientRect(), panel)
      : positionOutsideRect(canvas.getBoundingClientRect(), panelRect.width, panelRect.height);
    panel.style.left = `${Math.round(position[0])}px`;
    panel.style.top = `${Math.round(position[1])}px`;
  }

  // Polohu vůči kurzoru používá jen výslovná volba; klávesnice a ostatní tooltipy zůstávají u prvku.
  function positionFloatingPanel(target, panel, pointer) {
    const targetRect = target.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    const edgeGap = 8;
    const targetGap = 10;
    const followPointer = target.dataset.cbTooltipPosition === 'pointer-top' && pointer && Number.isFinite(pointer.clientX) && Number.isFinite(pointer.clientY);
    let left = followPointer ? pointer.clientX-panelRect.width/2 : targetRect.left + ((targetRect.width - panelRect.width) / 2);
    let top = (followPointer ? pointer.clientY : targetRect.top) - panelRect.height - targetGap;
    let placement = 'top';

    left = Math.max(edgeGap, Math.min(left, w.innerWidth - panelRect.width - edgeGap));
    if (!followPointer) {
      const below = w.innerHeight - targetRect.bottom - targetGap - edgeGap;
      const above = targetRect.top - targetGap - edgeGap;
      if (panelRect.height > above && below >= above) {
        top = targetRect.bottom + targetGap;
        placement = 'bottom';
      }
      top = Math.max(edgeGap, Math.min(top, w.innerHeight - panelRect.height - edgeGap));
    }

    panel.style.left = `${Math.round(left)}px`;
    panel.style.top = `${Math.max(edgeGap, Math.round(top))}px`;
    panel.dataset.placement = placement;
  }

  // Text i vzhled sdílí všechny moduly; souřadnice myši jsou volitelné.
  function showFloatingPanel(target, pointer) {
    const text = target.dataset.cbTooltipText || '';
    if (text === '') return;

    const panel = getFloatingPanel();
    const moduleRoot = target.closest('.obal_main') || d.querySelector('.obal_main');
    // Hlavička patří modulu přepínače i při otevření jiné pracovní plochy.
    const headerModule = target.closest('.head_module_link');
    const headerAccent = headerModule ? w.getComputedStyle(headerModule).getPropertyValue('--head-module-color').trim() : '';
    const accent = target.dataset.cbTooltipWarning === '1'
      ? '#dc2626'
      : (headerAccent || (moduleRoot instanceof HTMLElement
        ? w.getComputedStyle(moduleRoot).getPropertyValue('--cb-module-accent').trim()
        : ''));

    if (activeTarget && activeTarget !== target) hideFloatingPanel(activeTarget);
    activeTarget = target;
    const describedBy = new Set((target.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
    describedBy.add(panel.id);
    target.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
    clearTimeout(hideTimer);
    panel.textContent = text;
    // Tabulka se skládá přes textContent, nikdy přes innerHTML z atributu nebo serveru.
    let rows = [];
    try { rows = JSON.parse(target.dataset.cbTooltipTable || '[]'); } catch (_) { rows = []; }
    const hasTable = Array.isArray(rows) && rows.length > 0;
    panel.classList.toggle('cb_tooltip_table', hasTable);
    if (hasTable) {
      const caption = d.createElement('p');
      caption.textContent = text;
      panel.replaceChildren(caption);
      const table = d.createElement('table');
      const head = d.createElement('thead');
      const body = d.createElement('tbody');
      rows.forEach((row, index) => {
        if (!Array.isArray(row)) return;
        const tr = d.createElement('tr');
        row.forEach((value) => { const cell = d.createElement(index === 0 ? 'th' : 'td'); cell.textContent = String(value); tr.appendChild(cell); });
        (index === 0 ? head : body).appendChild(tr);
      });
      table.append(head, body); panel.appendChild(table);
    }
    panel.classList.toggle('cb_tooltip_multiline', target.dataset.cbTooltipMultiline === '1');
    panel.style.setProperty('--cb-tooltip-accent', accent || '#2563eb');
    panel.classList.add('cb_tooltip_visible');
    positionFloatingPanel(target, panel, pointer);
  }

  function hideFloatingPanel(target) {
    if (target && activeTarget !== target) return;
    if (activeTarget) {
      const ids = (activeTarget.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => id && id !== 'cb-global-tooltip');
      if (ids.length) activeTarget.setAttribute('aria-describedby', ids.join(' '));
      else activeTarget.removeAttribute('aria-describedby');
    }
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
      target.addEventListener('mouseenter', (event) => showFloatingPanel(target, event));
      // Bez dalšího panelu nebo časovače přesuneme tutéž bublinu nad aktuální polohu kurzoru.
      if (target.dataset.cbTooltipPosition === 'pointer-top') {
        target.addEventListener('mousemove', (event) => {
          if (activeTarget === target) positionFloatingPanel(target, floatingPanel, event);
        });
        target.addEventListener('click', () => hideFloatingPanel(target));
      }
      target.addEventListener('focus', () => showFloatingPanel(target));
      target.addEventListener('mouseleave', () => {
        if (target.dataset.cbTooltipTable) hideTimer = setTimeout(() => hideFloatingPanel(target), 150);
        else hideFloatingPanel(target);
      });
      target.addEventListener('blur', () => hideFloatingPanel(target));
    });
  }

  function init(root) {
    if (activeTarget && !activeTarget.isConnected) hideFloatingPanel();
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

      const showPanel = () => {
        panel.classList.add('provoz_tooltip_panel_visible');
        positionChartPanel(tooltip, panel);
      };
      const hidePanel = () => panel.classList.remove('provoz_tooltip_panel_visible');

      tooltip.addEventListener('mouseenter', showPanel);
      tooltip.addEventListener('focusin', showPanel);
      tooltip.addEventListener('mouseleave', hidePanel);
      tooltip.addEventListener('focusout', hidePanel);
    });
  }

  CB_TOOLTIP.init = init;
  CB_TOOLTIP.hideAll = hideAllTooltips;
  CB_TOOLTIP.positionOutsideRect = positionOutsideRect;
  CB_TOOLTIP.positionBelowRect = positionBelowRect;

  d.addEventListener('pointerdown', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target || !target.closest('[data-cb-tooltip-ready="1"], [data-tooltip="1"], .provoz_tooltip, #cb-global-tooltip')) {
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
  // Rolování velké tabulky tooltip nezavírá; posun podkladové stránky ano.
  w.addEventListener('scroll', (event) => {
    // Podrobnosti pod grafem mohou mit vlastni rolovani pri mensim okne.
    if (event.target instanceof HTMLElement && event.target.dataset.tooltipScroll === '1') return;
    if (!(floatingPanel && event.target instanceof Node && floatingPanel.contains(event.target))) hideAllTooltips();
  }, true);
  d.addEventListener('keydown', (event) => { if (event.key === 'Escape') hideAllTooltips(); });
  w.addEventListener('resize', hideAllTooltips);
  w.addEventListener('pagehide', hideAllTooltips);

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', () => init(d), { once: true });
  } else {
    init(d);
  }
})(window, document);
