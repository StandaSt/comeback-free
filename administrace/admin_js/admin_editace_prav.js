/*
 * Účel souboru: Ovládá oba panely stránky Editovat práva.
 * Všechny posluchače jsou delegované, aby fungovaly i po AJAXové výměně obsahu PP.
 */
'use strict';

(function () {
  /** Najde právě zobrazenou kostru editoru práv. */
  function editor() {
    return document.querySelector('[data-admin-editace-prav]');
  }

  /** Odešle formulář na konkrétní jednoúčelový AJAX endpoint. */
  function post(url, fields) {
    var body = new URLSearchParams();
    Object.keys(fields || {}).forEach(function (key) {
      body.set(key, String(fields[key]));
    });

    return fetch(String(url || ''), {
      method: 'POST',
      headers: {
        'X-Comeback-Admin-Editace-Prav': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok || !data || data.ok !== true) {
          throw new Error(String((data && data.err) || ('HTTP ' + response.status)));
        }
        return data;
      });
    });
  }

  /** Načte aktuální tabulku vybraného modulu. */
  function loadTable(root, idModul) {
    var target = root.querySelector('[data-admin-prava-editace-tabulka]');
    if (!target) return Promise.resolve();
    if (!idModul) {
      target.innerHTML = '';
      return Promise.resolve();
    }

    target.textContent = 'Načítám práva…';
    return post(root.getAttribute('data-url-nacist'), {id_modul: idModul})
      .then(function (data) {
        target.innerHTML = String(data.html || '');
      })
      .catch(function (error) {
        // Konkrétní chyba zůstane viditelná v panelu i po zavření upozornění.
        target.textContent = error.message || 'Práva modulu se nepodařilo načíst.';
        window.alert(error.message || 'Práva modulu se nepodařilo načíst.');
      });
  }

  /** Zobrazí uložení pouze u řádku, jehož vstupy se skutečně změnily. */
  function updateDirtyState(row) {
    var name = row.querySelector('[data-admin-pravo-nazev]');
    var description = row.querySelector('[data-admin-pravo-popis]');
    var save = row.querySelector('[data-admin-pravo-ulozit]');
    if (!name || !description || !save) return;

    var changed = name.value !== String(row.getAttribute('data-puvodni-nazev') || '')
      || description.value !== String(row.getAttribute('data-puvodni-popis') || '');
    save.hidden = !changed;
  }

  // Výběr modulu nezávisle ovládá formulář přidání nebo editační tabulku.
  document.addEventListener('change', function (event) {
    var root = editor();
    if (!root) return;

    var addSelect = event.target.closest ? event.target.closest('[data-admin-pravo-pridani-modul]') : null;
    if (addSelect) {
      var form = root.querySelector('[data-admin-pravo-pridani-form]');
      if (form) form.hidden = !addSelect.value;
      return;
    }

    var editSelect = event.target.closest ? event.target.closest('[data-admin-prava-editace-modul]') : null;
    if (editSelect) {
      loadTable(root, editSelect.value);
    }
  });

  // Každý editovaný vstup vyhodnocuje pouze vlastní řádek.
  document.addEventListener('input', function (event) {
    var input = event.target.closest ? event.target.closest('[data-admin-pravo-nazev], [data-admin-pravo-popis]') : null;
    var row = input ? input.closest('[data-admin-pravo-radek]') : null;
    if (row) updateDirtyState(row);
  });

  // Nové právo se uloží do modulu zvoleného v prvním panelu.
  document.addEventListener('submit', function (event) {
    var form = event.target.closest ? event.target.closest('[data-admin-pravo-pridani-form]') : null;
    var root = editor();
    if (!form || !root) return;
    event.preventDefault();

    var select = root.querySelector('[data-admin-pravo-pridani-modul]');
    var status = root.querySelector('[data-admin-pravo-pridani-stav]');
    var submit = form.querySelector('[type="submit"]');
    var data = new FormData(form);
    if (submit) submit.disabled = true;
    if (status) status.textContent = '';

    post(root.getAttribute('data-url-pridat'), {
      id_modul: select ? select.value : '',
      nazev: data.get('nazev') || '',
      popis: data.get('popis') || ''
    }).then(function (result) {
      form.reset();
      if (status) status.textContent = 'Právo „' + String(result.right.nazev || '') + '“ bylo uloženo.';
      var editSelect = root.querySelector('[data-admin-prava-editace-modul]');
      if (editSelect && select && editSelect.value === select.value) {
        return loadTable(root, editSelect.value);
      }
      return null;
    }).catch(function (error) {
      window.alert(error.message || 'Právo se nepodařilo uložit.');
    }).finally(function () {
      if (submit) submit.disabled = false;
    });
  });

  // Uložení názvu a popisu se týká pouze vybraného řádku.
  document.addEventListener('click', function (event) {
    var save = event.target.closest ? event.target.closest('[data-admin-pravo-ulozit]') : null;
    var root = editor();
    if (!save || !root) return;

    var row = save.closest('[data-admin-pravo-radek]');
    var name = row ? row.querySelector('[data-admin-pravo-nazev]') : null;
    var description = row ? row.querySelector('[data-admin-pravo-popis]') : null;
    if (!row || !name || !description) return;

    save.disabled = true;
    post(root.getAttribute('data-url-upravit'), {
      id_pravo: row.getAttribute('data-id-pravo') || '0',
      nazev: name.value,
      popis: description.value
    }).then(function (result) {
      name.value = String(result.right.nazev || '');
      description.value = String(result.right.popis || '');
      row.setAttribute('data-puvodni-nazev', name.value);
      row.setAttribute('data-puvodni-popis', description.value);
      updateDirtyState(row);
    }).catch(function (error) {
      window.alert(error.message || 'Změny práva se nepodařilo uložit.');
    }).finally(function () {
      save.disabled = false;
    });
  });

  // Posun po úspěšném prohození znovu načte tabulku a tím i krajní stavy šipek.
  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('[data-admin-pravo-posun]') : null;
    var root = editor();
    if (!button || !root) return;

    var row = button.closest('[data-admin-pravo-radek]');
    var select = root.querySelector('[data-admin-prava-editace-modul]');
    if (!row || !select) return;

    button.disabled = true;
    post(root.getAttribute('data-url-posunout'), {
      id_pravo: row.getAttribute('data-id-pravo') || '0',
      smer: button.getAttribute('data-admin-pravo-posun') || ''
    }).then(function () {
      return loadTable(root, select.value);
    }).catch(function (error) {
      button.disabled = false;
      window.alert(error.message || 'Pořadí práv se nepodařilo změnit.');
    });
  });
})();
