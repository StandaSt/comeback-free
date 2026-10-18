/* Účel souboru: Přidává volitelné sloty po dnech, automaticky ukládá platné úpravy a chrání rozepsaná data. */
(function () {
  'use strict';

  var dirtyForm = null;
  var baselines = new WeakMap();
  var saves = new WeakMap();

  // Souvislé indexy drží přidání i odebrání slotu ve stejném týdenním formuláři.
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

  // Otisk zahrnuje všech sedm dní, i když pohled právě ukazuje jen jeden.
  function snapshot(form) {
    form.querySelectorAll('[data-smeny-template-day]').forEach(reindexDay);
    var data = new URLSearchParams(new FormData(form));
    data.delete('cb_crf');
    data.delete('otisk');
    return data.toString();
  }

  // Výchozí hodnoty se zachytí jen jednou a slouží také návratu změn do původního stavu.
  function initForm(form) {
    if (form instanceof HTMLFormElement && !baselines.has(form)) {
      baselines.set(form, snapshot(form));
      saves.set(form, {timer:null, busy:false, pending:false, blocked:false});
    }
  }

  // Platné změny se řadí za sebe; nikdy neběží dva zápisy jedné šablony souběžně.
  function updateDirty(form) {
    if (!(form instanceof HTMLFormElement)) return;
    initForm(form);
    var changed = snapshot(form) !== baselines.get(form) || saves.get(form).busy;
    if (changed) {
      dirtyForm = form;
      form.setAttribute('data-smeny-dirty', '1');
      var state=saves.get(form);
      clearTimeout(state.timer);
      if (!state.blocked) {
        form.querySelector('[data-smeny-template-status]').textContent='';
        state.timer=setTimeout(function () { save(form); },350);
      }
      return;
    }
    if (dirtyForm === form) dirtyForm = null;
    form.removeAttribute('data-smeny-dirty');
  }

  // Neúplný čas se neodesílá; půlnoc patří provozně do předchozího dne.
  function validate(form) {
    var valid=true;
    form.querySelectorAll('[data-smeny-template-slot]').forEach(function (row) {
      var from=row.querySelector('[data-smeny-field="cas_od"]'), to=row.querySelector('[data-smeny-field="cas_do"]');
      var closing=row.closest('[data-smeny-template-day]').dataset.closing;
      function minutes(value) { var p=value.split(':'); var n=Number(p[0])*60+Number(p[1]); return n<360?n+1440:n; }
      var message='';
      if (!from.value || !to.value) message='Vyplňte oba časy slotu.';
      else if (minutes(to.value)<=minutes(from.value)) message='Konec slotu musí být později než začátek.';
      else if (minutes(to.value)>minutes(closing)) message='Slot nemůže končit po zavírací době.';
      else if (Number(from.value.slice(3,5))%30 || (Number(to.value.slice(3,5))%30 && to.value!==closing)) message='Nastavte čas po 30 minutách.';
      to.setCustomValidity(message);
      if(message) valid=false;
    });
    return valid && form.checkValidity();
  }

  // Odpověď přiřadí ID jen odeslaným řádkům; další změny se uloží následným požadavkem.
  function save(form) {
    var state=saves.get(form), status=form.querySelector('[data-smeny-template-status]');
    if (state.busy) { state.pending=true; return; }
    if (state.blocked || !form.isConnected || snapshot(form)===baselines.get(form)) return;
    if (!validate(form)) { status.textContent='Časy nejsou platné. Opravte je, změny zatím nejsou uložené.'; return; }
    var rows=Array.from(form.querySelectorAll('[data-smeny-template-slot]'));
    var body=new URLSearchParams(new FormData(form)); body.set('automaticky','1');
    var sent=snapshot(form);
    state.busy=true; state.pending=false; status.textContent='';
    fetch(form.getAttribute('action'),{method:'POST',body:body,credentials:'same-origin',cache:'no-store'})
      .then(function (response) { return response.json().then(function (data) {
        if (!response.ok || !data.ok) { state.blocked=true; throw new Error(data.message || 'Šablonu se nepodařilo uložit.'); }
        if (!Array.isArray(data.blocks) || data.blocks.length!==rows.length || !data.otisk) { state.blocked=true; throw new Error('Nepodařilo se ověřit uložení. Obnovte stránku.'); }
        return data;
      }); })
      .then(function (data) {
        var unchanged=snapshot(form)===sent;
        var persisted=new URLSearchParams(sent);
        var idNames=Array.from(persisted.keys()).filter(function (name) { return /^blocks\[\d+\]\[\d+\]\[id\]$/.test(name); });
        rows.forEach(function (row,index) {
          var field=row.querySelector('[data-smeny-field="id"]');
          // Název pole mohl mezitím změnit nový index; otisk upravujeme podle odeslaného pořadí.
          persisted.set(idNames[index],String(data.blocks[index].id));
          field.value=String(data.blocks[index].id);
        });
        baselines.set(form,persisted.toString());
        form.querySelector('[name="otisk"]').value=data.otisk;
        if (unchanged) {
          form.removeAttribute('data-smeny-dirty');
          if(dirtyForm===form) dirtyForm=null;
          status.textContent='';
        } else state.pending=true;
      })
      .catch(function (error) {
        // Po výpadku nelze vědět, zda server zapsal; další úprava nesmí slepě opakovat vložení slotů.
        state.blocked=true; status.textContent=(error.message || 'Uložení selhalo.')+' Změny nejsou potvrzené. Obnovte stránku před dalším ukládáním.';
      })
      .finally(function () { state.busy=false; if(state.pending && !state.blocked) save(form); });
  }

  // Náhled dostane událost až po dokončení změny DOM i přepočtu indexů.
  function notifyDay(day) {
    if (!(day instanceof HTMLElement)) return;
    // Prázdná volitelná pozice v novém pohledu nezabírá místo; její prototyp zůstává pro další přidání.
    day.querySelectorAll('[data-smeny-template-column]').forEach(function (column) {
      column.hidden=column.dataset.optional==='1' && !column.querySelector('[data-smeny-template-slot]');
    });
    day.dispatchEvent(new CustomEvent('cb:smeny-template-changed',{bubbles:true}));
  }

  // Formulář může přijít i interní navigací bez nového načtení dokumentu.
  function init(root) {
    (root || document).querySelectorAll('[data-smeny-template-form]').forEach(function (form) {
      initForm(form); form.querySelectorAll('[data-smeny-template-day]').forEach(notifyDay);
    });
  }

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest ? event.target.closest('[data-smeny-template-add]') : null;
    if (addButton) {
      var column = addButton.closest('[data-smeny-template-column]');
      // Horní tlačítko určuje pozici v témže dni; původní pole a prototyp zůstávají autoritou dat.
      if (!column && /^\d+$/.test(addButton.dataset.slot || '')) column = addButton.closest('[data-smeny-template-day]')?.querySelector('[data-smeny-template-column][data-slot="'+addButton.dataset.slot+'"]');
      var form = addButton.closest('[data-smeny-template-form]');
      if (!(column instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;
      var prototype = column.querySelector('[data-smeny-template-slot-prototype]');
      var slots = column.querySelector('[data-smeny-template-column-slots]');
      if (!(prototype instanceof HTMLTemplateElement) || !(slots instanceof HTMLElement)) return;
      initForm(form);
      var picker=addButton.closest('.smeny_slot_picker'); if(picker) picker.open=false;
      slots.appendChild(prototype.content.cloneNode(true));
      reindexDay(column.closest('[data-smeny-template-day]'));
      updateDirty(form);
      notifyDay(column.closest('[data-smeny-template-day]'));
      return;
    }

    var removeButton = event.target.closest ? event.target.closest('[data-smeny-template-remove]') : null;
    if (!removeButton) return;
    // Křížek náhledu odkazuje na stejný řádek formuláře; nevytváří druhou cestu ukládání.
    var row = removeButton.smenyTemplateSource || removeButton.closest('[data-smeny-template-slot]');
    var form = removeButton.closest('[data-smeny-template-form]');
    var day = removeButton.closest('[data-smeny-template-day]');
    if (!(row instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;
    initForm(form);
    row.remove();
    reindexDay(day);
    updateDirty(form);
    notifyDay(day);
  });

  document.addEventListener('input', function (event) {
    if (event.target.matches('[data-smeny-inline-time],[data-smeny-half-hours]')) return;
    var form = event.target.closest ? event.target.closest('[data-smeny-template-form]') : null;
    if (form) { updateDirty(form); notifyDay(event.target.closest('[data-smeny-template-day]')); }
  });

  document.addEventListener('change', function (event) {
    if (event.target.matches('[data-smeny-half-hours]')) return;
    var inline=event.target.matches('[data-smeny-inline-time]') ? event.target : null;
    if(inline) {
      inline.smenyTemplateSource.value=inline.value;
      inline.smenyTemplateSource.dispatchEvent(new Event('change',{bubbles:true}));
      return;
    }
    var form = event.target.closest ? event.target.closest('[data-smeny-template-form]') : null;
    if (form) { updateDirty(form); if(validate(form)) notifyDay(event.target.closest('[data-smeny-template-day]')); }
  });

  document.addEventListener('submit', function (event) {
    var form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form || !form.matches('[data-smeny-template-form]')) return;
    event.preventDefault();
    save(form);
  });

  window.addEventListener('beforeunload', function (event) {
    if (!(dirtyForm instanceof HTMLFormElement) || !dirtyForm.isConnected || dirtyForm.getAttribute('data-smeny-dirty') !== '1') return;
    event.preventDefault();
    event.returnValue = '';
  });

  // Interní navigace nepoužívá beforeunload; během zápisu či chyby chráníme tutéž rozepsanou šablonu.
  document.addEventListener('click',function (event) {
    if (!dirtyForm || !dirtyForm.isConnected || !event.target.closest('a[href]')) return;
    event.preventDefault(); event.stopImmediatePropagation();
    save(dirtyForm);
    var status=dirtyForm.querySelector('[data-smeny-template-status]');
    if (!saves.get(dirtyForm).blocked) status.textContent='Nejprve dokončete platné časy a počkejte na uložení.';
  },true);

  document.addEventListener('DOMContentLoaded', function () { init(document); });
  document.addEventListener('cb:main-swapped', function () { init(document); });
  init(document);
}());
