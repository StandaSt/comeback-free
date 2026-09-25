// js/denni_report_vyroba.js * Ovládání pobočky, data a směn v reportu Výroby.
'use strict';

(function (w) {
  function timeTools() {
    return w.CB_DENNI_REPORT_TIME;
  }

  function updateHours(row) {
    const start = row.querySelector('[data-vyroba-start]');
    const end = row.querySelector('[data-vyroba-end]');
    const pause = row.querySelector('[data-vyroba-pause]');
    const output = row.querySelector('[data-vyroba-hours]');
    const tools = timeTools();
    if (!(start instanceof HTMLInputElement) || !(end instanceof HTMLInputElement) ||
        !(pause instanceof HTMLInputElement) || !(output instanceof HTMLElement) || !tools) return;

    const startTime = tools.parseTimeValue(start.value);
    const endTime = tools.parseTimeValue(end.value);
    const pauseText = String(pause.value || '').replace(',', '.').trim();
    const pauseHours = pauseText === '' ? 0 : Number(pauseText);
    output.textContent = '—';
    row.setAttribute('data-vyroba-time-valid', '0');
    start.classList.toggle('err', startTime === null && (start.value !== '' || start.classList.contains('err')));
    end.classList.toggle('err', endTime === null && (end.value !== '' || end.classList.contains('err')));
    pause.classList.remove('err');
    if (!startTime || !endTime) return;

    let endMinutes = endTime.minutes;
    if (endMinutes < startTime.minutes) endMinutes += 1440;
    const shiftHours = (endMinutes - startTime.minutes) / 60;
    if (shiftHours <= 0) {
      end.classList.add('err');
      return;
    }
    if (!Number.isFinite(pauseHours) || pauseHours < 0 || pauseHours >= shiftHours) {
      pause.classList.add('err');
      return;
    }
    output.textContent = tools.formatWorkedHours(shiftHours - pauseHours).label;
    row.setAttribute('data-vyroba-time-valid', '1');
  }

  function normalizeTime(input) {
    const tools = timeTools();
    if (!tools) return;
    const parsed = tools.parseTimeValue(input.value);
    if (!parsed) {
      input.value = input.defaultValue;
      input.classList.add('err');
      return;
    }
    input.value = parsed.value;
    input.classList.remove('err');
    input.classList.toggle('edit', parsed.value !== input.defaultValue);
  }

  function sortRows(list) {
    const tools = timeTools();
    if (!(list instanceof HTMLElement) || !tools) return;
    Array.from(list.querySelectorAll('[data-vyroba-row]'))
      .map((row, index) => ({ row, index, time: tools.parseTimeValue(row.querySelector('[data-vyroba-start]')?.value)?.minutes ?? 0 }))
      .sort((a, b) => (a.time - b.time) || (a.index - b.index))
      .forEach((item) => list.appendChild(item.row));
  }

  function restoreOption(select, id, name) {
    if (!(select instanceof HTMLSelectElement) || !id || !name) return;
    if (Array.from(select.options).some((option) => option.value === id)) return;
    select.add(new Option(name, id));
    const placeholder = Array.from(select.options).find((option) => option.value === '');
    const collator = new Intl.Collator('cs', { sensitivity: 'base', numeric: true });
    const options = Array.from(select.options).filter((option) => option.value !== '');
    options.sort((a, b) => collator.compare(a.textContent || '', b.textContent || ''));
    if (placeholder) select.replaceChildren(placeholder, ...options);
    select.value = '';
  }

  function reloadReport(filterForm) {
    if (!(filterForm instanceof HTMLFormElement) || !filterForm.checkValidity()) return;
    const params = new URLSearchParams(new FormData(filterForm));
    if (typeof w.CB_LOAD_MODULE === 'function') {
      w.CB_LOAD_MODULE('provoz', false, params);
    } else {
      filterForm.requestSubmit();
    }
  }

  document.addEventListener('change', function (event) {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (target.matches('[data-vyroba-filter]')) {
      reloadReport(target.closest('.vyroba_report_filters'));
      return;
    }
    if (!target.matches('[data-vyroba-add-person]')) return;
    const select = target;
    const form = select.closest('[data-vyroba-form]');
    const list = form?.querySelector('[data-vyroba-rows]');
    const template = form?.parentElement?.querySelector('[data-vyroba-template]');
    const selected = select.selectedOptions[0];
    if (!(select instanceof HTMLSelectElement) || !(selected instanceof HTMLOptionElement) ||
        !selected.value || !(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) return;
    const row = template.content.firstElementChild?.cloneNode(true);
    if (!(row instanceof HTMLTableRowElement)) return;
    row.querySelector('[name="vyroba_id_person[]"]').value = selected.value;
    row.querySelector('[data-vyroba-person-name]').textContent = selected.textContent || '';
    list.appendChild(row);
    selected.remove();
    select.value = '';
  });

  document.addEventListener('click', function (event) {
    const remove = event.target instanceof Element ? event.target.closest('[data-vyroba-remove]') : null;
    if (!remove) return;
    const row = remove.closest('[data-vyroba-row]');
    const form = remove.closest('[data-vyroba-form]');
    if (!(row instanceof HTMLTableRowElement)) return;
    const id = row.querySelector('[name="vyroba_id_person[]"]')?.value || '';
    const name = row.querySelector('[data-vyroba-person-name]')?.textContent?.trim() || '';
    restoreOption(form?.querySelector('[data-vyroba-add-person]'), id, name);
    row.remove();
  });

  document.addEventListener('focusin', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-vyroba-time]')) return;
    input.value = '';
    input.classList.remove('err', 'edit');
  });

  document.addEventListener('focusout', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-vyroba-time]')) return;
    normalizeTime(input);
    const row = input.closest('[data-vyroba-row]');
    if (row instanceof HTMLElement) {
      updateHours(row);
      sortRows(row.parentElement);
    }
  });

  document.addEventListener('input', function (event) {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('[data-vyroba-pause]')) return;
    input.value = input.value.replace(',', '.').replace(/[^0-9.]/g, '');
    const firstDot = input.value.indexOf('.');
    if (firstDot !== -1) input.value = input.value.slice(0, firstDot + 1) + input.value.slice(firstDot + 1).replace(/\./g, '');
    const row = input.closest('[data-vyroba-row]');
    if (row instanceof HTMLElement) updateHours(row);
  });

  function initRows() {
    document.querySelectorAll('.vyroba_report [data-vyroba-row]').forEach(updateHours);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRows);
  } else {
    initRows();
  }
  document.addEventListener('cb:main-swapped', initRows);
})(window);
