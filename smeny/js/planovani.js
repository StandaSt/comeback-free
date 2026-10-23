/* Účel: Nabídne kompaktní skupiny pracovníků podle míst výkonu práce s označením cizí oblasti a výběrem osoby rovnou uloží směnu pro zvolený čas; chrání kolize i odebrání sloupce. */
(function () {
  'use strict';

  // Porovnáváme místní kalendářní časy z IS, bez závislosti na časové zóně zařízení.
  function stampValue(stamp) {
    var parts=String(stamp).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return parts ? Date.UTC(Number(parts[1]),Number(parts[2])-1,Number(parts[3]),Number(parts[4]),Number(parts[5])) : NaN;
  }
  // Čas před šestou patří k následujícímu kalendářnímu dni provozního dne.
  function slotValue(date,time) {
    var value=stampValue(date+' '+time);
    return Number(String(time).slice(0,2))<6 ? value+86400000 : value;
  }

  // Inicializace funguje i po výměně obsahu společnou navigací IS.
  function init(root) {
    (root || document).querySelectorAll('[data-smeny-planner]').forEach(function (planner) {
      if (!(planner instanceof HTMLElement) || planner.dataset.ready === '1') return;
      planner.dataset.ready = '1';
      var dataNode = planner.querySelector('[data-smeny-candidates]');
      var peoplePanel = planner.querySelector('[data-smeny-people]');
      var peopleList = planner.querySelector('[data-smeny-people-list]');
      var title = planner.querySelector('[data-smeny-selection-title]');
      var allBranches=planner.dataset.allBranches==='1';
      var form = planner.querySelector('[data-smeny-assignment-form]');
      var candidates = {};
      try { candidates = JSON.parse(dataNode ? dataNode.textContent : '{}'); } catch (error) { candidates = {}; }
      var busyNode = planner.querySelector('[data-smeny-busy]');
      var busy = {};
      try { busy = JSON.parse(busyNode ? busyNode.textContent : '{}'); } catch (error) { busy = {}; }
      var selection = null;
      var choice = planner.querySelector('[data-smeny-choice]');
      var choiceFrom = planner.querySelector('[data-smeny-choice-from]');
      var choiceTo = planner.querySelector('[data-smeny-choice-to]');
      var submitting = false;
      if (choice) choice.hidden = false;

      // Přidání sloupce patří rozpisu; podbarvení ze šablony se tím nemění.
      planner.querySelectorAll('[data-smeny-grid-day]').forEach(function (day) {
        var tools=document.createElement('div'); tools.className='smeny_classic_tools';
        var positions=JSON.parse(day.dataset.positions || '{}');
        var base=JSON.parse(day.dataset.basePositions || '[]');
        var picker=document.createElement('details'); picker.className='smeny_slot_picker';
        var summary=document.createElement('summary'); summary.textContent='+ Další slot'; picker.appendChild(summary);
        var options=document.createElement('div'); options.className='smeny_slot_picker_options'; picker.appendChild(options);
        Object.keys(positions).forEach(function (position) {
          var add=document.createElement('button'); add.type='button'; add.textContent='+ '+(Number(position)===5?'Operátor':positions[position]);
          add.dataset.smenyAddLane=position; add.dataset.date=day.dataset.date;
          add.disabled=planner.dataset.canEdit!=='1';
          if(add.disabled) add.title='Nejprve klikněte na Plánovat zvolený týden.';
          add.addEventListener('click',function () {
            var addForm=planner.querySelector('[data-smeny-column-add]');
            addForm.elements.datum.value=day.dataset.date; addForm.elements.id_slot.value=position; addForm.requestSubmit();
          });
          // Volitelná pozice se přidává jen do vybraného dne, nikdy automaticky do celého týdne.
          (base.includes(Number(position)) ? tools : options).appendChild(add);
        });
        if(options.childElementCount) tools.appendChild(picker);
        day.querySelector('header').appendChild(tools);
      });

      // Programové předvyplnění má vlastní událost, aby historie nezaměnila výchozí čas za ruční změnu.
      function notifySelection() {
        planner.dispatchEvent(new CustomEvent('cb:smeny-selection-changed',{bubbles:true}));
      }

      // Přesně navazující směny jsou povolené; přepínače požadavků a MAIN nikdy nevypínají kolize.
      function available(person,from,to) {
        var start=slotValue(selection.date,from), end=slotValue(selection.date,to);
        var source=Array.from(planner.querySelectorAll('.smeny_planner_row')).find(function (row) { return row.dataset.column===selection.column; });
        var columnBusy=source && Array.from(source.querySelectorAll('[data-smeny-assigned]')).some(function (shift) {
          return slotValue(selection.date,shift.dataset.from)<end && slotValue(selection.date,shift.dataset.to)>start;
        });
        return Number.isFinite(start) && end>start && !columnBusy && !(busy[String(person.id_person)] || []).some(function (interval) {
          return stampValue(interval.od)<end && stampValue(interval.do)>start;
        });
      }

      // Oba pohledy odesílají tentýž formulář, token i serverově kontrolované intervaly.
      function assign(from, to) {
        if (submitting || !selection || !selection.person || !(form instanceof HTMLFormElement)) return;
        if (!choiceFrom.reportValidity() || !choiceTo.reportValidity()) return;
        var person=(candidates[selection.date+':'+selection.slot] || []).find(function (item) { return String(item.id_person)===String(selection.person); });
        choiceFrom.value=from; choiceTo.value=to;
        if (!person || !available(person,from,to)) {
          selection.person=null; renderPeople(); return;
        }
        form.elements.datum.value = selection.date;
        form.elements.id_slot.value = selection.slot;
        form.elements.cas_od.value = from;
        form.elements.cas_do.value = to;
        form.elements.id_person.value = selection.person;
        form.elements.klic_slotu.value = selection.column;
        // Jeden klik znamená jeden zápis; další klik během odesílání už nic neposílá.
        submitting = true;
        peopleList.querySelectorAll('button').forEach(function (button) { button.disabled=true; });
        form.requestSubmit();
      }

      // Výběr nemění nastavení uložené u rozpisu.
      function reset() {
        planner.querySelectorAll('.is-start').forEach(function (node) { node.classList.remove('is-start'); });
        selection = null;
        if (peoplePanel) peoplePanel.hidden = true;
        if (peopleList) peopleList.innerHTML = '';
        notifySelection();
      }
      // Po změně osy zrušíme pouze rozpracovaný výběr, žádná uložená směna se nemění.
      planner.addEventListener('cb:smeny-resolution-changed',reset);

      // Požadavek musí pokrýt celý interval; jde jen o pořadí, nikoli zákaz plánování.
      function requestMatches(person) {
        if(Number(person.je_hpp)===1) return Number(person.ma_volno)!==1;
        return !!person.pozadavek_od && !!person.pozadavek_do && slotValue(selection.date,person.pozadavek_od)<=slotValue(selection.date,choiceFrom.value) && slotValue(selection.date,person.pozadavek_do)>=slotValue(selection.date,choiceTo.value);
      }
      // Čtyři oddělené skupiny: domácí s požadavkem, domácí bez něj, cizí s požadavkem a cizí bez něj.
      function renderPeople() {
        if (!selection || !(peopleList instanceof HTMLElement)) return;
        var rows = candidates[selection.date + ':' + selection.slot] || [];
        peopleList.innerHTML = '';
        // MAIN a požadavky jsou nezávislé filtry; cizí MAIN vyžaduje výslovné rozšíření.
        var visible = rows.filter(function (person) {
          return (allBranches || Number(person.je_v_oblasti) === 1) && available(person,choiceFrom.value,choiceTo.value);
        });
        function group(person) { return (Number(person.je_v_oblasti)===1 ? 0 : 2)+(requestMatches(person)?0:1); }
        visible.sort(function (a,b) { return group(a)-group(b) || String(a.jmeno).localeCompare(String(b.jmeno),'cs'); });
        if (visible.length === 0) {
          var empty = document.createElement('p');
          empty.className = 'smeny_planner_no_people';
          empty.textContent = 'Pro zvolený čas a filtry není dostupný zaměstnanec.';
          peopleList.appendChild(empty);
          return;
        }
        var currentGroup = null;
        visible.forEach(function (person) {
          var number=group(person);
          if (currentGroup !== number) {
            currentGroup = number;
            var heading = document.createElement('strong');
            heading.className = 'smeny_planner_people_group';
            heading.textContent = ['Hlavní pobočka - s požadavkem','Hlavní pobočka - bez požadavku','Jiná pobočka - s požadavkem','Jiná pobočka - bez požadavku'][number];
            peopleList.appendChild(heading);
          }
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'smeny_planner_person';
          button.dataset.person = String(person.id_person);
          // Kartička ukazuje pouze konkrétní požadavek nebo volno; chybějící požadavek rozlišuje nadpis skupiny.
          var request = Number(person.je_hpp) === 1
            ? (Number(person.ma_volno) === 1 ? 'požadované volno' : '')
            : (person.pozadavek_od ? (person.rezim === 'kdykoliv' ? 'Kdykoliv ' : 'požadavek ') + person.pozadavek_od + '–' + person.pozadavek_do : '');
          button.innerHTML = '<strong></strong>'+(request ? '<span></span>' : '')+'<small></small>';
          button.querySelector('strong').textContent = person.jmeno || ('Osoba #' + person.id_person);
          if(request) button.querySelector('span').textContent = request;
          button.querySelector('small').textContent = String(person.hodin_tyden).replace('.', ',') + ' h tento týden v IS · ' + person.hlavni_pobocka;
          peopleList.appendChild(button);
        });
      }

      planner.addEventListener('click', function (event) {
        var cancel = event.target.closest ? event.target.closest('[data-smeny-cancel]') : null;
        if (cancel) { reset(); return; }
        var personButton = event.target.closest ? event.target.closest('[data-person]') : null;
        if (personButton && selection) {
          selection.person = personButton.getAttribute('data-person');
          // Čas je nastaven před výběrem osoby; volba pracovníka rovnou provede stávající uložení.
          assign(choiceFrom.value,choiceTo.value);
          return;
        }
        var tick = event.target.closest ? event.target.closest('[data-smeny-time]') : null;
        if (!tick) return;
        if (submitting || tick.disabled || planner.dataset.canEdit === '0') return;
        var row = tick.closest('[data-smeny-row]');
        if (!(row instanceof HTMLElement)) return;
        var time = tick.getAttribute('data-smeny-time');
        if (selection) reset();
        selection = { date: row.dataset.date, slot: row.dataset.slot, column:row.dataset.column, person: null };
        tick.classList.add('is-start');
        if (choiceFrom && choiceTo) { choiceFrom.value = time; choiceTo.value = tick.dataset.smenyDefaultEnd || time; }
        notifySelection();
        if (peoplePanel) peoplePanel.hidden = false;
        if (title) title.textContent = row.dataset.slotName + ' - vyber zaměstnance';
        renderPeople();
        peoplePanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
      // Změna intervalu přepočítá nabídku a zruší dřívější výběr, který už může být obsazený.
      [choiceFrom,choiceTo].forEach(function (field) {
        if (!(field instanceof HTMLInputElement)) return;
        field.addEventListener('input',function () {
          if (selection) selection.person=null;
          renderPeople();
        });
      });
      // Renderer smí panel odkrýt až po připojení tlačítek a všech obsluh plánovače.
      planner.dataset.uiReady='1';
      planner.dispatchEvent(new CustomEvent('cb:smeny-planner-ready',{bubbles:true}));
    });
  }

  // Obsazený sloupec vyžaduje výslovné potvrzení; Zpět ani Escape nic neodesílají.
  document.addEventListener('submit',function (event) {
    var form=event.target;
    if(!(form instanceof HTMLFormElement) || !form.matches('[data-smeny-column-remove]') || Number(form.dataset.occupied)===0 || form.elements.potvrzeno.value==='1') return;
    event.preventDefault(); event.stopImmediatePropagation();
    var dialog=document.createElement('dialog'); dialog.className='smeny_column_confirm';
    var text=document.createElement('p'); text.textContent='Odstranit zvolený slot přestože je obsazen?'; dialog.appendChild(text);
    ['Odstranit','Zpět'].forEach(function (label,index) {
      var button=document.createElement('button'); button.type='button'; button.textContent=label;
      button.dataset.smenyColumnConfirm=index===0?'odstranit':'zpet';
      button.addEventListener('click',function () { dialog.close(); if(index===0 && form.isConnected) { form.elements.potvrzeno.value='1'; form.requestSubmit(); } });
      dialog.appendChild(button);
    });
    dialog.addEventListener('close',function () { dialog.remove(); });
    form.closest('.smeny_content').appendChild(dialog); dialog.showModal(); dialog.lastElementChild.focus();
  },true);

  document.addEventListener('DOMContentLoaded', function () { init(document); });
  document.addEventListener('cb:main-swapped', function () { init(document); });
  init(document);
}());
