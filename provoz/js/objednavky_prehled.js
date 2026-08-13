// js/objednavky_prehled.js * Prehled objednavek v PP
(function () {
  'use strict';

  var refreshStart = 0;
  var loaderTimers = [];

  function formatElapsed(ms) {
    return (Math.max(0, ms) / 1000).toFixed(1).replace('.', ',') + ' s';
  }

  function getOrderBlocks() {
    return Array.prototype.slice.call(document.querySelectorAll('.provoz_objednavky_block[data-pp-block="objednavky_prehled"]'));
  }

  function stopLoaderTimers() {
    loaderTimers.forEach(function (timer) {
      window.clearInterval(timer);
    });
    loaderTimers = [];
  }

  function ensureLoader(block) {
    var loader = block.querySelector('.provoz_objednavky_loader');
    if (loader instanceof HTMLElement) {
      return loader;
    }

    loader = document.createElement('div');
    loader.className = 'provoz_objednavky_loader';
    loader.setAttribute('role', 'status');
    loader.setAttribute('aria-live', 'polite');
    loader.innerHTML = '<span class="provoz_objednavky_loader_text">Načítám objednávky ...</span><span class="provoz_objednavky_loader_time" data-objednavky-loader-time>0,0 s</span>';
    block.appendChild(loader);
    return loader;
  }

  function startLoaderTimers() {
    stopLoaderTimers();
    var blocks = getOrderBlocks();
    if (blocks.length === 0) {
      return;
    }

    blocks.forEach(function (block) {
      var loader = ensureLoader(block);
      var time = loader.querySelector('[data-objednavky-loader-time]');
      var startedAt = performance.now();
      if (time instanceof HTMLElement) {
        time.textContent = '0,0 s';
      }
      loaderTimers.push(window.setInterval(function () {
        if (!(time instanceof HTMLElement) || !block.isConnected) {
          stopLoaderTimers();
          return;
        }
        time.textContent = formatElapsed(performance.now() - startedAt);
      }, 100));
    });
  }

  function startLoaderForRoot(root) {
    var block = root.closest ? root.closest('.provoz_objednavky_block[data-pp-block="objednavky_prehled"]') : null;
    if (!(block instanceof HTMLElement)) {
      return;
    }
    block.classList.add('is-loading');
    startLoaderTimers();
  }

  function setClientTime(root) {
    var target = root.querySelector('[data-objednavky-client-time]');
    if (!(target instanceof HTMLElement)) {
      return;
    }

    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        var ms = refreshStart > 0 ? Math.round(performance.now() - refreshStart) : Math.round(performance.now());
        target.textContent = String(ms) + ' ms';
        refreshStart = 0;
      });
    });
  }

  function initRoot(root) {
    if (!(root instanceof HTMLElement) || root.getAttribute('data-objednavky-ready') === '1') {
      return;
    }
    root.setAttribute('data-objednavky-ready', '1');

    setClientTime(root);

    var form = root.querySelector('[data-objednavky-filter-form="1"]');
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    var timer = 0;
    Array.prototype.slice.call(form.querySelectorAll('.provoz_objednavky_filter')).forEach(function (input) {
      input.addEventListener('input', function () {
        window.clearTimeout(timer);
        timer = window.setTimeout(function () {
          startLoaderForRoot(root);
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        }, 650);
      });
    });

    var perForm = root.querySelector('[data-objednavky-per-form="1"]');
    if (perForm instanceof HTMLFormElement) {
      perForm.addEventListener('submit', function () {
        startLoaderForRoot(root);
      });
      Array.prototype.slice.call(perForm.querySelectorAll('select')).forEach(function (select) {
        select.addEventListener('change', function () {
          startLoaderForRoot(root);
        });
      });
    }
  }

  function initAll(scope) {
    var rootScope = scope instanceof HTMLElement ? scope : document;
    Array.prototype.slice.call(rootScope.querySelectorAll('[data-objednavky-prehled="1"]')).forEach(initRoot);
    if (rootScope instanceof HTMLElement && rootScope.matches('[data-objednavky-prehled="1"]')) {
      initRoot(rootScope);
    }
  }

  document.addEventListener('cb:gn-changed', function () {
    refreshStart = performance.now();
    startLoaderTimers();
  });

  document.addEventListener('cb:gn-block-refreshed', function (event) {
    stopLoaderTimers();
    initAll(event.detail && event.detail.block);
  });

  document.addEventListener('cb:main-swapped', function () {
    stopLoaderTimers();
    initAll(document);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
    }, { once: true });
  } else {
    initAll(document);
  }
}());
