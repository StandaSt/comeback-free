// js/karty_min_nano.js * Verze: V4 * Aktualizace: 15.04.2026
'use strict';

(function (w) {
  const CARD_ROOT_SELECTOR = '.card_shell';
  const CARD_TO_NANO_SELECTOR = '[data-card-to-nano]';

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
      err_msg: String(errMsg || '').trim(),
      zdroj: 'karty_min_nano'
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

  function getCardRoots() {
    return Array.from(document.querySelectorAll(CARD_ROOT_SELECTOR)).filter((el) => el instanceof HTMLElement);
  }

  function getDashGrid() {
    const grid = document.querySelector('.dash_grid[data-login-id]');
    return grid instanceof HTMLElement ? grid : null;
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

  function clearCardPlacement(section) {
    if (!(section instanceof HTMLElement)) return;
    const root = getCardRootFromSection(section);
    if (!(root instanceof HTMLElement)) return;

    section.style.gridColumn = '';
    section.style.gridRow = '';
    root.setAttribute('data-card-col', '0');
    root.setAttribute('data-card-line', '0');
  }

  function requestCardMode(cardId, mode, options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const forceUnlock = opts.forceUnlock === true;
    return fetch('index.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Comeback-Set-Card-Mode': '1'
      },
      body: JSON.stringify({ id_karta: cardId, mode: mode, force_unlock: forceUnlock ? 1 : 0 })
    }).then((r) => r.json().catch(() => ({})).then((data) => {
      if (r.ok && data && data.ok) {
        return data;
      }
      const err = String((data && data.err) ? data.err : 'Uložení režimu karty selhalo').trim();
      const e = new Error(err !== '' ? err : 'Uložení režimu karty selhalo');
      e.needsConfirm = !!(data && data.needs_confirm);
      throw e;
    }));
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

  function showCardModeError(err) {
    const msg = (err && typeof err.message === 'string') ? err.message.trim() : '';
    if (msg !== '') {
      if (!openCardModeModal(msg)) {
        w.alert(msg);
      }
    }
  }

  function openNanoUnlockConfirm(onYes, onNo) {
    const modal = getCardModeModal();
    if (!modal || !(modal.confirmBtn instanceof HTMLElement)) return false;

    modal.msg.innerHTML = 'Karta je momentálně uzamčena na pozici.<br><br>Pokud trváš na přesunu karty,<br>pozice bude uvolněna pro další použití.';
    modal.closeBtn.textContent = 'Jéminkote, netrvám na tom';
    modal.confirmBtn.textContent = 'Trvám na přesunu';
    modal.confirmBtn.classList.remove('is-hidden');
    modal.root.classList.remove('is-hidden');
    modal.root.setAttribute('aria-hidden', 'false');
    modal.confirmBtn.focus();

    const yes = function (ev) {
      ev.preventDefault();
      modal.confirmBtn.removeEventListener('click', yes);
      modal.closeBtn.removeEventListener('click', no);
      closeCardModeModal();
      if (typeof onYes === 'function') onYes();
    };

    const no = function (ev) {
      ev.preventDefault();
      modal.confirmBtn.removeEventListener('click', yes);
      modal.closeBtn.removeEventListener('click', no);
      closeCardModeModal();
      if (typeof onNo === 'function') onNo();
    };

    modal.confirmBtn.addEventListener('click', yes);
    modal.closeBtn.addEventListener('click', no);
    return true;
  }

  function relayoutMiniCards(miniCards) {
    const ordered = [];

    miniCards.forEach((section, index) => {
      ordered.push({
        section,
        index,
        poradi: getCardOrderFromSection(section),
        cardId: getCardIdFromSection(section)
      });
    });

    ordered.sort((a, b) => {
      if (a.poradi !== b.poradi) {
        return a.poradi - b.poradi;
      }
      if (a.cardId !== b.cardId) {
        return a.cardId - b.cardId;
      }
      return a.index - b.index;
    });

    const placed = ordered.map((item) => item.section);
    placed.forEach((section) => {
      clearCardPlacement(section);
    });

    return placed;
  }

  function rebuildGridAfterModeSwitch() {
    const grid = getDashGrid();
    if (!(grid instanceof HTMLElement)) return;

    const miniCards = Array.from(grid.children).filter((child) => (
      child instanceof HTMLElement && child.matches('[data-cb-dash-card="1"]')
    ));
    grid.replaceChildren(...relayoutMiniCards(miniCards));
  }

  function relayoutDashboard() {
    rebuildGridAfterModeSwitch();
    document.dispatchEvent(new CustomEvent('cb:dashboard-layout-changed'));
    return { ok: true };
  }

  function finishModeSwitch() {
    setDashboardLoading(false);
  }

  function appendActivatedMiniCard(cardId) {
    const id = parseInt(String(cardId || '0'), 10);
    if (!Number.isFinite(id) || id <= 0) {
      return Promise.reject(new Error('ID karty nebylo nalezeno.'));
    }

    return fetch('index.php?cb_card_id=' + encodeURIComponent(String(id)), {
      method: 'GET',
      headers: {
        'X-Comeback-Card': '1',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    }).then((response) => response.text().then((text) => {
      let data = null;
      try {
        data = JSON.parse(String(text || '').trim());
      } catch (e) {
        data = null;
      }

      if (!response.ok || !data || typeof data.cardHtml !== 'string') {
        throw new Error(String((data && data.err) ? data.err : 'Aktivovanou kartu se nepodařilo načíst.'));
      }

      const wrap = document.createElement('div');
      wrap.innerHTML = String(data.cardHtml || '').trim();
      const card = wrap.firstElementChild;
      const grid = getDashGrid();
      if (!(card instanceof HTMLElement) || !(grid instanceof HTMLElement)) {
        throw new Error('Aktivovanou kartu se nepodařilo vložit na dashboard.');
      }

      grid.appendChild(card);
      relayoutDashboard();
      document.dispatchEvent(new CustomEvent('cb:card-swapped', {
        detail: {
          cardId: id,
          card: card
        }
      }));

      document.querySelectorAll('[data-cb-nano-card-row="' + String(id) + '"]').forEach((row) => {
        if (!(row instanceof HTMLElement)) return;
        const list = row.closest('[data-cb-nano-list="1"]');
        row.remove();
        if (list instanceof HTMLElement && !list.querySelector('[data-cb-nano-card-row]')) {
          const panel = list.parentElement;
          const empty = panel ? panel.querySelector('[data-cb-nano-empty="1"]') : null;
          if (empty instanceof HTMLElement) {
            empty.classList.remove('is-hidden');
          }
        }
      });

      return { ok: true, cardId: id, card: card };
    }));
  }

  function handleModeSwitch(cardId, targetMode, tracePrefix, actionId) {
    const idAkce = parseInt(String(actionId || '0'), 10);
    setDashboardLoading(true, 'Přesouvám kartu ...');

    requestCardMode(cardId, targetMode, { forceUnlock: false }).then(() => {
      traceAjax(tracePrefix + '_mode_saved', {
        card_id: cardId
      });
      if (targetMode === 'nano') {
        const root = document.querySelector('.card_shell[data-card-id="' + String(cardId) + '"]');
        const section = getCardSectionFromRoot(root);
        if (section instanceof HTMLElement) {
          section.remove();
        }
        return relayoutDashboard();
      }
      return appendActivatedMiniCard(cardId);
    }).then(() => {
      traceAjax(tracePrefix + '_ok', {
        card_id: cardId
      });
      logUserCardAction(idAkce, cardId, true, '');
      finishModeSwitch();
    }).catch((err) => {
      if (err && err.needsConfirm === true) {
        finishModeSwitch();
        const opened = openNanoUnlockConfirm(() => {
          setDashboardLoading(true, 'Přesouvám kartu ...');
          requestCardMode(cardId, targetMode, { forceUnlock: true }).then(() => {
            if (targetMode === 'nano') {
              const root = document.querySelector('.card_shell[data-card-id="' + String(cardId) + '"]');
              const section = getCardSectionFromRoot(root);
              if (section instanceof HTMLElement) {
                section.remove();
              }
              return relayoutDashboard();
            }
            return appendActivatedMiniCard(cardId);
          }).then(() => {
            traceAjax(tracePrefix + '_ok', {
              card_id: cardId
            });
            logUserCardAction(idAkce, cardId, true, '');
            finishModeSwitch();
          }).catch((err2) => {
            traceAjax(tracePrefix + '_error', {
              card_id: cardId,
              message: String((err2 && err2.message) ? err2.message : '')
            });
            logUserCardAction(idAkce, cardId, false, String((err2 && err2.message) ? err2.message : ''));
            finishModeSwitch();
            showCardModeError(err2);
          });
        }, () => {});
        if (opened) {
          return;
        }
      }
      traceAjax(tracePrefix + '_error', {
        card_id: cardId,
        message: String((err && err.message) ? err.message : '')
      });
      logUserCardAction(idAkce, cardId, false, String((err && err.message) ? err.message : ''));
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

      traceAjax('mini_to_nano_click', {
        card_id: cardId
      });

      handleModeSwitch(cardId, 'nano', 'mini_to_nano', 3);
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
        return;
      }

      const activate = target.closest('[data-cb-nano-activate]');
      if (activate instanceof HTMLElement) {
        const cardId = parseInt(String(activate.getAttribute('data-cb-nano-activate') || '0'), 10);
        if (!Number.isFinite(cardId) || cardId <= 0) return;
        handleModeSwitch(cardId, 'mini', 'nano_to_mini', 4);
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
