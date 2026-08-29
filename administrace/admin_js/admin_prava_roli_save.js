// Uklada stav globalniho prava role po zmene checkboxu.
'use strict';

(function () {
  var pendingActiveInput = null;

  function endpoint() {
    return String(window.CB_ENDPOINT || 'index.php');
  }

  function saveCheckbox(input) {
    var previous = !input.checked;
    var body = new URLSearchParams();
    body.set('admin_prava_action', 'role');
    body.set('id_role', String(input.getAttribute('data-id-role') || '0'));
    body.set('id_pravo', String(input.getAttribute('data-id-pravo') || '0'));
    body.set('allowed', input.checked ? '1' : '0');

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
        return response.json().then(function (data) {
          if (!response.ok) {
            throw new Error(String((data && data.err) || ('HTTP ' + response.status)));
          }
          return data;
        });
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

  function activeModal() {
    return document.querySelector('[data-admin-pravo-aktivni-modal]');
  }

  function closeActiveModal() {
    var modal = activeModal();
    if (modal && modal.open) {
      modal.close();
    }
  }

  function cancelDeactivate() {
    if (pendingActiveInput) {
      pendingActiveInput.checked = true;
      pendingActiveInput.disabled = false;
    }
    pendingActiveInput = null;
    closeActiveModal();
  }

  function applyActiveState(input, active) {
    var row = input.closest('tr');
    input.checked = active;
    if (!row) {
      return;
    }

    row.classList.toggle('is-inactive', !active);
    Array.prototype.forEach.call(row.querySelectorAll('input[data-admin-pravo="1"]'), function (roleInput) {
      roleInput.disabled = !active;
    });
    document.dispatchEvent(new CustomEvent('cb:admin-pravo-active-saved'));
  }

  function saveActive(input, active, confirmed) {
    var previous = !active;
    var body = new URLSearchParams();
    body.set('admin_prava_action', 'aktivni');
    body.set('id_pravo', String(input.getAttribute('data-id-pravo') || '0'));
    body.set('aktivni', active ? '1' : '0');
    body.set('potvrzeno', confirmed ? '1' : '0');
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
        return response.json().then(function (data) {
          if (!response.ok) {
            throw new Error(String((data && data.err) || ('HTTP ' + response.status)));
          }
          return data;
        });
      })
      .then(function (data) {
        if (!data || data.ok !== true) {
          throw new Error(String((data && data.err) || 'Změna aktivity práva selhala.'));
        }
        applyActiveState(input, active);
      })
      .catch(function (error) {
        input.checked = previous;
        window.alert((error && error.message) ? error.message : 'Změna aktivity práva selhala.');
      })
      .finally(function () {
        input.disabled = false;
      });
  }

  window.CB_ADMIN_PRAVA_SAVE = {
    saveCheckbox: saveCheckbox
  };

  document.addEventListener('change', function (event) {
    var activeInput = event.target && event.target.closest
      ? event.target.closest('input[data-admin-pravo-aktivni="1"]')
      : null;
    if (activeInput && activeInput.type === 'checkbox') {
      if (activeInput.checked) {
        saveActive(activeInput, true, false);
        return;
      }

      activeInput.checked = true;
      activeInput.disabled = true;
      pendingActiveInput = activeInput;
      var modal = activeModal();
      if (!modal) {
        pendingActiveInput = null;
        activeInput.disabled = false;
        throw new Error('Potvrzovací okno pro vypnutí práva nebylo nalezeno.');
      }
      var text = modal.querySelector('[data-admin-pravo-aktivni-text]');
      if (text) {
        text.textContent = 'Chystáte se vypnout hlídání práva „'
          + String(activeInput.getAttribute('data-pravo-nazev') || '')
          + '“.';
      }
      modal.showModal();
      return;
    }

    var input = event.target && event.target.closest ? event.target.closest('input[data-admin-pravo="1"]') : null;
    if (!input || input.type !== 'checkbox') {
      return;
    }

    saveCheckbox(input);
  });

  document.addEventListener('click', function (event) {
    var cancel = event.target && event.target.closest
      ? event.target.closest('[data-admin-pravo-aktivni-cancel]')
      : null;
    if (cancel) {
      cancelDeactivate();
      return;
    }

    var confirm = event.target && event.target.closest
      ? event.target.closest('[data-admin-pravo-aktivni-confirm]')
      : null;
    if (!confirm || !pendingActiveInput) {
      return;
    }

    var input = pendingActiveInput;
    pendingActiveInput = null;
    closeActiveModal();
    saveActive(input, false, true);
  });

  document.addEventListener('cancel', function (event) {
    if (event.target && event.target.matches && event.target.matches('[data-admin-pravo-aktivni-modal]')) {
      event.preventDefault();
      cancelDeactivate();
    }
  }, true);
})();
