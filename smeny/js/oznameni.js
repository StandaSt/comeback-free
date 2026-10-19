/* Účel: Otevře existující modál oznámení i po AJAX navigaci, rozliší souhlas od výhrad a oznámí otevření historii činnosti. */
(function () {
  'use strict';
  // Každý nově vykreslený dialog otevřeme jen jednou; zavření nic nepotvrzuje.
  function init() {
    document.querySelectorAll('[data-smeny-oznameni-modal]').forEach(function (dialog) {
      if (dialog.dataset.ready === '1') return;
      dialog.dataset.ready = '1';
      // Souhlas bez výhrad nelze odeslat s označenými nevyhovujícími dny; server kontrolu opakuje.
      var form = dialog.querySelector('[data-smeny-vyjadreni]');
      var agree = form && form.querySelector('[name="vyjadreni"][value="souhlas"]');
      var objections = form && form.querySelector('[name="vyjadreni"][value="vyhrady"]');
      if (agree && objections) {
        var update = function () {
          var selected = form.querySelectorAll('[name="vyhrady_dny[]"]:checked').length > 0;
          agree.disabled = selected;
          objections.disabled = !selected;
        };
        form.addEventListener('change', update);
        update();
      }
      dialog.querySelector('[data-smeny-oznameni-close]').addEventListener('click', function () {
        dialog.close();
      });
      dialog.showModal();
      dialog.dispatchEvent(new CustomEvent('cb:smeny-editor-opened',{bubbles:true}));
    });
  }
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('cb:main-swapped', init);
  init();
}());
