// admin_js/admin_prava_roli_save.js
'use strict';

(function () {
  function endpoint() {
    return String(window.CB_ENDPOINT || 'index.php');
  }

  function saveCheckbox(input) {
    var previous = !input.checked;
    var body = new URLSearchParams();
    body.set('id_role', String(input.getAttribute('data-id-role') || '0'));
    body.set('id_pravo', String(input.getAttribute('data-id-pravo') || '0'));
    body.set('povoleno', input.checked ? '1' : '0');

    input.disabled = true;

    return fetch(endpoint(), {
      method: 'POST',
      headers: {
        'X-Comeback-Admin-Prava': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }
        return response.json();
      })
      .then(function (data) {
        if (!data || data.ok !== true) {
          throw new Error(String((data && data.err) || 'Uložení práva selhalo.'));
        }
      })
      .catch(function (error) {
        input.checked = previous;
        window.alert((error && error.message) ? error.message : 'Uložení práva selhalo.');
      })
      .finally(function () {
        input.disabled = false;
        document.dispatchEvent(new CustomEvent('cb:admin-prava-saved', {
          detail: {
            idRole: String(input.getAttribute('data-id-role') || '0'),
            idModul: String(input.getAttribute('data-id-modul') || '0')
          }
        }));
      });
  }

  window.CB_ADMIN_PRAVA_SAVE = {
    saveCheckbox: saveCheckbox
  };

  document.addEventListener('change', function (event) {
    var input = event.target && event.target.closest ? event.target.closest('input[data-admin-pravo="1"]') : null;
    if (!input || input.type !== 'checkbox') {
      return;
    }

    saveCheckbox(input);
  });
})();
