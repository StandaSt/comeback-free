// Obnovuje jednotlivé bloky a pravidelná online data; místní období zachovává i více vybraných poboček.
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

  function refreshBlock(block, changeSource) {
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
    body.set('source', String(changeSource || ''));
    // Změna období zachová vyplněné místní filtry a řazení Objednávek.
    if (blockName === 'objednavky_prehled') {
      var form = block.querySelector('#provoz_objednavky_form');
      if (form) new FormData(form).forEach(function (value, key) {
        if (/^obj_(f\[|sort$|dir$|per$|p$)/.test(key)) body.append(key, String(value));
      });
    }
    if (block.getAttribute('data-google-compare') === '1') {
      body.set('zr_google_compare', '1');
    }

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
          throw new Error(window.CB_CHYBY.responseMessage(response, {}, 'Aktualizace části stránky se nepodařila.'));
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
        if (changeSource === 'obdobi_stranky') {
          window.alert('Období je uložené, ale data se nepodařilo obnovit. Obnovte stránku.');
        }
        if (window.console && window.console.warn) {
          window.console.warn(err);
        }
      });
  }

  function refreshGnBlocks(changeSource) {
    var blocks = Array.prototype.slice.call(document.querySelectorAll('[data-gn="1"][data-pp-block]'));
    if (blocks.length === 0) {
      return Promise.resolve();
    }
    return Promise.all(blocks.map(function (block) {
      return refreshBlock(block, changeSource);
    }));
  }

  function refreshNamedBlock(blockName, changeSource) {
    var selector = '[data-gn="1"][data-pp-block="' + String(blockName || '') + '"]';
    return refreshBlock(document.querySelector(selector), changeSource);
  }

  window.CB_GN_REFRESH = {
    refresh: refreshGnBlocks,
    refreshBlock: refreshNamedBlock
  };

  document.addEventListener('cb:period-changed', function (event) {
    var root = event.detail && event.detail.root;
    var block = root && root.closest('[data-pp-block]');
    // Panel v titulku ovlada jen pojmenovany blok ve stejne pracovni plose.
    var target = root && root.closest('[data-period-block-target]');
    var page = root && root.closest('.pp');
    if (!block && target && page) {
      block = page.querySelector('[data-pp-block="' + target.getAttribute('data-period-block-target') + '"]');
    }
    if (block && block.isConnected) event.detail.refreshPromise = refreshBlock(block, 'obdobi_stranky');
  });

  var periodicBlocks = {
    // Prubezne zadane reporty i uzaverka v 05:00 se projevi bez rucni obnovy stranky.
    denni_report_prehled: { interval: 30 * 1000, lastRefresh: Date.now(), running: false },
    uzivatele_online: { interval: 30 * 1000, lastRefresh: Date.now(), running: false },
    objednavky_online: { interval: 10 * 60 * 1000, lastRefresh: Date.now(), running: false }
  };

  function refreshPeriodicBlock(blockName, force) {
    var state = periodicBlocks[blockName];
    var block = document.querySelector('[data-gn="1"][data-pp-block="' + blockName + '"]');
    var now = Date.now();
    if (!state || !(block instanceof HTMLElement) || state.running) {
      return;
    }
    if (!force && (now - state.lastRefresh) < state.interval) {
      return;
    }

    state.running = true;
    state.lastRefresh = now;
    refreshBlock(block, 'auto_' + blockName).finally(function () {
      state.running = false;
    });
  }

  window.setInterval(function () {
    if (document.visibilityState !== 'hidden') {
      Object.keys(periodicBlocks).forEach(function (blockName) {
        refreshPeriodicBlock(blockName, false);
      });
    }
  }, 5000);

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      Object.keys(periodicBlocks).forEach(function (blockName) {
        refreshPeriodicBlock(blockName, false);
      });
    }
  });
}());
