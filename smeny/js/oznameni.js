/* Účel: Otevře a zavře modál oznámení směn, také po načtení stránky společnou AJAX navigací. */
(function () {
  'use strict';
  // Každý nově vykreslený dialog otevřeme jen jednou; zavření nic nepotvrzuje.
  function init() {
    document.querySelectorAll('[data-smeny-oznameni-modal]').forEach(function (dialog) {
      if (dialog.dataset.ready === '1') return;
      dialog.dataset.ready = '1';
      dialog.querySelector('[data-smeny-oznameni-close]').addEventListener('click', function () {
        dialog.close();
      });
      dialog.showModal();
    });
  }
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('cb:main-swapped', init);
  init();
}());
