// js/karty_min_nano.js * Verze: V2 * Aktualizace: 15.04.2026
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

  function reloadAfterModeSwitch() {
    setDashboardLoading(true);

    if (w.CB_AJAX && typeof w.CB_AJAX.refreshDashboard === 'function') {
      w.CB_AJAX.refreshDashboard({ force: true, loaderMode: 'cards' }).catch(() => {
        w.alert('Obnovení dashboardu po přepnutí režimu selhalo.');
      });
      return;
    }

    w.location.reload();
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

      setDashboardLoading(true);

      requestCardMode(cardId, 'nano').then(() => {
        traceAjax('mini_to_nano_ok', {
          card_id: cardId
        });
        reloadAfterModeSwitch();
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

        setDashboardLoading(true);

        requestCardMode(cardId, 'mini').then(() => {
          traceAjax('nano_to_mini_ok', {
            card_id: cardId
          });
          reloadAfterModeSwitch();
        }).catch((err) => {
          traceAjax('nano_to_mini_error', {
            card_id: cardId,
            message: String((err && err.message) ? err.message : '')
          });
          setDashboardLoading(false);
          showCardModeError(err);
        });
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

// js/karty_min_nano.js * Verze: V2 * Aktualizace: 15.04.2026
// Konec souboru
