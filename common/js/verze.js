/* Účel: Otevírá aktuální změny z DB, stránkuje starší záznamy a vrací fokus na informační tlačítko. */
(function () {
  'use strict';
  var dialog = document.getElementById('cb-verze-dialog');
  if (!(dialog instanceof HTMLDialogElement)) return;
  var opener = null;
  var content = dialog.querySelector('[data-verze-content]');
  var more = dialog.querySelector('[data-verze-more]');
  var page = 0;
  var generation = 0;

  // Nové otevření načte aktuální stav; stará odpověď nesmí přepsat novější přehled.
  function load(reset) {
    if (reset) { generation++; page = 0; content.textContent = 'Načítám změny…'; more.hidden = true; }
    var requestGeneration = generation;
    more.disabled = true;
    var url = new URL(dialog.getAttribute('data-verze-url'), window.location.href);
    url.searchParams.set('strana', String(page));
    fetch(url.toString(), {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}})
      .then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok || !data.ok || typeof data.html !== 'string') throw new Error(data.err || 'Přehled změn se nepodařilo načíst.');
          return data;
        });
      }).then(function (data) {
        if (requestGeneration !== generation) return;
        if (reset) content.innerHTML = data.html;
        else content.insertAdjacentHTML('beforeend', data.html);
        page++;
        more.hidden = !data.dalsi;
      }).catch(function (error) {
        if (requestGeneration !== generation) return;
        if (reset) content.textContent = error.message || 'Přehled změn se nepodařilo načíst.';
        else {
          var notice = document.createElement('p');
          notice.textContent = 'Starší změny se nepodařilo načíst. Zkuste to znovu.';
          content.appendChild(notice);
        }
      }).finally(function () { if (requestGeneration === generation) more.disabled = false; });
  }

  // Delegování zachová ovládání pro všechna menu vložená klientskou navigací.
  document.addEventListener('click', function (event) {
    if (!(event.target instanceof Element)) return;
    var button = event.target.closest('[data-cb-verze-open]');
    if (button instanceof HTMLButtonElement) {
      opener = button;
      if (!dialog.open) { dialog.showModal(); load(true); }
    }
    if (event.target.closest('[data-cb-verze-close]')) dialog.close();
    if (event.target.closest('[data-verze-more]') && !more.disabled) load(false);
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
