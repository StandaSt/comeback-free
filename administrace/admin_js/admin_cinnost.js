/* Účel: Otevírá každý autorizovaný detail historie samostatně bez zavírání ostatních detailů a bez dalšího načítání dat. */
(function () {
  'use strict';
  // Delegování funguje i po výměně pracovní plochy; dotaz i přepnutí zůstávají uvnitř aktuálního přehledu.
  document.addEventListener('click', function (event) {
    var button=event.target.closest?.('[data-nc-toggle]');
    if (!button) return;
    var root=button.closest('[data-nc-prehled]');
    var row=document.getElementById(button.dataset.ncToggle);
    if (!root || !row || !root.contains(row)) return;
    row.hidden=!row.hidden;
    root.querySelectorAll('[data-nc-toggle]').forEach(function (toggle) {
      if (toggle.dataset.ncToggle===row.id) toggle.setAttribute('aria-expanded',String(!row.hidden));
    });
  });
}());
