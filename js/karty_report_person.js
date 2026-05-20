// js/karty_report_person.js * Verze: V2 * Aktualizace: 12.05.2026
'use strict';

(function (w) {
  function getSavedList(root, type) {
    const list = root.querySelector('[data-zr-saved-list="' + type + '"]');
    return list instanceof HTMLElement ? list : null;
  }

  function getEditorField(row, key) {
    const field = row ? row.querySelector('[data-zr-editor-field="' + key + '"]') : null;
    return (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) ? field : null;
  }

  function buildSavedCell(text) {
    const cell = document.createElement('td');
    const value = document.createElement('strong');
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

  function buildRemoveButton() {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'zr_row_remove';
    button.setAttribute('data-zr-remove-row', '');
    button.setAttribute('title', 'Odebrat');
    button.setAttribute('aria-label', 'Odebrat');
    button.textContent = '×';
    return button;
  }

  function isTimeInput(el) {
    return el instanceof HTMLInputElement && (el.hasAttribute('data-zr-start') || el.hasAttribute('data-zr-end'));
  }

  function parseTimeValue(raw) {
    const value = String(raw || '').trim();
    if (value === '') return null;

    let hour = null;
    let minute = 0;

    if (/[:.,\s]/.test(value)) {
      const parts = value.split(/[:.,\s]+/).filter((part) => part !== '');
      if (parts.length < 1) return null;
      hour = Number.parseInt(parts[0], 10);
      if (parts.length > 1) {
        const minuteRaw = parts[1].slice(0, 2);
        minute = Number.parseInt(minuteRaw.length === 1 ? minuteRaw + '0' : minuteRaw, 10);
      }
    } else {
      const digits = value.replace(/\D+/g, '');
      if (digits.length === 0 || digits.length > 4) return null;
      if (digits.length <= 2) {
        hour = Number.parseInt(digits, 10);
        minute = 0;
      } else if (digits.length === 3) {
        hour = Number.parseInt(digits.slice(0, 1), 10);
        minute = Number.parseInt(digits.slice(1), 10);
      } else {
        hour = Number.parseInt(digits.slice(0, 2), 10);
        minute = Number.parseInt(digits.slice(2), 10);
      }
    }

    if (!Number.isInteger(hour) || !Number.isInteger(minute) || hour < 0 || hour > 23 || minute < 0 || minute > 59) {
      return null;
    }

    return {
      value: String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0'),
      minutes: (hour * 60) + minute
    };
  }

  function workdayMinutes(minutes) {
    return minutes < 360 ? minutes + (24 * 60) : minutes;
  }

  function markTimeInput(input, state) {
    if (!isTimeInput(input)) return;
    input.classList.toggle('err', state === 'err');
    input.classList.toggle('edit', state === 'edit');
  }

  function normalizeTimeInput(input) {
    if (!isTimeInput(input)) return true;

    const parsed = parseTimeValue(input.value);
    if (parsed === null) {
      input.value = input.defaultValue;
      markTimeInput(input, 'err');
      return false;
    }

    input.value = parsed.value;
    markTimeInput(input, parsed.value !== input.defaultValue ? 'edit' : '');
    return true;
  }

  function buildTimeInput(name, selected, kind) {
    const input = document.createElement('input');
    input.type = 'text';
    input.inputMode = 'numeric';
    input.name = name;
    input.value = selected;
    input.defaultValue = selected;
    input.className = 'zr_time_input';
    input.setAttribute('style', 'width:100%;text-align:center;');
    input.setAttribute(kind === 'start' ? 'data-zr-start' : 'data-zr-end', '');
    return input;
  }

  function sortTimeValue(row, selector) {
    const input = row instanceof HTMLElement ? row.querySelector(selector) : null;
    if (!(input instanceof HTMLInputElement)) return 0;

    const parsed = parseTimeValue(input.value);
    return parsed === null ? 0 : parsed.minutes;
  }

  function sortPersonRows(list) {
    if (!(list instanceof HTMLElement)) return;

    const rows = Array.from(list.children).filter((row) => row instanceof HTMLTableRowElement && row.hasAttribute('data-zr-person-row'));
    rows
      .map((row, index) => ({
        row,
        index,
        start: sortTimeValue(row, '[data-zr-start]'),
        end: sortTimeValue(row, '[data-zr-end]')
      }))
      .sort((a, b) => (a.start - b.start) || (a.end - b.end) || (a.index - b.index))
      .forEach((item) => {
        list.appendChild(item.row);
      });
  }

  function syncHoursForRow(row) {
    if (!(row instanceof HTMLElement)) return;

    const startEl = row.querySelector('[data-zr-start]');
    const endEl = row.querySelector('[data-zr-end]');
    const breakEl = row.querySelector('[data-zr-break]');
    const hoursEl = row.querySelector('[data-zr-hours]');
    const hoursHiddenEl = row.querySelector('[data-zr-hours-hidden]');

    if (!(startEl instanceof HTMLInputElement) || !(endEl instanceof HTMLInputElement) || !(breakEl instanceof HTMLInputElement) || !(hoursEl instanceof HTMLElement) || !(hoursHiddenEl instanceof HTMLInputElement)) {
      return;
    }

    const startParsed = parseTimeValue(startEl.value);
    const endParsed = parseTimeValue(endEl.value);
    const breakRaw = String(breakEl.value || '').replace(',', '.');

    if (startParsed === null || endParsed === null) {
      return;
    }

    const startMin = workdayMinutes(startParsed.minutes);
    const endMin = workdayMinutes(endParsed.minutes);
    if (endMin <= startMin) {
      markTimeInput(endEl, 'err');
      return;
    }
    markTimeInput(startEl, startEl.value !== startEl.defaultValue ? 'edit' : '');
    markTimeInput(endEl, endEl.value !== endEl.defaultValue ? 'edit' : '');

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

    const restiaEl = getEditorField(row, 'delivery_restia');
    const manualEl = getEditorField(row, 'delivery_manual');
    const totalEl = row.querySelector('[data-zr-delivery-total]');
    const restiaValueEl = row.querySelector('[data-zr-delivery-restia-value]');
    const phmValueEl = row.querySelector('[data-zr-phm-value]');
    const phmHiddenEl = row.querySelector('[data-zr-phm-hidden]');
    const carCheck = getEditorField(row, 'car');
    const carHiddenEl = row.querySelector('[data-zr-car-hidden]');

    if (restiaEl instanceof HTMLInputElement && restiaEl.type !== 'hidden') {
      restiaEl.value = String(restiaEl.value || '').replace(/\D+/g, '').slice(0, 4);
    }
    if (manualEl instanceof HTMLInputElement) {
      manualEl.value = String(manualEl.value || '').replace(/\D+/g, '').slice(0, 4);
    }

    if (restiaEl instanceof HTMLInputElement && manualEl instanceof HTMLInputElement && totalEl instanceof HTMLInputElement) {
      const restia = Number.parseInt(restiaEl.value || '0', 10) || 0;
      const manual = Number.parseInt(manualEl.value || '0', 10) || 0;
      const total = restia + manual;
      const phm = total * 45;
      totalEl.value = String(total);
      if (restiaValueEl instanceof HTMLElement) {
        restiaValueEl.textContent = String(restia);
      }
      if (phmValueEl instanceof HTMLElement) {
        phmValueEl.textContent = String(phm).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kc';
      }
      if (phmHiddenEl instanceof HTMLInputElement) {
        phmHiddenEl.value = String(phm);
      }
      if (carCheck instanceof HTMLInputElement && carHiddenEl instanceof HTMLInputElement) {
        carHiddenEl.value = carCheck.checked ? '1' : '0';
      }
    }
  }

  function getDeliveryCountForName(row, name) {
    const table = row instanceof HTMLElement ? row.closest('[data-zr-delivery-counts]') : null;
    if (!(table instanceof HTMLElement)) return 0;

    try {
      const data = JSON.parse(String(table.getAttribute('data-zr-delivery-counts') || '{}'));
      return Number.parseInt(data[String(name || '').trim()], 10) || 0;
    } catch (e) {
      return 0;
    }
  }

  function addPersonRow(root, type, name) {
    const list = getSavedList(root, type);
    if (!(list instanceof HTMLElement) || String(name || '').trim() === '') return;

    const savedRow = document.createElement('tr');
    savedRow.setAttribute('data-zr-person-row', type);

    const nameCell = buildSavedCell(name);
    nameCell.style.width = '220px';
    nameCell.insertBefore(buildRemoveButton(), nameCell.firstChild);
    nameCell.appendChild(buildHidden(type + '_jmeno[]', name));
    savedRow.appendChild(nameCell);

    const startCell = document.createElement('td');
    startCell.style.width = '58px';
    startCell.appendChild(buildTimeInput(type + '_zacatek[]', '', 'start'));
    savedRow.appendChild(startCell);

    const endCell = document.createElement('td');
    endCell.style.width = '58px';
    endCell.appendChild(buildTimeInput(type + '_konec[]', '', 'end'));
    savedRow.appendChild(endCell);

    const breakCell = document.createElement('td');
    breakCell.className = 'zr_person_cell_break';
    breakCell.style.width = '44px';
    const breakInput = document.createElement('input');
    breakInput.type = 'text';
    breakInput.inputMode = 'decimal';
    breakInput.name = type + '_pauza_hod[]';
    breakInput.value = '';
    breakInput.setAttribute('style', 'width:100%;text-align:center;');
    breakInput.setAttribute('data-zr-break', '');
    breakCell.appendChild(breakInput);
    savedRow.appendChild(breakCell);

    const hoursCell = document.createElement('td');
    hoursCell.style.width = '70px';
    const hoursValue = document.createElement('strong');
    hoursValue.className = 'zr_saved_value';
    hoursValue.setAttribute('data-zr-hours', '');
    hoursValue.textContent = '0 hod.';
    hoursCell.appendChild(hoursValue);
    const hoursHidden = buildHidden(type + '_hodiny[]', '0');
    hoursHidden.setAttribute('data-zr-hours-hidden', '');
    hoursCell.appendChild(hoursHidden);
    savedRow.appendChild(hoursCell);

    if (type === 'kuryr') {
      const deliveryRestiaValue = String(getDeliveryCountForName(list, name));
      const deliveryTotalValue = deliveryRestiaValue;
      const phmValue = String((Number.parseInt(deliveryTotalValue, 10) || 0) * 45);

      const deliveryRestiaCell = buildSavedCell(deliveryRestiaValue);
      deliveryRestiaCell.className = 'txt_c';
      deliveryRestiaCell.style.width = '48px';
      const deliveryRestiaHidden = buildHidden('kuryr_pocet_rozvozu_restia[]', deliveryRestiaValue);
      deliveryRestiaHidden.setAttribute('data-zr-editor-field', 'delivery_restia');
      deliveryRestiaCell.appendChild(deliveryRestiaHidden);
      savedRow.appendChild(deliveryRestiaCell);

      const deliveryManualCell = document.createElement('td');
      deliveryManualCell.style.width = '48px';
      const deliveryManualInput = document.createElement('input');
      deliveryManualInput.className = 'zr_delivery_input txt_c';
      deliveryManualInput.type = 'text';
      deliveryManualInput.inputMode = 'numeric';
      deliveryManualInput.name = 'kuryr_pocet_rozvozu_manual[]';
      deliveryManualInput.value = '';
      deliveryManualInput.setAttribute('style', 'width:100%;');
      deliveryManualInput.setAttribute('data-zr-editor-field', 'delivery_manual');
      deliveryManualInput.setAttribute('data-zr-int-short', '');
      deliveryManualCell.appendChild(deliveryManualInput);
      const deliveryTotalHidden = buildHidden('kuryr_pocet_rozvozu[]', deliveryTotalValue);
      deliveryTotalHidden.setAttribute('data-zr-delivery-total', '');
      deliveryManualCell.appendChild(deliveryTotalHidden);
      savedRow.appendChild(deliveryManualCell);

      const carCell = document.createElement('td');
      carCell.className = 'txt_c';
      carCell.style.width = '34px';
      const carWrap = document.createElement('span');
      carWrap.className = 'zr_chk txt_c zr_person_cell_car zr_person_cell_car_inline';
      const carInput = document.createElement('input');
      carInput.type = 'checkbox';
      carInput.value = '1';
      carInput.setAttribute('data-zr-editor-field', 'car');
      carInput.setAttribute('data-zr-car-check', '');
      carWrap.appendChild(carInput);
      carCell.appendChild(carWrap);
      const carHidden = buildHidden('kuryr_vlastni_vuz[]', '0');
      carHidden.setAttribute('data-zr-car-hidden', '');
      carCell.appendChild(carHidden);
      savedRow.appendChild(carCell);

      const phmCell = document.createElement('td');
      const phmValueEl = document.createElement('strong');
      phmValueEl.className = 'zr_hours_value';
      phmValueEl.setAttribute('data-zr-phm-value', '');
      phmValueEl.textContent = String(phmValue).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kc';
      phmCell.appendChild(phmValueEl);
      const phmHidden = buildHidden('kuryr_vyplatit_phm[]', phmValue);
      phmHidden.setAttribute('data-zr-phm-hidden', '');
      phmCell.appendChild(phmHidden);
      savedRow.appendChild(phmCell);
    } else {
      savedRow.appendChild(document.createElement('td'));
    }

    list.appendChild(savedRow);
    syncHoursForRow(savedRow);
    syncKuryrExtras(savedRow);
    sortPersonRows(list);
    if (typeof w.cbSyncReportFormState === 'function') {
      w.cbSyncReportFormState(root);
    }
  }

  function bindPeopleRows(root) {
    root.querySelectorAll('[data-zr-add-person]').forEach((select) => {
      if (!(select instanceof HTMLSelectElement) || select.getAttribute('data-zr-add-bound') === '1') {
        return;
      }
      select.setAttribute('data-zr-add-bound', '1');
      select.addEventListener('change', () => {
        const type = String(select.getAttribute('data-zr-add-person') || '');
        const name = String(select.value || '').trim();
        if (type !== '' && name !== '') {
          addPersonRow(root, type, name);
          select.value = '';
        }
      });
    });

    root.querySelectorAll('[data-zr-people-list]').forEach((list) => {
      if (!(list instanceof HTMLElement) || list.getAttribute('data-zr-bound') === '1') {
        return;
      }
      list.setAttribute('data-zr-bound', '1');

      list.addEventListener('change', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLSelectElement) && !(target instanceof HTMLInputElement)) return;
        if (isTimeInput(target)) return;

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
        if (isTimeInput(target)) return;
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

      list.addEventListener('focusin', (event) => {
        const target = event.target;
        if (isTimeInput(target)) {
          target.value = '';
          markTimeInput(target, '');
        }
      });

      list.addEventListener('focusout', (event) => {
        const target = event.target;
        if (!isTimeInput(target)) return;

        const normalized = normalizeTimeInput(target);
        const row = target.closest('[data-zr-person-row]');
        if (!row) return;

        if (normalized) {
          syncHoursForRow(row);
          sortPersonRows(list);
        }
        if (typeof w.cbSyncReportFormState === 'function') {
          w.cbSyncReportFormState(root);
        }
      });

      list.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;

        const removeBtn = target.closest('[data-zr-remove-row]');
        if (!(removeBtn instanceof HTMLElement)) return;

        event.preventDefault();
        const row = removeBtn.closest('[data-zr-person-row]');
        if (row instanceof HTMLTableRowElement) {
          row.remove();
          if (typeof w.cbSyncReportFormState === 'function') {
            w.cbSyncReportFormState(root);
          }
        }
      });
    });
  }

  function initOne(root) {
    if (!(root instanceof HTMLElement)) {
      return;
    }

    bindPeopleRows(root);
    root.querySelectorAll('[data-zr-person-row]').forEach(syncHoursForRow);
    root.querySelectorAll('[data-zr-person-row]').forEach(syncKuryrExtras);
    root.querySelectorAll('[data-zr-saved-list]').forEach(sortPersonRows);
    if (typeof w.cbSyncReportFormState === 'function') {
      w.cbSyncReportFormState(root);
    }
  }

  function initKartyReportPerson() {
    document.querySelectorAll('.cb-zadani-reportu, [data-zr-form]').forEach(initOne);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKartyReportPerson);
  } else {
    initKartyReportPerson();
  }
  document.addEventListener('cb:card-swapped', initKartyReportPerson);
  document.addEventListener('cb:card-max-loaded', initKartyReportPerson);
}(window));
