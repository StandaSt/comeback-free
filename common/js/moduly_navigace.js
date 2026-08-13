(function(){
  'use strict';

  var config = window.CB_MODULY_NAVIGACE || {};
  var root = document.querySelector('[data-obal-main="1"]');
  var shellUrl = String(config.shellUrl || window.CB_ENDPOINT || 'index.php');
  var publicShellUrl = String(config.publicShellUrl || '');
  var activeMainModule = String(config.activeMainModule || 'provoz');
  var povoleneModuly = ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk', 'administrace'];

  window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
  if (!root) return;
  history.replaceState(null, '', publicShellUrl);
  var currentShellKey = makeShellKey(activeMainModule, new URLSearchParams(window.location.search));
  var moduleLoadRunning = false;
  var pageLoaderTimer = 0;

  function makeShellKey(moduleName, params){
    var normalized = new URLSearchParams();
    normalized.set('m', moduleName);
    if (params instanceof URLSearchParams) {
      params.forEach(function(value, key){
        if (key !== 'm') {
          normalized.set(key, value);
        }
      });
    }
    normalized.sort();
    return normalized.toString();
  }

  function fetchSmenyPlanState(){
    return fetch(shellUrl, {
      method: 'GET',
      headers: {
        'X-Comeback-Smeny-Plan-State': '1',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    })
      .then(function(response){
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function(state){
        return !!(state && Number(state.should_run || 0) === 1);
      });
  }

  function showModuleError(error){
    if (window.CB_LOADER && typeof window.CB_LOADER.hide === 'function') {
      window.CB_LOADER.hide();
    }
    stopPageLoaderTimer();
    root.innerHTML = '<div class="cb-module-load-error">Modul se nepodařilo načíst: ' + String(error.message || error) + '</div>';
  }

  function formatElapsed(ms) {
    return (Math.max(0, ms) / 1000).toFixed(1).replace('.', ',') + ' s';
  }

  function stopPageLoaderTimer() {
    if (pageLoaderTimer) {
      window.clearInterval(pageLoaderTimer);
      pageLoaderTimer = 0;
    }
  }

  function setPageLoaderDetail(text) {
    var detailNode = root.querySelector('.cb_page_loader_detail');
    if (!(detailNode instanceof HTMLElement)) return;
    detailNode.textContent = String(text || '').trim();
  }

  function loaderText(moduleName, params) {
    var page = params instanceof URLSearchParams ? String(params.get('page') || '') : '';
    if (moduleName === 'provoz') {
      if (page === 'objednavky') return 'Načítám objednávky ...';
      if (page === 'denni_report') return 'Načítám denní report ...';
      if (page === 'prehled' || page === '' || page === 'dashboard') return 'Načítám přehled ...';
      return 'Načítám Provoz ...';
    }
    if (moduleName === 'hr') return 'Načítám HR ...';
    if (moduleName === 'smeny') return 'Načítám Směny ...';
    if (moduleName === 'ukoly') return 'Načítám Úkoly ...';
    if (moduleName === 'helpdesk') return 'Načítám Helpdesk ...';
    if (moduleName === 'administrace') return 'Načítám Administraci ...';
    return 'Načítám ...';
  }

  function showPageLoader(moduleName, params) {
    stopPageLoaderTimer();
    if (window.CB_LOADER && typeof window.CB_LOADER.hide === 'function') {
      window.CB_LOADER.hide();
    }

    var pp = root.querySelector('.pp');
    var text = loaderText(moduleName, params);
    var html = ''
      + '<div class="cb_page_loader" role="status" aria-live="polite" aria-atomic="true">'
      + '<span class="cb_page_loader_text"></span>'
      + '<span class="cb_page_loader_detail"></span>'
      + '<span class="cb_page_loader_time" data-cb-page-loader-time>0,0 s</span>'
      + '</div>';

    if (pp instanceof HTMLElement) {
      pp.classList.add('is-page-loading');
      pp.innerHTML = html;
      pp.setAttribute('data-module', moduleName);
      if (params instanceof URLSearchParams && params.get('page')) {
        pp.setAttribute('data-page', String(params.get('page') || ''));
      }
    } else {
      root.innerHTML = '<section class="pp is-page-loading" data-module="' + moduleName + '">' + html + '</section>';
      pp = root.querySelector('.pp');
    }

    var loader = pp ? pp.querySelector('.cb_page_loader') : null;
    var textNode = loader ? loader.querySelector('.cb_page_loader_text') : null;
    var timeNode = loader ? loader.querySelector('[data-cb-page-loader-time]') : null;
    if (textNode instanceof HTMLElement) {
      textNode.textContent = text;
    }
    var startedAt = performance.now();
    pageLoaderTimer = window.setInterval(function () {
      if (!(timeNode instanceof HTMLElement) || !timeNode.isConnected) {
        stopPageLoaderTimer();
        return;
      }
      timeNode.textContent = formatElapsed(performance.now() - startedAt);
    }, 100);
  }

  function ensureRestiaBeforeProvoz(moduleName){
    if (moduleName !== 'provoz') {
      return Promise.resolve(null);
    }
    if (
      !window.CB_RESTIA
      || typeof window.CB_RESTIA.fetchState !== 'function'
      || typeof window.CB_RESTIA.run !== 'function'
      || typeof window.CB_RESTIA.isFresh !== 'function'
    ) {
      return Promise.resolve(null);
    }

    return window.CB_RESTIA.fetchState()
      .then(function(state){
        var running = !!(state && Number(state.active || 0) === 1);
        if (!running && window.CB_RESTIA.isFresh(state)) {
          return state;
        }

        return window.CB_RESTIA.run({
          moduleName: 'provoz',
          loaderText: ''
        });
      });
  }

  function setActive(moduleName){
    if (moduleName === 'helpdesk') return;

    var links = Array.prototype.slice.call(document.querySelectorAll('[data-cb-module-link="1"]'));
    links.forEach(function(link){
      var active = link.getAttribute('data-cb-module') === moduleName;
      link.classList.toggle('is-active', active);
    });
  }

  function setClickedMenuActive(link) {
    if (!(link instanceof HTMLElement)) return;

    var menu = link.closest('.blok_menu');
    if (menu instanceof HTMLElement) {
      Array.prototype.slice.call(menu.querySelectorAll('.blok_menu_btn.is-active, .blok_menu_btn.active')).forEach(function(btn){
        btn.classList.remove('is-active', 'active');
      });
      if (link.classList.contains('blok_menu_btn')) {
        link.classList.add('is-active');
      }
      return;
    }

    if (link.getAttribute('data-cb-module-link') === '1') {
      setActive(String(link.getAttribute('data-cb-module') || ''));
    }
  }

  function setRootModule(moduleName){
    if (moduleName !== 'helpdesk') {
      activeMainModule = moduleName;
      window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
    }
    var visualModule = moduleName === 'helpdesk' ? 'helpdesk' : activeMainModule;
    root.classList.remove('cb-context--provoz', 'cb-context--hr', 'cb-context--smeny', 'cb-context--ukoly', 'cb-context--helpdesk', 'cb-context--administrace');
    root.classList.add('cb-context--' + visualModule);
    document.body.classList.remove('cb-context--provoz', 'cb-context--hr', 'cb-context--smeny', 'cb-context--ukoly', 'cb-context--helpdesk', 'cb-context--administrace');
    document.body.classList.add('cb-context--' + visualModule);
  }

  function loadModule(moduleName, pushState, params){
    if (!moduleName) return;
    if (moduleLoadRunning) return;
    var requestedKey = makeShellKey(moduleName, params);
    if (pushState && requestedKey === currentShellKey) {
      return;
    }
    moduleLoadRunning = true;
    showPageLoader(moduleName, params);

    var body = new URLSearchParams();
    body.set('cb_shell_module', moduleName);
    if (params instanceof URLSearchParams) {
      params.forEach(function(value, key){
        if (key !== 'm') {
          body.set(key, value);
        }
      });
    }
    if (moduleName === 'helpdesk') {
      body.set('cb_helpdesk_source_module', activeMainModule);
      window.CB_HELPDESK_SOURCE_MODULE = activeMainModule;
    }
    function fetchModule(showSmenyLoader){
      return fetch(shellUrl, {
        method: 'POST',
        headers: {
          'X-Comeback-Shell-Module': '1',
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString(),
        credentials: 'same-origin'
      })
        .then(function(response){
          if (!response.ok) throw new Error('HTTP ' + response.status);
          return response.text();
        })
        .then(function(html){
          if (typeof window.__CB_HELPDESK_CLEANUP__ === 'function') {
            window.__CB_HELPDESK_CLEANUP__();
            window.__CB_HELPDESK_CLEANUP__ = null;
          }
          setRootModule(moduleName);
          stopPageLoaderTimer();
          root.innerHTML = html;
          currentShellKey = requestedKey;
          document.dispatchEvent(new CustomEvent('cb:main-swapped'));
          if (moduleName === 'hr' && window.CB_HR_INIT) {
            window.CB_HR_INIT(root);
          }
          if (moduleName === 'helpdesk' && window.CB_HELPDESK_INIT) {
            window.CB_HELPDESK_INIT(root);
          }
          setActive(moduleName);
          if (window.CB_LOADER && typeof window.CB_LOADER.hide === 'function') {
            window.CB_LOADER.hide();
          }
        });
    }

    if (moduleName === 'provoz' && params instanceof URLSearchParams && params.get('page') === 'denni_report') {
      setPageLoaderDetail('Aktualizuji objednávky ...');
      fetchSmenyPlanState()
        .catch(function(){
          return false;
        })
        .then(function(showSmenyLoader){
          return ensureRestiaBeforeProvoz(moduleName)
            .then(function(){
              setPageLoaderDetail(showSmenyLoader ? 'Načítám směny ...' : 'Připravuji denní report ...');
              return fetchModule(showSmenyLoader);
            });
        })
        .catch(showModuleError)
        .finally(function(){
          moduleLoadRunning = false;
        });
      return;
    }

    if (moduleName === 'provoz') {
      ensureRestiaBeforeProvoz(moduleName)
        .then(function(){
          return fetchModule(false);
        })
        .catch(showModuleError)
        .finally(function(){
          moduleLoadRunning = false;
        });
      return;
    }

    fetchModule(false)
      .catch(showModuleError)
      .finally(function(){
        moduleLoadRunning = false;
      });
  }

  window.CB_LOAD_MODULE = loadModule;

  document.addEventListener('click', function(event){
    var link = event.target.closest ? event.target.closest('[data-cb-module-link="1"]') : null;
    if (!link) return;

    var moduleName = link.getAttribute('data-cb-module') || '';
    if (!moduleName) return;

    event.preventDefault();
    setClickedMenuActive(link);
    var params = null;
    try {
      params = new URL(link.href, window.location.href).searchParams;
    } catch (e) {
      params = null;
    }
    loadModule(moduleName, true, params);
  });

  root.addEventListener('click', function(event){
    var link = event.target.closest ? event.target.closest('a[href]') : null;
    if (!link || link.getAttribute('data-cb-module-link') === '1') return;
    if (link.target && link.target !== '_self') return;
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return;
    }

    if (url.origin !== window.location.origin) return;
    if (url.pathname !== new URL(shellUrl, window.location.href).pathname) return;

    var moduleName = url.searchParams.get('m') || '';
    if (povoleneModuly.indexOf(moduleName) === -1) return;

    event.preventDefault();
    setClickedMenuActive(link);
    loadModule(moduleName, true, url.searchParams);
  });
})();
