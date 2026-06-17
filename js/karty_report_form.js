// js/karty_report_form.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  const FORM_CONTROL_SELECTOR = 'input, select, button';
  const reportCalcTimers = new WeakMap();

  function getFieldValue(root, selector) {
    const field = root.querySelector(selector);
    if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) {
      return '';
    }
    return String(field.value || '').trim();
  }

  function getForm(root) {
    if (root instanceof HTMLFormElement) return root;
    const form = root.querySelector('[data-zr-form]');
    return form instanceof HTMLFormElement ? form : null;
  }

  function getReportValue(root, selector) {
    const field = root.querySelector(selector);
    return field instanceof HTMLInputElement ? String(field.value || '').trim() : '';
  }

  function saveDraftAction(root, action, data) {
    const form = getForm(root);
    if (!(form instanceof HTMLFormElement)) return Promise.resolve({ ok: true });

    const body = new FormData();
    body.set('dr_action', action);
    body.set('id_pob', getReportValue(root, 'input[name="id_pob"]'));
    body.set('datum_reportu', getReportValue(root, 'input[name="datum_reportu"]'));
    Object.keys(data || {}).forEach((key) => {
      body.set(key, String(data[key] ?? ''));
    });

    return fetch(form.action || 'index.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-Comeback-Dr-Pracovni': '1'
      },
      body
    }).then((res) => {
      return res.text().then((text) => {
        let json = null;
        try {
          json = JSON.parse(String(text || '{}'));
        } catch (e) {
          json = null;
        }
        if (!res.ok || !json || json.ok !== true) {
          throw new Error((json && json.err) ? String(json.err) : 'Uložení pole selhalo.');
        }
        return json;
      });
    });
  }

  function dbMoneyField(uiField) {
    const map = {
      pokladna_hotovost: 'hotovost',
      pokladna_terminal: 'terminal',
      pokladna_stravenky: 'stravenky',
      vydaje_benzin: 'vydaje_benzin',
      vydaje_auta: 'vydaje_auta',
      vydaje_suroviny: 'vydaje_suroviny',
      vydaje_ostatni: 'vydaje_ostatni',
      vydaje_phm_soukrome: 'vydaje_phm_soukrome'
    };
    return map[String(uiField || '')] || '';
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

  function applyReportCalculation(root, json) {
    const visible = root.querySelector('[data-zr-report-rozdil]');
    const hidden = root.querySelector('[data-zr-report-rozdil-value]');
    if (visible instanceof HTMLElement) {
      visible.textContent = String(json && json.rozdil_label ? json.rozdil_label : '-- K\u010d');
    }
    if (hidden instanceof HTMLInputElement) {
      hidden.value = json && json.rozdil !== null && json.rozdil !== undefined ? Number(json.rozdil).toFixed(2) : '';
    }

    const colVisible = root.querySelector('[data-zr-report-col]');
    const colHidden = root.querySelector('[data-zr-report-col-value]');
    if (colVisible instanceof HTMLElement) {
      colVisible.textContent = String(json && json.col_label ? json.col_label : '-- %');
    }
    if (colHidden instanceof HTMLInputElement) {
      colHidden.value = json && json.col_pomer !== null && json.col_pomer !== undefined ? Number(json.col_pomer).toFixed(6) : '';
    }

    const colBezDph = root.querySelector('[data-zr-report-col-bez-dph]');
    if (colBezDph instanceof HTMLElement) {
      colBezDph.textContent = String(json && json.col_bez_dph_label ? json.col_bez_dph_label : 'COL bez DPH: -- %');
    }
  }

  function requestReportCalculation(root) {
    const form = getForm(root);
    if (!(form instanceof HTMLFormElement)) return Promise.resolve(null);

    const body = new FormData(form);
    body.set('dr_action', 'prepocet_col_rozdil');
    body.set('id_pob', getReportValue(root, 'input[name="id_pob"]'));
    body.set('datum_reportu', getReportValue(root, 'input[name="datum_reportu"]'));

    return fetch(form.action || 'index.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-Comeback-Dr-Pracovni': '1'
      },
      body
    }).then((res) => {
      return res.text().then((text) => {
        let json = null;
        try {
          json = JSON.parse(String(text || '{}'));
        } catch (e) {
          json = null;
        }
        if (!res.ok || !json || json.ok !== true) {
          throw new Error((json && json.err) ? String(json.err) : 'Prepocet reportu selhal.');
        }
        applyReportCalculation(root, json);
        return json;
      });
    });
  }

  function syncReportDifference(root) {
    if (!(root instanceof HTMLElement)) return;
    const oldTimer = reportCalcTimers.get(root);
    if (oldTimer) {
      w.clearTimeout(oldTimer);
    }
    const timer = w.setTimeout(() => {
      reportCalcTimers.delete(root);
      requestReportCalculation(root).catch((err) => {
        if (w.console && w.console.warn) w.console.warn(err);
      });
    }, 200);
    reportCalcTimers.set(root, timer);
  }

  function formatDuration(totalSeconds) {
    const safeSeconds = Math.max(0, Math.floor(Number(totalSeconds) || 0));
    const hours = Math.floor(safeSeconds / 3600);
    const minutes = Math.floor((safeSeconds % 3600) / 60);
    const seconds = safeSeconds % 60;
    return hours + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
  }

  function setSubmitLocked(button, text) {
    button.disabled = true;
    button.setAttribute('aria-disabled', 'true');
    button.style.background = '#d9dee8';
    button.style.borderColor = '#c1c9d6';
    button.style.color = '#5f6b7a';
    button.style.cursor = 'not-allowed';
    button.style.opacity = '1';
    button.textContent = text;
  }

  function setSubmitReady(button, text) {
    button.disabled = false;
    button.setAttribute('aria-disabled', 'false');
    button.style.background = '';
    button.style.borderColor = '';
    button.style.color = '';
    button.style.cursor = '';
    button.style.opacity = '';
    button.textContent = text;
  }

  function setSubmitMissing(button, text) {
    button.disabled = true;
    button.setAttribute('aria-disabled', 'true');
    button.style.background = '#f8d7da';
    button.style.borderColor = '#f1aeb5';
    button.style.color = '#842029';
    button.style.cursor = 'not-allowed';
    button.style.opacity = '1';
    button.textContent = text;
  }

  function areSubmitRequiredFieldsFilled(root) {
    if (
      getFieldValue(root, '[data-zr-field="oteviral"]') === ''
      || getFieldValue(root, '[data-zr-field="zaviral"]') === ''
      || parseMoneyValue(getFieldValue(root, '[data-zr-field="pokladna_hotovost"]'), 'int') === ''
      || parseMoneyValue(getFieldValue(root, '[data-zr-field="pokladna_terminal"]'), 'decimal') === ''
      || parseMoneyValue(getFieldValue(root, '[data-zr-field="pokladna_stravenky"]'), 'int') === ''
    ) {
      return false;
    }

    const personRows = root.querySelectorAll('[data-zr-person-row="instor"], [data-zr-person-row="kuryr"]');
    for (const row of personRows) {
      const start = row.querySelector('[data-zr-start]');
      const end = row.querySelector('[data-zr-end]');
      if (
        !(start instanceof HTMLInputElement)
        || !(end instanceof HTMLInputElement)
        || String(start.value || '').trim() === ''
        || String(end.value || '').trim() === ''
      ) {
        return false;
      }
    }

    return true;
  }

  function syncSubmitButton(root) {
    const button = root.querySelector('[data-zr-submit]');
    if (!(button instanceof HTMLButtonElement)) {
      return true;
    }

    const targetTs = Number.parseInt(String(button.getAttribute('data-zr-submit-at') || '0'), 10) || 0;
    const lockedText = String(button.getAttribute('data-zr-submit-locked-text') || 'Report bude možné uložit za');
    const readyText = String(button.getAttribute('data-zr-submit-ready-text') || 'Report je zkontrolovaný, uložit');
    const missingText = String(button.getAttribute('data-zr-submit-missing-text') || 'Vyplň povinná pole');
    const remaining = targetTs - Math.floor(Date.now() / 1000);

    if (remaining > 0) {
      setSubmitLocked(button, lockedText + ' ' + formatDuration(remaining));
      return false;
    }

    if (!areSubmitRequiredFieldsFilled(root)) {
      setSubmitMissing(button, missingText);
      return false;
    }

    setSubmitReady(button, readyText);
    return true;
  }

  function bindSubmitCountdown(root) {
    const button = root.querySelector('[data-zr-submit]');
    if (!(button instanceof HTMLButtonElement) || button.getAttribute('data-zr-countdown-bound') === '1') {
      return;
    }
    button.setAttribute('data-zr-countdown-bound', '1');

    if (syncSubmitButton(root)) {
      return;
    }

    const timer = w.setInterval(() => {
      if (!document.body.contains(button) || syncSubmitButton(root)) {
        w.clearInterval(timer);
      }
    }, 1000);
  }

  function bindFinalSubmit(root) {
    const button = root.querySelector('[data-zr-submit]');
    if (!(button instanceof HTMLButtonElement) || button.getAttribute('data-zr-final-bound') === '1') {
      return;
    }
    button.setAttribute('data-zr-final-bound', '1');

    button.addEventListener('click', () => {
      if (button.disabled || !syncSubmitButton(root)) {
        return;
      }
      const originalText = button.textContent;
      button.disabled = true;
      button.textContent = 'Ukládám report';
      saveDraftAction(root, 'final_save', {
        rozdil: getReportValue(root, '[data-zr-report-rozdil-value]'),
        col_pomer: getReportValue(root, '[data-zr-report-col-value]')
      })
        .then(() => {
          button.textContent = 'Report uložen';
        })
        .catch((err) => {
          button.disabled = false;
          button.textContent = originalText || 'Report je zkontrolovaný, uložit';
          if (w.alert) w.alert(err && err.message ? err.message : 'Uložení reportu selhalo.');
        });
    });
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
        const field = root.querySelector('[data-zr-saved-list="instor"] input[name="instor_jmeno[]"]');
        return field instanceof HTMLInputElement && String(field.value || '').trim() !== '';
      }
      case 'instor_zacatek': {
        const field = root.querySelector('[data-zr-saved-list="instor"] input[name="instor_zacatek[]"]');
        return field instanceof HTMLInputElement && String(field.value || '').trim() !== '';
      }
      case 'instor_konec': {
        const field = root.querySelector('[data-zr-saved-list="instor"] input[name="instor_konec[]"]');
        return field instanceof HTMLInputElement && String(field.value || '').trim() !== '';
      }
      case 'instor_pauza': {
        const field = root.querySelector('[data-zr-saved-list="instor"] input[name="instor_pauza_hod[]"]');
        return field instanceof HTMLInputElement && String(field.value || '').trim() !== '';
      }
      default:
        return false;
    }
  }

  function syncRequiredState(root) {
    if (!(root instanceof HTMLElement)) return;

    root.querySelectorAll('[data-zr-required-label]').forEach((label) => {
      if (!(label instanceof HTMLElement)) return;
      const key = String(label.getAttribute('data-zr-required-label') || '');
      label.classList.toggle('is-valid', isRequiredFilled(root, key));
    });

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
        syncReportDifference(root);
      });

      input.addEventListener('blur', () => {
        input.value = formatMoneyValue(input.value, kind);
        syncRequiredState(root);
        syncReportDifference(root);
        const field = dbMoneyField(input.getAttribute('data-zr-field'));
        if (field !== '') {
          saveDraftAction(root, 'update_money', {
            field,
            value: parseMoneyValue(input.value, kind)
          }).catch((err) => {
            if (w.alert) w.alert(err && err.message ? err.message : 'Uložení částky selhalo.');
          });
        }
      });

      if (String(input.value || '').trim() !== '') {
        input.value = formatMoneyValue(input.value, kind);
      }
    });
  }

  function bindNoteInput(root) {
    const input = root.querySelector('[data-zr-note]');
    if (!(input instanceof HTMLInputElement) || input.getAttribute('data-zr-note-bound') === '1') {
      return;
    }
    input.setAttribute('data-zr-note-bound', '1');

    input.addEventListener('blur', () => {
      saveDraftAction(root, 'update_note', {
        value: String(input.value || '').trim()
      }).catch((err) => {
        if (w.alert) w.alert(err && err.message ? err.message : 'Uložení poznámky selhalo.');
      });
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
    bindNoteInput(root);
    bindEnterNavigation(root);
    bindSubmitCountdown(root);
    bindFinalSubmit(root);
    syncWeekdayFromDate(root);
    syncRequiredState(root);
    syncReportDifference(root);

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
      form.addEventListener('input', () => {
        syncRequiredState(root);
        syncSubmitButton(root);
      });
      form.addEventListener('change', (event) => {
        syncRequiredState(root);
        syncSubmitButton(root);
        const target = event.target;
        if (!(target instanceof HTMLSelectElement)) return;
        const field = String(target.getAttribute('data-zr-field') || '');
        if (field !== 'oteviral' && field !== 'zaviral') return;

        saveDraftAction(root, 'update_user', {
          field,
          value: String(target.value || '')
        }).catch((err) => {
          if (w.alert) w.alert(err && err.message ? err.message : 'Uložení jména selhalo.');
        });
      });
    }
  }

  function initKartyReportForm() {
    document.querySelectorAll('.cb-zadani-reportu, [data-zr-form]').forEach(initOne);
  }

  w.cbSyncReportFormState = syncRequiredState;
  w.cbPrepocetReportValues = function (root) {
    const target = root instanceof HTMLElement ? root : document.querySelector('[data-zr-form]');
    return target instanceof HTMLElement ? requestReportCalculation(target) : Promise.resolve(null);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyReportForm);
  } else {
    initKartyReportForm();
  }
  document.addEventListener('cb:card-swapped', initKartyReportForm);
  document.addEventListener('cb:card-max-loaded', initKartyReportForm);
}(window));
