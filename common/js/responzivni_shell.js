/*
 * Ucel souboru: Globalni responzivni chovani prihlasene kostry IS.
 * Ridi zasouvaci menu a mobilni vyber puvodnich modulu v obou orientacich telefonu.
 * Do 600 px maji vsechny moduly stejnou hlavicku; sirsi mobilni Smeny zachovavaji uspornou listu.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-obal-main="1"]');
  if (!(root instanceof HTMLElement)) {
    return;
  }

  var narrowMenu = window.matchMedia('(max-width: 899px)');
  var shiftsMobile = window.matchMedia('(max-width: 980px)');
  var compactHeader = window.matchMedia('(max-width: 600px)');
  var moduleToggle = root.querySelector('[data-cb-module-toggle]');
  var modulePanel = root.querySelector('#cb-header-modules');

  // Vyber je bez kopii navigace; vsechny polozky obsluhuje puvodni modulovy handler.
  function setModulesOpen(open, restoreFocus) {
    var shouldOpen = open === true && root.classList.contains('is-compact-header');
    root.classList.toggle('is-modules-open', shouldOpen);
    if (moduleToggle instanceof HTMLButtonElement) {
      moduleToggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
      if (restoreFocus && !shouldOpen && root.classList.contains('is-compact-header')) {
        moduleToggle.focus();
      }
    }
  }

  // Kontext modulu ridi puvodni navigace; popisek cteme jen z hlavicky, nikoli z odkazu bocniho menu.
  function syncCompactHeader() {
    var enabled = compactHeader.matches && moduleToggle instanceof HTMLButtonElement;
    root.classList.toggle('is-compact-header', enabled);
    if (!enabled) { setModulesOpen(false); }
    var current = root.querySelector('[data-cb-module-current]');
    var links = root.querySelectorAll('.blok_hlavicka [data-cb-module-link="1"]');
    links.forEach(function (link) {
      if (!root.classList.contains('cb-context--' + link.getAttribute('data-cb-module'))) { return; }
      var name = link.querySelector('.head_module_name');
      var label = name ? name.textContent : (link.getAttribute('data-cb-module') === 'co_link' ? 'Co-Link' : 'E-mail');
      if (current && label) {
        current.textContent = label;
        moduleToggle.setAttribute('aria-label', 'Vybrat modul, aktuálně ' + label);
      }
      // Pristupne oznaceni sleduje stejny kontext jako aktivni prepinace navigace.
      if (modulePanel && modulePanel.contains(link)) { link.setAttribute('aria-current', 'page'); }
    });
    if (modulePanel) {
      modulePanel.querySelectorAll('[aria-current]').forEach(function (link) {
        if (!root.classList.contains('cb-context--' + link.getAttribute('data-cb-module'))) { link.removeAttribute('aria-current'); }
      });
    }
  }

  // Směny podporují i širší telefon na šířku; ostatní moduly zachovávají svůj breakpoint.
  function usesMobileMenu() {
    return narrowMenu.matches || (shiftsMobile.matches && root.classList.contains('cb-context--smeny') && !root.classList.contains('is-smeny-full-shell'));
  }

  function toggleButton() {
    return root.querySelector('[data-cb-menu-toggle="1"]');
  }

  // Mobilni menu je dostupne podle sirky okna v obou orientacich.
  function setMenuOpen(open) {
    var shouldOpen = open === true && usesMobileMenu();
    root.classList.toggle('is-menu-open', shouldOpen);
    document.body.classList.toggle('cb-menu-open', shouldOpen);

    var button = toggleButton();
    if (button instanceof HTMLButtonElement) {
      button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }
  }

  // Pri prechodu na desktop zavre zasouvaci menu.
  function syncShell() {
    syncCompactHeader();
    if (!usesMobileMenu()) {
      setMenuOpen(false);
    }
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    if (target.closest('[data-cb-module-toggle]')) {
      setMenuOpen(false);
      setModulesOpen(!root.classList.contains('is-modules-open'));
      return;
    }
    if (target.closest('[data-cb-module-backdrop]')) {
      setModulesOpen(false, true);
      return;
    }
    var selectedModule = target.closest('[data-cb-module-link="1"]');
    if (selectedModule && selectedModule.getAttribute('data-cb-module-disabled') !== '1') {
      setModulesOpen(false, modulePanel && modulePanel.contains(selectedModule));
    }

    // Návrat pouze odkryje navigaci IS; nevybírá za uživatele jiný modul ani neopouští rozepsaný formulář.
    if (target.closest('[data-smeny-exit]')) {
      setMenuOpen(false);
      root.classList.add('is-smeny-full-shell');
      window.dispatchEvent(new Event('resize'));
      var moduleLink=root.classList.contains('is-compact-header') ? moduleToggle : root.querySelector('.head_module_link:not(.is-disabled)');
      if (moduleLink) moduleLink.focus();
      return;
    }

    if (target.closest('[data-cb-menu-toggle="1"]')) {
      setModulesOpen(false);
      setMenuOpen(!root.classList.contains('is-menu-open'));
      return;
    }

    if (target.closest('[data-cb-menu-close="1"], [data-cb-menu-backdrop="1"]')) {
      setMenuOpen(false);
      return;
    }

    if (root.classList.contains('is-menu-open') && target.closest('.blok_menu a[href]')) {
      setMenuOpen(false);
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      setModulesOpen(false, root.classList.contains('is-modules-open'));
      setMenuOpen(false);
    }
  });

  // Pri tabulatoru mimo nabidku ji zavreme bez presouvani fokusu z noveho ovladace.
  document.addEventListener('focusin', function (event) {
    if (root.classList.contains('is-modules-open') && modulePanel && !modulePanel.contains(event.target) && event.target !== moduleToggle) {
      setModulesOpen(false);
    }
  });

  window.addEventListener('resize', syncShell);
  window.addEventListener('orientationchange', syncShell);
  window.addEventListener('pageshow', syncShell);
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
      syncShell();
    }
  });
  document.addEventListener('cb:main-swapped', function () {
    setModulesOpen(false);
    setMenuOpen(false);
    root.classList.remove('is-smeny-full-shell');
    syncShell();
  });

  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', syncShell);
  }
  if (window.screen.orientation && typeof window.screen.orientation.addEventListener === 'function') {
    window.screen.orientation.addEventListener('change', syncShell);
  }
  if (typeof narrowMenu.addEventListener === 'function') {
    narrowMenu.addEventListener('change', syncShell);
  }

  syncShell();
})();
