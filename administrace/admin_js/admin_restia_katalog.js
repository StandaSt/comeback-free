// Zobrazuje postup ručního načítání katalogu Restia po jednotlivých pobočkách.
'use strict';

(function () {
  function request(form, action, idPob) {
    var body = new URLSearchParams(new FormData(form));
    body.set('cb_action', action);
    if (idPob !== undefined) {
      body.set('id_pob', String(idPob));
    }

    return fetch(form.action, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().then(function (data) {
        if (!response.ok || !data || data.ok !== true) {
          throw new Error(String((data && data.chyba) || ('HTTP ' + response.status)));
        }
        return data;
      });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-admin-restia-katalog-form]')) {
      return;
    }
    event.preventDefault();

    var status = document.querySelector('[data-admin-restia-katalog-prubeh]');
    var button = form.querySelector('[data-admin-restia-katalog-button]');
    if (!(status instanceof HTMLElement) || !(button instanceof HTMLButtonElement) || button.disabled) {
      return;
    }

    var downloaded = 0;
    var updated = 0;
    button.disabled = true;
    status.hidden = false;
    status.classList.remove('is-error');
    status.textContent = 'Načítám seznam poboček…';

    request(form, 'admin_restia_katalog_pobocky')
      .then(function (data) {
        var branches = Array.isArray(data.pobocky) ? data.pobocky : [];
        return branches.reduce(function (chain, branch, index) {
          return chain.then(function () {
            status.textContent = 'Stahuji: ' + String(branch.nazev || '')
              + ' (' + String(index + 1) + '/' + String(branches.length) + ')';
            return request(form, 'admin_restia_katalog_pobocka', branch.id_pob).then(function (result) {
              downloaded += Number(result.stazeno || 0);
              updated += Number(result.aktualizovano || 0);
            });
          });
        }, Promise.resolve());
      })
      .then(function () {
        status.textContent = 'Staženo: ' + String(downloaded) + ', aktualizováno: ' + String(updated);
      })
      .catch(function (error) {
        status.classList.add('is-error');
        status.textContent = 'Chyba: ' + String((error && error.message) || 'Načítání katalogu selhalo.');
      })
      .finally(function () {
        button.disabled = false;
      });
  });
}());
