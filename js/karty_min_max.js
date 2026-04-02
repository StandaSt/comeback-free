// js/karty_min_max.js * Verze: V2 * Aktualizace: 25.03.2026
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
  const CARD_TO_NANO_SELECTOR = '[data-card-to-nano]';
  const CARD_NANO_TARGET_SELECTOR = '[data-card-nano-target]';

  function getCurrentLoginId() {
    const grid = document.querySelector('.dash_grid[data-login-id]');
    if (!(grid instanceof HTMLElement)) return '0';
    return String(grid.getAttribute('data-login-id') || '0').trim() || '0';
  }

  function clearMaxiState() {
    try { w.sessionStorage.removeItem(MAXI_STATE_KEY); } catch (e) {}
  }

  function saveMaxiState(cardId, returnMode) {
    const cid = String(cardId || '').trim();
    if (cid === '') return;
    const mode = (returnMode === 'nano') ? 'nano' : 'mini';
    const payload = {
      cardId: cid,
      returnMode: mode,
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
      const returnMode = (parsed.returnMode === 'nano') ? 'nano' : 'mini';
      const loginId = String(parsed.loginId || '0').trim() || '0';
      return { cardId, returnMode, loginId };
    } catch (e) {
      return null;
    }
  }

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
    return Array.from(document.querySelectorAll(CARD_ROOT_SELECTOR)).filter((el) => el instanceof HTMLElement);
  }

  function getLayer(dashBox) {
    if (!dashBox) return null;

    let layer = dashBox.querySelector('.dash_maxi_layer');
    if (layer) return layer;

    layer = document.createElement('div');
    layer.className = 'dash_maxi_layer odstup_vnitrni_10';
    layer.innerHTML = '<div class="dash_maxi_stage"></div>';
    dashBox.appendChild(layer);
    return layer;
  }

  function updateToggle(toggle, isOn) {
    if (!toggle) return;
    toggle.textContent = isOn ? ICON_MIN : ICON_MAX;
    toggle.setAttribute('aria-expanded', isOn ? 'true' : 'false');
  }

  function updateSubtitle(root, isExpanded) {
    if (!root) return;
    const subtitle = root.querySelector('[data-card-subtitle]');
    if (!(subtitle instanceof HTMLElement)) return;

    const minText = String(subtitle.getAttribute('data-subtitle-min') || '');
    const maxText = String(subtitle.getAttribute('data-subtitle-max') || '');
    subtitle.textContent = isExpanded ? maxText : minText;
  }

  function requestCardMode(cardId, mode) {
    return fetch('index.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Set-Card-Mode': '1'
      },
      body: JSON.stringify({ id_karta: cardId, mode: mode })
    }).then((r) => r.json().catch(() => ({})));
  }

  function reloadAfterNanoSwitch(root, pendingMode) {
    const dashCard = getDashCard(root);
    if (dashCard) {
      dashCard.classList.add('is-nano-switching');
    }
    if (pendingMode === 'maxi') {
      const cardId = String(root.getAttribute('data-card-id') || '').trim();
      if (cardId !== '') {
        saveMaxiState(cardId, 'nano');
      }
    }
    w.setTimeout(() => { w.location.reload(); }, 180);
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
      overlayToggle,
      overlayNanoBtn,
      returnMode
    } = item;

    const targetReturnMode = (returnMode === 'nano') ? 'nano' : 'mini';

    if (overlayToggle) {
      overlayToggle.removeEventListener('click', item.handleOverlayToggle);
    }
    if (overlayNanoBtn) {
      overlayNanoBtn.removeEventListener('click', item.handleOverlayNano);
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
    updateSubtitle(root, false);
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
    clearMaxiState();

    if (targetReturnMode === 'nano') {
      const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
      if (cardId > 0) {
        requestCardMode(cardId, 'nano').then(() => {
          reloadAfterNanoSwitch(root, 'mini');
        }).catch(() => {});
      }
    }
  }

  function openMaxi(root, compactSel, expandedSel, toggleSel, preferredReturnMode) {
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

    const sourceMode = String(root.getAttribute('data-card-mode') || 'mini').trim();
    const normalizedReturnMode = (preferredReturnMode === 'nano' || preferredReturnMode === 'mini')
      ? preferredReturnMode
      : (sourceMode === 'nano' ? 'nano' : 'mini');

    const accentClasses = Array.from(dashCard.classList).filter((name) => /^card_/.test(name));
    const overlayCard = document.createElement('section');
    overlayCard.className = ['dash_maxi_card'].concat(accentClasses).join(' ');
    const sourceRect = dashCard.getBoundingClientRect();
    if (sourceRect.width > 0) {
      overlayCard.style.minWidth = sourceRect.width + 'px';
    }
    if (sourceRect.height > 0) {
      overlayCard.style.minHeight = sourceRect.height + 'px';
    }

    updateSubtitle(root, true);
    const head = getCardHead(root);
    const headClone = head ? head.cloneNode(true) : document.createElement('div');
    const overlayToggle = headClone.querySelector(toggleSel);
    const overlayNanoBtn = headClone.querySelector(CARD_TO_NANO_SELECTOR);

    const expandedNextSibling = expanded.nextSibling;
    const handleOverlayClose = () => {
      closeActiveMaxi();
    };
    const handleOverlayToggle = () => {
      if (activeMaxi && activeMaxi.root === root) {
        activeMaxi.returnMode = 'mini';
      }
      closeActiveMaxi();
    };
    const handleOverlayNano = () => {
      if (activeMaxi && activeMaxi.root === root) {
        activeMaxi.returnMode = 'nano';
      }
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
      overlayToggle.addEventListener('click', handleOverlayToggle);
    }
    if (overlayNanoBtn) {
      overlayNanoBtn.addEventListener('click', handleOverlayNano);
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
      overlayNanoBtn,
      expandedNextSibling,
      handleOverlayClose,
      handleOverlayToggle,
      handleOverlayNano,
      returnMode: normalizedReturnMode
    };
    saveMaxiState(String(root.getAttribute('data-card-id') || ''), normalizedReturnMode);
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
    updateSubtitle(root, false);
  }

  function initCard(root) {
    if (!root || root.getAttribute('data-card-init') === '1') return;
    root.setAttribute('data-card-init', '1');

    const mode = String(root.getAttribute('data-card-mode') || 'mini').trim();
    const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
    const compact = root.querySelector(CARD_COMPACT_SELECTOR);
    const expanded = root.querySelector(CARD_EXPANDED_SELECTOR);

    if (mode === 'nano') {
      if (compact) compact.classList.add('is-hidden');
      if (expanded) expanded.classList.add('is-hidden');

      const nanoHead = getCardHead(root);
      if (nanoHead) {
        nanoHead.addEventListener('dblclick', () => {
          if (!Number.isFinite(cardId) || cardId <= 0) return;
          requestCardMode(cardId, 'maxi').then(() => {
            reloadAfterNanoSwitch(root, 'maxi');
          }).catch(() => {});
        });
      }

      const nanoTargets = root.querySelectorAll(CARD_NANO_TARGET_SELECTOR);
      nanoTargets.forEach((btn) => {
        btn.addEventListener('click', () => {
          if (!Number.isFinite(cardId) || cardId <= 0) return;
          const target = String(btn.getAttribute('data-card-nano-target') || 'mini').trim();
          if (!['mini', 'maxi'].includes(target)) return;
          requestCardMode(cardId, target).then(() => {
            reloadAfterNanoSwitch(root, target);
          }).catch(() => {});
        });
      });
      return;
    }

    const toggle = root.querySelector(CARD_TOGGLE_SELECTOR);
    const head = getCardHead(root);

    if (!toggle || !head || !compact || !expanded) {
      return;
    }

    const startExpanded = root.getAttribute('data-card-start-expanded') === '1';
    setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, startExpanded);

    toggle.addEventListener('click', () => {
      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, !isExpanded);
    });

    const nanoBtn = root.querySelector(CARD_TO_NANO_SELECTOR);
    if (nanoBtn) {
      nanoBtn.addEventListener('click', () => {
        if (!Number.isFinite(cardId) || cardId <= 0) return;
        requestCardMode(cardId, 'nano').then(() => {
          reloadAfterNanoSwitch(root, 'mini');
        }).catch(() => {});
      });
    }

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
    const roots = getCardRoots();
    roots.forEach(initCard);

    const state = loadMaxiState();
    if (state) {
      const currentLoginId = getCurrentLoginId();
      if (state.loginId !== currentLoginId) {
        clearMaxiState();
        return;
      }
      const root = roots.find((r) => String(r.getAttribute('data-card-id') || '') === state.cardId && String(r.getAttribute('data-card-mode') || '') !== 'nano');
      if (root) {
        openMaxi(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, state.returnMode);
      } else {
        clearMaxiState();
      }
    }
  }

  function wireOnce() {
    if (w.__CB_KARTY_MINMAX_WIRED__) return;
    w.__CB_KARTY_MINMAX_WIRED__ = true;

    document.addEventListener('cb:main-swapped', () => {
      closeActiveMaxi();
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

// js/karty_min_max.js * Verze: V2 * Aktualizace: 25.03.2026
// Konec souboru
