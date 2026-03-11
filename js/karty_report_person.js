// js/karty_report_person.js * Verze: V1 * Aktualizace: 11.03.2026
'use strict';

(function (w) {
  function getEditorRow(root, type) {
    const row = root.querySelector('[data-zr-editor="' + type + '"]');
    return row instanceof HTMLElement ? row : null;
  }

  function getSavedList(root, type) {
    const list = root.querySelector('[data-zr-saved-list="' + type + '"]');
    return list instanceof HTMLElement ? list : null;
  }

  function getEditorField(row, key) {
    const field = row ? row.querySelector('[data-zr-editor-field="' + key + '"]') : null;
    return (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) ? field : null;
  }

  function buildSavedCell(text) {
    const cell = document.createElement('div');
    cell.className = 'zr_saved_cell';
    const value = document.createElement('span');
    value.className = 'zr_saved_value';
    value.textContent = String(text || '');
    cell.appendChild(value);
    return cell;
  }

  function buildHidden(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = String(value ?? '');
    return input;
  }

  function syncHoursForRow(row) {
    if (!(row instanceof HTMLElement)) return;

    const startEl = row.querySelector('[data-zr-start]');
    const endEl = row.querySelector('[data-zr-end]');
    const breakEl = row.querySelector('[data-zr-break]');
    const hoursEl = row.querySelector('[data-zr-hours]');
    const hoursHiddenEl = row.querySelector('[data-zr-hours-hidden]');

    if (!(startEl instanceof HTMLSelectElement) || !(endEl instanceof HTMLSelectElement) || !(breakEl instanceof HTMLInputElement) || !(hoursEl instanceof HTMLElement) || !(hoursHiddenEl instanceof HTMLInputElement)) {
      return;
    }

    const startRaw = String(startEl.value || '');
    const endRaw = String(endEl.value || '');
    const breakRaw = String(breakEl.value || '').replace(',', '.');

    if (startRaw === '' || endRaw === '') {
      hoursEl.textContent = '10 hod.';
      hoursHiddenEl.value = '10';
      return;
    }

    const parseMinutes = (value) => {
      const parts = value.split(':');
      if (parts.length !== 2) return null;
      const h = parseInt(parts[0], 10);
      const m = parseInt(parts[1], 10);
      if (Number.isNaN(h) || Number.isNaN(m)) return null;
      return (h * 60) + m;
    };

    let startMin = parseMinutes(startRaw);
    let endMin = parseMinutes(endRaw);
    if (startMin === null || endMin === null) {
      return;
    }

    if (endMin < startMin) {
      endMin += 24 * 60;
    }

    let totalHours = (endMin - startMin) / 60;
    const pauseHours = parseFloat(breakRaw);
    if (!Number.isNaN(pauseHours)) {
      totalHours -= pauseHours;
    }
    if (totalHours < 0) {
      totalHours = 0;
    }

    const formatted = totalHours.toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1');
    hoursEl.textContent = formatted + ' hod.';
    hoursHiddenEl.value = formatted;
  }

  function syncKuryrExtras(row) {
    if (!(row instanceof HTMLElement) || String(row.getAttribute('data-zr-person-row') || '') !== 'kuryr') {
      return;
    }

    const restiaEl = row.querySelector('input[name="kuryr_pocet_rozvozu_restia[]"]');
    const manualEl = row.querySelector('input[name="kuryr_pocet_rozvozu_manual[]"]');
    const totalEl = row.querySelector('[data-zr-delivery-total]');
    const carCheck = row.querySelector('[data-zr-car-check]');
    const phmField = row.querySelector('[data-zr-phm-field]');
    const phmValueEl = row.querySelector('[data-zr-phm-value]');
    const phmHiddenEl = row.querySelector('[data-zr-phm-hidden]');

    if (restiaEl instanceof HTMLInputElement) {
      restiaEl.value = String(restiaEl.value || '').replace(/\D+/g, '').slice(0, 4);
    }
    if (manualEl instanceof HTMLInputElement) {
      manualEl.value = String(manualEl.value || '').replace(/\D+/g, '').slice(0, 4);
    }

    if (restiaEl instanceof HTMLInputElement && manualEl instanceof HTMLInputElement && totalEl instanceof HTMLInputElement) {
      const restia = Number.parseInt(restiaEl.value || '0', 10) || 0;
      const manual = Number.parseInt(manualEl.value || '0', 10) || 0;
      const total = restia + manual;
      const phm = total * 40;
      totalEl.value = String(total);
      if (phmValueEl instanceof HTMLElement) {
        phmValueEl.textContent = String(phm).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč';
      }
      if (phmHiddenEl instanceof HTMLInputElement) {
        phmHiddenEl.value = String(phm);
      }
    }

    if (carCheck instanceof HTMLInputElement && phmField instanceof HTMLElement) {
      phmField.classList.toggle('is-hidden', !carCheck.checked);
    }
  }

  function resetEditorRow(root, type) {
    const row = getEditorRow(root, type);
    if (!row) return;

    row.querySelectorAll('select').forEach((select) => {
      if (!(select instanceof HTMLSelectElement)) return;
      if (select.hasAttribute('data-zr-start')) {
        select.value = '10:00';
      } else if (select.hasAttribute('data-zr-end')) {
        select.value = '16:00';
      } else {
        select.value = '';
      }
    });

    row.querySelectorAll('input').forEach((input) => {
      if (!(input instanceof HTMLInputElement)) return;
      if (input.type === 'checkbox') {
        input.checked = false;
      } else if (input.hasAttribute('data-zr-break')) {
        input.value = '0';
      } else if (input.hasAttribute('data-zr-hours-hidden')) {
        input.value = '10';
      } else if (input.hasAttribute('data-zr-delivery-total')) {
        input.value = '0';
      } else if (input.hasAttribute('data-zr-phm-hidden')) {
        input.value = '0';
      } else if (input.getAttribute('data-zr-editor-field') === 'delivery_restia') {
        input.value = '0';
      } else {
        input.value = '';
      }
    });

    row.querySelectorAll('[data-zr-hours]').forEach((el) => {
      if (el instanceof HTMLElement) {
        el.textContent = '10 hod.';
      }
    });
    row.querySelectorAll('[data-zr-phm-value]').forEach((el) => {
      if (el instanceof HTMLElement) {
        el.textContent = '0 Kč';
      }
    });
    syncKuryrExtras(row);
    const saveBtn = root.querySelector(type === 'kuryr' ? '#zr_kuryr_ulozit' : '#zr_instor_ulozit');
    if (saveBtn instanceof HTMLButtonElement) {
      saveBtn.disabled = true;
    }
  }

  function saveEditorRow(root, type) {
    const row = getEditorRow(root, type);
    const list = getSavedList(root, type);
    const nameReady = getEditorField(row, 'jmeno');
    if (!row || !list || !(nameReady instanceof HTMLSelectElement) || String(nameReady.value || '').trim() === '') return;

    const name = getEditorField(row, 'jmeno');
    const start = getEditorField(row, 'start');
    const end = getEditorField(row, 'end');
    const breakEl = getEditorField(row, 'break');
    const hoursHiddenEl = row.querySelector('[data-zr-hours-hidden]');

    if (!(name instanceof HTMLSelectElement) || !(start instanceof HTMLSelectElement) || !(end instanceof HTMLSelectElement) || !(breakEl instanceof HTMLInputElement) || !(hoursHiddenEl instanceof HTMLInputElement)) {
      return;
    }

    const savedRow = document.createElement('div');
    savedRow.className = 'zr_saved_row ' + (type === 'kuryr' ? 'zr_saved_row_kuryr' : 'zr_saved_row_instor');

    savedRow.appendChild(buildSavedCell(name.value));
    savedRow.appendChild(buildSavedCell(start.value));
    savedRow.appendChild(buildSavedCell(end.value));
    savedRow.appendChild(buildSavedCell(breakEl.value || '0'));
    savedRow.appendChild(buildSavedCell(hoursHiddenEl.value + ' hod.'));

    savedRow.appendChild(buildHidden(type + '_jmeno[]', name.value));
    savedRow.appendChild(buildHidden(type + '_zacatek[]', start.value));
    savedRow.appendChild(buildHidden(type + '_konec[]', end.value));
    savedRow.appendChild(buildHidden(type + '_pauza_hod[]', breakEl.value || '0'));
    savedRow.appendChild(buildHidden(type + '_hodiny[]', hoursHiddenEl.value));

    if (type === 'kuryr') {
      const deliveryRestia = getEditorField(row, 'delivery_restia');
      const deliveryManual = getEditorField(row, 'delivery_manual');
      const deliveryTotal = row.querySelector('[data-zr-delivery-total]');
      const carCheck = getEditorField(row, 'car');
      const phmHidden = row.querySelector('[data-zr-phm-hidden]');

      const deliveryRestiaValue = deliveryRestia instanceof HTMLInputElement ? (deliveryRestia.value || '0') : '0';
      const deliveryManualValue = deliveryManual instanceof HTMLInputElement ? (deliveryManual.value || '0') : '0';
      const deliveryTotalValue = deliveryTotal instanceof HTMLInputElement ? (deliveryTotal.value || '0') : '0';
      const carValue = carCheck instanceof HTMLInputElement && carCheck.checked ? '1' : '0';
      const phmValue = phmHidden instanceof HTMLInputElement ? (phmHidden.value || '0') : '0';

      savedRow.appendChild(buildSavedCell(deliveryRestiaValue + ' + ' + deliveryManualValue));
      savedRow.appendChild(buildSavedCell(carValue === '1' ? 'Ano' : 'Ne'));
      savedRow.appendChild(buildSavedCell(String(phmValue).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč'));

      savedRow.appendChild(buildHidden('kuryr_pocet_rozvozu_restia[]', deliveryRestiaValue));
      savedRow.appendChild(buildHidden('kuryr_pocet_rozvozu_manual[]', deliveryManualValue));
      savedRow.appendChild(buildHidden('kuryr_pocet_rozvozu[]', deliveryTotalValue));
      savedRow.appendChild(buildHidden('kuryr_vlastni_vuz[]', carValue));
      savedRow.appendChild(buildHidden('kuryr_vyplatit_phm[]', phmValue));
    }

    list.appendChild(savedRow);
    resetEditorRow(root, type);
    if (typeof w.cbSyncReportFormState === 'function') {
      w.cbSyncReportFormState(root);
    }
  }

  function bindPeopleRows(root) {
    root.querySelectorAll('[data-zr-people-list]').forEach((list) => {
      if (!(list instanceof HTMLElement) || list.getAttribute('data-zr-bound') === '1') {
        return;
      }
      list.setAttribute('data-zr-bound', '1');

      list.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLSelectElement) && !(target instanceof HTMLInputElement)) return;

        const row = target.closest('[data-zr-person-row]');
        if (!row) return;

        syncHoursForRow(row);
        syncKuryrExtras(row);
        if (typeof w.cbSyncReportFormState === 'function') {
          w.cbSyncReportFormState(root);
        }
      });

      list.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;
        if (target.hasAttribute('data-zr-break')) {
          target.value = String(target.value || '').replace(',', '.').replace(/[^0-9.]/g, '');
          const firstDot = target.value.indexOf('.');
          if (firstDot !== -1) {
            target.value = target.value.slice(0, firstDot + 1) + target.value.slice(firstDot + 1).replace(/\./g, '');
          }
        }
        const row = target.closest('[data-zr-person-row]');
        if (!row) return;
        syncHoursForRow(row);
        syncKuryrExtras(row);
        if (typeof w.cbSyncReportFormState === 'function') {
          w.cbSyncReportFormState(root);
        }
      });
    });

    root.querySelectorAll('[data-zr-save-row]').forEach((btn) => {
      if (!(btn instanceof HTMLButtonElement) || btn.getAttribute('data-zr-save-bound') === '1') {
        return;
      }
      btn.setAttribute('data-zr-save-bound', '1');
      btn.addEventListener('click', () => {
        saveEditorRow(root, String(btn.getAttribute('data-zr-save-row') || ''));
      });
    });
  }

  function initOne(root) {
    if (!(root instanceof HTMLElement) || root.getAttribute('data-zr-person-init') === '1') {
      return;
    }
    root.setAttribute('data-zr-person-init', '1');

    bindPeopleRows(root);
    root.querySelectorAll('[data-zr-person-row]').forEach(syncHoursForRow);
    root.querySelectorAll('[data-zr-person-row]').forEach(syncKuryrExtras);
    if (typeof w.cbSyncReportFormState === 'function') {
      w.cbSyncReportFormState(root);
    }
  }

  function initKartyReportPerson() {
    document.querySelectorAll('.cb-zadani-reportu').forEach(initOne);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyReportPerson);
  } else {
    initKartyReportPerson();
  }
}(window));
