// js/karty_min_nano.js * Verze: V4 * Aktualizace: 15.04.2026
'use strict';

(function (w) {
  const CARD_ROOT_SELECTOR = '.card_shell';
  const CARD_TO_NANO_SELECTOR = '[data-card-to-nano]';
  const CARD_NANO_TARGET_SELECTOR = '[data-card-nano-target]';
  const CARD_COMPACT_SELECTOR = '[data-card-compact]';
  const CARD_EXPANDED_SELECTOR = '[data-card-expanded]';
  const MAX_NANO_CARDS = 9;

  function getCardRoots() {
    return Array.from(document.querySelectorAll(CARD_ROOT_SELECTOR)).filter((el) => el instanceof HTMLElement);
  }

  function getDashGrid() {
    const grid = document.querySelector('.dash_grid[data-login-id]');
    return grid instanceof HTMLElement ? grid : null;
  }

  function getDashCols(grid) {
    if (!(grid instanceof HTMLElement)) return 3;
    if (grid.classList.contains('dash_cols_5')) return 5;
    if (grid.classList.contains('dash_cols_4')) return 4;
    return 3;
  }

  function getCardSectionFromRoot(root) {
    if (!(root instanceof HTMLElement)) return null;
    const card = root.closest('[data-cb-dash-card="1"]');
    return card instanceof HTMLElement ? card : null;
  }

  function getCardRootFromSection(section) {
    if (!(section instanceof HTMLElement)) return null;
    const root = section.querySelector('.card_shell');
    return root instanceof HTMLElement ? root : null;
  }

  function getCardModeFromSection(section) {
    const root = getCardRootFromSection(section);
    if (!(root instanceof HTMLElement)) return 'mini';
    return String(root.getAttribute('data-card-mode') || 'mini').trim() || 'mini';
  }

  function getCardOrderFromSection(section) {
    const root = getCardRootFromSection(section);
    if (!(root instanceof HTMLElement)) return 999999;
    const value = parseInt(String(root.getAttribute('data-card-poradi') || '0'), 10);
    return Number.isFinite(value) && value > 0 ? value : 999999;
  }

  function getCardIdFromSection(section) {
    const root = getCardRootFromSection(section);
    if (!(root instanceof HTMLElement)) return 0;
    const value = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
    return Number.isFinite(value) && value > 0 ? value : 0;
  }

  function readPositiveIntAttr(el, name) {
    if (!(el instanceof HTMLElement)) return 0;
    const value = parseInt(String(el.getAttribute(name) || '0'), 10);
    return Number.isFinite(value) && value > 0 ? value : 0;
  }

  function getCardSlot(section, cols) {
    const root = getCardRootFromSection(section);
    if (!(root instanceof HTMLElement)) return 0;
    const col = readPositiveIntAttr(root, 'data-card-col');
    const line = readPositiveIntAttr(root, 'data-card-line');
    if (col <= 0 || line <= 0) return 0;
    return (((line - 1) * cols) + col);
  }

  function setCardPlacement(section, col, line) {
    if (!(section instanceof HTMLElement)) return;
    const root = getCardRootFromSection(section);
    if (!(root instanceof HTMLElement)) return;

    const safeCol = (Number.isFinite(col) && col > 0) ? Math.trunc(col) : 0;
    const safeLine = (Number.isFinite(line) && line > 0) ? Math.trunc(line) : 0;

    if (safeCol > 0 && safeLine > 0) {
      section.style.gridColumn = String(safeCol);
      section.style.gridRow = String(safeLine);
      root.setAttribute('data-card-col', String(safeCol));
      root.setAttribute('data-card-line', String(safeLine));
    } else {
      section.style.gridColumn = '';
      section.style.gridRow = '';
      root.setAttribute('data-card-col', '0');
      root.setAttribute('data-card-line', '0');
    }
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

  function setDashboardLoading(on, text) {
    if (w.CB_AJAX && typeof w.CB_AJAX.setDashboardLoading === 'function') {
      w.CB_AJAX.setDashboardLoading(!!on, 'cards', text);
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

  function createBreakNode() {
    const el = document.createElement('div');
    el.className = 'dash_break odstup_vnejsi_0 odstup_vnitrni_0';
    el.setAttribute('aria-hidden', 'true');
    return el;
  }

  function collectGridSections(grid) {
    const sections = [];
    Array.from(grid.children).forEach((child) => {
      if (!(child instanceof HTMLElement)) return;

      if (child.classList.contains('dash_nano_group')) {
        Array.from(child.children).forEach((nested) => {
          if (nested instanceof HTMLElement && nested.matches('[data-cb-dash-card="1"]')) {
            sections.push(nested);
          }
        });
        return;
      }

      if (child.matches('[data-cb-dash-card="1"]')) {
        sections.push(child);
      }
    });
    return sections;
  }

  function relayoutMiniCards(miniCards, startSlot, cols) {
    const lockedBySlot = new Map();
    const unlocked = [];

    miniCards.forEach((section, index) => {
      const root = getCardRootFromSection(section);
      const isLocked = root instanceof HTMLElement && String(root.getAttribute('data-card-pos-locked') || '0') === '1';
      const slot = getCardSlot(section, cols);

      if (isLocked && slot > 0 && !lockedBySlot.has(slot)) {
        lockedBySlot.set(slot, section);
        return;
      }

      unlocked.push({
        section,
        index,
        poradi: getCardOrderFromSection(section),
        cardId: getCardIdFromSection(section)
      });
    });

    unlocked.sort((a, b) => {
      if (a.poradi !== b.poradi) {
        return a.poradi - b.poradi;
      }
      if (a.cardId !== b.cardId) {
        return a.cardId - b.cardId;
      }
      return a.index - b.index;
    });

    const total = miniCards.length;
    const placed = [];
    let nextUnlocked = 0;
    let slot = startSlot > 0 ? startSlot : 1;

    while (placed.length < total) {
      if (lockedBySlot.has(slot)) {
        placed.push(lockedBySlot.get(slot));
        slot++;
        continue;
      }

      if (nextUnlocked < unlocked.length) {
        placed.push(unlocked[nextUnlocked].section);
        nextUnlocked++;
        slot++;
        continue;
      }

      slot++;
    }

    placed.forEach((section, index) => {
      const targetSlot = startSlot + index;
      const col = ((targetSlot - 1) % cols) + 1;
      const line = Math.floor((targetSlot - 1) / cols) + 1;
      setCardPlacement(section, col, line);
    });

    return placed;
  }

  function rebuildGridAfterModeSwitch() {
    const grid = getDashGrid();
    if (!(grid instanceof HTMLElement)) return;

    const cols = getDashCols(grid);
    const allSections = collectGridSections(grid);
    const nanoCards = [];
    const miniCards = [];

    allSections.forEach((section) => {
      if (getCardModeFromSection(section) === 'nano') {
        nanoCards.push(section);
      } else {
        miniCards.push(section);
      }
    });

    nanoCards.forEach((section) => {
      setCardPlacement(section, 0, 0);
    });

    const nodes = [];
    if (grid.classList.contains('dash_nano_kde_1')) {
      if (nanoCards.length > 0) {
        const group = document.createElement('div');
        group.className = 'dash_nano_group';
        nanoCards.forEach((section) => {
          group.appendChild(section);
        });
        nodes.push(group);
      }

      const placedMini = relayoutMiniCards(miniCards, nanoCards.length > 0 ? 2 : 1, cols);
      placedMini.forEach((section) => {
        nodes.push(section);
      });

      grid.replaceChildren(...nodes);
      return;
    }

    nanoCards.forEach((section) => {
      nodes.push(section);
    });

    const placedMini = relayoutMiniCards(miniCards, nanoCards.length + 1, cols);

    if (nanoCards.length > 0 && placedMini.length > 0) {
      nodes.push(createBreakNode());
    }

    placedMini.forEach((section) => {
      nodes.push(section);
    });

    grid.replaceChildren(...nodes);
  }

  function refreshOnlyCurrentCard(cardId) {
    if (!(w.CB_AJAX && typeof w.CB_AJAX.refreshCard === 'function')) {
      return Promise.reject(new Error('Obnovení jedné karty není dostupné.'));
    }

    return w.CB_AJAX.refreshCard(cardId, {
      force: true,
      keepLoading: true,
      loaderMode: 'cards'
    }).then((result) => {
      if (w.CB_AJAX && typeof w.CB_AJAX.relayoutDashboard === 'function') {
        w.CB_AJAX.relayoutDashboard();
      } else {
        rebuildGridAfterModeSwitch();
        document.dispatchEvent(new CustomEvent('cb:dashboard-layout-changed'));
      }
      return result;
    });
  }

  function relayoutDashboard() {
    rebuildGridAfterModeSwitch();
    document.dispatchEvent(new CustomEvent('cb:dashboard-layout-changed'));
    return { ok: true };
  }

  function finishModeSwitch() {
    setDashboardLoading(false);
  }

  function handleModeSwitch(cardId, targetMode, tracePrefix) {
    setDashboardLoading(true, 'Přesouvám kartu ...');

    requestCardMode(cardId, targetMode).then(() => {
      traceAjax(tracePrefix + '_mode_saved', {
        card_id: cardId
      });
      return refreshOnlyCurrentCard(cardId);
    }).then(() => {
      traceAjax(tracePrefix + '_ok', {
        card_id: cardId
      });
      finishModeSwitch();
    }).catch((err) => {
      traceAjax(tracePrefix + '_error', {
        card_id: cardId,
        message: String((err && err.message) ? err.message : '')
      });
      finishModeSwitch();
      showCardModeError(err);
    });
  }

  function initMiniCard(root) {
    if (!(root instanceof HTMLElement) || root.getAttribute('data-card-init-nano') === '1') return;
    if (String(root.getAttribute('data-card-mode') || 'mini').trim() === 'nano') return;

    root.setAttribute('data-card-init-nano', '1');

    const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
    const nanoBtn = root.querySelector(CARD_TO_NANO_SELECTOR);

    if (!(nanoBtn instanceof HTMLElement)) {
      return;
    }

    nanoBtn.addEventListener('click', () => {
      if (!Number.isFinite(cardId) || cardId <= 0) return;

      if (!canSwitchToNano(cardId)) {
        showNanoLimitAlert();
        return;
      }

      traceAjax('mini_to_nano_click', {
        card_id: cardId
      });

      handleModeSwitch(cardId, 'nano', 'mini_to_nano');
    });
  }

  function initNanoCard(root) {
    if (!(root instanceof HTMLElement) || root.getAttribute('data-card-init-nano') === '1') return;
    if (String(root.getAttribute('data-card-mode') || 'mini').trim() !== 'nano') return;

    root.setAttribute('data-card-init-nano', '1');

    const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
    const compact = root.querySelector(CARD_COMPACT_SELECTOR);
    const expanded = root.querySelector(CARD_EXPANDED_SELECTOR);

    if (compact instanceof HTMLElement) compact.classList.add('is-hidden');
    if (expanded instanceof HTMLElement) expanded.classList.add('is-hidden');

    const nanoTargets = root.querySelectorAll(CARD_NANO_TARGET_SELECTOR);

    nanoTargets.forEach((btn) => {
      btn.addEventListener('click', () => {
        if (!Number.isFinite(cardId) || cardId <= 0) return;

        const target = String(btn.getAttribute('data-card-nano-target') || 'mini').trim();
        if (target !== 'mini') return;

        traceAjax('nano_to_mini_click', {
          card_id: cardId
        });

        handleModeSwitch(cardId, 'mini', 'nano_to_mini');
      });
    });
  }

  function initKartyMinNano() {
    const roots = getCardRoots();
    roots.forEach((root) => {
      initCardRoot(root);
    });
  }

  function initCardRoot(root) {
    if (!(root instanceof HTMLElement)) return;
    if (String(root.getAttribute('data-card-mode') || 'mini').trim() === 'nano') {
      initNanoCard(root);
      return;
    }
    initMiniCard(root);
  }

  function wireOnce() {
    if (w.__CB_KARTY_MINNANO_WIRED__) return;
    w.__CB_KARTY_MINNANO_WIRED__ = true;

    document.addEventListener('click', (event) => {
      const target = event.target instanceof Element ? event.target : null;
      if (!target) return;

      if (target.closest('[data-cb-cardmode-close]')) {
        closeCardModeModal();
      }
    });

    document.addEventListener('cb:main-swapped', () => {
      initKartyMinNano();
    });

    document.addEventListener('cb:card-swapped', (event) => {
      const detail = event && event.detail && typeof event.detail === 'object' ? event.detail : null;
      const card = detail && detail.card instanceof HTMLElement ? detail.card : null;
      if (!card) return;
      const root = card.querySelector(CARD_ROOT_SELECTOR);
      if (root instanceof HTMLElement) {
        initCardRoot(root);
      }
    });
  }

  wireOnce();

  if (w.CB_AJAX) {
    w.CB_AJAX.relayoutDashboard = relayoutDashboard;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyMinNano, { once: true });
  } else {
    initKartyMinNano();
  }
})(window);

// js/karty_min_nano.js * Verze: V4 * Aktualizace: 15.04.2026
// Konec souboru
