// js/tooltip_pozice.js * Jednoucelove pozicovani tooltipu
'use strict';

(function () {
  const ROOT_SELECTOR = '[data-cb-tooltip-position="1"]';
  const PANEL_SELECTOR = '[data-cb-tooltip-panel="1"]';
  const BOUNDARY_SELECTOR = '[data-cb-tooltip-boundary="1"]';
  const GAP = 8;
  const EXTRA_RIGHT_OFFSET = 15;
  let activeRoot = null;
  const panelState = new WeakMap();

  function clamp(value, min, max) {
    return Math.max(min, Math.min(value, max));
  }

  function hide(root) {
    const target = root || activeRoot;
    if (!target) return;

    const state = panelState.get(target) || null;
    const panel = state && state.panel instanceof HTMLElement
      ? state.panel
      : target.querySelector(PANEL_SELECTOR);
    if (panel instanceof HTMLElement) {
      panel.classList.remove('is-visible');
      panel.style.left = '';
      panel.style.top = '';
      if (state && state.parent instanceof Node) {
        state.parent.insertBefore(panel, state.nextSibling instanceof Node ? state.nextSibling : null);
      }
    }
    panelState.delete(target);

    if (activeRoot === target) {
      activeRoot = null;
    }
  }

  function getPanel(root) {
    const state = panelState.get(root) || null;
    if (state && state.panel instanceof HTMLElement) {
      return state.panel;
    }
    return root.querySelector(PANEL_SELECTOR);
  }

  function movePanelToBody(root, panel) {
    if (panelState.has(root)) return;

    panelState.set(root, {
      panel: panel,
      parent: panel.parentNode,
      nextSibling: panel.nextSibling
    });
    document.body.appendChild(panel);
  }

  function place(root) {
    const panel = getPanel(root);
    if (!(panel instanceof HTMLElement)) return;

    const anchorRect = root.getBoundingClientRect();
    const boundary = root.closest(BOUNDARY_SELECTOR);
    const boundaryRect = boundary instanceof HTMLElement ? boundary.getBoundingClientRect() : anchorRect;

    movePanelToBody(root, panel);
    panel.classList.add('is-visible');
    activeRoot = root;

    const panelRect = panel.getBoundingClientRect();
    const panelWidth = panelRect.width || 0;
    const panelHeight = panelRect.height || 0;
    const viewWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    const viewHeight = window.innerHeight || document.documentElement.clientHeight || 0;

    let left = anchorRect.right + GAP;
    let top = anchorRect.bottom + GAP;

    if (left + panelWidth + GAP > viewWidth) {
      left = boundaryRect.left - panelWidth - GAP;
    }
    left += EXTRA_RIGHT_OFFSET;
    if (top + panelHeight + GAP > viewHeight) {
      top = anchorRect.top - panelHeight - GAP;
    }

    panel.style.left = clamp(left, GAP, Math.max(GAP, viewWidth - panelWidth - GAP)) + 'px';
    panel.style.top = clamp(top, GAP, Math.max(GAP, viewHeight - panelHeight - GAP)) + 'px';
  }

  function show(root) {
    if (activeRoot && activeRoot !== root) {
      hide(activeRoot);
    }
    place(root);
  }

  document.addEventListener('mouseenter', (event) => {
    const root = event.target instanceof Element ? event.target.closest(ROOT_SELECTOR) : null;
    if (root instanceof HTMLElement) {
      show(root);
    }
  }, true);

  document.addEventListener('mouseleave', (event) => {
    const root = event.target instanceof Element ? event.target.closest(ROOT_SELECTOR) : null;
    if (root instanceof HTMLElement && !root.contains(event.relatedTarget)) {
      hide(root);
    }
  }, true);

  document.addEventListener('focusin', (event) => {
    const root = event.target instanceof Element ? event.target.closest(ROOT_SELECTOR) : null;
    if (root instanceof HTMLElement) {
      show(root);
    }
  });

  document.addEventListener('focusout', (event) => {
    const root = event.target instanceof Element ? event.target.closest(ROOT_SELECTOR) : null;
    if (root instanceof HTMLElement && !root.contains(event.relatedTarget)) {
      hide(root);
    }
  });

  window.addEventListener('resize', () => {
    if (activeRoot) {
      place(activeRoot);
    }
  });
}());
