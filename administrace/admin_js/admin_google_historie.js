// Jednorázový převod Google historie po pobočkách s viditelným průběhem.
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
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || !data || data.ok !== true) {
          throw new Error(window.cbAdminResponseError(response.status, data, 'Převod historických reportů se nepodařil.'));
        }
        return data;
      });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-admin-google-historie-form]')) {
      return;
    }
    event.preventDefault();

    var button = form.querySelector('[data-admin-google-historie-button]');
    var progress = document.querySelector('[data-admin-google-historie-progress]');
    var status = progress && progress.querySelector('[data-admin-google-historie-status]');
    var bar = progress && progress.querySelector('[data-admin-google-historie-bar]');
    var list = progress && progress.querySelector('[data-admin-google-historie-branches]');
    if (!(button instanceof HTMLButtonElement) || button.disabled
        || !(progress instanceof HTMLElement) || !(status instanceof HTMLElement)
        || !(bar instanceof HTMLProgressElement) || !(list instanceof HTMLOListElement)) {
      return;
    }

    var completed = 0;
    var converted = 0;
    var currentName = '';
    var currentItem = null;
    var branchTimer = null;
    var originalButtonText = button.textContent;
    button.disabled = true;
    button.textContent = 'Převod probíhá…';
    progress.hidden = false;
    status.classList.remove('is-error');
    status.textContent = 'Zjišťuji aktuální seznam poboček…';
    list.replaceChildren();
    bar.max = 1;
    bar.value = 0;

    request(form, 'admin_google_historie_pobocky')
      .then(function (data) {
        var branches = Array.isArray(data.pobocky) ? data.pobocky : [];
        if (branches.length === 0) {
          status.textContent = 'Není co převádět. Všechny způsobilé reporty už jsou v IS.';
          return null;
        }
        bar.max = branches.length;
        branches.forEach(function (branch) {
          var item = document.createElement('li');
          item.textContent = String(branch.nazev || 'Pobočka') + ': čeká ('
            + String(branch.pocet || 0) + ' reportů)';
          list.appendChild(item);
          branch.progressItem = item;
        });

        return branches.reduce(function (chain, branch, index) {
          return chain.then(function () {
            currentName = String(branch.nazev || 'Pobočka');
            currentItem = branch.progressItem;
            currentItem.textContent = currentName + ': probíhá ('
              + String(index + 1) + '/' + String(branches.length) + ')';
            var startedAt = Date.now();
            var renderWaiting = function () {
              var elapsed = Math.floor((Date.now() - startedAt) / 1000);
              status.textContent = 'Čekám na výsledek pobočky ' + currentName + ' ('
                + String(index + 1) + '/' + String(branches.length) + ', '
                + String(elapsed) + ' s). Hotovo: ' + String(converted)
                + ' reportů. Nechte stránku otevřenou.';
            };
            renderWaiting();
            branchTimer = window.setInterval(renderWaiting, 1000);
            return request(form, 'admin_google_historie_pobocka', branch.id_pob)
              .then(function (result) {
                var count = Number(result.reporty || 0);
                converted += count;
                completed += 1;
                bar.value = completed;
                currentItem.textContent = currentName + ': hotovo (' + String(count) + ' reportů)';
                status.textContent = 'Hotovo ' + String(completed) + '/'
                  + String(branches.length) + ' poboček, převedeno '
                  + String(converted) + ' reportů.';
                return result;
              }).finally(function () {
                window.clearInterval(branchTimer);
                branchTimer = null;
              });
          });
        }, Promise.resolve()).then(function (lastResult) {
          return Number(lastResult && lastResult.zbyva || 0);
        });
      })
      .then(function (remaining) {
        if (remaining === null) return;
        if (remaining > 0) {
          status.classList.add('is-error');
          status.textContent = 'Převedeno ' + String(converted)
            + ' reportů, ale zbývá ' + String(remaining)
            + '. Znovu načtěte náhled a zkontrolujte zbývající pobočky.';
        } else {
          status.textContent = 'Převod dokončen. Převedeno ' + String(converted)
            + ' reportů na ' + String(completed) + ' pobočkách.';
        }
      })
      .catch(function (error) {
        if (currentItem instanceof HTMLElement) {
          currentItem.textContent = currentName + ': chyba';
        }
        status.classList.add('is-error');
        status.textContent = 'Převod zastaven'
          + (currentName ? ' u pobočky ' + currentName : '')
          + '. Dosud převedeno ' + String(converted) + ' reportů. '
          + window.cbAdminErrorMessage(error, 'Převod historických reportů se nepodařil.');
      })
      .finally(function () {
        button.disabled = false;
        button.textContent = originalButtonText;
      });
  });
}());
