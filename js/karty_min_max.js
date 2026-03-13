// js/karty_min_max.js * Verze: V1 * Aktualizace: 09.03.2026
'use strict';

(function (w) {
  const ICON_MAX = '\u2922'; // ⤢
  const ICON_MIN = '\u2212'; // −
  let activeMaxi = null;
  const CARD_ROOT_SELECTOR = '.card_shell';
  const CARD_TOGGLE_SELECTOR = '[data-card-toggle]';
  const CARD_COMPACT_SELECTOR = '[data-card-compact]';
  const CARD_EXPANDED_SELECTOR = '[data-card-expanded]';

  function getDashBox(root) {
    if (!root) return null;
    return root.closest('.dash_box') || document.querySelector('.dash_box');
  }

  function getDashCard(root) {
    if (!root) return null;
    return root.closest('[data-cb-dash-card="1"]');
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
    const roots = new Set();

    document.querySelectorAll(CARD_TOGGLE_SELECTOR).forEach((toggle) => {
      if (!(toggle instanceof HTMLElement)) return;
      const root = toggle.closest(CARD_ROOT_SELECTOR);
      if (root instanceof HTMLElement) {
        roots.add(root);
      }
    });

    return Array.from(roots);
  }

  function getLayer(dashBox) {
    if (!dashBox) return null;

    let layer = dashBox.querySelector('.dash_maxi_layer');
    if (layer) return layer;

    layer = document.createElement('div');
    layer.className = 'dash_maxi_layer';
    layer.innerHTML = '<div class="dash_maxi_stage"></div>';
    dashBox.appendChild(layer);
    return layer;
  }

  function updateToggle(toggle, isOn) {
    if (!toggle) return;
    toggle.textContent = isOn ? ICON_MIN : ICON_MAX;
    toggle.setAttribute('aria-expanded', isOn ? 'true' : 'false');
  }

  function closeActiveMaxi() {
    if (!activeMaxi) return;

    const item = activeMaxi;
    const {
      root,
      compact,
      expanded,
      toggle,
      dashCard,
      dashBox,
      layer,
      stage,
      overlayCard,
      expandedNextSibling,
      overlayToggle
    } = item;

    if (overlayToggle) {
      overlayToggle.removeEventListener('click', item.handleOverlayClose);
    }

    if (expanded && root) {
      if (expandedNextSibling && expandedNextSibling.parentNode === root) {
        root.insertBefore(expanded, expandedNextSibling);
      } else {
        root.appendChild(expanded);
      }
      expanded.classList.add('is-hidden');
    }

    if (compact) compact.classList.remove('is-hidden');
    if (toggle) updateToggle(toggle, false);
    if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
      w.cbSetBranchSelectDisabledForRoot(root, false);
    }
    if (dashCard) {
      dashCard.classList.remove('is-expanded');
      dashCard.classList.remove('is-maxi-source');
    }
    if (dashBox) dashBox.classList.remove('has-maxi');
    if (stage && overlayCard && overlayCard.parentNode === stage) {
      stage.removeChild(overlayCard);
    }
    if (layer) layer.classList.remove('is-active');

    activeMaxi = null;
  }

  function openMaxi(root, compactSel, expandedSel, toggleSel) {
    if (!root) return;

    if (activeMaxi && activeMaxi.root === root) {
      closeActiveMaxi();
      return;
    }

    closeActiveMaxi();

    const compact = root.querySelector(compactSel);
    const expanded = root.querySelector(expandedSel);
    const toggle = root.querySelector(toggleSel);
    const dashCard = getDashCard(root);
    const dashBox = getDashBox(root);
    const layer = getLayer(dashBox);
    const stage = layer ? layer.querySelector('.dash_maxi_stage') : null;

    if (!compact || !expanded || !toggle || !dashCard || !dashBox || !layer || !stage) {
      return;
    }

    const accentClasses = Array.from(dashCard.classList).filter((name) => /^card_/.test(name));
    const overlayCard = document.createElement('section');
    overlayCard.className = ['dash_maxi_card'].concat(accentClasses).join(' ');

    const head = getCardHead(root);
    const headClone = head ? head.cloneNode(true) : document.createElement('div');
    const overlayToggle = headClone.querySelector(toggleSel);

    const expandedNextSibling = expanded.nextSibling;
    const handleOverlayClose = () => {
      closeActiveMaxi();
    };

    expanded.classList.remove('is-hidden');
    compact.classList.add('is-hidden');
    dashCard.classList.add('is-expanded');
    dashCard.classList.add('is-maxi-source');
    dashBox.classList.add('has-maxi');
    updateToggle(toggle, true);
    if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
      w.cbSetBranchSelectDisabledForRoot(root, true);
    }

    if (overlayToggle) {
      updateToggle(overlayToggle, true);
      overlayToggle.addEventListener('click', handleOverlayClose);
    }

    if (headClone) {
      headClone.addEventListener('dblclick', (event) => {
        if (event.target instanceof Element && event.target.closest(toggleSel)) {
          return;
        }
        handleOverlayClose();
      });
    }

    overlayCard.appendChild(headClone);
    overlayCard.appendChild(expanded);
    stage.appendChild(overlayCard);
    layer.classList.add('is-active');

    activeMaxi = {
      root,
      compact,
      expanded,
      toggle,
      dashCard,
      dashBox,
      layer,
      stage,
      overlayCard,
      overlayToggle,
      expandedNextSibling,
      handleOverlayClose
    };
  }

  function setExpanded(root, compactSel, expandedSel, toggleSel, on) {
    if (!root) return;

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

    if (compact) compact.classList.remove('is-hidden');
    if (expanded) expanded.classList.add('is-hidden');
    if (dashCard) dashCard.classList.remove('is-expanded');
    updateToggle(toggle, false);
  }

  function initCard(root) {
    if (!root || root.getAttribute('data-card-init') === '1') return;
    root.setAttribute('data-card-init', '1');

    const toggle = root.querySelector(CARD_TOGGLE_SELECTOR);
    const head = getCardHead(root);

    if (!toggle || !head || !root.querySelector(CARD_COMPACT_SELECTOR) || !root.querySelector(CARD_EXPANDED_SELECTOR)) {
      return;
    }

    const startExpanded = root.getAttribute('data-card-start-expanded') === '1';
    setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, startExpanded);

    toggle.addEventListener('click', () => {
      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, !isExpanded);
    });

    head.addEventListener('dblclick', (event) => {
      if (event.target instanceof Element && event.target.closest(CARD_TOGGLE_SELECTOR)) {
        return;
      }
      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, !isExpanded);
    });
  }

  function forceCompact() {
    closeActiveMaxi();
    getCardRoots().forEach((root) => {
      if (!root.querySelector(CARD_TOGGLE_SELECTOR) || !root.querySelector(CARD_COMPACT_SELECTOR) || !root.querySelector(CARD_EXPANDED_SELECTOR)) {
        return;
      }
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, false);
    });
  }

  function initKartyMinMax() {
    getCardRoots().forEach(initCard);
  }

  function wireOnce() {
    if (w.__CB_KARTY_MINMAX_WIRED__) return;
    w.__CB_KARTY_MINMAX_WIRED__ = true;

    document.addEventListener('cb:main-swapped', () => {
      closeActiveMaxi();
      initKartyMinMax();
    });
    document.addEventListener('cb:menu-same-sekce', forceCompact);
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

// js/karty_min_max.js * Verze: V1 * Aktualizace: 09.03.2026
// Konec souboru
