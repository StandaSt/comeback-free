// Uklada individualni vyjimku prava a oznami obnoveni seznamu uzivatelu.
'use strict';

(function () {
  function updateToggle(input, result) {
    var label = input.closest('.admin_exception_toggle');
    if (!label) {
      return;
    }

    label.classList.remove('is-plus', 'is-minus');
    if (!result || result.vyjimka !== true) {
      input.checked = false;
      return;
    }

    input.checked = true;
    if (Number(result.povoleno) === 1) {
      label.classList.add('is-plus');
    } else {
      label.classList.add('is-minus');
    }
  }

  function saveException(input) {
    var previous = input.checked;
    input.disabled = true;

    window.CB_ADMIN_INDIVIDUAL.post('save', {
      id_user: input.getAttribute('data-id-user') || '0',
      id_pravo: input.getAttribute('data-id-pravo') || '0',
      vyjimka: input.checked ? '1' : '0'
    })
      .then(function (data) {
        updateToggle(input, data.result);
        var page = input.closest('[data-admin-individual="1"]');
        var detail = page ? page.querySelector('[data-admin-individual-detail]') : null;
        if (detail && data.detail_html) {
          detail.innerHTML = data.detail_html;
        }
        document.dispatchEvent(new CustomEvent('cb:admin-individual-exception-saved'));
      })
      .catch(function (error) {
        input.checked = !previous;
        window.alert(window.cbAdminErrorMessage(error, 'Uložení výjimky se nepodařilo.'));
      })
      .finally(function () {
        input.disabled = false;
      });
  }

  document.addEventListener('change', function (event) {
    var input = event.target && event.target.closest ? event.target.closest('input[data-admin-vyjimka="1"]') : null;
    if (!input || input.type !== 'checkbox') {
      return;
    }

    if (!window.CB_ADMIN_INDIVIDUAL || typeof window.CB_ADMIN_INDIVIDUAL.post !== 'function') {
      window.alert('Uložení výjimky není připravené.');
      return;
    }

    saveException(input);
  });
})();
