// js/karty_report_form.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  const FORM_CONTROL_SELECTOR = 'input, select, button';

  function getFieldValue(root, selector) {
    const field = root.querySelector(selector);
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) {
      return '';
    }
    return String(field.value || '').trim();
  }

  function parseMoneyValue(raw, kind) {
    let value = String(raw || '').replace(/\s+/g, '').replace(/Kč/gi, '').replace(',', '.');
    if (kind === 'decimal') {
      value = value.replace(/[^0-9.]/g, '');
      const firstDot = value.indexOf('.');
      if (firstDot !== -1) {
        value = value.slice(0, firstDot + 1) + value.slice(firstDot + 1).replace(/\./g, '');
      }
      return value.replace(/^0+(?=\d)/, '');
    }
    return value.replace(/\D+/g, '').replace(/^0+(?=\d)/, '');
  }

  function formatMoneyValue(raw, kind) {
    const clean = parseMoneyValue(raw, kind);
    if (clean === '') return '';

    if (kind === 'decimal') {
      const numeric = Number.parseFloat(clean);
      if (Number.isNaN(numeric)) return '';
      const parts = numeric.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1').split('.');
      const whole = String(parts[0] || '0').replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
      const decimal = parts[1] ? ',' + parts[1] : '';
      return whole + decimal + ' Kč';
    }

    const numeric = Number.parseInt(clean, 10);
    if (Number.isNaN(numeric)) return '';
    return String(numeric).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč';
  }

  function syncWeekdayFromDate(root) {
    const dateInput = root.querySelector('[data-zr-date]');
    const dateDisplay = root.querySelector('[data-zr-date-display]');
    if (!(dateInput instanceof HTMLInputElement) || !(dateDisplay instanceof HTMLInputElement)) {
      return;
    }

    const raw = String(dateInput.value || '').trim();
    if (raw === '') {
      dateDisplay.value = '';
      return;
    }

    const date = new Date(raw + 'T12:00:00');
    if (Number.isNaN(date.getTime())) {
      dateDisplay.value = raw;
      return;
    }

    const weekdays = [
      'Neděle',
      'Pondělí',
      'Úterý',
      'Středa',
      'Čtvrtek',
      'Pátek',
      'Sobota'
    ];
    const day = weekdays[date.getDay()] || '';
    const formatted = date.getDate() + '.' + (date.getMonth() + 1) + '.' + date.getFullYear();
    dateDisplay.value = day.toLowerCase() + ' ' + formatted;
  }

  function isRequiredFilled(root, key) {
    switch (key) {
      case 'datum':
        return getFieldValue(root, '[data-zr-date]') !== '';
      case 'oteviral':
        return getFieldValue(root, '[data-zr-field="oteviral"]') !== '';
      case 'zaviral':
        return getFieldValue(root, '[data-zr-field="zaviral"]') !== '';
      case 'pokladna_hotovost':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="pokladna_hotovost"]'), 'int') !== '';
      case 'pokladna_terminal':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="pokladna_terminal"]'), 'decimal') !== '';
      case 'pokladna_stravenky':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="pokladna_stravenky"]'), 'int') !== '';
      case 'vydaje_benzin':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="vydaje_benzin"]'), 'int') !== '';
      case 'vydaje_auta':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="vydaje_auta"]'), 'int') !== '';
      case 'vydaje_suroviny':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="vydaje_suroviny"]'), 'int') !== '';
      case 'vydaje_ostatni':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="vydaje_ostatni"]'), 'int') !== '';
      case 'vydaje_phm_soukrome':
        return parseMoneyValue(getFieldValue(root, '[data-zr-field="vydaje_phm_soukrome"]'), 'int') !== '';
      case 'instor_jmeno': {
        const field = root.querySelector('[data-zr-editor="instor"] [data-zr-editor-field="jmeno"]');
        return field instanceof HTMLSelectElement && String(field.value || '').trim() !== '';
      }
      case 'instor_zacatek': {
        const field = root.querySelector('[data-zr-editor="instor"] [data-zr-editor-field="start"]');
        return field instanceof HTMLSelectElement && String(field.value || '').trim() !== '';
      }
      case 'instor_konec': {
        const field = root.querySelector('[data-zr-editor="instor"] [data-zr-editor-field="end"]');
        return field instanceof HTMLSelectElement && String(field.value || '').trim() !== '';
      }
      case 'instor_pauza': {
        const field = root.querySelector('[data-zr-editor="instor"] [data-zr-editor-field="break"]');
        return field instanceof HTMLInputElement && String(field.value || '').trim() !== '';
      }
      default:
        return false;
    }
  }

  function isFirstInstorRowComplete(root) {
    const list = root.querySelector('[data-zr-saved-list="instor"]');
    return !!(list instanceof HTMLElement && list.children.length > 0);
  }

  function syncRequiredState(root) {
    if (!(root instanceof HTMLElement)) return;

    root.querySelectorAll('[data-zr-required-label]').forEach((label) => {
      if (!(label instanceof HTMLElement)) return;
      const key = String(label.getAttribute('data-zr-required-label') || '');
      label.classList.toggle('is-valid', isRequiredFilled(root, key));
    });

    const submit = root.querySelector('[data-zr-submit]');
    if (!(submit instanceof HTMLButtonElement)) return;

    const ready = (
      isRequiredFilled(root, 'datum') &&
      isRequiredFilled(root, 'oteviral') &&
      isRequiredFilled(root, 'zaviral') &&
      isRequiredFilled(root, 'pokladna_hotovost') &&
      isRequiredFilled(root, 'pokladna_terminal') &&
      isRequiredFilled(root, 'pokladna_stravenky') &&
      isRequiredFilled(root, 'vydaje_benzin') &&
      isRequiredFilled(root, 'vydaje_auta') &&
      isRequiredFilled(root, 'vydaje_suroviny') &&
      isRequiredFilled(root, 'vydaje_ostatni') &&
      isRequiredFilled(root, 'vydaje_phm_soukrome') &&
      isFirstInstorRowComplete(root)
    );

    submit.classList.toggle('is-hidden', !ready);
  }

  function bindMoneyInputs(root) {
    root.querySelectorAll('[data-zr-money]').forEach((input) => {
      if (!(input instanceof HTMLInputElement) || input.getAttribute('data-zr-money-bound') === '1') {
        return;
      }
      input.setAttribute('data-zr-money-bound', '1');

      const kind = String(input.getAttribute('data-zr-money') || 'int');

      input.addEventListener('focus', () => {
        input.value = parseMoneyValue(input.value, kind);
      });

      input.addEventListener('input', () => {
        input.value = parseMoneyValue(input.value, kind);
      });

      input.addEventListener('blur', () => {
        input.value = formatMoneyValue(input.value, kind);
        syncRequiredState(root);
      });

      if (String(input.value || '').trim() !== '') {
        input.value = formatMoneyValue(input.value, kind);
      }
    });
  }

  function bindEnterNavigation(root) {
    const form = root.querySelector('[data-zr-form]');
    if (!(form instanceof HTMLFormElement) || form.getAttribute('data-zr-enter-bound') === '1') {
      return;
    }
    form.setAttribute('data-zr-enter-bound', '1');

    form.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) {
        return;
      }

      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.matches(FORM_CONTROL_SELECTOR)) {
        return;
      }

      if (target instanceof HTMLButtonElement) {
        return;
      }

      event.preventDefault();

      const focusables = Array.from(form.querySelectorAll(FORM_CONTROL_SELECTOR))
        .filter((el) => {
          if (!(el instanceof HTMLElement)) return false;
          if (el instanceof HTMLInputElement && el.type === 'hidden') return false;
          if (el instanceof HTMLInputElement && el.readOnly) return false;
          return !el.hasAttribute('disabled') && !el.classList.contains('is-hidden') && el.offsetParent !== null;
        });

      const currentIndex = focusables.indexOf(target);
      if (currentIndex === -1) return;

      const next = focusables[currentIndex + 1];
      if (next instanceof HTMLElement) {
        next.focus();
        if (next instanceof HTMLInputElement) {
          next.select();
        }
      }
    });
  }

  function initOne(root) {
    if (!(root instanceof HTMLElement) || root.getAttribute('data-zr-form-init') === '1') {
      return;
    }
    root.setAttribute('data-zr-form-init', '1');

    bindMoneyInputs(root);
    bindEnterNavigation(root);
    syncWeekdayFromDate(root);
    syncRequiredState(root);

    const dateInput = root.querySelector('[data-zr-date]');
    if (dateInput instanceof HTMLInputElement) {
      dateInput.addEventListener('change', () => {
        syncWeekdayFromDate(root);
        syncRequiredState(root);
      });
      dateInput.addEventListener('input', () => {
        syncWeekdayFromDate(root);
        syncRequiredState(root);
      });
    }

    const form = root.querySelector('[data-zr-form]');
    if (form instanceof HTMLFormElement) {
      form.addEventListener('input', () => syncRequiredState(root));
      form.addEventListener('change', () => syncRequiredState(root));
    }
  }

  function initKartyReportForm() {
    document.querySelectorAll('.cb-zadani-reportu').forEach(initOne);
  }

  w.cbSyncReportFormState = syncRequiredState;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyReportForm);
  } else {
    initKartyReportForm();
  }
}(window));
