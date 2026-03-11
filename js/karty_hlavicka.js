// js/karty_hlavicka.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  function setBranchSelectDisabledForRoot(root, isDisabled) {
    if (!(root instanceof Element) || !root.classList.contains('cb-zadani-reportu')) {
      return;
    }

    const branchSelect = document.getElementById('cbPobockaSelect');
    if (!(branchSelect instanceof HTMLSelectElement)) {
      return;
    }

    branchSelect.disabled = !!isDisabled;
  }

  function syncBranchTitle() {
    const branchSelect = document.getElementById('cbPobockaSelect');
    let branchName = '';

    if (branchSelect instanceof HTMLSelectElement) {
      const option = branchSelect.selectedOptions && branchSelect.selectedOptions[0]
        ? branchSelect.selectedOptions[0]
        : branchSelect.options[branchSelect.selectedIndex] || null;
      branchName = option ? String(option.textContent || '').trim() : '';
    }

    document.querySelectorAll('.cb-zadani-reportu [data-zr-card-title], .dash_maxi_card [data-zr-card-title]').forEach((titleEl) => {
      if (!(titleEl instanceof HTMLElement)) return;
      const base = String(titleEl.getAttribute('data-zr-card-title-base') || '').trim();
      if (base === '') return;
      titleEl.textContent = branchName !== '' ? (base + ' - ' + branchName) : base;
    });
  }

  function initKartyHlavicka() {
    const branchSelect = document.getElementById('cbPobockaSelect');
    if (branchSelect instanceof HTMLSelectElement && branchSelect.getAttribute('data-zr-title-bound') !== '1') {
      branchSelect.setAttribute('data-zr-title-bound', '1');
      branchSelect.addEventListener('change', syncBranchTitle);
    }

    syncBranchTitle();
  }

  w.cbSetBranchSelectDisabledForRoot = setBranchSelectDisabledForRoot;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyHlavicka);
  } else {
    initKartyHlavicka();
  }
}(window));
