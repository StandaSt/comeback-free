// admin_js/admin_individualni_prava_save.js
'use strict';

(function () {
  function updateToggle(input, result) {
    var label = input.closest('.admin_exception_toggle');
    var mark = label ? label.querySelector('span') : null;
    if (!label || !mark) {
      return;
    }

    label.classList.remove('is-plus', 'is-minus');
    mark.textContent = '';
    if (!result || result.vyjimka !== true) {
      input.checked = false;
      return;
    }

    input.checked = true;
    if (Number(result.povoleno) === 1) {
      label.classList.add('is-plus');
      mark.textContent = '+';
    } else {
      label.classList.add('is-minus');
      mark.textContent = '-';
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
      })
      .catch(function (error) {
        input.checked = !previous;
        window.alert(error.message || 'Uložení výjimky selhalo.');
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
