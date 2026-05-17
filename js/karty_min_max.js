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

  function logUserCardAction(actionId, cardId, success, errMsg) {
    const idAkce = parseInt(String(actionId || '0'), 10);
    const idKarta = parseInt(String(cardId || '0'), 10);
    if (!Number.isFinite(idAkce) || idAkce <= 0 || !Number.isFinite(idKarta) || idKarta <= 0) {
      return;
    }

    const payload = {
      id_akce: idAkce,
      id_karta: idKarta,
      vysledek: success ? 1 : 0,
      err_msg: String(errMsg || '').trim()
    };

    w.fetch('index.php', {
      method: 'POST',
      headers: {
        'X-Comeback-User-Akce': '1',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).catch(() => {});
  }

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

  function closeActiveMaxi(opts) {
    if (!activeMaxi) return;

    const options = (opts && typeof opts === 'object') ? opts : {};
    const preserveState = !!options.preserveState;
    const doLogAction = options.logAction !== false;

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

    if (doLogAction) {
      const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
      logUserCardAction(2, cardId, true, '');
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
      logUserCardAction(1, parseInt(String(root.getAttribute('data-card-id') || '0'), 10), true, '');
      return;
    }

    if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
      w.CB_AJAX.setDashboardLoading(true, 'cards');
    }

    if (!(w.CB_CARD_MAX_LOADER && typeof w.CB_CARD_MAX_LOADER.load === 'function')) {
      if (w.alert) {
        w.alert('Nacteni max karty neni pripravene.');
      }
      if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
        w.CB_AJAX.setDashboardLoading(false, 'cards');
      }
      return;
    }

    w.CB_CARD_MAX_LOADER.load(root, {
      expandedSelector: CARD_EXPANDED_SELECTOR
    }).then((nextRoot) => {
      initCard(nextRoot);
      finishOpenMaxi(nextRoot, compactSel, expandedSel, toggleSel);
      logUserCardAction(1, parseInt(String(nextRoot.getAttribute('data-card-id') || '0'), 10), true, '');
    }).catch(() => {
      logUserCardAction(1, parseInt(String(root.getAttribute('data-card-id') || '0'), 10), false, 'Otevreni max karty selhalo');
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
      closeActiveMaxi({ preserveState: false, logAction: false });
    });

    document.addEventListener('cb:main-swapped', () => {
      if (suppressNextRestore) {
        closeActiveMaxi({ preserveState: false, logAction: false });
      } else {
        closeActiveMaxi({ preserveState: true, logAction: false });
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
        closeActiveMaxi({ logAction: true });
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
