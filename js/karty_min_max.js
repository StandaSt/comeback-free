// js/karty_min_max.js * Verze: V4 * Aktualizace: 15.04.2026
'use strict';

(function (w) {
  const ICON_MAX = '\u2922';
  const ICON_MIN = '\u2212';
  const MAXI_STATE_KEY = 'cb_maxi_state_v1';
  let activeMaxi = null;

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

    if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
      w.cbSetBranchSelectDisabledForRoot(root, false);
    }

    if (dashCard) {
      dashCard.classList.remove('is-expanded');
      dashCard.classList.remove('is-maxi-overlay');
      dashCard.style.top = '';
    }

    if (dashBox) {
      dashBox.classList.remove('has-maxi');
    }

    activeMaxi = null;

    if (!preserveState) {
      clearMaxiState();
    }
  }

  function openMaxi(root, compactSel, expandedSel, toggleSel) {
    if (!(root instanceof HTMLElement)) return;

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

    if (!(compact instanceof HTMLElement) || !(expanded instanceof HTMLElement) || !(toggle instanceof HTMLElement) || !(dashCard instanceof HTMLElement)) {
      return;
    }

    const overlayTop = (dashBox instanceof HTMLElement) ? dashBox.scrollTop : 0;

    updateSubtitle(root, true);
    expanded.classList.remove('is-hidden');
    compact.classList.add('is-hidden');
    dashCard.classList.add('is-expanded');
    dashCard.classList.add('is-maxi-overlay');
    dashCard.style.top = String(overlayTop) + 'px';

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
      dashBox
    };

    saveMaxiState(String(root.getAttribute('data-card-id') || ''));
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
    if (dashCard instanceof HTMLElement) dashCard.classList.remove('is-expanded');
    updateToggle(toggle, false);
    updateSubtitle(root, false);
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

    const forms = root.querySelectorAll('form');
    forms.forEach((formEl) => {
      if (!(formEl instanceof HTMLFormElement)) return;
      formEl.addEventListener('submit', () => {
        if (activeMaxi && activeMaxi.root === root) {
          closeActiveMaxi({ preserveState: true });
        }
      });
    });
  }

  function initKartyMinMax() {
    const roots = getCardRoots();
    roots.forEach(initCard);

    const state = loadMaxiState();
    if (!state) return;

    const currentLoginId = getCurrentLoginId();
    if (state.loginId !== currentLoginId) {
      clearMaxiState();
      return;
    }

    const root = roots.find((r) => String(r.getAttribute('data-card-id') || '') === state.cardId && String(r.getAttribute('data-card-mode') || '') !== 'nano');
    if (!root) {
      clearMaxiState();
      return;
    }

    openMaxi(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR);
  }

  function wireOnce() {
    if (w.__CB_KARTY_MINMAX_WIRED__) return;
    w.__CB_KARTY_MINMAX_WIRED__ = true;

    document.addEventListener('cb:main-swapped', () => {
      closeActiveMaxi({ preserveState: true });
      initKartyMinMax();
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

// js/karty_min_max.js * Verze: V4 * Aktualizace: 15.04.2026
// Konec souboru
