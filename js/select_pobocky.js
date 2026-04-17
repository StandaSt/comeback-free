// js/select_pobocky.js * Verze: V2 * Aktualizace: 02.04.2026
'use strict';

(function (w) {
  function initRoot(root) {
    if (!root || root.getAttribute('data-cb-init') === '1') return;
    root.setAttribute('data-cb-init', '1');

    var areaInputs = Array.prototype.slice.call(root.querySelectorAll('.cb-pob-area'));
    var areaAllInput = root.querySelector('.cb-pob-area-all');
    var branchInputs = Array.prototype.slice.call(root.querySelectorAll('.cb-pob-branch'));
    var saveBtn = root.querySelector('[data-cb-pob-save="1"]');
    var panel = root.querySelector('[data-cb-pob-panel="1"]');
    var toggle = root.querySelector('[data-cb-pob-toggle="1"]');
    if (!saveBtn && root.id === 'cbSelectPobockyCard') {
      saveBtn = document.getElementById('cbPobockySaveBtn');
    }
    var saveUrl = String(root.getAttribute('data-save-url') || '');
    if (saveUrl === '') {
      saveUrl = 'index.php';
    }
    var branchToggleBtn = root.querySelector('[data-cb-pob-toggle="1"]');

    function updateBranchButtonLabel(payloadMode, payloadSelectedAreas) {
      if (!branchToggleBtn) return;

      var names = [];
      var allBranchNames = branchInputs.map(function (el) {
        return String(el.getAttribute('data-cb-name') || '').trim();
      }).filter(function (n) { return n !== ''; });

      if (payloadMode === 'area') {
        var selectedAreas = Array.isArray(payloadSelectedAreas) ? payloadSelectedAreas : [];
        names = branchInputs
          .filter(function (el) {
            var oblast = String(el.getAttribute('data-cb-oblast') || '').trim();
            return selectedAreas.indexOf(oblast) !== -1;
          })
          .map(function (el) { return String(el.getAttribute('data-cb-name') || '').trim(); })
          .filter(function (n) { return n !== ''; });
      } else {
        names = branchInputs
          .filter(function (el) { return !!el.checked; })
          .map(function (el) { return String(el.getAttribute('data-cb-name') || '').trim(); })
          .filter(function (n) { return n !== ''; });
      }

      var full = names.join(', ');
      var label = '';
      var title = full;
      var allSelected = (allBranchNames.length > 1 && names.length === allBranchNames.length);

      if (allSelected) {
        label = 'Pobočky: zvoleny všechny';
      } else if (names.length === 1) {
        label = names[0];
      } else if (names.length > 1) {
        label = full;
        if (full.length > 68) {
          var keep = names.slice(0, 3);
          var moreCount = Math.max(0, names.length - keep.length);
          label = keep.join(', ') + ' + ' + String(moreCount) + ' dalsi';
          title = full;
        }
      } else {
        label = String(branchToggleBtn.textContent || '').trim();
      }

      if (label !== '') {
        branchToggleBtn.textContent = label;
      }
      if (title !== '') {
        branchToggleBtn.setAttribute('title', title);
        branchToggleBtn.setAttribute('aria-label', title);
      }
    }

    function syncState(sourceType) {
      var checkedAreas = areaInputs.filter(function (el) { return el.checked; });
      var checkedBranches = branchInputs.filter(function (el) { return el.checked; });

      var hasArea = checkedAreas.length > 0;
      var hasBranch = checkedBranches.length > 0;

      if (sourceType === 'area' && hasArea) {
        branchInputs.forEach(function (el) { el.checked = false; });
        return;
      }

      if (sourceType === 'branch' && hasBranch) {
        areaInputs.forEach(function (el) { el.checked = false; });
        return;
      }

      if (hasArea) {
        branchInputs.forEach(function (el) { el.checked = false; });
        return;
      }

      if (hasBranch) {
        areaInputs.forEach(function (el) { el.checked = false; });
        if (areaAllInput) {
          areaAllInput.checked = false;
        }
      }
    }

    function refreshAreaAllState() {
      if (!areaAllInput) return;
      var allAreasChecked = areaInputs.length > 0 && areaInputs.every(function (el) { return el.checked; });
      var allBranchesChecked = branchInputs.length > 0 && branchInputs.every(function (el) { return el.checked; });
      areaAllInput.checked = !!(allAreasChecked || allBranchesChecked);
    }

    areaInputs.forEach(function (el) {
      el.addEventListener('change', function () {
        syncState('area');
        refreshAreaAllState();
      });
    });
    branchInputs.forEach(function (el) {
      el.addEventListener('change', function () {
        syncState('branch');
        refreshAreaAllState();
      });
    });
    if (areaAllInput) {
      areaAllInput.addEventListener('change', function () {
        if (areaAllInput.checked) {
          areaInputs.forEach(function (el) { el.checked = true; });
          branchInputs.forEach(function (el) { el.checked = false; });
          syncState('area');
          return;
        }
        areaInputs.forEach(function (el) { el.checked = false; });
        syncState('area');
      });
    }

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

        if (panel) {
          panel.classList.add('is-hidden');
        }
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'false');
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
              if (panel) {
                panel.classList.remove('is-hidden');
              }
              if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
              }
              return;
            }
            if (w.CB_AJAX && typeof w.CB_AJAX.refreshDashboard === 'function') {
              updateBranchButtonLabel(String(payload.mode || ''), payload.selected_oblasti || []);
              return w.CB_AJAX.refreshDashboard({ loaderText: 'Změna volby poboček, aktualizuji ...' });
            }
          })
          .catch(function () {
            alert('Uložení výběru selhalo.');
            if (panel) {
              panel.classList.remove('is-hidden');
            }
            if (toggle) {
              toggle.setAttribute('aria-expanded', 'true');
            }
          })
          .finally(function () {
            saveBtn.disabled = false;
          });
      });
    }

    syncState('');
    refreshAreaAllState();
  }

  function initHeaderToggle(root) {
    if (!root || root.getAttribute('data-cb-pob-header') !== '1') return;
    if (root.getAttribute('data-cb-pob-toggle-init') === '1') return;
    root.setAttribute('data-cb-pob-toggle-init', '1');

    var toggle = root.querySelector('[data-cb-pob-toggle="1"]');
    var panel = root.querySelector('[data-cb-pob-panel="1"]');
    if (!toggle || !panel) return;

    function closePanel() {
      panel.classList.add('is-hidden');
      toggle.setAttribute('aria-expanded', 'false');
    }
    function openPanel() {
      panel.classList.remove('is-hidden');
      toggle.setAttribute('aria-expanded', 'true');
    }

    toggle.setAttribute('aria-expanded', 'false');

    toggle.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (panel.classList.contains('is-hidden')) {
        openPanel();
      } else {
        closePanel();
      }
    });

    panel.addEventListener('click', function (event) {
      event.stopPropagation();
    });

    document.addEventListener('click', function () {
      closePanel();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closePanel();
      }
    });
  }

  function initSelectPobocky() {
    var roots = Array.prototype.slice.call(document.querySelectorAll('[data-cb-select-pobocky-root="1"]'));
    var legacy = document.getElementById('cbSelectPobockyCard');
    if (legacy) {
      roots.push(legacy);
    }
    roots.forEach(function (root) {
      initRoot(root);
      initHeaderToggle(root);
    });
  }

  document.addEventListener('cb:main-swapped', initSelectPobocky);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSelectPobocky, { once: true });
  } else {
    initSelectPobocky();
  }
})(window);

// js/select_pobocky.js * Verze: V2 * Aktualizace: 02.04.2026
