/* Účel: Otevírá společný přehled verzí i po výměně menu a vrací fokus na informační tlačítko. */
(function () {
  'use strict';
  var dialog = document.getElementById('cb-verze-dialog');
  if (!(dialog instanceof HTMLDialogElement)) return;
  var opener = null;

  // Delegování zachová ovládání pro všechna menu vložená klientskou navigací.
  document.addEventListener('click', function (event) {
    if (!(event.target instanceof Element)) return;
    var button = event.target.closest('[data-cb-verze-open]');
    if (button instanceof HTMLButtonElement) {
      opener = button;
      if (!dialog.open) dialog.showModal();
    }
    if (event.target.closest('[data-cb-verze-close]')) dialog.close();
  });

  // Kliknutí na skutečné pozadí zavře okno; kliknutí do prázdného místa uvnitř ne.
  dialog.addEventListener('click', function (event) {
    if (event.target !== dialog) return;
    var rect = dialog.getBoundingClientRect();
    if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
  });

  // Escape obslouží nativní dialog; zastavení zabrání současnému zavření mobilního menu.
  dialog.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') event.stopPropagation();
  });
  dialog.addEventListener('close', function () {
    if (opener && opener.isConnected) opener.focus();
  });
})();
