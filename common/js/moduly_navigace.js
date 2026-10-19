/*
 * Ucel souboru: Ridi klientskou navigaci mezi moduly a internimi strankami aplikace.
 * Soubecne nacteni stejne hlida promenna moduleLoadRunning.
 * Výběr týdnů je přímo ve stránkách Směn; navigace zachovává jejich parametry i po AJAX výměně obsahu.
 * Chybové odpovědi načítání modulů čte společná knihovna, aby se neztratil konkrétní důvod.
 * Události navigace umožňují historii rozlišit požadavek, dokončené zobrazení i klik bez přechodu.
 * Kompletní menu připravuje PHP podle práv; přepnutí používá hotové šablony a obsah ani Restia menu nepřepisují.
 */
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
  var initialParams = new URLSearchParams(window.location.search);
  history.replaceState(null, '', publicShellUrl);
  var currentShellKey = makeShellKey(activeMainModule, initialParams);
  var moduleLoadRunning = false;
  var pageLoaderTimer = 0;
  var pageBusyHandles = [];
  var pageBusyHandleSequence = 0;
  var pageBusyLoaderTimer = 0;

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
        return response.json().catch(function(){ return {}; }).then(function(state){
          if (!response.ok) {
            throw new Error(window.CB_CHYBY.responseMessage(response, state, 'Stav plánovaných směn se nepodařilo načíst.'));
          }
          return state;
        });
      })
      .then(function(state){
        return !!(state && Number(state.should_run || 0) === 1);
      });
  }

  function showModuleError(error){
    stopPageLoaderTimer();
    // Síťový výpadek má stejně srozumitelný popis jako chyba vrácená serverem.
    var detail = window.CB_CHYBY.errorMessage(error, 'Modul se nepodařilo načíst.');
    var message = detail === window.CB_CHYBY.publicMessage
      ? detail
      : 'Modul se nepodařilo načíst: ' + (detail || 'Zkuste stránku obnovit.');
    var errorBox = document.createElement('div');
    errorBox.className = 'cb-module-load-error';
    errorBox.textContent = message;
    var pp = root.querySelector('.pp');
    if (pp instanceof HTMLElement) {
      pp.innerHTML = '';
      pp.appendChild(errorBox);
      pp.classList.remove('is-page-loading');
      return;
    }
    pp = document.createElement('section');
    pp.className = 'pp';
    pp.appendChild(errorBox);
    root.appendChild(pp);
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

  function stopPageBusyLoaderTimer() {
    if (pageBusyLoaderTimer) {
      window.clearInterval(pageBusyLoaderTimer);
      pageBusyLoaderTimer = 0;
    }
  }

  function removePageBusyLoader() {
    stopPageBusyLoaderTimer();
    var loader = root.querySelector('.cb_page_loader--floating');
    if (loader instanceof HTMLElement) {
      loader.remove();
    }
  }

  function showPageBusyLoader(text, detail) {
    removePageBusyLoader();
    var loader = document.createElement('div');
    loader.className = 'cb_page_loader cb_page_loader--floating';
    loader.setAttribute('role', 'status');
    loader.setAttribute('aria-live', 'polite');
    loader.setAttribute('aria-atomic', 'true');

    var textNode = document.createElement('span');
    textNode.className = 'cb_page_loader_text';
    textNode.textContent = String(text || 'Načítám ...');
    loader.appendChild(textNode);

    var detailNode = document.createElement('span');
    detailNode.className = 'cb_page_loader_detail';
    detailNode.textContent = String(detail || '');
    loader.appendChild(detailNode);

    var timeNode = document.createElement('span');
    timeNode.className = 'cb_page_loader_time';
    timeNode.textContent = '0,0 s';
    loader.appendChild(timeNode);
    root.appendChild(loader);

    var startedAt = performance.now();
    pageBusyLoaderTimer = window.setInterval(function () {
      if (!timeNode.isConnected) {
        stopPageBusyLoaderTimer();
        return;
      }
      timeNode.textContent = formatElapsed(performance.now() - startedAt);
    }, 100);
  }

  function startPageBusy(text, detail) {
    var handle = ++pageBusyHandleSequence;
    pageBusyHandles.push(handle);
    document.body.classList.add('cb-page-busy');
    root.setAttribute('aria-busy', 'true');
    if (String(text || '').trim() !== '') {
      showPageBusyLoader(text, detail);
    }
    return handle;
  }

  function stopPageBusy(handle) {
    var index = pageBusyHandles.indexOf(handle);
    if (index === -1) return;
    pageBusyHandles.splice(index, 1);
    if (pageBusyHandles.length > 0) return;
    document.body.classList.remove('cb-page-busy');
    root.removeAttribute('aria-busy');
    removePageBusyLoader();
  }

  window.CB_PAGE_BUSY = {
    start: startPageBusy,
    stop: stopPageBusy
  };

  function blockPageBusyEvent(event) {
    if (pageBusyHandles.length === 0) return;
    event.preventDefault();
    event.stopImmediatePropagation();
  }

  document.addEventListener('pointerdown', blockPageBusyEvent, true);
  document.addEventListener('click', blockPageBusyEvent, true);

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
      if (page === 'ai_analytik') return 'Načítám AI analytika ...';
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
      pp = document.createElement('section');
      pp.className = 'pp is-page-loading';
      pp.setAttribute('data-module', moduleName);
      pp.innerHTML = html;
      root.appendChild(pp);
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

  function isProductionDailyReport(params) {
    if (!(params instanceof URLSearchParams) || params.get('page') !== 'denni_report') return false;
    var requestedBranch = Number(params.get('zr_id_pob') || 0);
    return requestedBranch > 0 ? requestedBranch === 7 : Number(config.denniReportMainBranchId || 0) === 7;
  }

  function provozPageNeedsRestia(params) {
    var page = params instanceof URLSearchParams ? String(params.get('page') || '') : '';
    if (page === 'denni_report' && isProductionDailyReport(params)) return false;
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
      moduleName: 'provoz'
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

  function syncHeader(moduleName) {
    setActive(moduleName);
  }

  // Obsah modulu může obsahovat i dialogy; kopie menu z odpovědi nikdy nenahradí již zobrazené menu.
  function replaceModuleContent(html, moduleChanged) {
    var response=document.createElement('template'); response.innerHTML=html;
    var nextPp=response.content.querySelector('.pp');
    if (!(nextPp instanceof HTMLElement)) throw new Error('Odpověď modulu neobsahuje pracovní plochu.');
    if (moduleChanged) {
      response.content.querySelectorAll('.blok_menu').forEach(function (menu) { menu.remove(); });
      Array.from(root.children).forEach(function (child) {
        if (!child.classList.contains('blok_hlavicka') && !child.classList.contains('blok_menu')) child.remove();
      });
      root.appendChild(response.content);
    } else {
      var currentPp=root.querySelector('.pp');
      if (!(currentPp instanceof HTMLElement)) throw new Error('Chybí pracovní plocha aplikace.');
      currentPp.replaceWith(nextPp);
    }
    return nextPp;
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

  // Označení vychází z metadat stejné serverové definice, včetně detailů patřících pod společnou položku.
  function activateMenuPage(menu, page) {
    if (!menu) return;
    menu.querySelectorAll('[data-menu-pages]').forEach(function (item) {
      var pages=JSON.parse(item.dataset.menuPages), link=item.querySelector('.blok_menu_btn');
      if (link) link.classList.toggle('is-active',pages.indexOf(page)!==-1);
    });
  }

  // Kompletní menu je v dokumentu před kliknutím; žádná položka ani pravidlo práv se zde nevytváří.
  function renderModuleMenu(moduleName, params, sourceModule) {
    var template=document.querySelector('template[data-cb-module-menu="'+moduleName+'"]');
    if (!(template instanceof HTMLTemplateElement)) throw new Error('Menu tohoto modulu není pro aktuální profil dostupné. Obnovte stránku.');
    var nav=template.content.querySelector('.blok_menu').cloneNode(true);
    var page=params instanceof URLSearchParams ? params.get(nav.dataset.menuKey)||params.get('page') : '';
    activateMenuPage(nav,page||nav.dataset.menuDefault);
    // HelpDesk zachová zdroj, ze kterého byl otevřen; jde pouze o kontext odkazů, nikoli o definici menu.
    if (moduleName==='helpdesk') {
      var source=params instanceof URLSearchParams ? params.get('src') : '';
      if (['provoz','hr','smeny','ukoly'].indexOf(source)===-1) source=sourceModule;
      if (['provoz','hr','smeny','ukoly'].indexOf(source)===-1) source='provoz';
      nav.querySelectorAll('a[href]').forEach(function (link) {
        var url=new URL(link.href,window.location.href);
        if (url.searchParams.get('m')==='helpdesk') { url.searchParams.set('src',source); link.href=url.pathname+url.search; }
      });
    }
    // Barva se mohla změnit od načtení šablon; zápatí musí převzít současný odstín.
    var theme=nav.querySelector('[data-cb-theme-form]'), level=Number(document.documentElement.dataset.themeLevel||0);
    if (theme) {
      theme.dataset.themeLevel=String(level);
      theme.querySelector('[data-cb-theme-value]').textContent=String(level);
      theme.querySelector('[data-cb-theme-delta="-1"]').disabled=level<=0;
      theme.querySelector('[data-cb-theme-delta="1"]').disabled=level>=6;
      theme.querySelector('[name="cb_theme_return"]').value=moduleUrl(moduleName,nav.dataset.menuKey,page||nav.dataset.menuDefault);
    }
    var oldMenu=root.querySelector('.blok_menu');
    if (oldMenu) oldMenu.replaceWith(nav); else root.insertBefore(nav,root.querySelector('.pp'));
  }

  function setClickedMenuActive(link) {
    if (!(link instanceof HTMLElement)) return;

    var menu = link.closest('.blok_menu');
    if (menu instanceof HTMLElement && link.classList.contains('blok_menu_btn')) {
      Array.prototype.slice.call(menu.querySelectorAll('.blok_menu_btn.is-active, .blok_menu_btn.active')).forEach(function(btn){
        btn.classList.remove('is-active', 'active');
      });
      link.classList.add('is-active');
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
      document.dispatchEvent(new CustomEvent('cb:menu-no-effect',{detail:{module:moduleName}}));
      return;
    }
    // Zastaralá nebo nepovolená adresa nesmí přepnout kontext a ponechat načítání zablokované.
    if (moduleName!==activeMainModule && !document.querySelector('template[data-cb-module-menu="'+moduleName+'"]')) {
      showModuleError(new Error('Tento modul není pro aktuální profil dostupný. Obnovte stránku.'));
      return;
    }
    // Stránka plánovače uvolní svůj zámek před nahrazením obsahu navigací.
    document.dispatchEvent(new CustomEvent('cb:main-before-load', { detail: { module: moduleName, params: params instanceof URLSearchParams ? params.toString() : '' } }));
    var sourceMainModule = activeMainModule;
    var moduleChanged = moduleName !== activeMainModule;
    moduleLoadRunning = true;
    var pageBusyHandle = startPageBusy();
    if (moduleChanged) {
      saveActiveModule(moduleName);
      setRootModule(moduleName);
      syncHeader(moduleName);
      renderModuleMenu(moduleName, params, sourceMainModule);
    }
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
      var requestedSourceModule = params instanceof URLSearchParams ? String(params.get('src') || '') : '';
      if (['provoz', 'hr', 'smeny', 'ukoly'].indexOf(requestedSourceModule) !== -1) {
        sourceMainModule = requestedSourceModule;
      }
      if (['provoz', 'hr', 'smeny', 'ukoly'].indexOf(sourceMainModule) === -1) {
        sourceMainModule = 'provoz';
      }
      body.set('cb_helpdesk_source_module', sourceMainModule);
      window.CB_HELPDESK_SOURCE_MODULE = sourceMainModule;
    }
    function fetchModule(showSmenyLoader){
      var headers = {
        'X-Comeback-Shell-Module': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      };
      if (!moduleChanged) {
        headers['X-Comeback-PP-Only'] = '1';
      }
      return fetch(shellUrl, {
        method: 'POST',
        headers: headers,
        body: body.toString(),
        credentials: 'same-origin'
      })
        .then(function(response){
          // Stejný převod jako při přepnutí reportu zachová JSON i HTML popis chyby.
          return window.CB_CHYBY.readHtml(response, 'Modul se nepodařilo načíst.');
        })
        .then(function(html){
          if (typeof window.__CB_HELPDESK_CLEANUP__ === 'function') {
            window.__CB_HELPDESK_CLEANUP__();
            window.__CB_HELPDESK_CLEANUP__ = null;
          }
          setRootModule(moduleName);
          stopPageLoaderTimer();
          var nextPp=replaceModuleContent(html,moduleChanged);
          activateMenuPage(root.querySelector('.blok_menu'),nextPp.dataset.page||'');
          currentShellKey = requestedKey;
          document.dispatchEvent(new CustomEvent('cb:main-swapped'));
          if (moduleName === 'hr' && window.CB_HR_INIT) {
            window.CB_HR_INIT(root);
          }
          if (moduleName === 'helpdesk' && window.CB_HELPDESK_INIT) {
            window.CB_HELPDESK_INIT(root);
          }
          syncHeader(moduleName);
        });
    }

    if (moduleName === 'provoz' && params instanceof URLSearchParams && params.get('page') === 'denni_report' && !isProductionDailyReport(params)) {
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
          stopPageBusy(pageBusyHandle);
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
          stopPageBusy(pageBusyHandle);
        });
      return;
    }

    fetchModule(false)
      .catch(showModuleError)
      .finally(function(){
        moduleLoadRunning = false;
        stopPageBusy(pageBusyHandle);
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
    if (pageBusyHandles.length > 0) {
      return;
    }
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
    if (pageBusyHandles.length > 0) {
      return;
    }
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
      return response.json().catch(function(){ return {}; }).then(function(json){
        if (!response.ok || !json.ok) {
          throw new Error(window.CB_CHYBY.responseMessage(response, json, 'Uložení nastavení reportu se nepodařilo.'));
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
      // Také obnovení stránky po uložení musí zachovat důvod případné chyby.
      return window.CB_CHYBY.readHtml(response, 'Načtení stránky selhalo.');
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

  root.addEventListener('click', function(event){
    var button = event.target.closest ? event.target.closest('[data-pobocka-provoz-save="1"]') : null;
    if (!(button instanceof HTMLElement)) {
      return;
    }

    event.preventDefault();
    var confirmation = String(button.getAttribute('data-confirm') || '');
    if (confirmation !== '' && !window.confirm(confirmation)) {
      return;
    }
    var form = button.closest('[data-pobocka-provoz-form="1"]');
    if (!(form instanceof HTMLFormElement) || !form.reportValidity()) {
      return;
    }

    var body = new URLSearchParams(new FormData(form));
    var actionUrl = form.getAttribute('action') || moduleUrl('provoz', 'page', 'nastaveni_reportu');
    button.setAttribute('disabled', 'disabled');
    fetch(actionUrl, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Comeback-Pobocka-Provoz': '1',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function(response){
      return response.json().catch(function(){ return {}; }).then(function(json){
        if (!response.ok || !json.ok) {
          throw new Error(window.CB_CHYBY.responseMessage(response, json, 'Uložení nastavení provozu se nepodařilo.'));
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
      // Také obnovení stránky po uložení musí zachovat důvod případné chyby.
      return window.CB_CHYBY.readHtml(response, 'Načtení stránky selhalo.');
    }).then(function(html){
      var pp = root.querySelector('.pp');
      var wrap = document.createElement('div');
      wrap.innerHTML = html;
      var nextPp = wrap.querySelector('.pp');
      if (!(pp instanceof HTMLElement) || !(nextPp instanceof HTMLElement)) {
        throw new Error('Obnovená stránka nemá platný obsah.');
      }
      pp.replaceWith(nextPp);
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
      return response.json().catch(function(){ return {}; }).then(function(result){
        if (!response.ok || !result || typeof result.redirect !== 'string') {
          var data = result && typeof result.message === 'string' ? {err: result.message} : result;
          throw new Error(window.CB_CHYBY.responseMessage(response, data, 'Formulář se nepodařilo odeslat.'));
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
