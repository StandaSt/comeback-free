// js/karty_min_max.js * Verze: V6 * Aktualizace: 23.06.2026
'use strict';

(function (w) {
  let activeMaxi = null;

  const CARD_ROOT_SELECTOR = '.card_shell';
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

    w.fetch('index_is.php', {
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

  function getDashCard(root) {
    if (!root) return null;
    return root.closest('[data-cb-dash-card="1"]');
  }

  function getDashBox(root) {
    if (!root) return null;
    return root.closest('.dash_box') || document.querySelector('.dash_box');
  }

  function getOverlayLayer(dashBox) {
    if (!(dashBox instanceof HTMLElement)) return null;

    let layer = dashBox.querySelector('[data-cb-maxi-layer="1"]');
    if (layer instanceof HTMLElement) {
      return layer;
    }

    layer = document.createElement('div');
    layer.className = 'cb_maxi_layer';
    layer.setAttribute('data-cb-maxi-layer', '1');
    dashBox.appendChild(layer);

    return layer;
  }

  function getCardHead(root) {
    if (!root) return null;
    const fallback = root.querySelector('.card_top');
    return fallback instanceof HTMLElement ? fallback : null;
  }

  function getCardRoots() {
    return Array.from(document.querySelectorAll(CARD_ROOT_SELECTOR)).filter((el) => {
      return el instanceof HTMLElement && !(el.closest('[data-cb-maxi-clone="1"]') instanceof HTMLElement);
    });
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
    event.preventDefault();
    clearSelection();
  }

  function hasLoadedMax(root) {
    if (!(root instanceof HTMLElement)) return false;
    return String(root.getAttribute('data-card-max-loaded') || '0') === '1';
  }

  function syncOverlayPosition(item) {
    if (!item || !(item.overlayCard instanceof HTMLElement) || !(item.dashBox instanceof HTMLElement)) {
      return;
    }

    item.overlayCard.style.top = String(item.dashBox.scrollTop) + 'px';
  }

  function buildOverlayClone(item) {
    if (
      !item
      || !(item.dashCard instanceof HTMLElement)
      || !(item.expanded instanceof HTMLElement)
    ) {
      return null;
    }

    const clone = item.dashCard.cloneNode(true);
    if (!(clone instanceof HTMLElement)) return null;

    clone.removeAttribute('data-cb-dash-card');
    clone.classList.remove('is-maxi-placeholder');
    clone.classList.add('is-maxi-overlay');
    clone.setAttribute('data-cb-maxi-clone', '1');

    const cloneShell = clone.querySelector(CARD_ROOT_SELECTOR);
    if (!(cloneShell instanceof HTMLElement)) return null;

    const cloneCompact = clone.querySelector(CARD_COMPACT_SELECTOR);
    const cloneExpanded = clone.querySelector(CARD_EXPANDED_SELECTOR);
    const cloneBody = clone.querySelector('.card_body');
    const cloneNano = clone.querySelector('[data-card-to-nano]');
    const cloneHead = getCardHead(cloneShell);
    if (!(cloneBody instanceof HTMLElement)) return null;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'cb_maxi_close_btn';
    closeBtn.setAttribute('aria-label', 'Zavřít max kartu');
    closeBtn.setAttribute('title', 'Zavřít max kartu');
    closeBtn.textContent = '×';
    closeBtn.addEventListener('click', () => {
      closeActiveMaxi();
    });

    if (cloneCompact instanceof HTMLElement) {
      cloneCompact.classList.add('is-hidden');
    }
    if (cloneExpanded instanceof HTMLElement) {
      cloneExpanded.remove();
    }
    item.expanded.classList.remove('is-hidden');
    cloneBody.appendChild(item.expanded);
    if (cloneNano instanceof HTMLElement) {
      cloneNano.style.display = 'none';
    }

    if (cloneHead instanceof HTMLElement) {
      cloneHead.appendChild(closeBtn);
      cloneHead.addEventListener('mousedown', blockHeadSelection);
      cloneHead.addEventListener('dblclick', () => {
        clearSelection();
        closeActiveMaxi();
      });
    }

    return clone;
  }

  function closeActiveMaxi(opts) {
    if (!activeMaxi) return;

    const options = (opts && typeof opts === 'object') ? opts : {};
    const doLogAction = options.logAction !== false;

    const item = activeMaxi;
    const {
      root,
      compact,
      expanded,
      dashCard,
      dashBox,
      overlayLayer,
      overlayCard,
      scrollHandler,
      originalBody
    } = item;

    if (expanded instanceof HTMLElement && originalBody instanceof HTMLElement) {
      originalBody.appendChild(expanded);
      expanded.classList.add('is-hidden');
    }
    if (compact instanceof HTMLElement) compact.classList.remove('is-hidden');
    updateSubtitle(root, false);
    toggleNanoBtn(root, true);

    if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
      w.cbSetBranchSelectDisabledForRoot(root, false);
    }

    if (dashCard instanceof HTMLElement) {
      dashCard.classList.remove('is-expanded');
    }

    if (dashBox instanceof HTMLElement) {
      dashBox.classList.remove('has-maxi');
    }

    if (dashBox instanceof HTMLElement && typeof scrollHandler === 'function') {
      dashBox.removeEventListener('scroll', scrollHandler);
    }

    if (overlayCard instanceof HTMLElement) {
      overlayCard.remove();
    }

    if (overlayLayer instanceof HTMLElement && !overlayLayer.children.length) {
      overlayLayer.remove();
    }

    activeMaxi = null;

    if (doLogAction) {
      const cardId = parseInt(String(root.getAttribute('data-card-id') || '0'), 10);
      logUserCardAction(2, cardId, true, '');
    }

    const closedCard = dashCard instanceof HTMLElement ? dashCard : null;
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

  function finishOpenMaxi(root, compactSel, expandedSel) {
    const compact = root.querySelector(compactSel);
    const expanded = root.querySelector(expandedSel);
    const dashCard = getDashCard(root);
    const dashBox = getDashBox(root);
    const overlayLayer = getOverlayLayer(dashBox);
    const originalBody = root.querySelector('.card_body');

    if (
      !(compact instanceof HTMLElement)
      || !(expanded instanceof HTMLElement)
      || !(dashCard instanceof HTMLElement)
      || !(dashBox instanceof HTMLElement)
      || !(overlayLayer instanceof HTMLElement)
      || !(originalBody instanceof HTMLElement)
    ) {
      return;
    }

    if (activeMaxi && activeMaxi.root === root) {
      if (activeMaxi.dashBox instanceof HTMLElement && typeof activeMaxi.scrollHandler === 'function') {
        activeMaxi.dashBox.removeEventListener('scroll', activeMaxi.scrollHandler);
      }
      if (activeMaxi.overlayCard instanceof HTMLElement) {
        activeMaxi.overlayCard.remove();
      }
      activeMaxi.overlayCard = null;
      activeMaxi.scrollHandler = null;
    }

    updateSubtitle(root, true);
    toggleNanoBtn(root, false);

    dashBox.classList.add('has-maxi');

    if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
      w.cbSetBranchSelectDisabledForRoot(root, true);
    }

    const nextItem = {
      root,
      compact,
      expanded,
      dashCard,
      dashBox,
      overlayLayer,
      overlayCard: null,
      originalBody,
      scrollHandler: null
    };

    const overlayCard = buildOverlayClone(nextItem);
    if (!(overlayCard instanceof HTMLElement)) {
      toggleNanoBtn(root, true);
      updateSubtitle(root, false);
      dashBox.classList.remove('has-maxi');
      if (typeof w.cbSetBranchSelectDisabledForRoot === 'function') {
        w.cbSetBranchSelectDisabledForRoot(root, false);
      }
      return;
    }

    overlayLayer.innerHTML = '';
    overlayLayer.appendChild(overlayCard);
    dashCard.classList.add('is-expanded');
    document.dispatchEvent(new CustomEvent('cb:card-max-loaded', {
      detail: {
        cardId: parseInt(String(root.getAttribute('data-card-id') || '0'), 10) || 0,
        card: overlayCard
      }
    }));
    nextItem.overlayCard = overlayCard;
    nextItem.scrollHandler = () => {
      syncOverlayPosition(nextItem);
    };
    dashBox.addEventListener('scroll', nextItem.scrollHandler, { passive: true });
    syncOverlayPosition(nextItem);

    activeMaxi = nextItem;
  }

  function openMaxi(root, compactSel, expandedSel) {
    if (!(root instanceof HTMLElement)) return;

    if (activeMaxi && activeMaxi.root === root) {
      closeActiveMaxi();
      return;
    }

    closeActiveMaxi();

    if (hasLoadedMax(root)) {
      finishOpenMaxi(root, compactSel, expandedSel);
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
      finishOpenMaxi(nextRoot, compactSel, expandedSel);
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

  function setExpanded(root, compactSel, expandedSel, on) {
    if (!(root instanceof HTMLElement)) return;

    const compact = root.querySelector(compactSel);
    const expanded = root.querySelector(expandedSel);
    const dashCard = getDashCard(root);
    const isOn = !!on;

    if (isOn) {
      openMaxi(root, compactSel, expandedSel);
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
    updateSubtitle(root, false);
    toggleNanoBtn(root, true);
  }

  function openCardMax(root) {
    if (!(root instanceof HTMLElement)) return;
    setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, true);
  }

  function initCard(root) {
    if (!(root instanceof HTMLElement) || root.getAttribute('data-card-init-max') === '1') return;
    if (String(root.getAttribute('data-card-mode') || 'mini').trim() === 'nano') return;

    root.setAttribute('data-card-init-max', '1');

    const head = getCardHead(root);
    const compact = root.querySelector(CARD_COMPACT_SELECTOR);
    const expanded = root.querySelector(CARD_EXPANDED_SELECTOR);

    if (!(head instanceof HTMLElement) || !(compact instanceof HTMLElement) || !(expanded instanceof HTMLElement)) {
      return;
    }

    makeHeadInteractive(head);
    setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, false);

    head.addEventListener('mousedown', blockHeadSelection);
    head.addEventListener('dblclick', () => {
      clearSelection();
      const isExpanded = !!(activeMaxi && activeMaxi.root === root);
      setExpanded(root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR, !isExpanded);
    });
  }

  function initKartyMinMax() {
    const roots = getCardRoots();
    roots.forEach(initCard);
  }

  function wireOnce() {
    if (w.__CB_KARTY_MINMAX_WIRED__) return;
    w.__CB_KARTY_MINMAX_WIRED__ = true;

    document.addEventListener('cb:main-swapped', () => {
      closeActiveMaxi({ logAction: false });
      initKartyMinMax();
    });

    document.addEventListener('cb:dashboard-layout-changed', () => {
      initKartyMinMax();
      if (activeMaxi && activeMaxi.root instanceof HTMLElement) {
        finishOpenMaxi(activeMaxi.root, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR);
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
        if (activeMaxi.dashBox instanceof HTMLElement && typeof activeMaxi.scrollHandler === 'function') {
          activeMaxi.dashBox.removeEventListener('scroll', activeMaxi.scrollHandler);
        }
        if (activeMaxi.overlayCard instanceof HTMLElement) {
          activeMaxi.overlayCard.remove();
        }
        activeMaxi = null;
        finishOpenMaxi(nextRoot, CARD_COMPACT_SELECTOR, CARD_EXPANDED_SELECTOR);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeActiveMaxi({ logAction: true });
      }
    });
  }

  wireOnce();
  w.CB_KARTY_MINMAX = w.CB_KARTY_MINMAX || {};
  w.CB_KARTY_MINMAX.openCardMax = openCardMax;

  function initKartyMinMaxOnLoad() {
    initKartyMinMax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyMinMaxOnLoad, { once: true });
  } else {
    initKartyMinMaxOnLoad();
  }
})(window);

// js/karty_min_max.js * Verze: V6 * Aktualizace: 23.06.2026
// Konec souboru
