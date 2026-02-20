/* js/filtry_reset.js
 * Verze: V4
 * Aktualizace: 20.2.2026
 * Účel: Zobrazovat tlačítko "X" (reset filtrů) až když je vyplněn aspoň jeden filtr.
 * Pozn.: Funguje i při AJAX navigaci (MutationObserver).
 */

(function () {
  'use strict';

  // pro rychlou kontrolu v konzoli:
  // window.CB_FILTRY_RESET_LOADED musí být true
  window.CB_FILTRY_RESET_LOADED = true;

  function hasAnyFilterValue(formEl) {
    const inputs = formEl.querySelectorAll('input.filter-input[name^="f["]');
    for (const inp of inputs) {
      if (String(inp.value || '').trim().length > 0) return true;
    }
    return false;
  }

  function getResetButtons(formEl) {
    const row = formEl.querySelector('tr.filter-row');
    if (!row) return [];
    // může být více variant (a.icon-x, .icon-x.small, atd.)
    return Array.from(row.querySelectorAll('.icon-x'));
  }

  function setBtnVisible(btn, visible) {
    if (visible) {
      btn.style.visibility = 'visible';
      btn.style.opacity = '1';
      btn.style.pointerEvents = 'auto';
    } else {
      btn.style.visibility = 'hidden';
      btn.style.opacity = '0';
      btn.style.pointerEvents = 'none';
    }
  }

  function updateForm(formEl) {
    const any = hasAnyFilterValue(formEl);
    const btns = getResetButtons(formEl);
    for (const b of btns) setBtnVisible(b, any);
  }

  function bindForm(formEl) {
    if (formEl.dataset.cbFiltryResetBound === '1') return;
    formEl.dataset.cbFiltryResetBound = '1';

    const inputs = formEl.querySelectorAll('input.filter-input[name^="f["]');
    if (!inputs.length) return;

    // počáteční stav (kvůli předvyplněným filtrům z URL)
    updateForm(formEl);

    const onChange = function () { updateForm(formEl); };

    for (const inp of inputs) {
      inp.addEventListener('input', onChange);
      inp.addEventListener('change', onChange);
    }
  }

  function scanAndBind(root) {
    const forms = (root || document).querySelectorAll('form');
    for (const f of forms) {
      // bereme jen formuláře, kde je filter-row + aspoň jeden filter input
      if (f.querySelector('tr.filter-row') && f.querySelector('input.filter-input[name^="f["]')) {
        bindForm(f);
      }
    }
  }

  function init() {
    scanAndBind(document);

    // AJAX / dynamický obsah: sleduj změny v DOM a znovu bindni nové formuláře
    const obs = new MutationObserver(function (mutations) {
      for (const m of mutations) {
        for (const n of m.addedNodes) {
          if (n && n.nodeType === 1) scanAndBind(n);
        }
      }
    });

    obs.observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();