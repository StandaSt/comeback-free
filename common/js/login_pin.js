/* Rucni zadavani PINu, povinna prvni zmena a soubezne cekani na 2FA; tajne hodnoty se neukladaji do prohlizece. */
(function () {
  'use strict';
  var form = document.querySelector('[data-pin-form]');
  var changeForm = document.querySelector('[data-pin-change]');
  // Vsechny PINy zachovaji pocatecni nuly, dovoli presne ctyri cislice a po navratu zustanou prazdne.
  document.querySelectorAll('.cb-pin-input').forEach(function (input) {
    input.addEventListener('input', function () { input.value = input.value.replace(/[^0-9]/g, '').slice(0, 4); });
    window.addEventListener('pageshow', function () { input.value = ''; });
  });
  if (form) {
    var input = form.querySelector('[name="pin"]');
    form.addEventListener('click', function (event) {
      var button = event.target.closest('button[type="button"]');
      if (!button) return;
      if (button.hasAttribute('data-pin-digit') && input.value.length < 4) input.value += button.getAttribute('data-pin-digit');
      if (button.hasAttribute('data-pin-clear')) input.value = '';
      if (button.hasAttribute('data-pin-back')) input.value = input.value.slice(0, -1);
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }
  if (changeForm) {
    var newPin = changeForm.querySelector('[name="novy_pin"]');
    var confirmation = changeForm.querySelector('[name="novy_pin_znovu"]');
    changeForm.addEventListener('input', function () {
      confirmation.setCustomValidity(confirmation.value && confirmation.value !== newPin.value ? 'PINy se neshodují.' : '');
    });
  }
  var card = document.querySelector('[data-pin-push-url]');
  if (!card || typeof window.fetch !== 'function') return;
  var busy = false;
  var stopped = false;
  // Polling pouziva pouze session; verejna stranka nedostane token push vyzvy ani identitu jineho uctu.
  function checkPush() {
    if (busy || stopped) return;
    busy = true;
    window.fetch(card.getAttribute('data-pin-push-url'), { credentials:'same-origin', cache:'no-store' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(function (data) {
        if (!data || !data.ok) return;
        if (data.stav === 'ok' || data.stav === 'ne') {
          stopped = true;
          var target = new URL(data.cil || window.location.href, window.location.href);
          if (target.origin === window.location.origin) window.location.assign(target.href);
        } else if (data.stav === 'exp') {
          stopped = true;
          var status = card.querySelector('.cb-pin-push-status');
          if (status) status.textContent = 'Potvrzení notifikací vypršelo. Můžete zadat PIN.';
        }
      }).catch(function () { /* Vypadek push neblokuje zadani PINu. */ })
      .finally(function () { busy = false; });
  }
  document.querySelectorAll('form').forEach(function (item) { item.addEventListener('submit', function () { stopped = true; }); });
  window.setInterval(checkPush, 2000);
  checkPush();
}());
