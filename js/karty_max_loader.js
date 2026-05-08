// js/karty_max_loader.js * Verze: V1 * Aktualizace: 08.05.2026
'use strict';

(function (w) {
  function getDashCard(root) {
    if (!(root instanceof HTMLElement)) return null;
    return root.closest('[data-cb-dash-card="1"]');
  }

  function load(root, options) {
    const opts = (options && typeof options === 'object') ? options : {};
    const expandedSelector = String(opts.expandedSelector || '[data-card-expanded]');
    const cardId = String(root instanceof HTMLElement ? (root.getAttribute('data-card-id') || '') : '').trim();

    if (!(root instanceof HTMLElement)) {
      return Promise.reject(new Error('Koren karty nebyl nalezen.'));
    }
    if (cardId === '') {
      return Promise.reject(new Error('ID karty nebylo nalezeno.'));
    }
    if (!(w.CB_AJAX && typeof w.CB_AJAX.loadCardMaxContent === 'function')) {
      return Promise.reject(new Error('Nacteni max obsahu neni dostupne.'));
    }

    return w.CB_AJAX.loadCardMaxContent(cardId, {
      loaderMode: 'cards'
    }).then((result) => {
      const maxHtml = result && typeof result.maxHtml === 'string' ? result.maxHtml : '';
      const expanded = root.querySelector(expandedSelector);
      if (!(expanded instanceof HTMLElement)) {
        throw new Error('Karta nema max kontejner.');
      }

      expanded.innerHTML = maxHtml;
      root.setAttribute('data-card-max-loaded', '1');
      document.dispatchEvent(new CustomEvent('cb:card-max-loaded', {
        detail: {
          cardId: parseInt(cardId, 10) || 0,
          card: getDashCard(root)
        }
      }));

      return root;
    });
  }

  w.CB_CARD_MAX_LOADER = w.CB_CARD_MAX_LOADER || {};
  w.CB_CARD_MAX_LOADER.load = load;
})(window);

// js/karty_max_loader.js * Verze: V1 * Aktualizace: 08.05.2026
// Konec souboru
