/*
 * Ovladani formulare noveho hesla.
 * Resi pouze kontrolu sily, shodu hesel a odpocet platnosti formulare.
 */
(function () {
  'use strict';

  var password = document.getElementById('cb_pass');
  var confirmation = document.getElementById('cb_pass2');
  var meter = document.getElementById('cb-password-meter-fill');
  var status = document.getElementById('cb-password-status');
  var matchStatus = document.getElementById('cb-password-match');
  var submit = document.getElementById('cb-prvni-submit');
  var countdown = document.getElementById('cb-prvni-countdown');
  if (!password || !confirmation || !meter || !status || !matchStatus || !submit || !countdown) {
    return;
  }

  var countdownValue = countdown.querySelector('span');
  var deadline = Date.now() + (Number(countdown.getAttribute('data-seconds')) * 1000);
  var redirectUrl = countdown.getAttribute('data-redirect') || '';
  var expired = false;

  /* Zkontroluje pravidla hesla, zobrazi silu a povoli odeslani. */
  function check() {
    var value = password.value;
    var points = 0;
    if (value.length >= 8) points++;
    if (/[a-z]/.test(value)) points++;
    if (/[A-Z]/.test(value)) points++;
    if (/[0-9]/.test(value)) points++;
    if (/[^A-Za-z0-9]/.test(value)) points++;
    var valid = value.length >= 8 && /[a-z]/.test(value) && /[A-Z]/.test(value) && /[0-9]/.test(value);
    var level = value === '' ? 0 : (valid ? 4 : (points <= 1 ? 1 : (points === 2 ? 2 : 3)));
    meter.style.width = String(level * 25) + '%';
    meter.className = valid ? 'is-strong' : (level >= 3 ? 'is-medium' : 'is-weak');
    var strength = value === '' ? 'Síla hesla' : (level === 1 ? 'Velmi slabé' : (level === 2 ? 'Slabé' : (level === 3 ? 'Málo bezpečné' : 'OK')));
    status.textContent = strength;
    status.className = 'modal-password-strength-status ' + (valid ? 'is-ok' : (level <= 1 ? 'is-weak' : 'is-medium'));
    var same = confirmation.value !== '' && value === confirmation.value;
    var sameBeginning = value.indexOf(confirmation.value) === 0;
    matchStatus.textContent = confirmation.value === '' || (sameBeginning && !same) ? '' : (same ? 'Hesla se shodují.' : 'Hesla se neshodují.');
    matchStatus.className = 'modal-password-status' + (same ? ' is-ok' : (sameBeginning ? '' : ' is-error'));
    submit.disabled = expired || !(valid && same);
  }

  /* Aktualizuje odpocet a po vyprseni vrati uzivatele na zadany vstup. */
  function updateCountdown() {
    var seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    var minutes = Math.floor(seconds / 60);
    var rest = String(seconds % 60).padStart(2, '0');
    countdownValue.textContent = String(minutes) + ':' + rest;
    if (seconds <= 0) {
      expired = true;
      submit.disabled = true;
      if (redirectUrl !== '') {
        window.location.replace(redirectUrl);
      }
      return;
    }
    window.setTimeout(updateCountdown, 250);
  }

  password.addEventListener('input', check);
  confirmation.addEventListener('input', check);
  check();
  updateCountdown();
})();
