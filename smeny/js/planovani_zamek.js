/* Účel: Udržuje zámek jen podle skutečné aktivity, uvolní jej při odchodu a po 20 minutách nečinnosti přejde na přehled; důvod ukončení předá historii. */
(function () {
  'use strict';
  var current = null;
  var limit = 20 * 60 * 1000;

  // Stejná stránka a pobočka smějí po uložení či obnovení pokračovat se stejným zámkem.
  function samePage(url, branch) {
    var target = new URL(url, window.location.href);
    return target.origin === window.location.origin && target.searchParams.get('m') === 'smeny' &&
      target.searchParams.get('page') === 'planovani_smen' && target.searchParams.get('id_pob') === branch;
  }

  // Každý požadavek nese původní token; opožděný odchod nemůže uvolnit cizí plánování.
  function body(state, action) {
    var values = new URLSearchParams(state.fields);
    values.set('action', action);
    values.set('necinost_ms', String(Math.max(0, Date.now() - state.lastActivity)));
    return values;
  }

  // Odchod je jednorázový a funguje i během zavírání karty; server stále ověřuje relaci a CRF.
  function release(state) {
    if (state.released) return;
    state.released = true;
    var values = body(state, 'smeny_plan_zamek_odchod');
    if (!navigator.sendBeacon(state.form.action, values)) {
      fetch(state.form.action, {method:'POST', body:values, credentials:'same-origin', keepalive:true}).catch(function () {});
    }
  }

  // Zablokování před přesměrováním znemožní odeslání staré směny i při pomalé navigaci.
  function leave(state, reason) {
    if (state.finished) return;
    state.finished = true;
    // Historie rozlišuje nečinnost a neúspěšné ověření; bezpečné ukončení proběhne nezávisle na loggeru.
    document.dispatchEvent(new CustomEvent('cb:smeny-planning-ended',{detail:{reason:reason || 'zamek_nedostupny'}}));
    state.page.querySelectorAll('button,input,select,textarea').forEach(function (control) { control.disabled = true; });
    release(state);
    window.location.replace(state.page.dataset.lockOverview);
  }

  // Kontrola času vždy předchází uznání nové aktivity, zejména po probuzení počítače.
  function expired(state) {
    if (state.finished || state.released) return true;
    if (Date.now() - state.lastActivity >= limit) { leave(state,'necinost'); return true; }
    return false;
  }

  // Síťová chyba nebo ztráta vlastnictví znamená konec editace, nikoliv tiché převzetí zámku.
  function check(state, renew) {
    if (expired(state)) return Promise.resolve(false);
    return fetch(state.form.action, {method:'POST', body:body(state, renew ? 'smeny_plan_zamek_aktivita' : 'smeny_plan_zamek_stav'), credentials:'same-origin', cache:'no-store'})
      .then(function (response) { if (!response.ok) throw new Error('Zámek není dostupný.'); return response.json(); })
      .then(function (result) { if (!result.ok) throw new Error('Zámek není dostupný.'); return !state.finished && !state.released; })
      .catch(function () { if (!state.released) leave(state); return false; });
  }

  // Inicializace po výměně obsahu uklidí původní časovač a převezme serverovou zbývající platnost.
  function init() {
    if (current) clearInterval(current.timer);
    var page = document.querySelector('[data-smeny-lock]');
    current = null;
    if (!page) return;
    var form = page.querySelector('[data-smeny-lock-form]');
    if (!form) return;
    var remaining = Math.max(0, Math.min(1200, Number(page.dataset.lockRemaining)));
    var state = {page:page, form:form, fields:new URLSearchParams(new FormData(form)), lastActivity:Date.now() - (1200 - remaining) * 1000,
      lastSent:0, lastRequest:0, released:false, finished:false, preserve:false, checking:false};
    state.lastSent = state.lastActivity;
    current = state;
    state.timer = setInterval(function () {
      if (expired(state)) return;
      if (state.lastActivity > state.lastSent && !state.checking && Date.now() - state.lastRequest >= 15000) {
        state.lastSent = state.lastActivity;
        state.lastRequest = Date.now();
        state.checking = true;
        check(state, true).finally(function () { state.checking = false; });
      }
    }, 1000);
    // Čtení stránky samo zámek neprodlouží, ale ihned ověří jeho stále platného vlastníka.
    check(state, false);
  }

  // Pohyb, práce s klávesnicí a posun uvnitř plánování představují skutečnou aktivitu.
  ['pointerdown','pointermove','keydown','wheel','touchstart','input','click','change'].forEach(function (eventName) {
    document.addEventListener(eventName, function (event) {
      var state = current;
      if (!state || !state.page.contains(event.target)) return;
      if (expired(state)) { event.preventDefault(); event.stopImmediatePropagation(); return; }
      state.lastActivity = Date.now();
    }, {capture:true, passive:false});
  });

  // Odeslání směny i automatické formuláře musí projít kontrolou nečinnosti.
  document.addEventListener('submit', function (event) {
    var state = current;
    if (!state || !state.page.contains(event.target)) return;
    if (expired(state)) { event.preventDefault(); event.stopImmediatePropagation(); return; }
    var form = event.target;
    // Pole či tlačítko name="action" překryje DOM vlastnost form.action; adresu čteme z atributu.
    var target = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') {
      target.search = new URLSearchParams(new FormData(form)).toString();
    }
    state.preserve = samePage(target.href, state.page.dataset.lockBranch);
    if (!state.preserve) release(state);
  }, true);

  // Společná navigace mění obsah bez pagehide, proto odchod hlásí ještě před jeho nahrazením.
  document.addEventListener('cb:main-before-load', function (event) {
    var state = current;
    if (!state) return;
    var detail = event.detail || {};
    var params = new URLSearchParams(detail.params || '');
    var stay = detail.module === 'smeny' && params.get('page') === 'planovani_smen' &&
      (!params.has('id_pob') || params.get('id_pob') === state.page.dataset.lockBranch);
    if (!stay) release(state);
  });

  // Navigation API rozliší obnovení stejné adresy od skutečného opuštění či zavření karty.
  if (window.navigation) window.navigation.addEventListener('navigate', function (event) {
    if (!current) return;
    current.preserve = samePage(event.destination.url, current.page.dataset.lockBranch);
  });
  window.addEventListener('pagehide', function () { if (current && !current.preserve) release(current); });

  // Probuzená karta se ověří před další prací; ztracený nebo propadlý zámek se nikdy neobnovuje.
  function resume() {
    var state = current;
    if (!state || document.hidden) return;
    // Návrat z historie nesmí oživit dokument, který už odeslal uvolnění svého zámku.
    if (state.released) { leave(state); return; }
    if (expired(state)) return;
    state.page.inert = true;
    check(state, false).then(function (ok) { if (ok) state.page.inert = false; });
  }
  window.addEventListener('focus', resume);
  window.addEventListener('pageshow', resume);
  document.addEventListener('visibilitychange', resume);
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('cb:main-swapped', init);
  init();
}());
