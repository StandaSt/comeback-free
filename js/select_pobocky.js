// js/select_pobocky.js * Verze: V1 * Aktualizace: 25.03.2026
'use strict';

(function (w) {
  function initSelectPobockyCard() {
    var root = document.getElementById('cbSelectPobockyCard');
    if (!root) return;
    if (root.getAttribute('data-cb-init') === '1') return;
    root.setAttribute('data-cb-init', '1');

    var areaInputs = Array.prototype.slice.call(root.querySelectorAll('.cb-pob-area'));
    var branchInputs = Array.prototype.slice.call(root.querySelectorAll('.cb-pob-branch'));
    var saveBtn = document.getElementById('cbPobockySaveBtn');
    var saveUrl = String(root.getAttribute('data-save-url') || '');
    if (saveUrl === '') {
      saveUrl = 'index.php';
    }

    function syncState(sourceType) {
      var checkedAreas = areaInputs.filter(function (el) { return el.checked; });
      var checkedBranches = branchInputs.filter(function (el) { return el.checked; });

      var hasArea = checkedAreas.length > 0;
      var hasBranch = checkedBranches.length > 0;

      if (sourceType === 'area' && hasArea) {
        branchInputs.forEach(function (el) {
          el.checked = false;
        });
        return;
      }

      if (sourceType === 'branch' && hasBranch) {
        areaInputs.forEach(function (el) {
          el.checked = false;
        });
        return;
      }

      if (hasArea) {
        branchInputs.forEach(function (el) {
          el.checked = false;
        });
        return;
      }

      if (hasBranch) {
        areaInputs.forEach(function (el) {
          el.checked = false;
        });
        return;
      }
    }

    areaInputs.forEach(function (el) {
      el.addEventListener('change', function () {
        syncState('area');
      });
    });
    branchInputs.forEach(function (el) {
      el.addEventListener('change', function () {
        syncState('branch');
      });
    });

    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var selectedAreas = areaInputs.filter(function (el) { return el.checked; });
        var selectedBranches = branchInputs.filter(function (el) { return el.checked; }).map(function (el) {
          return parseInt(el.value || '0', 10);
        }).filter(function (id) {
          return Number.isFinite(id) && id > 0;
        });

        var payload = {};
        if (selectedAreas.length > 0) {
          payload = {
            mode: 'area',
            selected_oblasti: selectedAreas.map(function (el) {
              return String(el.value || '').trim();
            }).filter(function (v) {
              return v !== '';
            })
          };
        } else if (selectedBranches.length > 0) {
          payload = {
            mode: 'custom',
            selected_pobocky: selectedBranches
          };
        } else {
          alert('Vyberte oblast nebo jednu či více poboček.');
          return;
        }

        saveBtn.disabled = true;
        fetch(saveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Comeback-Set-Branches': '1'
          },
          body: JSON.stringify(payload)
        })
          .then(function (r) { return r.json().catch(function () { return {}; }); })
          .then(function (json) {
            if (!json || json.ok !== true) {
              alert((json && json.err) ? json.err : 'Uložení výběru selhalo.');
              saveBtn.disabled = false;
              return;
            }
            var activeBtn = document.querySelector('.head_menu .head_menu_btn.is-on[data-sekce]');
            var sekce = activeBtn ? String(activeBtn.getAttribute('data-sekce') || '').trim() : '';
            if (sekce === '1' || sekce === '2' || sekce === '3') {
              w.location.href = '?sekce=' + sekce;
            } else {
              w.location.href = '?sekce=3';
            }
          })
          .catch(function () {
            alert('Uložení výběru selhalo.');
            saveBtn.disabled = false;
          });
      });
    }

      syncState('');
  }

  document.addEventListener('cb:main-swapped', initSelectPobockyCard);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSelectPobockyCard, { once: true });
  } else {
    initSelectPobockyCard();
  }
})(window);

// js/select_pobocky.js * Verze: V1 * Aktualizace: 25.03.2026
