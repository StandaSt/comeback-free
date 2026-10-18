/* Účel: Skládá plánovač se zarovnanými řádky, přizpůsobenou mřížkou a bloky odděleného příjmení a jména; časový tooltip sleduje kurzor a interval zůstává i v detailu. */
(function () {
  'use strict';
  var dayViews = [];
  var gridDays = new Set();
  var gridObserved = new Set();
  var layoutFrame = null;
  // Měníme pouze rozměry existujících prvků; resize nesmí zrušit rozepsaný čas ani fokus.
  function scheduleGridLayout() {
    if (layoutFrame !== null) return;
    layoutFrame=requestAnimationFrame(function () {
      layoutFrame=null;
      // Pevný referenční okraj dává select šablony; horní dva řádky se přizpůsobí jemu, nikoli naopak.
      document.querySelectorAll('.smeny_planning_controls').forEach(function (controls) {
        var template=controls.closest('.smeny_content').querySelector('.smeny_planning_template_select select');
        if(!template || !template.getClientRects().length) return;
        var width=template.getBoundingClientRect().right-controls.getBoundingClientRect().left;
        if(width>0) controls.style.width=width+'px';
      });
      gridObserved.forEach(function (element) {
        if (!element.isConnected) { gridObserver.unobserve(element); gridObserved.delete(element); }
      });
      gridDays.forEach(function (day) {
        if (!day.isConnected) { gridDays.delete(day); return; }
        if (day.hidden || !day.getClientRects().length) return;
        var scroll=day.querySelector('.smeny_classic_template>.smeny_classic_scroll,.smeny_classic_plan>.smeny_classic_scroll');
        if (!scroll || !scroll.smenyFitHeight) return;
        var page=day.closest('.smeny_content'), rect=scroll.getBoundingClientRect();
        var viewport=window.visualViewport;
        var bottom=viewport ? viewport.offsetTop+viewport.height : window.innerHeight;
        if (page.closest('#obal_main')) bottom=Math.min(bottom,page.getBoundingClientRect().bottom);
        bottom-=parseFloat(getComputedStyle(page).paddingBottom || '0');
        var notice=document.querySelector('.cb_prebrani_prav_notice');
        if (notice && notice.getClientRects().length) bottom=Math.min(bottom,notice.getBoundingClientRect().top-8);
        var status=day.closest('form')?.querySelector('[data-smeny-template-status]');
        if (status && status.getClientRects().length) bottom-=status.getBoundingClientRect().height+6;
        var planner=day.closest('[data-smeny-planner]');
        if(planner) for(var sibling=planner.nextElementSibling;sibling;sibling=sibling.nextElementSibling) {
          if(!sibling.matches('dialog') && sibling.getClientRects().length) bottom-=sibling.getBoundingClientRect().height+6;
        }
        var tail=day.getBoundingClientRect().bottom-rect.bottom;
        scroll.smenyFitHeight(Math.max(80,bottom-(rect.top+page.scrollTop)-tail));
      });
      // Plné načtení i AJAX mají stejnou hranici zobrazení; nečekáme odhadnutý počet milisekund.
      document.querySelectorAll('[data-smeny-view-pending]').forEach(function (page) {
        var planner=page.querySelector('[data-smeny-planner]');
        if(planner && planner.dataset.uiReady!=='1') return;
        page.removeAttribute('data-smeny-view-pending');
      });
    });
  }
  var gridObserver=new ResizeObserver(scheduleGridLayout);
  // Jeden posluchač obsluhuje i interní navigaci; odpojené stránky se průběžně zahodí.
  window.addEventListener('resize', function () {
    dayViews = dayViews.filter(function (view) { return view.container.isConnected; });
    dayViews.forEach(function (view) { view.update(); });
    scheduleGridLayout();
  });
  if (window.visualViewport) window.visualViewport.addEventListener('resize',scheduleGridLayout);
  // Ovládání plánovače může při plném načtení přijít v pozdějším souboru než renderer mřížky.
  document.addEventListener('cb:smeny-planner-ready',scheduleGridLayout);

  // Provozní den pokračuje přes půlnoc; přesný čas zůstává v popisku i u necelé půlhodiny.
  function minute(time) {
    var parts = String(time).split(':');
    var value = Number(parts[0]) * 60 + Number(parts[1]);
    return value < 360 ? value + 1440 : value;
  }
  // Popisek vrací běžný čas i pro interval po půlnoci.
  function clock(value) {
    return String(Math.floor(value / 60) % 24).padStart(2, '0') + ':' + String(value % 60).padStart(2, '0');
  }
  // Popisky pocházející z HR se vkládají jako text, nikdy jako HTML.
  function node(tag, className, text) {
    var element = document.createElement(tag);
    element.className = className || '';
    if (text !== undefined) element.textContent = text;
    return element;
  }
  // Ovládací tlačítka náhledu nesmějí nechtěně odeslat nadřazený formulář.
  function button(text, action) {
    var element = node('button', '', text);
    element.type = 'button';
    element.addEventListener('click', action);
    return element;
  }

  // Jedna mřížka slouží plánu i náhledu šablony; vodorovný posuv patří jen její vnitřní ploše.
  function grid(columns, end, editable, open, removable, halfHours, adaptHeight) {
    // Šablona dostane výslednou výšku až po vložení do stránky; poměr osy a podbarvení je společný.
    var step = halfHours ? 30 : 60;
    var rowHeight = removable ? 22 : 32;
    var geometry=[];
    // Uložená geometrie dovolí přepočet celé osy bez opětovného vytvoření časových vstupů.
    function position(element, offset, duration, eventBox) {
      geometry.push({element:element,offset:offset,duration:duration,eventBox:eventBox});
    }
    // Šablona může začínat i před obvyklou desátou; žádný platný interval se nesmí oříznout.
    var start = 600;
    columns.forEach(function (column) {
      column.guides.concat(column.shifts).forEach(function (item) { if (Number.isFinite(item.start)) start = Math.min(start,item.start); });
    });
    start = Math.floor(start/step)*step;
    var ticks = [];
    for (var value=start; value<end; value+=step) ticks.push(value);
    ticks.push(end);
    var scroll = node('div', 'smeny_classic_scroll');
    scroll.tabIndex = 0;
    scroll.setAttribute('role', 'region');
    scroll.setAttribute('aria-label', 'Denní mřížka, jednotlivá pracovní místa');
    var table = node('div', 'smeny_classic_grid');
    table.style.setProperty('--smeny-columns', String(columns.length));
    // Pevnou šířku míst určuje jen CSS; mřížka nikdy nerozděluje volné místo na frakce.
    var times = node('div', 'smeny_classic_hours');
    times.appendChild(node('div', 'smeny_classic_heading', 'Čas'));
    ticks.forEach(function (time,index) {
      var hour=node('div', 'smeny_classic_hour', removable && time%step!==0 ? '' : clock(time));
      hour.style.height=((index<ticks.length-1 ? ticks[index+1]-time : step)/step*rowHeight)+'px';
      position(hour,null,index<ticks.length-1 ? ticks[index+1]-time : step,false);
      times.appendChild(hour);
    });
    table.appendChild(times);
    columns.forEach(function (column, index) {
      var lane = node('div', 'smeny_classic_lane smeny_slot_color_' + column.group.slot);
      lane.dataset.smenyRow = '';
      lane.dataset.date = column.group.date || '';
      lane.dataset.slot = String(column.group.slot);
      lane.dataset.slotName = column.label;
      lane.dataset.column = column.source ? column.source.dataset.column || '' : '';
      var heading=node('div', 'smeny_classic_heading');
      heading.appendChild(node('span','smeny_classic_heading_label',column.label));
      lane.appendChild(heading);
      if(!removable && editable && column.removeForm) {
        var removeColumn=button('×',function () { column.removeForm.requestSubmit(); });
        removeColumn.className='smeny_classic_remove'; removeColumn.setAttribute('aria-label','Odstranit slot '+column.label);
        removeColumn.dataset.smenyRemoveLane=column.source.dataset.column;
        heading.classList.add('smeny_classic_heading--removable'); heading.appendChild(removeColumn);
      }
      var body = node('div', 'smeny_classic_body');
      column.guides.forEach(function (guide) {
        var shade = node('div', 'smeny_classic_guide');
        shade.style.top = ((guide.start-start)/step*rowHeight) + 'px';
        shade.style.height = ((guide.end-guide.start)/step*rowHeight) + 'px';
        position(shade,guide.start-start,guide.end-guide.start,false);
        body.appendChild(shade);
      });
      ticks.forEach(function (time,index) {
        var tick = node('button', 'smeny_classic_tick', time === end ? 'Konec' : (editable ? '+' : ''));
        tick.type = 'button'; tick.disabled = !editable || time === end || (!removable && time<600);
        tick.style.height=((index<ticks.length-1 ? ticks[index+1]-time : step)/step*rowHeight)+'px';
        position(tick,null,index<ticks.length-1 ? ticks[index+1]-time : step,false);
        tick.dataset.smenyTime = clock(time);
        var guide = column.guides.find(function (item) { return item.start <= time && item.end > time; });
        var defaultEnd=guide ? guide.end : end;
        column.shifts.forEach(function (shift) { if(shift.start>time) defaultEnd=Math.min(defaultEnd,shift.start); });
        tick.dataset.smenyDefaultEnd = clock(defaultEnd);
        tick.setAttribute('aria-label', column.label + ' od ' + clock(time));
        body.appendChild(tick);
      });
      column.shifts.forEach(function (shift) {
        var event = removable ? node('div') : button('', function () { open(shift,event); });
        event.dataset.shiftId=shift.source.dataset.shiftId || '';
        event.dataset.assignedPerson=shift.source.dataset.assignedPerson || '';
        event.className = 'smeny_classic_event';
        // U krátkého slotu zastoupí název záhlaví sloupce, aby časy a křížek zůstaly celé dostupné.
        if (removable && shift.end-shift.start<=30) event.classList.add('is-short');
        event.style.top = ((shift.start-start)/step*rowHeight) + 'px';
        event.style.height = Math.max(16,(shift.end-shift.start)/step*rowHeight-3) + 'px';
        position(event,shift.start-start,shift.end-shift.start,true);
        // Části jména přicházejí z HR; rozdělení podle mezer by poškodilo víceslovná jména.
        if(!removable) {
          event.appendChild(node('strong','',shift.surname || shift.name));
          if(shift.givenName) event.appendChild(node('span','smeny_shift_given_name',shift.givenName));
        }
        if (removable) {
          // Vstupy náhledu mění jediná pojmenovaná pole formuláře; do POST se neposílají dvakrát.
          var inputs=node('div','smeny_template_inline_times');
          ['cas_od','cas_do'].forEach(function (fieldName) {
            var source=shift.source.querySelector('[data-smeny-field="'+fieldName+'"]');
            var input=source.cloneNode(true); input.removeAttribute('name');
            input.dataset.smenyInlineTime=fieldName; input.smenyTemplateSource=source;
            inputs.appendChild(input);
          });
          event.appendChild(inputs);
        } else {
          // V bloku stačí jméno; přesný interval zůstává v tooltipu a přístupném názvu tlačítka.
          var shiftTime=clock(shift.start)+'–'+clock(shift.end);
          event.title=shiftTime;
          event.dataset.cbTooltipPosition='pointer-top';
          event.setAttribute('aria-label',shift.name+' '+shiftTime);
        }
        body.appendChild(event);
        // Jeden sloupec šablony představuje jeden slot; jeho křížek patří za název v záhlaví.
        if (removable) {
          event.smenyTemplateSource=shift.source;
          var remove=node('button','smeny_classic_remove','×');
          remove.type='button'; remove.dataset.smenyTemplateRemove=''; remove.smenyTemplateSource=shift.source;
          remove.setAttribute('aria-label','Odstranit slot '+shift.name+' '+clock(shift.start)+'–'+clock(shift.end));
          heading.classList.add('smeny_classic_heading--removable');
          heading.appendChild(remove);
        }
      });
      lane.appendChild(body); table.appendChild(lane);
    });
    scroll.appendChild(table);
    // Mřížka vzniká dynamicky i při změně hustoty; vždy připojíme společný tooltip jen k novým prvkům.
    if(window.CB_TOOLTIP) window.CB_TOOLTIP.init(scroll);
    if (removable || adaptHeight) {
      // Na nízkém displeji zachováme čitelné minimum a posuv uvnitř mřížky, nikoli přes celou stránku.
      scroll.smenyFitHeight=function (height) {
        scroll.style.height=height+'px';
        var headingHeight=times.firstElementChild.getBoundingClientRect().height;
        // Plán má stejně vysoká záhlaví i s křížkem. Rezerva kryje zaokrouhlení clientHeight při zoomu.
        // Půlhodiny mohou být nižší než 16 px; jinak vznikal přesah i na běžně vysokém okně.
        var available=scroll.clientHeight-headingHeight-(adaptHeight?1:0);
        rowHeight=Math.max(adaptHeight?12:16,available/((end-start)/step+1));
        geometry.forEach(function (item) {
          var size=item.duration/step*rowHeight;
          if (item.offset !== null) item.element.style.top=(item.offset/step*rowHeight)+'px';
          item.element.style.height=(item.eventBox ? Math.max(16,size-3) : size)+'px';
          if (item.eventBox && removable) item.element.classList.toggle('is-short',size<48);
        });
      };
    }
    return scroll;
  }

  // Přepínání dní neodesílá formulář a zachová rozepsané hodnoty ostatních dní v DOM.
  function days(container, selector) {
    if (container.dataset.daysReady === '1') return;
    container.dataset.daysReady = '1';
    var items = Array.from(container.querySelectorAll(selector));
    if (!items.length) return;
    var select = node('select'); select.setAttribute('aria-label', 'Den');
    var nav = node('nav', 'smeny_day_navigation'); nav.setAttribute('aria-label', 'Dny týdne');
    var previous = button('‹', function () { select.selectedIndex = Math.max(0,select.selectedIndex-1); update(); });
    previous.setAttribute('aria-label','Předchozí den');
    var next = button('›', function () { select.selectedIndex = Math.min(items.length-1,select.selectedIndex+1); update(); });
    next.setAttribute('aria-label','Následující den');
    items.forEach(function (item, index) {
      var label = item.dataset.dayLabel || item.querySelector('.smeny_template_day_name').textContent;
      var option = node('option', '', label); option.value = String(index); select.appendChild(option);
    });
    select.selectedIndex=Math.min(items.length-1,Number(container.closest('.smeny_content').dataset.selectedDay || 0));
    // Nový pohled zůstává na desktopu týdenní; na mobilu se oba pohledy ovládají po dnech.
    function update() {
      var classic = !!container.closest('.smeny_style--classic');
      var single = classic || window.matchMedia('(max-width:980px)').matches;
      nav.hidden = !single;
      items.forEach(function (item,index) { item.hidden = single && index !== select.selectedIndex; });
      previous.disabled = select.selectedIndex === 0; next.disabled = select.selectedIndex === items.length-1;
      container.closest('.smeny_content').querySelectorAll('input[name="den_index"]').forEach(function (field) { field.value=String(select.selectedIndex); });
      scheduleGridLayout();
    }
    nav.append(previous,select,next);
    var toolbar=container.closest('[data-smeny-template-form]')?.querySelector('.smeny_template_toolbar');
    var resolution=container.closest('.smeny_content').querySelector('[data-smeny-planning-resolution]');
    if (toolbar) toolbar.insertBefore(nav,toolbar.querySelector('.smeny_template_resolution'));
    else if(resolution) {
      // Přepínač zůstane viditelný i v týdenním pohledu, kde se navigace jednotlivých dní schová.
      var dayToolbar=resolution.closest('.smeny_planning_day_toolbar');
      dayToolbar.prepend(nav);
    } else container.before(nav);
    select.addEventListener('change',update);
    dayViews = dayViews.filter(function (view) { return view.container.isConnected; });
    dayViews.push({container:container,update:update});
    update();
  }

  // Jeden panel v horní vrstvě zachová původní formulář, tokeny i kontroly bez zakrytí rozpisu.
  function editor(planner, source, anchor) {
    var previous=planner.querySelector('.smeny_shift_panel');
    if(previous) previous.hidePopover();
    var panel=node('div','smeny_shift_panel'); panel.setAttribute('popover','auto');
    panel.setAttribute('role','dialog'); panel.setAttribute('aria-label','Detail směny '+source.dataset.name);
    var header=node('header',''); header.appendChild(node('strong','',source.dataset.name));
    var close=button('×',function () { panel.hidePopover(); }); close.setAttribute('aria-label','Zavřít detail směny');
    header.appendChild(close);panel.appendChild(header);
    // Režim pouze pro čtení nemá časová pole; detail proto zpřístupní interval i na dotykovém zařízení.
    if(!source.querySelector('form')) panel.appendChild(node('small','smeny_shift_panel_time',source.dataset.from+'–'+source.dataset.to));
    var hours=node('div','smeny_shift_hours'); hours.appendChild(node('strong','','Naplánováno tento týden'));
    var rows=[];
    try { rows=JSON.parse(planner.querySelector('[data-smeny-week-hours]').textContent); } catch(error) { rows=[]; }
    var branches={};
    rows.filter(function (row) { return Number(row.id_person)===Number(source.dataset.assignedPerson); }).forEach(function (row) {
      var key=String(row.id_pob);
      if(!branches[key]) branches[key]={name:row.pobocka,slots:[],minutes:0};
      branches[key].slots.push((row.slot || 'Slot #'+row.id_slot)+' '+formatHours(row.minuty));
      branches[key].minutes+=Number(row.minuty);
    });
    var total=0;
    Object.keys(branches).sort(function (a,b) { return (Number(b)===Number(planner.dataset.currentBranch))-(Number(a)===Number(planner.dataset.currentBranch)); }).forEach(function (key) {
      var branch=branches[key];total+=branch.minutes;
      var line=node('p','');line.appendChild(node('b','',branch.name+': '));
      line.appendChild(document.createTextNode(branch.slots.join(' · ')));hours.appendChild(line);
    });
    hours.appendChild(node('p','smeny_shift_hours_total','Celkem '+formatHours(total)));
    panel.appendChild(hours);
    var marker=document.createComment('Původní umístění formuláře směny');source.before(marker);
    panel.appendChild(source);planner.appendChild(panel);
    var details=source.querySelector('details'),wasOpen=details && details.open;
    if(details) details.open=true;
    // Zavření kliknutím ven nebo Escape vrátí jediný formulář na jeho původní místo.
    panel.addEventListener('toggle',function (event) {
      if(event.newState==='closed') { if(details) details.open=wasOpen;marker.replaceWith(source);panel.remove(); }
    });
    panel.showPopover();
    var rect=anchor.getBoundingClientRect(),width=panel.offsetWidth,height=panel.offsetHeight;
    var left=rect.right+8;
    if(left+width>window.innerWidth-8) left=rect.left-width-8;
    panel.style.left=Math.max(8,Math.min(left,window.innerWidth-width-8))+'px';
    panel.style.top=Math.max(8,Math.min(rect.top,window.innerHeight-height-8))+'px';
  }

  // Minuty z databáze zůstávají přesné i přes půlnoc; zaokrouhlujeme jen čitelný počet hodin.
  function formatHours(minutes) {
    return new Intl.NumberFormat('cs-CZ',{maximumFractionDigits:2}).format(Number(minutes)/60)+' h';
  }

  // Samostatný uložený sloupec má právě jeden zdrojový řádek; šablona pouze podbarvuje jeho časovou osu.
  function planning(planner) {
    var halfHours=!!planner.closest('.smeny_content').querySelector('[data-smeny-half-hours]:checked');
    // Týdenní pohled používá stejné rozlišení; přesné začátky směn se nezaokrouhlují.
    planner.querySelectorAll('.smeny_planner_row .smeny_planner_tick').forEach(function (tick) {
      tick.hidden=!halfHours && minute(tick.dataset.smenyTime)%60!==0;
    });
    if (!planner.closest('.smeny_style--classic')) return;
    planner.querySelectorAll('[data-smeny-grid-day]').forEach(function (day) {
      var end = minute(day.dataset.closing);
      if (!Number.isFinite(end)) return;
      var columns = Array.from(day.querySelectorAll('.smeny_planner_row')).map(function (row) {
        var guides = JSON.parse(row.dataset.guides || '[]').map(function (guide) { return {start:minute(guide.cas_od),end:minute(guide.cas_do)}; });
        var shifts = Array.from(row.querySelectorAll('[data-smeny-assigned]')).map(function (item) {
          return {start:minute(item.dataset.from),end:minute(item.dataset.to),name:item.dataset.name,surname:item.dataset.surname,givenName:item.dataset.givenName,source:item};
        });
        return {group:{slot:row.dataset.slot,date:row.dataset.date},label:row.dataset.slotName,source:row,removeForm:row.querySelector('[data-smeny-column-remove]'),guides:guides,shifts:shifts};
      });
      var host=day.querySelector('.smeny_classic_plan');
      if(!host) { host=node('div','smeny_classic_plan'); day.appendChild(host); }
      var oldScroll=host.firstElementChild, left=oldScroll?oldScroll.scrollLeft:0;
      host.replaceChildren(grid(columns,end,planner.dataset.canEdit === '1',function (shift,anchor) { editor(planner,shift.source,anchor); },false,halfHours,true));
      host.firstElementChild.scrollLeft=left;
      day.classList.add('smeny_grid_ready');
      gridDays.add(day);
      [day.closest('.smeny_content'),day.querySelector('header'),document.querySelector('.cb_prebrani_prav_notice'),day.closest('.smeny_content').querySelector('.smeny_planning_actions')].forEach(function (element) {
        if(element && !gridObserved.has(element)) { gridObserver.observe(element); gridObserved.add(element); }
      });
      scheduleGridLayout();
    });
  }

  // Mřížka čte jediná zdrojová pole a zachovává posun při změně času i hustoty osy.
  function template(day) {
    if (!day.closest('.smeny_style--classic')) return;
    var end = minute(day.dataset.closing);
    if (!Number.isFinite(end)) return;
    var columns = [];
    day.querySelectorAll('[data-smeny-template-column]').forEach(function (column) {
      var name = column.querySelector('header strong').textContent;
      var blocks = Array.from(column.querySelectorAll('[data-smeny-template-slot]'));
      if (!blocks.length && column.dataset.optional==='1') return;
      if (!blocks.length) columns.push({group:{slot:column.dataset.slot},label:name,guides:[],shifts:[]});
      blocks.forEach(function (row,index) {
        var start=minute(row.querySelector('[data-smeny-field="cas_od"]').value), stop=minute(row.querySelector('[data-smeny-field="cas_do"]').value);
        if (Number.isFinite(start) && Number.isFinite(stop) && stop>start) row.smenyTemplateRange={start:start,end:stop};
        var range=row.smenyTemplateRange || {start:600,end:630};
        var shift={start:range.start,end:range.end,name:name,source:row};
        columns.push({group:{slot:column.dataset.slot},label:name+(blocks.length>1?' '+(index+1):''),guides:[shift],shifts:[shift]});
      });
    });
    var host = day.querySelector('.smeny_classic_template');
    if (!host) { host = node('div','smeny_classic_template'); day.querySelector('.smeny_template_columns').before(host); }
    var oldScroll=host.querySelector('.smeny_classic_scroll');
    var top=oldScroll ? oldScroll.scrollTop : 0, left=oldScroll ? oldScroll.scrollLeft : 0;
    host.replaceChildren(grid(columns,end,false,function () {},true,!!day.closest('.smeny_content').querySelector('[data-smeny-half-hours]:checked')));
    host.firstElementChild.scrollTop=top; host.firstElementChild.scrollLeft=left;
    day.classList.add('smeny_grid_ready');
    gridDays.add(day);
    [day.closest('.smeny_content'),day.closest('form').querySelector('.smeny_template_toolbar'),
      day.closest('form').querySelector('[data-smeny-template-status]'),document.querySelector('.cb_prebrani_prav_notice')].forEach(function (element) {
      if (element && !gridObserved.has(element)) { gridObserver.observe(element); gridObserved.add(element); }
    });
    scheduleGridLayout();
  }

  // Na mobilech mají běžné tabulky popisky u hodnot; vlastní šířka stránky se nezvětšuje.
  function tables(root) {
    root.querySelectorAll('.smeny_table_scroll table,.smeny_shift_table').forEach(function (table) {
      var header = table.querySelector('thead tr:last-child');
      var labels=header ? Array.from(header.children).map(function (cell) { return cell.textContent.trim(); }) : [];
      table.querySelectorAll('.filter-row th').forEach(function (cell,index) { cell.dataset.label=labels[index] || ''; });
      table.querySelectorAll('tbody tr').forEach(function (row) { Array.from(row.children).forEach(function (cell,index) { cell.dataset.label=cell.colSpan>1 ? '' : (labels[index] || ''); }); });
    });
  }

  // Inicializace je společná pro plné načtení i interní navigaci a nikdy nezapisuje preference sama od sebe.
  function init() {
    document.querySelectorAll('.smeny_content').forEach(tables);
    document.querySelectorAll('.smeny_planner').forEach(function (planner) {
      planning(planner); var container=planner.querySelector('.smeny_planner_days'); if (container) days(container,'.smeny_planner_day');
    });
    document.querySelectorAll('[data-smeny-template-day]').forEach(template);
    document.querySelectorAll('.smeny_template_week').forEach(function (week) { days(week,'[data-smeny-template-day]'); });
    scheduleGridLayout();
    document.querySelectorAll('[data-smeny-style-form]').forEach(function (form) {
      if (form.dataset.ready === '1') return; form.dataset.ready='1';
      var saved=form.querySelector('input:checked').value;
      form.addEventListener('change',function () { form.requestSubmit(); });
      form.addEventListener('submit',function (event) {
        event.preventDefault();
        var status=form.querySelector('[data-smeny-style-status]');
        var body=new URLSearchParams(new FormData(form)); var fields=form.querySelector('fieldset'); fields.disabled=true; status.textContent='Ukládám…';
        fetch(form.getAttribute('action'),{method:'POST',body:body,credentials:'same-origin',cache:'no-store'})
          .then(function (response) { return response.json().then(function (data) { if (!response.ok || !data.ok) throw new Error(data.message || 'Vzhled se nepodařilo uložit.'); return data; }); })
          .then(function (data) {
            saved=String(data.style); var page=form.closest('.smeny_content');
            page.classList.toggle('smeny_style--classic',saved==='1'); page.classList.toggle('smeny_style--new',saved==='2');
            status.textContent='Uloženo';
          }).catch(function (error) { form.querySelector('input[name="smeny_styl"][value="'+saved+'"]').checked=true; status.textContent=error.message || 'Vzhled se nepodařilo uložit.'; })
          .finally(function () { fields.disabled=false; });
      });
    });
  }

  // Editor oznámí hotovou změnu; náhled pak čte už přepočítaná skutečná pole.
  document.addEventListener('cb:smeny-template-changed',function (event) { template(event.target); });
  // Hustota mění jen zobrazení, uložené intervaly včetně půlhodin zůstávají přesné.
  document.addEventListener('change',function (event) {
    if (!event.target.matches('[data-smeny-half-hours]')) return;
    var page=event.target.closest('.smeny_content');
    page.querySelectorAll('[data-smeny-template-day]').forEach(template);
    page.querySelectorAll('[data-smeny-planner]').forEach(function (planner) {
      planner.dispatchEvent(new CustomEvent('cb:smeny-resolution-changed'));
      planning(planner);
    });
  });
  // Rozbalení souhrnu nikdy neotevírá editor; odkaz názvu zůstává samostatnou navigací.
  document.addEventListener('click',function (event) {
    var toggle=event.target.closest('[data-smeny-template-detail]');
    if(!toggle) return;
    var card=toggle.closest('.smeny_template_card'), summary=card.querySelector('.smeny_template_card_summary');
    summary.hidden=!summary.hidden; toggle.textContent=summary.hidden?'Detail':'Skrýt';
    toggle.setAttribute('aria-expanded',String(!summary.hidden)); card.classList.toggle('is-open',!summary.hidden);
  });
  document.addEventListener('DOMContentLoaded',init);
  document.addEventListener('cb:main-swapped',init);
  document.addEventListener('cb:filters-swapped',function () { document.querySelectorAll('.smeny_content').forEach(tables); });
  init();
}());
