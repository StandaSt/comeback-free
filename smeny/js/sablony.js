/* Účel souboru: Upravuje sloty šablony v prohlížeči a připraví jediné uložení celého týdne. */
(function () {
  'use strict';

  var dirtyForm = null;
  var baselines = new WeakMap();

  function reindexDay(day) {
    if (!(day instanceof HTMLElement)) return;
    var dayNumber = String(day.getAttribute('data-smeny-template-day') || '');
    var index = 0;
    day.querySelectorAll('[data-smeny-template-slot]').forEach(function (row) {
      row.querySelectorAll('[data-smeny-field]').forEach(function (field) {
        field.name = 'blocks[' + dayNumber + '][' + index + '][' + field.getAttribute('data-smeny-field') + ']';
      });
      index++;
    });
  }

  function snapshot(form) {
    form.querySelectorAll('[data-smeny-template-day]').forEach(reindexDay);
    var data = new URLSearchParams(new FormData(form));
    data.delete('cb_crf');
    return data.toString();
  }

  function initForm(form) {
    if (form instanceof HTMLFormElement && !baselines.has(form)) {
      baselines.set(form, snapshot(form));
    }
  }

  function updateDirty(form) {
    if (!(form instanceof HTMLFormElement)) return;
    initForm(form);
    var changed = snapshot(form) !== baselines.get(form);
    var actions = form.querySelector('[data-smeny-template-actions]');
    if (changed) {
      dirtyForm = form;
      form.setAttribute('data-smeny-dirty', '1');
      if (actions instanceof HTMLElement) actions.hidden = false;
      return;
    }
    if (dirtyForm === form) dirtyForm = null;
    form.removeAttribute('data-smeny-dirty');
    if (actions instanceof HTMLElement) actions.hidden = true;
  }

  function init(root) {
    (root || document).querySelectorAll('[data-smeny-template-form]').forEach(initForm);
  }

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest ? event.target.closest('[data-smeny-template-add]') : null;
    if (addButton) {
      var column = addButton.closest('[data-smeny-template-column]');
      var form = addButton.closest('[data-smeny-template-form]');
      if (!(column instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;
      var prototype = column.querySelector('[data-smeny-template-slot-prototype]');
      var slots = column.querySelector('[data-smeny-template-column-slots]');
      if (!(prototype instanceof HTMLTemplateElement) || !(slots instanceof HTMLElement)) return;
      initForm(form);
      slots.appendChild(prototype.content.cloneNode(true));
      reindexDay(column.closest('[data-smeny-template-day]'));
      updateDirty(form);
      return;
    }

    var removeButton = event.target.closest ? event.target.closest('[data-smeny-template-remove]') : null;
    if (!removeButton) return;
    var row = removeButton.closest('[data-smeny-template-slot]');
    var form = removeButton.closest('[data-smeny-template-form]');
    var day = removeButton.closest('[data-smeny-template-day]');
    if (!(row instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;
    initForm(form);
    row.remove();
    reindexDay(day);
    updateDirty(form);
  });

  document.addEventListener('input', function (event) {
    var form = event.target.closest ? event.target.closest('[data-smeny-template-form]') : null;
    if (form) updateDirty(form);
  });

  document.addEventListener('change', function (event) {
    var form = event.target.closest ? event.target.closest('[data-smeny-template-form]') : null;
    if (form) updateDirty(form);
  });

  document.addEventListener('submit', function (event) {
    var form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form || !form.matches('[data-smeny-template-form]')) return;
    form.querySelectorAll('[data-smeny-template-day]').forEach(reindexDay);
    dirtyForm = null;
    form.removeAttribute('data-smeny-dirty');
  });

  window.addEventListener('beforeunload', function (event) {
    if (!(dirtyForm instanceof HTMLFormElement) || dirtyForm.getAttribute('data-smeny-dirty') !== '1') return;
    event.preventDefault();
    event.returnValue = '';
  });

  document.addEventListener('DOMContentLoaded', function () { init(document); });
  document.addEventListener('cb:main-swapped', function () { init(document); });
  init(document);
}());
