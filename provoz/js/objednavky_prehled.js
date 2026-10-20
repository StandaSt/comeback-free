// Přehled objednávek: detaily, výběr poboček jednotlivě i podle oblastí, aktualizace Restie a zarovnání titulku.
(function () {
  'use strict';

  var loaderTimers = [];
  var headerSizeObserver = null;

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

  // Kazde tlacitko navazeme jen jednou; nove radky po filtrovani potrebuji vlastni obsluhu.
  function bindOrderDetails(root) {
    Array.prototype.forEach.call(root.querySelectorAll('[data-provoz-objednavky-toggle]'), function (button) {
      if (!(button instanceof HTMLButtonElement) || button.getAttribute('data-provoz-objednavky-toggle-bound') === '1') {
        return;
      }
      button.setAttribute('data-provoz-objednavky-toggle-bound', '1');
      button.addEventListener('click', function () {
        var detailId = String(button.getAttribute('aria-controls') || '');
        var detail = detailId !== '' ? document.getElementById(detailId) : null;
        if (!(detail instanceof HTMLTableRowElement)) {
          return;
        }
        var willOpen = detail.hidden;
        detail.hidden = !willOpen;
        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      });
    });
  }

  // Tabulka meni sirku podle obsahu i pisma; titulek ji sleduje take po vymene filtru.
  function alignHeader(root) {
    var page = root.closest('.pp');
    var header = page && page.querySelector('.provoz_objednavky_header_title');
    var table = root.querySelector('.provoz_objednavky_table_wrap');
    if (headerSizeObserver) headerSizeObserver.disconnect();
    if (!header || !table) return;
    function syncWidth() {
      if (header.isConnected && table.isConnected) header.style.width = table.getBoundingClientRect().width + 'px';
    }
    syncWidth();
    headerSizeObserver = new ResizeObserver(syncWidth);
    headerSizeObserver.observe(table);
  }

  // Panel používá společné filtrování formuláře; po výměně tabulky zůstane otevřený pro další volbu.
  function bindBranches(root) {
    var picker = root.querySelector('[data-objednavky-branches]');
    if (!picker || picker.dataset.bound === '1') return;
    picker.dataset.bound = '1';
    // Pevný panel zarovnáme k ovladači i po změně šířky tabulky.
    function positionPanel() {
      var rect = picker.querySelector('summary').getBoundingClientRect();
      var panel = picker.querySelector('.provoz_objednavky_branches_panel');
      panel.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - panel.offsetWidth - 8)) + 'px';
      panel.style.top = (rect.bottom + 4) + 'px';
    }
    picker.open = root.dataset.branchesOpen === '1';
    if (picker.open) positionPanel();
    picker.addEventListener('toggle', function () {
      if (!picker.isConnected) return;
      root.dataset.branchesOpen = picker.open ? '1' : '0';
      if (picker.open) positionPanel();
    });
    // Oblast nahradí výběr odpovídajícími povolenými pobočkami; prázdná oblast znamená Vše.
    picker.querySelectorAll('[data-objednavky-branches-group]').forEach(function (button) {
      button.addEventListener('click', function () {
        var region = button.getAttribute('data-objednavky-branches-group');
        picker.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
          input.checked = region !== '' && input.getAttribute('data-branch-region') === region;
        });
        root.dataset.branchesOpen = '0';
        picker.closest('form').requestSubmit();
      });
    });
  }

  // Kontejner zustava pri filtrovani stejny, ale jeho tlacitka se meni.
  function initRoot(root) {
    if (!(root instanceof HTMLElement)) {
      return;
    }
    alignHeader(root);
    root.setAttribute('data-objednavky-ready', '1');
    bindOrderDetails(root);
    bindBranches(root);
  }

  function bindRestiaRefresh(scope) {
    var root = scope instanceof HTMLElement ? scope : document;
    var button = root.querySelector('[data-objednavky-restia-refresh]');
    if (!(button instanceof HTMLButtonElement) || button.getAttribute('data-objednavky-restia-bound') === '1') {
      return;
    }
    button.setAttribute('data-objednavky-restia-bound', '1');

    function setButtonReady() {
      button.disabled = false;
      button.classList.remove('is-loading');
      button.removeAttribute('aria-disabled');
      button.textContent = 'Aktualizace objednávek';
      button.title = 'Aktualizovat objednávky z Restie';
    }

    function setButtonLocked(text, title) {
      button.disabled = true;
      button.setAttribute('aria-disabled', 'true');
      button.textContent = text;
      button.title = title;
    }

    function syncRefreshState() {
      if (!window.CB_RESTIA || typeof window.CB_RESTIA.fetchState !== 'function' || !button.isConnected) {
        return;
      }
      window.CB_RESTIA.fetchState().then(function (state) {
        if (!button.isConnected) {
          return;
        }
        if (Number(state && state.active || 0) === 1) {
          setButtonLocked('Aktualizace objednávek (probíhá)', 'Aktualizace Restie právě běží.');
          window.setTimeout(syncRefreshState, 1000);
          return;
        }

        var refreshAfter = Number(state && state.manual_refresh_after_ts || 0);
        var remaining = refreshAfter - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
          setButtonReady();
          return;
        }

        setButtonLocked('Aktualizace objednávek (možno za ' + remaining + ' s.)', 'Aktualizace bude možná za ' + remaining + ' s.');
        window.setTimeout(syncRefreshState, 1000);
      }).catch(function () {
        if (button.isConnected) {
          setButtonReady();
        }
      });
    }

    syncRefreshState();

    button.addEventListener('click', function () {
      if (button.disabled) {
        return;
      }
      if (!window.CB_RESTIA || typeof window.CB_RESTIA.run !== 'function') {
        window.alert('Aktualizace Restie není dostupná.');
        return;
      }

      button.disabled = true;
      button.classList.add('is-loading');
      var originalText = button.textContent;
      button.textContent = 'Aktualizuji…';
      var pageBusy = window.CB_PAGE_BUSY && typeof window.CB_PAGE_BUSY.start === 'function' && typeof window.CB_PAGE_BUSY.stop === 'function'
        ? window.CB_PAGE_BUSY
        : null;
      var pageBusyHandle = pageBusy
        ? pageBusy.start('Aktualizuji objednávky ...', 'Načítám nová data Restie')
        : null;

      window.CB_RESTIA.run({
        moduleName: 'provoz',
        manualOrdersRefresh: true
      }).then(function () {
        var params = new URLSearchParams();
        params.set('page', 'objednavky');
        var form = document.querySelector('[data-objednavky-prehled="1"] form[method="get"]');
        if (form instanceof HTMLFormElement) {
          new FormData(form).forEach(function (value, key) {
            if (key !== 'm') {
              // Více poboček má opakovaný název; ostatní parametry zůstávají jediné.
              if (/\[\]$/.test(key)) params.append(key, String(value));
              else params.set(key, String(value));
            }
          });
        }

        if (typeof window.CB_LOAD_MODULE === 'function') {
          window.CB_LOAD_MODULE('provoz', false, params);
          return;
        }
        window.location.assign('index.php?m=provoz&' + params.toString());
      }).catch(function (error) {
        window.alert(error && error.message ? error.message : 'Aktualizace Restie selhala.');
        button.textContent = originalText;
        syncRefreshState();
      }).finally(function () {
        if (pageBusy) {
          pageBusy.stop(pageBusyHandle);
        }
      });
    });
  }

  function initAll(scope) {
    var rootScope = scope instanceof HTMLElement ? scope : document;
    Array.prototype.slice.call(rootScope.querySelectorAll('[data-objednavky-prehled="1"]')).forEach(initRoot);
    if (rootScope instanceof HTMLElement && rootScope.matches('[data-objednavky-prehled="1"]')) {
      initRoot(rootScope);
    }
    bindRestiaRefresh(document);
  }

  document.addEventListener('cb:period-changed', function (event) {
    if (event.detail && event.detail.root && event.detail.root.getAttribute('data-period-scope') === 'provoz.objednavky') startLoaderTimers();
  });

  // Zavření mimo seznam a klávesou Escape zachová běžné chování rozbalovacího filtru.
  document.addEventListener('click', function (event) {
    document.querySelectorAll('[data-objednavky-branches][open]').forEach(function (picker) {
      if (!picker.contains(event.target)) picker.open = false;
    });
  });
  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-objednavky-branches][open]').forEach(function (picker) {
      picker.open = false;
      picker.querySelector('summary').focus();
    });
  });

  document.addEventListener('cb:gn-block-refreshed', function (event) {
    stopLoaderTimers();
    initAll(event.detail && event.detail.block);
  });

  document.addEventListener('cb:main-swapped', function () {
    stopLoaderTimers();
    initAll(document);
  });

  // Spolecne filtry meni jen tabulku; obsluhu detailu obnovime v jejim formulari.
  document.addEventListener('cb:filters-swapped', function (event) {
    var form = event.detail && event.detail.form;
    var root = form instanceof HTMLFormElement ? form.closest('[data-objednavky-prehled="1"]') : null;
    if (root instanceof HTMLElement) {
      initRoot(root);
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initAll(document);
    }, { once: true });
  } else {
    initAll(document);
  }
}());
