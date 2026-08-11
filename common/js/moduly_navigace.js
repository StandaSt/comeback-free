(function(){
  'use strict';

  var config = window.CB_MODULY_NAVIGACE || {};
  var root = document.querySelector('[data-obal-main="1"]');
  var shellUrl = String(config.shellUrl || window.CB_ENDPOINT || 'index.php');
  var publicShellUrl = String(config.publicShellUrl || '');
  var activeMainModule = String(config.activeMainModule || 'provoz');
  var povoleneModuly = ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk'];

  window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
  if (!root) return;
  history.replaceState(null, '', publicShellUrl);
  var currentShellKey = makeShellKey(activeMainModule, new URLSearchParams(window.location.search));

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

  function setActive(moduleName){
    if (moduleName === 'helpdesk') return;

    var links = Array.prototype.slice.call(document.querySelectorAll('[data-cb-module-link="1"]'));
    links.forEach(function(link){
      var active = link.getAttribute('data-cb-module') === moduleName;
      link.classList.toggle('is-active', active);
    });
  }

  function setRootModule(moduleName){
    if (moduleName !== 'helpdesk') {
      activeMainModule = moduleName;
      window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
    }
    var visualModule = moduleName === 'helpdesk' ? 'helpdesk' : activeMainModule;
    root.classList.remove('cb-context--provoz', 'cb-context--hr', 'cb-context--smeny', 'cb-context--ukoly', 'cb-context--helpdesk');
    root.classList.add('cb-context--' + visualModule);
    document.body.classList.remove('cb-context--provoz', 'cb-context--hr', 'cb-context--smeny', 'cb-context--ukoly', 'cb-context--helpdesk');
    document.body.classList.add('cb-context--' + visualModule);
  }

  function loadModule(moduleName, pushState, params){
    if (!moduleName) return;
    var requestedKey = makeShellKey(moduleName, params);
    if (pushState && requestedKey === currentShellKey) {
      return;
    }

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
      if (showSmenyLoader && window.CB_LOADER && typeof window.CB_LOADER.show === 'function') {
        window.CB_LOADER.show('Kontroluji plán směn ...');
      }

      fetch(shellUrl, {
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
        })
        .catch(function(error){
          if (window.CB_LOADER && typeof window.CB_LOADER.hide === 'function') {
            window.CB_LOADER.hide();
          }
          root.innerHTML = '<div class="cb-module-load-error">Modul se nepodařilo načíst: ' + String(error.message || error) + '</div>';
        });
    }

    if (moduleName === 'provoz' && params instanceof URLSearchParams && params.get('page') === 'denni_report') {
      fetchSmenyPlanState()
        .then(fetchModule)
        .catch(function(){
          fetchModule(false);
        });
      return;
    }

    fetchModule(false);
  }

  window.CB_LOAD_MODULE = loadModule;

  document.addEventListener('click', function(event){
    var link = event.target.closest ? event.target.closest('[data-cb-module-link="1"]') : null;
    if (!link) return;

    var moduleName = link.getAttribute('data-cb-module') || '';
    if (!moduleName) return;

    event.preventDefault();
    loadModule(moduleName, true);
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
    loadModule(moduleName, true, url.searchParams);
  });
})();
