/* Účel: Ovládá tříkrokové plánování V2 a zobrazuje intervaly, Kdykoliv i volno HPP. */
(function () {
  'use strict';

  // Inicializace funguje i po výměně obsahu společnou navigací IS.
  function init(root) {
    (root || document).querySelectorAll('[data-smeny-planner]').forEach(function (planner) {
      if (!(planner instanceof HTMLElement) || planner.dataset.ready === '1') return;
      planner.dataset.ready = '1';
      var dataNode = planner.querySelector('[data-smeny-candidates]');
      var peoplePanel = planner.querySelector('[data-smeny-people]');
      var peopleList = planner.querySelector('[data-smeny-people-list]');
      var title = planner.querySelector('[data-smeny-selection-title]');
      var ignore = planner.querySelector('[data-smeny-ignore]');
      var form = planner.querySelector('[data-smeny-assignment-form]');
      var candidates = {};
      try { candidates = JSON.parse(dataNode ? dataNode.textContent : '{}'); } catch (error) { candidates = {}; }
      var selection = null;

      function reset() {
        planner.querySelectorAll('.is-start,.is-end').forEach(function (node) { node.classList.remove('is-start', 'is-end'); });
        selection = null;
        if (peoplePanel) peoplePanel.hidden = true;
        if (peopleList) peopleList.innerHTML = '';
        if (ignore instanceof HTMLInputElement) ignore.checked = false;
      }

      function renderPeople() {
        if (!selection || !(peopleList instanceof HTMLElement)) return;
        var rows = candidates[selection.date + ':' + selection.slot] || [];
        var showAll = ignore instanceof HTMLInputElement && ignore.checked;
        peopleList.innerHTML = '';
        var visible = rows.filter(function (person) { return showAll || person.podle_pozadavku; });
        if (visible.length === 0) {
          var empty = document.createElement('p');
          empty.className = 'smeny_planner_no_people';
          empty.textContent = showAll ? 'Pro tuto pozici není aktivní zaměstnanec.' : 'Nikdo nemá požadavek. Zapněte „Bez ohledu na požadavky“.';
          peopleList.appendChild(empty);
          return;
        }
        var currentMain = null;
        visible.forEach(function (person) {
          var main = Number(person.je_hlavni_pobocka) === 1;
          if (currentMain !== main) {
            currentMain = main;
            var heading = document.createElement('strong');
            heading.className = 'smeny_planner_people_group';
            heading.textContent = main ? 'Hlavní pobočka zde' : 'Ostatní pobočky';
            peopleList.appendChild(heading);
          }
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'smeny_planner_person';
          button.dataset.person = String(person.id_person);
          var request = Number(person.je_hpp) === 1
            ? (Number(person.ma_volno) === 1 ? 'HPP – požadované volno' : 'HPP – bez volna')
            : (person.pozadavek_od ? (person.rezim === 'kdykoliv' ? 'Kdykoliv ' : 'požadavek ') + person.pozadavek_od + '–' + person.pozadavek_do : 'bez požadavku');
          button.innerHTML = '<strong></strong><span></span><small></small>';
          button.querySelector('strong').textContent = person.jmeno || ('Osoba #' + person.id_person);
          button.querySelector('span').textContent = request;
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
          peopleList.querySelectorAll('.is-selected').forEach(function (node) { node.classList.remove('is-selected'); });
          personButton.classList.add('is-selected');
          if (title) title.textContent = 'Teď klikněte na konec směny';
          return;
        }
        var tick = event.target.closest ? event.target.closest('[data-smeny-time]') : null;
        if (!tick) return;
        var row = tick.closest('[data-smeny-row]');
        if (!(row instanceof HTMLElement)) return;
        var time = tick.getAttribute('data-smeny-time');
        if (!selection) {
          selection = { row: row, date: row.dataset.date, slot: row.dataset.slot, start: time, person: null };
          tick.classList.add('is-start');
          if (peoplePanel) peoplePanel.hidden = false;
          if (title) title.textContent = row.dataset.slotName + ' od ' + time + ' – vyberte zaměstnance';
          renderPeople();
          peoplePanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          return;
        }
        if (selection.row !== row || !selection.person || !(form instanceof HTMLFormElement)) return;
        tick.classList.add('is-end');
        form.elements.datum.value = selection.date;
        form.elements.id_slot.value = selection.slot;
        form.elements.cas_od.value = selection.start;
        form.elements.cas_do.value = time;
        form.elements.id_person.value = selection.person;
        form.elements.bez_ohledu.value = ignore instanceof HTMLInputElement && ignore.checked ? '1' : '0';
        form.submit();
      });
      // Po změně filtru je nutné vybrat člověka znovu, aby nezůstal skrytý výběr.
      if (ignore instanceof HTMLInputElement) ignore.addEventListener('change', function () {
        if (selection) selection.person = null;
        renderPeople();
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () { init(document); });
  document.addEventListener('cb:main-swapped', function () { init(document); });
  init(document);
}());
