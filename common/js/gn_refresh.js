(function () {
  'use strict';

  function currentModule(block) {
    var pp = block && block.closest ? block.closest('.pp[data-module]') : null;
    if (pp instanceof HTMLElement) {
      return String(pp.getAttribute('data-module') || 'provoz');
    }
    return String(window.CB_ACTIVE_MAIN_MODULE || new URL(window.location.href).searchParams.get('m') || 'provoz');
  }

  function currentPage(block) {
    var pp = block && block.closest ? block.closest('.pp[data-page]') : null;
    if (pp instanceof HTMLElement) {
      return String(pp.getAttribute('data-page') || 'prehled');
    }
    return String(new URL(window.location.href).searchParams.get('page') || 'prehled');
  }

  function refreshBlock(block) {
    if (!(block instanceof HTMLElement)) {
      return Promise.resolve();
    }

    var blockName = String(block.getAttribute('data-pp-block') || '').trim();
    if (blockName === '') {
      return Promise.resolve();
    }

    block.classList.add('is-loading');

    var body = new URLSearchParams();
    body.set('module', currentModule(block));
    body.set('page', currentPage(block));
    body.set('block', blockName);

    return fetch(window.CB_ENDPOINT || 'index.php', {
      method: 'POST',
      headers: {
        'X-Comeback-Gn-Block': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.text();
      })
      .then(function (html) {
        var wrap = document.createElement('div');
        wrap.innerHTML = String(html || '').trim();
        var nextBlock = wrap.firstElementChild;
        if (!(nextBlock instanceof HTMLElement)) {
          throw new Error('Prázdná odpověď bloku.');
        }
        block.replaceWith(nextBlock);
        document.dispatchEvent(new CustomEvent('cb:gn-block-refreshed', {
          detail: { block: nextBlock }
        }));
      })
      .catch(function (err) {
        block.classList.remove('is-loading');
        if (window.console && window.console.warn) {
          window.console.warn(err);
        }
      });
  }

  function refreshGnBlocks() {
    var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-gn="1"][data-pp-block]'));
    if (blocks.length === 0) {
      return Promise.resolve();
    }
    return Promise.all(blocks.map(refreshBlock));
  }

  window.CB_GN_REFRESH = {
    refresh: refreshGnBlocks
  };

  document.addEventListener('cb:gn-changed', function () {
    refreshGnBlocks();
  });
}());
