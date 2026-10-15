// Automaticke filtry s dorovnanim mezi obdobi, halerove vypocty a ochrana rozepsane kontroly.
(() => {
  'use strict';
  // Inicializace funguje i po dynamickem vlozeni stranky do shellu IS.
  function init() {
    document.querySelectorAll('[data-kt-filters]').forEach(filters => {
      if (filters.dataset.ktReady) return;
      filters.dataset.ktReady = '1';
      const mode = filters.querySelector('[name="kt_rezim"]');
      const from = filters.querySelector('[name="kt_od"]');
      const to = filters.querySelector('[name="kt_do"]');
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
        const match = raw.trim().replace(/[\s\u00a0]/g, '').replace(',', '.').match(/^(-?)(\d{1,11})(?:\.(\d{1,2}))?$/);
        return match ? (match[1] ? -1 : 1) * (Number(match[2]) * 100 + Number((match[3] || '').padEnd(2, '0'))) : null;
      };
      const money = value => value === null ? '—' : `${value < 0 ? '−' : ''}${Math.floor(Math.abs(value) / 100).toLocaleString('cs-CZ')},${String(Math.abs(value) % 100).padStart(2, '0')} Kč`;
      const recalculate = () => {
        const values = {};
        form.querySelectorAll('[data-kt-row]').forEach(row => {
          const input = row.querySelector('.kt_amount');
          const cell = row.querySelector('[data-kt-right]');
          const value = input ? parse(input.value) : (cell.dataset.ktRight === '' ? null : Number(cell.dataset.ktRight));
          if (input) input.setCustomValidity(input.value.trim() !== '' && value === null ? 'Zadejte částku s nejvýše dvěma desetinnými místy.' : '');
          values[row.dataset.ktRow] = value;
        });
        const keys = Object.keys(values).filter(key => !['dj_cash', 'trzba', 'rozdil'].includes(key));
        values.rozdil = keys.some(key => values[key] === null) || values.trzba === null
          ? null : keys.reduce((sum, key) => sum + values[key], 0) - values.trzba;
        form.querySelectorAll('[data-kt-row]').forEach(row => {
          const value = values[row.dataset.ktRow];
          if (row.dataset.ktRow === 'rozdil') row.querySelector('[data-kt-value]').textContent = money(value);
          const left = row.dataset.left === '' ? null : Number(row.dataset.left);
          const diff = value === null || left === null ? null : value - left;
          const cell = row.querySelector('[data-kt-diff]');
          cell.textContent = money(diff);
          cell.classList.toggle('kt_negative', diff !== null && diff < 0);
          cell.classList.toggle('kt_positive', diff !== null && diff >= 0);
        });
      };
      form.addEventListener('input', () => { dirty = true; recalculate(); });
      form.addEventListener('submit', () => { if (form.checkValidity()) dirty = false; });
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
  document.addEventListener('cb:filters-swapped', init);
  document.addEventListener('cb:main-swapped', init);
})();
