// Zobrazuje skutečné kroky kontroly podkladů prvního kompletního naplnění HR.
'use strict';

(function () {
  function request(form, action) {
    var body = new URLSearchParams(new FormData(form));
    body.set('cb_action', action);

    return fetch(form.action, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || !data || data.ok !== true) {
          throw new Error(window.cbAdminResponseError(response.status, data, 'Kontrola podkladů se nepodařila.'));
        }
        return data;
      });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-admin-hr-preview-form]')) {
      return;
    }
    event.preventDefault();

    var status = document.querySelector('[data-admin-hr-import-progress]');
    var button = form.querySelector('[data-admin-hr-preview-button]');
    if (!(status instanceof HTMLElement) || !(button instanceof HTMLButtonElement) || button.disabled) {
      return;
    }

    var steps = [
      ['admin_hr_kompletni_preview_pripravit', 'Kontroluji a rozbaluji data/google_data/HR.zip…'],
      ['admin_hr_kompletni_preview_zamestnanci', 'Čtu Zaměstnanci.xlsx a porovnávám osoby s USER…'],
      ['admin_hr_kompletni_preview_mzdy', 'Čtu HR 2024.xlsx a kontroluji historii mezd a sazeb…'],
      ['admin_hr_kompletni_preview_dokumenty', 'Kontroluji doklady a smlouvy a jejich přiřazení k zaměstnancům…']
    ];

    button.disabled = true;
    status.hidden = false;
    status.classList.remove('is-error');

    steps.reduce(function (chain, step) {
      return chain.then(function () {
        status.textContent = step[1];
        return request(form, step[0]);
      });
    }, Promise.resolve()).then(function () {
      status.textContent = 'Kontrola dokončena. Načítám výsledky…';
      window.location.assign(form.action);
    }).catch(function (error) {
      status.classList.add('is-error');
      status.textContent = window.cbAdminErrorMessage(error, 'Kontrola podkladů se nepodařila.');
      button.disabled = false;
    });
  });
}());
