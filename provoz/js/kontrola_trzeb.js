// Filtry a vice pobocek archivu, castky s menou, presne souhrny a rucne potvrzene ulozeni.
(() => {
  'use strict';
  // Inicializace funguje i po dynamickem vlozeni stranky do shellu IS.
  function init() {
    document.querySelectorAll('[data-kt-validity]').forEach(form => {
      if (form.dataset.ktReady) return;
      form.dataset.ktReady = '1';
      form.addEventListener('submit', event => {
        if (!window.confirm('Potvrdit změnu platnosti této kontroly?')) event.preventDefault();
      });
    });
    document.querySelectorAll('[data-kt-filters]').forEach(filters => {
      if (filters.dataset.ktReady) return;
      filters.dataset.ktReady = '1';
      const mode = filters.querySelector('[name="kt_rezim"]');
      const from = filters.querySelector('[name="kt_od"]');
      const to = filters.querySelector('[name="kt_do"]');
      const branchSelect = filters.querySelector('.kt_branch_select');
      let branchesChanged = false;
      // Vice checkboxu lze zmenit najednou; vyber se pouzije zavrenim nabidky.
      branchSelect?.addEventListener('toggle', () => {
        if (!branchSelect.open && branchesChanged) { branchesChanged = false; filters.requestSubmit(); }
      });
      // Skryta pole nezucastnujeme validace; kalendare neomezuji poradi zmen mezi.
      const update = () => {
        if (!mode) return;
        filters.querySelectorAll('[data-kt-week]').forEach(el => { el.hidden = mode.value !== 'tyden'; });
        filters.querySelectorAll('[data-kt-range]').forEach(el => { el.hidden = mode.value !== 'obdobi'; });
        filters.querySelector('[name="kt_tyden"]').disabled = mode.value !== 'tyden';
        from.disabled = to.disabled = mode.value !== 'obdobi';
        from.required = to.required = mode.value === 'obdobi';
        filters.querySelector('[name="kt_tyden"]').required = mode.value === 'tyden';
      };
      // requestSubmit zachova validaci i existujici ochranu neulozenych kontrolnich castek.
      filters.addEventListener('change', event => {
        if (branchSelect?.contains(event.target)) {
          const boxes = Array.from(branchSelect.querySelectorAll('[name="kt_pobocky[]"]'));
          const all = branchSelect.querySelector('[data-kt-all]');
          if (event.target === all) boxes.forEach(box => { box.checked = all.checked; });
          const selected = boxes.filter(box => box.checked).length;
          all.checked = selected === boxes.length;
          all.indeterminate = selected > 0 && selected < boxes.length;
          branchSelect.querySelector('summary').textContent = `Pobočky: ${all.checked ? 'všechny' : selected}`;
          branchesChanged = true;
          return;
        }
        // Upravena mez ma prednost; druhou dorovname, aby uzivatel nemusel resit poradi.
        if (mode?.value === 'obdobi' && from.value !== '' && to.value !== '' && from.value > to.value) {
          if (event.target === from) to.value = from.value;
          if (event.target === to) from.value = to.value;
        }
        update();
        if (filters.reportValidity()) filters.requestSubmit();
      });
      update();
    });
    document.querySelectorAll('[data-kt-form]').forEach(form => {
      if (form.dataset.ktReady) return;
      form.dataset.ktReady = '1';
      let dirty = false;
      // Zadani se nesmi zaokrouhlit ani prevest pres binarni desetinne cislo.
      const parse = raw => {
        const match = raw.trim().replace(/\s*Kč$/, '').replace(/[\s\u00a0]/g, '').replace('−', '-').replace(',', '.').match(/^(-?)(\d{1,11})(?:\.(\d{1,2}))?$/);
        return match ? (match[1] ? -1 : 1) * (Number(match[2]) * 100 + Number((match[3] || '').padEnd(2, '0'))) : null;
      };
      const money = value => value === null ? '—' : `${value < 0 ? '−' : ''}${Math.floor(Math.abs(value) / 100).toLocaleString('cs-CZ')},${String(Math.abs(value) % 100).padStart(2, '0')} Kč`;
      // Pri editaci a odeslani zustava presny desetinny zapis; mimo editaci zobrazujeme menu.
      const decimal = value => `${value < 0 ? '-' : ''}${Math.floor(Math.abs(value) / 100)},${String(Math.abs(value) % 100).padStart(2, '0')}`;
      const formatAmount = input => { const value = parse(input.value); if (value !== null) input.value = money(value); };
      form.querySelectorAll('.kt_amount').forEach(input => {
        formatAmount(input);
        input.addEventListener('focus', () => { const value = parse(input.value); if (value !== null) input.value = decimal(value); });
        input.addEventListener('blur', () => formatAmount(input));
      });
      const recalculate = () => {
        const values = {};
        const differences = {};
        let total = 0, complete = true, manualDiff = 0, allDiff = 0, checked = 0;
        form.querySelectorAll('[data-kt-row]').forEach(row => {
          const input = row.querySelector('.kt_amount');
          const cell = row.querySelector('[data-kt-right]');
          const value = input ? parse(input.value) : (cell.dataset.ktRight === '' ? null : Number(cell.dataset.ktRight));
          if (input) input.setCustomValidity(input.value.trim() !== '' && value === null ? 'Zadejte částku s nejvýše dvěma desetinnými místy.' : '');
          values[row.dataset.ktRow] = value;
          const key = row.dataset.ktRow;
          const left = row.dataset.left === '' ? null : Number(row.dataset.left);
          differences[key] = value === null || left === null ? null : value - left;
          if (!['dj_cash', 'trzba', 'rozdil'].includes(key)) {
            if (value === null) complete = false; else total += value;
            if (differences[key] !== null) {
              allDiff += differences[key];
              if (cell.dataset.ktManual === '1') { manualDiff += differences[key]; checked++; }
            }
          }
        });
        values.trzba = complete ? total : null;
        values.rozdil = checked ? manualDiff : null;
        form.querySelectorAll('[data-kt-row]').forEach(row => {
          const value = values[row.dataset.ktRow];
          if (['trzba', 'rozdil'].includes(row.dataset.ktRow)) row.querySelector('[data-kt-value]').textContent = money(value);
          const left = row.dataset.left === '' ? null : Number(row.dataset.left);
          const diff = row.dataset.ktRow === 'rozdil' ? (checked ? allDiff : null) : (value === null || left === null ? null : value - left);
          const cell = row.querySelector('[data-kt-diff]');
          cell.textContent = money(diff);
          cell.classList.toggle('kt_negative', diff !== null && diff < 0);
          cell.classList.toggle('kt_positive', diff !== null && diff >= 0);
        });
      };
      // Poznamka roste jen podle obsahu; ostatni radky zustavaji kompaktni.
      const resizeNote = note => { note.style.height = '24px'; note.style.height = `${Math.max(24, note.scrollHeight + 2)}px`; };
      form.querySelectorAll('textarea').forEach(resizeNote);
      form.addEventListener('input', event => { dirty = true; if (event.target.matches('textarea')) resizeNote(event.target); recalculate(); });
      form.addEventListener('click', event => {
        const button = event.target.closest('[data-kt-copy]');
        if (!button) return;
        const row = button.closest('[data-kt-row]');
        const value = Number(row.dataset.left);
        const input = row.querySelector('.kt_amount');
        input.focus();
        input.value = money(value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
      // Enter v castce neodesila formular, v poznamce ponechava novy radek.
      form.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.matches('input')) event.preventDefault();
      });
      let saveClicked = false;
      form.querySelector('.kt_save')?.addEventListener('click', () => {
        saveClicked = true;
        setTimeout(() => { saveClicked = false; }, 0);
      });
      form.addEventListener('submit', event => {
        if (!saveClicked || !form.checkValidity()) { event.preventDefault(); return; }
        // PHP dostane puvodni ciselny format bez meny; zobrazeni nesmi ovlivnit ulozena data.
        form.querySelectorAll('.kt_amount').forEach(input => { input.value = decimal(parse(input.value)); });
        dirty = false;
      });
      // Zmena pobocky nebo tydne nesmi bez upozorneni zahodit rucne spocitane castky.
      const warn = event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } };
      window.addEventListener('beforeunload', warn);
      const confirmLink = event => {
        if (!form.isConnected || !dirty || !(event.target instanceof Element)) return;
        const link = event.target.closest('a[href]');
        if (!link || link.getAttribute('href').startsWith('#')) return;
        if (!window.confirm('Opustit rozepsanou kontrolu? Neuložené částky a poznámky se ztratí.')) {
          event.preventDefault();
          event.stopPropagation();
        } else dirty = false;
      };
      document.addEventListener('click', confirmLink, true);
      const cleanup = () => {
        if (!form.isConnected) {
          window.removeEventListener('beforeunload', warn);
          document.removeEventListener('click', confirmLink, true);
          document.removeEventListener('cb:main-swapped', cleanup);
        }
      };
      document.addEventListener('cb:main-swapped', cleanup);
      document.querySelector('[data-kt-filters]')?.addEventListener('submit', event => {
        if (dirty && !window.confirm('Opustit rozepsanou kontrolu? Neuložené částky a poznámky se ztratí.')) event.preventDefault();
        else dirty = false;
      });
      recalculate();
    });
  }
  init();
  // Ovladac poctu radku patri k filtrovacimu formulari i mimo jeho HTML obal.
  document.addEventListener('change', event => {
    if (event.target.matches('select[name="kt_per"]') && event.target.form) event.target.form.requestSubmit();
  });
  document.addEventListener('click', event => {
    document.querySelectorAll('.kt_branch_select[open]').forEach(select => {
      if (!select.contains(event.target)) select.open = false;
    });
  });
  // Filtry meni jen pracovni plochu; neni treba znovu spoustet shell ani aktualizaci Restie.
  // Poslouchame az po formulari, aby nejdrive rozhodla ochrana neulozenych castek.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.matches('[data-kt-filters]') || typeof window.CB_LOAD_MODULE !== 'function') return;
    event.preventDefault();
    const params = new URLSearchParams();
    let branchIndex = 0;
    // Navigace prenasi parametry pres set(); indexy zabrani prepsani opakovanych checkboxu.
    new FormData(form).forEach((value, key) => {
      params.append(key === 'kt_pobocky[]' ? `kt_pobocky[${branchIndex++}]` : key, value);
    });
    if (event.submitter?.name) params.set(event.submitter.name, event.submitter.value);
    window.CB_LOAD_MODULE('provoz', false, params);
  });
  document.addEventListener('cb:filters-swapped', init);
  document.addEventListener('cb:main-swapped', init);
})();
