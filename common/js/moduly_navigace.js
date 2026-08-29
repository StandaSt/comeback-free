/*
 * Ucel souboru: Ridi klientskou navigaci mezi moduly a internimi strankami aplikace.
 * Soubecne nacteni stejne hlida promenna moduleLoadRunning.
 */
(function(){
  'use strict';

  var config = window.CB_MODULY_NAVIGACE || {};
  var root = document.querySelector('[data-obal-main="1"]');
  var shellUrl = String(config.shellUrl || window.CB_ENDPOINT || 'index.php');
  var publicShellUrl = String(config.publicShellUrl || '');
  var activeMainModule = String(config.activeMainModule || 'provoz');
  var povoleneModuly = ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk', 'administrace'];
  var menuDefs = {
    provoz: {
      title: 'Provoz',
      aria: 'Menu Provozu',
      key: 'page',
      defaultPage: 'prehled',
      items: [
        ['prehled', 'Přehled'],
        ['denni_report', 'Denní report'],
        ['objednavky', 'Objednávky'],
        ['prehled_hodin', 'Přehled hodin']
      ]
    },
    hr: {
      title: 'Personalistika',
      aria: 'Menu personalistiky',
      key: 'page',
      defaultPage: 'prehled',
      items: [
        ['prehled', 'Přehled'],
        ['nabor', 'Nábor'],
        ['zamestnanci', 'Zaměstnanci'],
        ['pozadavky', 'Požadavky'],
        ['pracovni_pomery', 'Pracovní poměry'],
        ['dokumenty', 'Dokumenty'],
        ['skoleni', 'Školení'],
        ['prohlidky', 'Lékařské prohlídky'],
        ['dovolene', 'Dovolené'],
        ['reporty', 'Reporty']
      ]
    },
    smeny: {
      title: 'Směny',
      aria: 'Menu směn',
      key: 'page',
      defaultPage: 'prehled',
      items: [
        ['prehled', 'Přehled'],
        ['pozadavky', 'Požadavky'],
        ['hodnoceni', 'Hodnocení'],
        ['me_smeny', 'Mé směny', ['Aktuální týden', 'Týden + 1', 'Týden + 2']],
        ['planovani_smen', 'Plánování směn', ['Aktuální týden', 'Týden + 1']],
        ['sablony', 'Šablony'],
        ['naplanovane_smeny', 'Naplánované směny', ['Aktuální týden', 'Týden + 1', 'Týden + 2']],
        ['zadane_pozadavky', 'Zadané požadavky', ['Aktuální týden', 'Týden + 1', 'Týden + 2', 'Historie']],
        ['administrace', 'Administrace']
      ]
    },
    ukoly: {
      title: 'Úkoly-požadavky',
      aria: 'Menu úkolů',
      key: 'page',
      defaultPage: 'prehled',
      items: [
        ['prehled', 'Přehled']
      ]
    },
    helpdesk: {
      title: 'HelpDesk',
      aria: 'HelpDesk menu',
      key: 'hd',
      defaultPage: 'all',
      items: [
        ['all', 'Přehled'],
        ['new-ticket', 'Nový tiket'],
        ['mine', 'Moje tikety'],
        ['watched', 'Sledované'],
        ['closed', 'Uzavřené'],
        ['admin', 'Admin']
      ]
    },
    administrace: {
      title: 'Administrace',
      aria: 'Menu administrace',
      key: 'page',
      defaultPage: 'prava_roli',
      items: [
        ['prava_roli', 'Práva rolí'],
        ['individualni_prava', 'Individuální práva uživatele']
      ]
    }
  };

  window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
  if (!root) return;
  var initialParams = new URLSearchParams(window.location.search);
  history.replaceState(null, '', publicShellUrl);
  var currentShellKey = makeShellKey(activeMainModule, initialParams);
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

  function provozPageNeedsRestia(params) {
    var page = params instanceof URLSearchParams ? String(params.get('page') || '') : '';
    return page === '' || page === 'dashboard' || page === 'prehled' || page === 'denni_report' || page === 'objednavky';
  }

  function ensureRestiaBeforeProvoz(moduleName, params){
    if (moduleName !== 'provoz' || !provozPageNeedsRestia(params)) {
      return Promise.resolve(null);
    }
    if (
      !window.CB_RESTIA
      || typeof window.CB_RESTIA.run !== 'function'
    ) {
      return Promise.resolve(null);
    }

    return window.CB_RESTIA.run({
      moduleName: 'provoz',
      loaderText: ''
    });
  }

  function saveActiveModule(moduleName) {
    moduleName = String(moduleName || '').trim();
    if (povoleneModuly.indexOf(moduleName) === -1) {
      return;
    }

    var body = new URLSearchParams();
    body.set('module', moduleName);

    fetch(shellUrl, {
      method: 'POST',
      headers: {
        'X-Comeback-Active-Module': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).catch(function(){});
  }

  function setActive(moduleName){
    var links = Array.prototype.slice.call(document.querySelectorAll('[data-cb-module-link="1"]'));
    links.forEach(function(link){
      var active = link.getAttribute('data-cb-module') === moduleName;
      link.classList.toggle('is-active', active);
    });
  }

  function hideHeaderUpdate() {
    var box = root.querySelector('[data-cb-head-update="1"]');
    if (box instanceof HTMLElement) {
      box.hidden = true;
    }
  }

  function moduleUrl(moduleName, pageKey, pageValue) {
    var url = new URL(publicShellUrl || shellUrl || window.location.href, window.location.href);
    url.search = '';
    url.searchParams.set('m', moduleName);
    if (pageKey && pageValue) {
      url.searchParams.set(pageKey, pageValue);
    }
    return url.pathname + url.search;
  }

  function currentMenuPage(def, params) {
    if (!(params instanceof URLSearchParams)) {
      return def.defaultPage;
    }
    return String(params.get(def.key) || params.get('page') || def.defaultPage);
  }

  function updateMenuBottom(bottom, moduleName) {
    if (!(bottom instanceof HTMLElement)) return bottom;
    var themeInput = bottom.querySelector('input[name="cb_theme_module"]');
    if (themeInput instanceof HTMLInputElement) {
      themeInput.value = moduleName;
    }
    var profileLink = bottom.querySelector('.blok_menu_user_profile');
    if (profileLink instanceof HTMLAnchorElement) {
      var profileKey = moduleName === 'helpdesk' ? 'hd' : 'page';
      profileLink.href = moduleUrl(moduleName, profileKey, 'uprava_profilu');
    }
    var adminLink = bottom.querySelector('[data-cb-module-link="1"][data-cb-module="administrace"]');
    if (adminLink instanceof HTMLElement) {
      var adminList = adminLink.closest('.blok_menu_list');
      if (adminList instanceof HTMLElement) {
        adminList.style.display = moduleName === 'administrace' ? 'none' : '';
      }
    }
    return bottom;
  }

  function renderImmediateMenu(moduleName, params) {
    var def = menuDefs[moduleName];
    if (!def) return;

    var oldMenu = root.querySelector('.blok_menu');
    var oldBottom = oldMenu ? oldMenu.querySelector('.blok_menu_bottom') : null;
    var bottom = oldBottom instanceof HTMLElement ? oldBottom.cloneNode(true) : document.createElement('div');
    bottom.classList.add('blok_menu_bottom');
    updateMenuBottom(bottom, moduleName);

    var nav = document.createElement('nav');
    nav.className = 'blok_menu';
    nav.setAttribute('aria-label', def.aria);

    var title = document.createElement('h2');
    title.className = 'blok_menu_title';
    title.textContent = def.title;
    nav.appendChild(title);

    var list = document.createElement('ul');
    list.className = 'blok_menu_list';
    var activePage = currentMenuPage(def, params);

    def.items.forEach(function(itemDef){
      var page = String(itemDef[0] || '');
      var label = String(itemDef[1] || '');
      var children = Array.isArray(itemDef[2]) ? itemDef[2] : [];
      if (!page || !label) return;

      var item = document.createElement('li');
      item.className = 'blok_menu_item';

      var link = document.createElement('a');
      link.className = 'blok_menu_btn' + (page === activePage ? ' is-active' : '');
      link.href = moduleUrl(moduleName, def.key, page);

      var labelNode = document.createElement('span');
      labelNode.textContent = label;
      link.appendChild(labelNode);

      if (children.length > 0) {
        var chev = document.createElement('span');
        chev.className = 'blok_menu_chev';
        chev.setAttribute('aria-hidden', 'true');
        chev.textContent = '⌄';
        link.appendChild(chev);
      }

      item.appendChild(link);

      if (children.length > 0) {
        var submenu = document.createElement('ul');
        submenu.className = 'blok_submenu';
        children.forEach(function(childLabel){
          var childItem = document.createElement('li');
          var childButton = document.createElement('button');
          childButton.type = 'button';
          childButton.className = 'blok_submenu_btn';
          childButton.textContent = String(childLabel || '');
          childItem.appendChild(childButton);
          submenu.appendChild(childItem);
        });
        item.appendChild(submenu);
      }

      list.appendChild(item);
    });

    nav.appendChild(list);
    nav.appendChild(bottom);

    if (oldMenu instanceof HTMLElement) {
      oldMenu.replaceWith(nav);
    } else {
      var pp = root.querySelector('.pp');
      if (pp instanceof HTMLElement) {
        root.insertBefore(nav, pp);
      } else {
        root.appendChild(nav);
      }
    }
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
    activeMainModule = moduleName;
    window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
    var visualModule = activeMainModule;
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
    var sourceMainModule = activeMainModule;
    moduleLoadRunning = true;
    saveActiveModule(moduleName);
    setRootModule(moduleName);
    setActive(moduleName);
    hideHeaderUpdate();
    renderImmediateMenu(moduleName, params);
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
      if (['provoz', 'hr', 'smeny', 'ukoly'].indexOf(sourceMainModule) === -1) {
        sourceMainModule = 'provoz';
      }
      body.set('cb_helpdesk_source_module', sourceMainModule);
      window.CB_HELPDESK_SOURCE_MODULE = sourceMainModule;
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
          return ensureRestiaBeforeProvoz(moduleName, params)
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

    if (moduleName === 'provoz' && provozPageNeedsRestia(params)) {
      setPageLoaderDetail('Aktualizuji objednávky ...');
      ensureRestiaBeforeProvoz(moduleName, params)
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

    if (link.getAttribute('data-cb-module-disabled') === '1') {
      event.preventDefault();
      return;
    }

    var moduleName = link.getAttribute('data-cb-module') || '';
    if (!moduleName) return;

    event.preventDefault();
    if (moduleName === activeMainModule) {
      return;
    }
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

  root.addEventListener('click', function(event){
    var button = event.target.closest ? event.target.closest('[data-report-promenne-save="1"]') : null;
    if (!(button instanceof HTMLElement)) {
      return;
    }

    event.preventDefault();
    var form = button.closest('[data-report-promenne-form="1"]');
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    var body = new URLSearchParams(new FormData(form));
    var actionUrl = form.getAttribute('action') || moduleUrl('provoz', 'page', 'nastaveni_reportu');

    button.setAttribute('disabled', 'disabled');
    fetch(actionUrl, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Comeback-Report-Promenne': '1',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function(response){
      return response.json().then(function(json){
        if (!response.ok || !json.ok) {
          throw new Error(json && json.err ? String(json.err) : 'Uložení selhalo.');
        }
      });
    }).then(function(){
      return fetch(actionUrl, {
        method: 'GET',
        headers: {
          'X-Comeback-PP-Only': '1',
          'Accept': 'text/html'
        },
        credentials: 'same-origin'
      });
    }).then(function(response){
      if (!response.ok) {
        throw new Error('Načtení stránky selhalo.');
      }
      return response.text();
    }).then(function(html){
      var pp = root.querySelector('.pp');
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      var nextPp = wrap.querySelector('.pp');
      if (pp instanceof HTMLElement && nextPp instanceof HTMLElement) {
        pp.replaceWith(nextPp);
      }
    }).catch(function(error){
      button.removeAttribute('disabled');
      window.alert(error && error.message ? error.message : 'Uložení selhalo.');
    });
  });

  root.addEventListener('submit', function(event){
    var form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form || (form.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
    if (!form.querySelector('input[name="cb_action"]')) return;
    if (form.getAttribute('data-cb-form-pending') === '1') return;

    var actionUrl;
    try {
      actionUrl = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    } catch (e) {
      return;
    }
    if (actionUrl.origin !== window.location.origin || actionUrl.searchParams.get('m') !== 'hr') return;

    event.preventDefault();
    form.setAttribute('data-cb-form-pending', '1');
    var submitButtons = Array.prototype.slice.call(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
    submitButtons.forEach(function(button){ button.setAttribute('disabled', 'disabled'); });

    fetch(actionUrl.toString(), {
      method: 'POST',
      headers: {
        'X-Comeback-Form': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      body: new URLSearchParams(new FormData(form)).toString(),
      credentials: 'same-origin'
    }).then(function(response){
      return response.json().then(function(result){
        if (!response.ok || !result || typeof result.redirect !== 'string') {
          throw new Error(result && result.message ? String(result.message) : 'Formulář se nepodařilo odeslat.');
        }
        return result;
      });
    }).then(function(result){
      var redirectUrl = new URL(result.redirect, window.location.href);
      var moduleName = redirectUrl.searchParams.get('m') || '';
      if (povoleneModuly.indexOf(moduleName) === -1) {
        window.location.assign(redirectUrl.toString());
        return;
      }
      loadModule(moduleName, false, redirectUrl.searchParams);
    }).catch(function(error){
      form.removeAttribute('data-cb-form-pending');
      submitButtons.forEach(function(button){ button.removeAttribute('disabled'); });
      window.alert(error && error.message ? error.message : 'Formulář se nepodařilo odeslat.');
    });
  });

  if (config.initialAutoLoad === true) {
    window.setTimeout(function(){
      loadModule(activeMainModule, false, initialParams);
    }, 0);
  }
})();
