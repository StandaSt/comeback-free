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
  const MAX_NANO_CARDS = 9;

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
    if (!toggle) return;
    toggle.textContent = isOn ? ICON_MIN : ICON_MAX;
    toggle.setAttribute('aria-expanded', isOn ? 'true' : 'false');
    toggle.setAttribute('title', isOn ? 'Přepnout do mini' : 'Přepnout do max');
  }

  function updateSubtitle(root, isExpanded) {
    if (!root) return;
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

  function requestCardMode(cardId, mode) {
    return fetch('index.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Set-Card-Mode': '1'
      },
      body: JSON.stringify({ id_karta: cardId, mode: mode })
    }).then((r) => r.json().catch(() => ({})).then((data) => {
      if (r.ok && data && data.ok) {
        return data;
      }
      const err = String((data && data.err) ? data.err : 'Uložení režimu karty selhalo').trim();
      throw new Error(err !== '' ? err : 'Uložení režimu karty selhalo');
    }));
  }

  function getNanoCardCount() {
    return document.querySelectorAll('.card_shell[data-card-mode="nano"]').length;
  }

  function setDashboardLoading(on) {
    if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
        w.CB_AJAX.setDashboardLoading(!!on, 'cards');
    }
  }

  function traceAjax(event, data) {
    if (w.CB_AJAX && typeof w.CB_AJAX.trace === 'function') {
      w.CB_AJAX.trace(event, data);
    }
  }

  function canSwitchToNano(cardId) {
    const cid = parseInt(String(cardId || '0'), 10);
    if (!Number.isFinite(cid) || cid <= 0) return false;
    const alreadyNano = document.querySelector('.card_shell[data-card-id="' + String(cid) + '"][data-card-mode="nano"]');
    if (alreadyNano) return true;
    return getNanoCardCount() < MAX_NANO_CARDS;
  }

  function getCardModeModal() {
    const root = document.getElementById('cbCardModeModal');
    if (!(root instanceof HTMLElement)) return null;
    const msg = root.querySelector('[data-cb-cardmode-msg]');
    const closeBtn = root.querySelector('[data-cb-cardmode-close]');
    const confirmBtn = root.querySelector('[data-cb-cardmode-confirm]');
    if (!(msg instanceof HTMLElement) || !(closeBtn instanceof HTMLElement)) return null;
    return {
      root,
      msg,
      closeBtn,
      confirmBtn: (confirmBtn instanceof HTMLElement) ? confirmBtn : null
    };
  }

  function closeCardModeModal() {
    const modal = getCardModeModal();
    if (!modal) return false;
    modal.root.classList.add('is-hidden');
    modal.root.setAttribute('aria-hidden', 'true');
    modal.msg.textContent = '';
    modal.closeBtn.textContent = 'Rozumím';
    if (modal.confirmBtn) {
      modal.confirmBtn.classList.add('is-hidden');
      modal.confirmBtn.textContent = 'Potvrdit';
    }
    return true;
  }

  function openCardModeModal(message) {
    const text = String(message || '').trim();
    if (text === '') return false;
    const modal = getCardModeModal();
    if (!modal) return false;
    modal.msg.textContent = text;
    modal.closeBtn.textContent = 'Rozumím';
    if (modal.confirmBtn) {
      modal.confirmBtn.classList.add('is-hidden');
      modal.confirmBtn.textContent = 'Potvrdit';
    }
    modal.root.classList.remove('is-hidden');
    modal.root.setAttribute('aria-hidden', 'false');
    modal.closeBtn.focus();
    return true;
  }

  function showNanoLimitAlert() {
    // TODO: Tuto akci (pokus o 10. nano kartu) budeme logovat v logování akcí uživatele.
    const msg = 'Nano režim je omezen na 9 karet.\nDesátou kartu nelze přidat.';
    if (!openCardModeModal(msg)) {
      w.alert(msg);
    }
  }

  function showCardModeError(err) {
    const msg = (err && typeof err.message === 'string') ? err.message.trim() : '';
    if (msg !== '') {
      if (!openCardModeModal(msg)) {
        w.alert(msg);
      }
    }
  }

  function reloadAfterNanoSwitch(root, pendingMode) {
    const dashCard = getDashCard(root);
    if (dashCard) {
      dashCard.classList.add('is-nano-switching');
    }
    traceAjax('nano_switch_reload', {
      card_id: String(root.getAttribute('data-card-id') || ''),
      pendingMode: String(pendingMode || ''),
      sourceMode: String(root.getAttribute('data-card-mode') || '')
    });
    if (pendingMode === 'maxi') {
      const cardId = String(root.getAttribute('data-card-id') || '').trim();
      if (cardId !== '') {
        saveMaxiState(cardId, 'nano');
      }
    }
    setDashboardLoading(true);
    if (w.CB_AJAX && typeof w.CB_AJAX.refreshDashboard === 'function') {
      w.CB_AJAX.refreshDashboard({ force: true, loaderMode: 'cards' }).catch(() => {
        window.alert('Obnovení dashboardu po přepnutí nano režimu selhalo.');
      });
    }
  }

  function closeActiveMaxi(opts) {
    if (!activeMaxi) return;
    const options = (opts && typeof opts === 'object') ? opts : {};
    const forceMini = !!options.forceMini;
    const preserveState = !!options.preserveState;

    const item = activeMaxi;
    const {
      root,
      compact,
      expanded,
      toggle,
      dashCard,
      dashBox,
      returnMode
    } = item;

    const targetReturnMode = forceMini ? 'mini' : ((returnMode === 'nano') ? 'nano' : 'mini');

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

    if (targetReturnMode === 'nano') {
      const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
      if (cardId > 0) {
        if (!canSwitchToNano(cardId)) {
          showNanoLimitAlert();
          return;
        }
        setDashboardLoading(true);
        requestCardMode(cardId, 'nano').then(() => {
          reloadAfterNanoSwitch(root, 'mini');
        }).catch((err) => {
          setDashboardLoading(false);
          showCardModeError(err);
        });
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

    if (!compact || !expanded || !toggle || !dashCard) {
      return;
    }

    const sourceMode = String(root.getAttribute('data-card-mode') || 'mini').trim();
    const normalizedReturnMode = (preferredReturnMode === 'nano' || preferredReturnMode === 'mini')
      ? preferredReturnMode
      : (sourceMode === 'nano' ? 'nano' : 'mini');
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
      dashBox,
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
        makeHeadInteractive(nanoHead);
        nanoHead.addEventListener('mousedown', blockHeadSelection);
        nanoHead.addEventListener('dblclick', () => {
          if (!Number.isFinite(cardId) || cardId <= 0) return;
          clearSelection();
          traceAjax('nano_to_maxi_click', {
            card_id: cardId
          });
          setDashboardLoading(true);
          requestCardMode(cardId, 'maxi').then(() => {
            traceAjax('nano_to_maxi_ok', {
              card_id: cardId
            });
            reloadAfterNanoSwitch(root, 'maxi');
          }).catch(() => {
            traceAjax('nano_to_maxi_error', {
              card_id: cardId
            });
            setDashboardLoading(false);
          });
        });
      }

      const nanoTargets = root.querySelectorAll(CARD_NANO_TARGET_SELECTOR);
      nanoTargets.forEach((btn) => {
        btn.addEventListener('click', () => {
          if (!Number.isFinite(cardId) || cardId <= 0) return;
          const target = String(btn.getAttribute('data-card-nano-target') || 'mini').trim();
          if (!['mini', 'maxi'].includes(target)) return;
          traceAjax('nano_target_click', {
            card_id: cardId,
            target: target
          });
          setDashboardLoading(true);
          requestCardMode(cardId, target).then(() => {
            traceAjax('nano_target_ok', {
              card_id: cardId,
              target: target
            });
            reloadAfterNanoSwitch(root, target);
          }).catch(() => {
            traceAjax('nano_target_error', {
              card_id: cardId,
              target: target
            });
            setDashboardLoading(false);
          });
        });
      });
      return;
    }

    const toggle = root.querySelector(CARD_TOGGLE_SELECTOR);
    const head = getCardHead(root);

    if (!toggle || !head || !compact || !expanded) {
      return;
    }

    makeHeadInteractive(head);

    setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, false);

    toggle.addEventListener('click', () => {
      const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
      if (isExpanded && activeMaxi && activeMaxi.root === root) {
        closeActiveMaxi({ forceMini: true });
        return;
      }
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, CARD_TOGGLE_SELECTOR, !isExpanded);
    });

    const nanoBtn = root.querySelector(CARD_TO_NANO_SELECTOR);
    if (nanoBtn) {
      nanoBtn.addEventListener('click', () => {
        if (!Number.isFinite(cardId) || cardId <= 0) return;
        if (!canSwitchToNano(cardId)) {
          showNanoLimitAlert();
          return;
        }
        traceAjax('mini_to_nano_click', {
          card_id: cardId
        });
        setDashboardLoading(true);
        requestCardMode(cardId, 'nano').then(() => {
          traceAjax('mini_to_nano_ok', {
            card_id: cardId
          });
          reloadAfterNanoSwitch(root, 'mini');
        }).catch((err) => {
          traceAjax('mini_to_nano_error', {
            card_id: cardId,
            message: String((err && err.message) ? err.message : '')
          });
          setDashboardLoading(false);
          showCardModeError(err);
        });
      });
    }

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

    document.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) return;
      if (target.closest('[data-cb-cardmode-close]')) {
        closeCardModeModal();
      }
    });

    document.addEventListener('cb:main-swapped', () => {
      closeActiveMaxi({ preserveState: true });
      initKartyMinMax();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        const modal = getCardModeModal();
        if (modal && !modal.root.classList.contains('is-hidden')) {
          return;
        }
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
