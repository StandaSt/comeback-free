// js/karty_min_nano.js * Verze: V3 * Aktualizace: 15.04.2026
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

  function collectCardsForMode1(grid) {
    const nanoCards = [];
    const miniCards = [];

    Array.from(grid.children).forEach((child) => {
      if (!(child instanceof HTMLElement)) return;

      if (child.classList.contains('dash_nano_group')) {
        Array.from(child.children).forEach((card) => {
          if (card instanceof HTMLElement && card.matches('[data-cb-dash-card="1"]')) {
            nanoCards.push(card);
          }
        });
        return;
      }

      if (!child.matches('[data-cb-dash-card="1"]')) {
        return;
      }

      const shell = child.querySelector('.card_shell');
      if (!(shell instanceof HTMLElement)) return;

      if (String(shell.getAttribute('data-card-mode') || '').trim() === 'nano') {
        nanoCards.push(child);
      } else {
        miniCards.push(child);
      }
    });

    return { nanoCards, miniCards };
  }

  function collectCardsForMode0(grid) {
    const nanoCards = [];
    const miniCards = [];

    Array.from(grid.children).forEach((child) => {
      if (!(child instanceof HTMLElement)) return;

      if (child.classList.contains('dash_nano_group')) {
        Array.from(child.children).forEach((card) => {
          if (card instanceof HTMLElement && card.matches('[data-cb-dash-card="1"]')) {
            const shell = card.querySelector('.card_shell');
            if (shell instanceof HTMLElement && String(shell.getAttribute('data-card-mode') || '').trim() === 'nano') {
              nanoCards.push(card);
            } else {
              miniCards.push(card);
            }
          }
        });
        return;
      }

      if (child.classList.contains('dash_break')) {
        return;
      }

      if (!child.matches('[data-cb-dash-card="1"]')) {
        return;
      }

      const shell = child.querySelector('.card_shell');
      if (!(shell instanceof HTMLElement)) return;

      if (String(shell.getAttribute('data-card-mode') || '').trim() === 'nano') {
        nanoCards.push(child);
      } else {
        miniCards.push(child);
      }
    });

    return { nanoCards, miniCards };
  }

  function rebuildGridAfterModeSwitch() {
    const grid = getDashGrid();
    if (!(grid instanceof HTMLElement)) return;

    if (grid.classList.contains('dash_nano_kde_1')) {
      const cards = collectCardsForMode1(grid);
      const nodes = [];

      if (cards.nanoCards.length > 0) {
        const group = document.createElement('div');
        group.className = 'dash_nano_group';
        cards.nanoCards.forEach((card) => {
          group.appendChild(card);
        });
        nodes.push(group);
      }

      cards.miniCards.forEach((card) => {
        nodes.push(card);
      });

      grid.replaceChildren(...nodes);
      return;
    }

    const cards = collectCardsForMode0(grid);
    const nodes = [];

    cards.nanoCards.forEach((card) => {
      nodes.push(card);
    });

    if (cards.nanoCards.length > 0 && cards.miniCards.length > 0) {
      nodes.push(createBreakNode());
    }

    cards.miniCards.forEach((card) => {
      nodes.push(card);
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
      rebuildGridAfterModeSwitch();
      document.dispatchEvent(new CustomEvent('cb:main-swapped'));
      return result;
    });
  }

  function finishModeSwitch() {
    setDashboardLoading(false);
  }

  function handleModeSwitch(cardId, targetMode, tracePrefix) {
    setDashboardLoading(true);

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
      if (String(root.getAttribute('data-card-mode') || 'mini').trim() === 'nano') {
        initNanoCard(root);
      } else {
        initMiniCard(root);
      }
    });
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
  }

  wireOnce();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyMinNano, { once: true });
  } else {
    initKartyMinNano();
  }
})(window);

// js/karty_min_nano.js * Verze: V3 * Aktualizace: 15.04.2026
// Konec souboru
