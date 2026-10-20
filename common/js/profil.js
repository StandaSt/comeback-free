/* Účel: Obsluhuje ikonky oka uvnitř polí hesel a kontrolu shody i po AJAX přepnutí modulu. */
(function () {
  'use strict';
  // Delegace zůstává funkční po výměně stránky, hesla se nikam neukládají ani neodesílají skriptem.
  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-profile-password]');
    if (!button || button.disabled) return;
    var input = button.parentElement.querySelector('input');
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(show));
    var label = button.getAttribute('aria-label') || '';
    label = label.replace(/^(Zobrazit|Skrýt)/, show ? 'Skrýt' : 'Zobrazit');
    button.setAttribute('aria-label', label);
    button.setAttribute('title', label);
  });
  // Stejnou shodu a pravidla nezávisle ověřuje server před jakýmkoli zápisem.
  document.addEventListener('input', function (event) {
    var form = event.target.closest('.common_profile_form');
    if (!form) return;
    var password = form.querySelector('[name="heslo"]');
    var confirmation = form.querySelector('[name="heslo_znovu"]');
    if (!password || !confirmation) return;
    confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value ? 'Hesla se neshodují.' : '');
  });
}());
