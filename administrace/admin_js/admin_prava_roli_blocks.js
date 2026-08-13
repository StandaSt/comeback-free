// admin_js/admin_prava_roli_blocks.js
'use strict';

(function () {
  function selectorValue(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
      return window.CSS.escape(String(value));
    }

    return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function rightsForBlock(idRole, idModul) {
    return Array.prototype.slice.call(document.querySelectorAll(
      'input[data-admin-pravo="1"][data-id-role="' + selectorValue(idRole) + '"][data-id-modul="' + selectorValue(idModul) + '"]'
    ));
  }

  function syncBlockCheckbox(idRole, idModul) {
    var blockInput = document.querySelector(
      'input[data-admin-blok="1"][data-id-role="' + selectorValue(idRole) + '"][data-id-modul="' + selectorValue(idModul) + '"]'
    );
    if (!blockInput) {
      return;
    }

    var rights = rightsForBlock(idRole, idModul);
    var checkedCount = rights.filter(function (rightInput) {
      return rightInput.checked;
    }).length;

    blockInput.disabled = rights.length === 0;
    blockInput.checked = rights.length > 0 && checkedCount === rights.length;
    blockInput.indeterminate = checkedCount > 0 && checkedCount < rights.length;
  }

  function syncAllBlockCheckboxes() {
    Array.prototype.forEach.call(document.querySelectorAll('input[data-admin-blok="1"]'), function (blockInput) {
      syncBlockCheckbox(
        String(blockInput.getAttribute('data-id-role') || '0'),
        String(blockInput.getAttribute('data-id-modul') || '0')
      );
    });
  }

  function saveBlockCheckbox(input) {
    var idRole = String(input.getAttribute('data-id-role') || '0');
    var idModul = String(input.getAttribute('data-id-modul') || '0');
    var rights = rightsForBlock(idRole, idModul);
    var targetChecked = input.checked;
    var changedRights = rights.filter(function (rightInput) {
      return rightInput.checked !== targetChecked;
    });

    input.disabled = true;

    Promise.all(changedRights.map(function (rightInput) {
      rightInput.checked = targetChecked;
      return window.CB_ADMIN_PRAVA_SAVE.saveCheckbox(rightInput);
    }))
      .finally(function () {
        input.disabled = false;
        syncBlockCheckbox(idRole, idModul);
      });
  }

  document.addEventListener('change', function (event) {
    var input = event.target && event.target.closest ? event.target.closest('input[data-admin-blok="1"]') : null;
    if (!input || input.type !== 'checkbox') {
      return;
    }

    if (!window.CB_ADMIN_PRAVA_SAVE || typeof window.CB_ADMIN_PRAVA_SAVE.saveCheckbox !== 'function') {
      window.alert('Uložení práva není připravené.');
      return;
    }

    saveBlockCheckbox(input);
  });

  document.addEventListener('cb:admin-prava-saved', function (event) {
    var detail = event.detail || {};
    syncBlockCheckbox(String(detail.idRole || '0'), String(detail.idModul || '0'));
  });

  document.addEventListener('cb:main-swapped', syncAllBlockCheckboxes);
  syncAllBlockCheckboxes();
})();
