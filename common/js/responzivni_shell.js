/*
 * Ucel souboru: Globalni responzivni chovani prihlasene kostry IS.
 * Udrzuje upozorneni na orientaci telefonu a zasouvaci menu napric moduly.
 * Mobilní Směny mají úspornou lištu s návratem k celé hlavičce bez změny přihlášení.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-obal-main="1"]');
  if (!(root instanceof HTMLElement)) {
    return;
  }

  var narrowMenu = window.matchMedia('(max-width: 899px)');
  var shiftsMobile = window.matchMedia('(max-width: 980px)');

  // Směny podporují i širší telefon na šířku; ostatní moduly zachovávají svůj breakpoint.
  function usesMobileMenu() {
    return narrowMenu.matches || (shiftsMobile.matches && root.classList.contains('cb-context--smeny') && !root.classList.contains('is-smeny-full-shell'));
  }

  function toggleButton() {
    return root.querySelector('[data-cb-menu-toggle="1"]');
  }

  function setMenuOpen(open) {
    var portraitAllowed = root.classList.contains('cb-context--smeny');
    var shouldOpen = open === true
      && usesMobileMenu()
      && (!root.classList.contains('is-small-portrait') || portraitAllowed);
    root.classList.toggle('is-menu-open', shouldOpen);
    document.body.classList.toggle('cb-menu-open', shouldOpen);

    var button = toggleButton();
    if (button instanceof HTMLButtonElement) {
      button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    }
  }

  function currentViewport() {
    var viewport = window.visualViewport;
    return {
      width: viewport ? viewport.width : window.innerWidth,
      height: viewport ? viewport.height : window.innerHeight
    };
  }

  function syncOrientation() {
    var viewport = currentViewport();
    var smallPortrait = viewport.width <= 767 && viewport.height > viewport.width;
    root.classList.toggle('is-small-portrait', smallPortrait);
    if (smallPortrait) {
      setMenuOpen(false);
    }
  }

  function syncShell() {
    syncOrientation();
    if (!usesMobileMenu()) {
      setMenuOpen(false);
    }
  }

  document.addEventListener('click', function (event) {
    var target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    // Návrat pouze odkryje navigaci IS; nevybírá za uživatele jiný modul ani neopouští rozepsaný formulář.
    if (target.closest('[data-smeny-exit]')) {
      setMenuOpen(false);
      root.classList.add('is-smeny-full-shell');
      window.dispatchEvent(new Event('resize'));
      var moduleLink=root.querySelector('.head_module_link:not(.is-disabled)');
      if (moduleLink) moduleLink.focus();
      return;
    }

    if (target.closest('[data-cb-menu-toggle="1"]')) {
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
      setMenuOpen(false);
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
    setMenuOpen(false);
    root.classList.remove('is-smeny-full-shell');
    syncOrientation();
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
