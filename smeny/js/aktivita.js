/* Účel: Popisuje skutečné volby a navigaci ve Směnách pro společný sběrač; rozpracované ovládání neoznačuje za uložené směny. */
(function () {
  'use strict';
  var logger=window.CB_USER_ACTIVITY;
  if (!logger) return;
  var values=new WeakMap(), current=null, leaving=false, filterTimers=new WeakMap(), dirtyFields=new Set();
  var ids=['id_pob','week','id_person','id_slot','id_smeny_rozpis','id_smeny_sablona','id_smeny_smena','id_smeny_oznameni'];
  var fields=['week','id_pob','branch','id_smeny_sablona','cas_od','cas_do','nazev','kdykoliv','datum_volna','planovat_kdykoliv','bez_ohledu','bez_ohledu_na_pobocku','uzaverka_den','uzaverka_hodina','smeny_styl'];

  // Kontext čteme ze skutečných ovládacích prvků; neodesíláme FormData, text poznámek ani bezpečnostní tokeny.
  function context(target) {
    // Křížek náhledu loguje konkrétní původní slot, nikoliv pouze obrázek barevného bloku.
    if (target && target.smenyTemplateSource) target=target.smenyTemplateSource;
    var page=target && target.closest ? target.closest('.smeny_content') : null;
    page=page || document.querySelector('.smeny_content');
    var result={};
    if (!page) return result;
    var form=target && target.closest ? target.closest('form') : null;
    ids.forEach(function (name) {
      var field=(form && form.querySelector('[name="'+name+'"]')) || (target && target.tagName==='DIALOG' && target.querySelector('[name="'+name+'"]')) || (['id_pob','week','id_smeny_rozpis'].includes(name) && page.querySelector('[name="'+name+'"]'));
      if (field && /^\d+$/.test(field.value)) result[name]=Number(field.value);
    });
    var panel=target && target.closest ? target.closest('[data-smeny-people]') : null;
    var planner=panel && panel.closest('[data-smeny-planner]');
    var row=target && target.closest ? target.closest('[data-smeny-row],[data-smeny-template-column]') : null;
    if(!row && planner) row=planner.querySelector('.is-start')?.closest('[data-smeny-row]');
    if (row) { if(row.dataset.slot) result.id_slot=Number(row.dataset.slot); if(row.dataset.date) result.datum=row.dataset.date; }
    if(row && row.dataset.day) result.den_index=Number(row.dataset.day)-1;
    if(target && target.matches?.('[data-smeny-template-add][data-slot]')) {
      result.id_slot=Number(target.dataset.slot);
      result.den_index=Number(target.closest('[data-smeny-template-day]').dataset.smenyTemplateDay)-1;
    }
    var interval=target && target.closest ? target.closest('[data-smeny-template-slot]') : null;
    [['cas_od','[data-smeny-field="cas_od"],[data-smeny-choice-from]'],['cas_do','[data-smeny-field="cas_do"],[data-smeny-choice-to]']].forEach(function (item) {
      var field=(interval || panel)?.querySelector(item[1]); if(field) result[item[0]]=field.value;
    });
    var person=target && target.closest ? target.closest('[data-person]') : null;
    if (person) result.id_person=Number(person.dataset.person);
    var assignment=target && target.closest ? target.closest('[data-smeny-assigned]') : null;
    if (assignment) { var id=assignment.querySelector('[name=id_smeny_smena]'); if(id) result.id_smeny_smena=Number(id.value); }
    var shift=target && target.closest ? target.closest('[data-shift-id]') : null;
    if(shift && shift.dataset.shiftId) { result.id_smeny_smena=Number(shift.dataset.shiftId); if(shift.dataset.assignedPerson) result.id_person=Number(shift.dataset.assignedPerson); }
    var day=target && target.closest ? target.closest('[data-smeny-request-day]') : null;
    var date=day && day.querySelector('[data-smeny-start-value]')?.name.match(/\d{4}-\d{2}-\d{2}/);
    if(date) result.datum=date[0];
    return result;
  }

  // Jednotné pojmenování pole funguje i pro vnořené bloky šablon a klientské časové volby.
  function fieldName(field) {
    if (field.matches('[data-smeny-choice-from]')) return 'cas_od';
    if (field.matches('[data-smeny-half-hours]')) return 'po_30_min';
    if (field.matches('[data-smeny-choice-to]')) return 'cas_do';
    if (field.matches('[data-smeny-ignore]')) return 'bez_ohledu';
    if (field.matches('[data-smeny-ignore-branch]')) return 'bez_ohledu_na_pobocku';
    if (field.matches('.smeny_day_navigation select')) return 'den_index';
    if (field.matches('[data-smeny-start]')) return 'pozadavek_od';
    if (field.matches('[data-smeny-end]')) return 'pozadavek_do';
    var name=field.dataset.smenyField || field.name || '';
    return fields.includes(name) || /^sm_f\[(den|pobocka|slot|jmeno)\]$/.test(name) ? name : '';
  }
  // Checkbox má hodnotu i při odškrtnutí; limit velikosti je stejný jako na přijímací straně.
  function value(field) {
    if(field.type==='radio') return String((field.closest('form') || document).querySelector('input[type=radio][name="'+CSS.escape(field.name)+'"]:checked')?.value || '').slice(0,300);
    if(field.matches('[data-smeny-start],[data-smeny-end]')) { var minute=(360+(Number(field.value)-1)*15)%1440; return String(Math.floor(minute/60)).padStart(2,'0')+':'+String(minute%60).padStart(2,'0'); }
    return String(field.type==='checkbox'?(field.checked?'1':'0'):field.value || '').slice(0,300);
  }
  // Událost prohlížeče je jen pozorování ovládání, identitu a čas příjmu doplní server.
  function emit(code,target,extra) {
    var page=document.querySelector('.smeny_content');
    if (!page) return '';
    return logger.record('smeny',code,page.dataset.page || 'prehled',Object.assign(context(target),extra || {}));
  }

  // Výchozí hodnoty zachytíme po renderu a po přepočtu formulářů, bez logování samotné inicializace.
  function init() {
    var page=document.querySelector('.smeny_content');
    if (!page || !page.dataset.page || page.classList.contains('is-page-loading')) { current=null; return; }
    page.querySelectorAll('input,select').forEach(function (field) { if(fieldName(field)) values.set(field,value(field)); });
    if (current!==page) { current=page; leaving=false; emit('stranka_zobrazena',page); }
  }
  // Finální změna hodnoty dostane jeden záznam, i když prohlížeč vyvolá input a následně change.
  function changed(field) {
    if (!field.isConnected || !field.closest('.smeny_content')) return;
    var name=fieldName(field); if (!name) return;
    var old=values.has(field)?values.get(field):'', next=value(field);
    if (old===next) return;
    values.set(field,next);
    var code=name==='week'?'tyden_vybran':(name==='id_pob' || name==='branch'?'pobocka_vybrana':(name==='den_index'?'den_vybran':(name.indexOf('sm_f[')===0?'filtr_zmenen':'pole_zmeneno')));
    var extra={pole:name,old:old,new:next}; if(name==='den_index') extra.den_index=Number(next);
    emit(code,field,extra);
  }
  // Před tlačítkem zachováme i rozepsanou hodnotu, kterou jeho obsluha následně vynuluje.
  function flushFields() {
    dirtyFields.forEach(function (field) { clearTimeout(filterTimers.get(field)); changed(field); });
    dirtyFields.clear();
  }

  // Menu, odkazy týdnů a ovládací tlačítka se zachytí před nahrazením nebo odstraněním jejich DOM.
  document.addEventListener('click',function (event) {
    flushFields();
    var target=event.target.closest ? event.target.closest('a,button,summary,[data-person]') : null;
    var page=document.querySelector('.smeny_content');
    if (!target || !page || target.disabled) return;
    if (target.tagName==='A') {
      var url=new URL(target.getAttribute('href') || '',location.href);
      if (url.origin!==location.origin) return;
      var detail={cil_modul:(url.searchParams.get('m') || '').slice(0,50),cil_stranka:(url.searchParams.get('page') || '').slice(0,50),popisek:String(target.textContent || '').trim().slice(0,120)};
      if(target.closest('#cb-main-menu') || target.closest('.smeny_content')) {
        if(url.searchParams.has('week')) detail.new=(url.searchParams.get('week') || '').slice(0,300);
        emit(target.closest('#cb-main-menu')?'menu_klik':(target.classList.contains('filter-reset-btn')?'filtr_reset':(url.searchParams.has('week')?'tyden_vybran':'ovladani_klik')),target,detail);
        logger.flush();
      }
      return;
    }
    if (!target.closest('.smeny_content')) return;
    var code='ovladani_klik', extra={};
    if(target.matches('[data-person]')) code='osoba_vybrana';
    else if(target.matches('[data-smeny-time]')) { code='cas_vybran'; extra.cas_od=target.dataset.smenyTime; }
    else if(target.matches('[data-smeny-add-lane]')) { code='sloupec_pridan'; extra.id_slot=Number(target.dataset.smenyAddLane); extra.datum=target.dataset.date; }
    else if(target.matches('[data-smeny-remove-lane]')) { code='ovladani_klik'; extra.ovladac='Odebrat sloupec'; }
    else if(target.matches('[data-smeny-column-confirm]')) { code='ovladani_klik'; extra.ovladac=target.dataset.smenyColumnConfirm==='odstranit'?'Potvrdit odebrání sloupce':'Zpět z odebrání sloupce'; }
    else if(target.matches('[data-smeny-template-add]')) code='slot_pridan';
    else if(target.matches('[data-smeny-template-remove]')) code='slot_odebran';
    else if(target.matches('[data-smeny-cancel]')) code='vyber_zrusen';
    else if(target.matches('.smeny_classic_event,summary')) code=target.tagName==='SUMMARY' && target.parentElement.open?'editor_zavren':'editor_otevren';
    else if(target.matches('[data-smeny-oznameni-close]') || target.closest('dialog') && target.type==='button' && !target.name) return;
    else if(target.closest('.smeny_day_navigation')) {
      code='den_vybran'; var select=target.closest('.smeny_day_navigation').querySelector('select');
      extra.old=select.value; extra.new=String(Math.max(0,Math.min(select.options.length-1,select.selectedIndex+(target.getAttribute('aria-label')==='Předchozí den'?-1:1))));
      extra.den_index=Number(extra.new);
    }
    if(target.name==='action') extra.akce=String(target.value || '').slice(0,80);
    // Popisek nese význam tlačítka, nikoliv celý seznam zaměstnanců uvnitř panelu.
    if(code==='ovladani_klik') extra.popisek=String(target.getAttribute('aria-label') || target.textContent || '').trim().slice(0,120);
    emit(code,target,extra);
  },true);
  // Před přímou editací zachytíme i hodnoty, které editor naplnil nebo resetoval programově.
  ['pointerdown','focusin'].forEach(function (name) { document.addEventListener(name,function (event) { var field=event.target; if(field.matches('.smeny_content input,.smeny_content select') && fieldName(field) && !values.has(field)) values.set(field,value(field)); },true); });
  document.addEventListener('change',function (event) { if(event.target.matches('input,select')) { clearTimeout(filterTimers.get(event.target)); dirtyFields.delete(event.target); changed(event.target); } },true);
  document.addEventListener('input',function (event) {
    var field=event.target;
    if (!field.matches('.smeny_content input,.smeny_content select') || !fieldName(field)) return;
    dirtyFields.add(field);
    clearTimeout(filterTimers.get(field)); filterTimers.set(field,setTimeout(function () { dirtyFields.delete(field); changed(field); },600));
  },true);

  // Odeslání a serverový výsledek propojí ID události v jediném skrytém poli bez změny doménových dat.
  document.addEventListener('submit',function (event) {
    var form=event.target;
    if (!form.closest('.smeny_content') || event.defaultPrevented) return;
    flushFields();
    var submitter=event.submitter;
    var action=submitter && submitter.name==='action'?submitter.value:form.querySelector('[name=action]')?.value || '';
    var id=emit('formular_odeslan',form,{akce:String(action).slice(0,80)});
    if ((form.getAttribute('method') || 'get').toLowerCase()==='post') {
      var hidden=form.querySelector('[name=cb_aktivita_id]');
      if(!hidden) { hidden=document.createElement('input'); hidden.type='hidden'; hidden.name='cb_aktivita_id'; form.appendChild(hidden); }
      hidden.value=id;
    }
    logger.flush();
  },true);
  document.addEventListener('invalid',function (event) { if(event.target.closest('.smeny_content')) emit('formular_neplatny',event.target,{pole:fieldName(event.target) || 'pole'}); },true);
  // Interní přechod i běžný odchod mají samostatný konec; další heartbeat není uživatelská událost.
  function leave() {
    if(current && !leaving) {
      flushFields();
      emit('stranka_opustena',current); leaving=true; logger.flush(true);
    }
  }
  document.addEventListener('close',function (event) { if(event.target.matches('.smeny_content dialog')) emit('editor_zavren',event.target); },true);
  document.addEventListener('cb:smeny-editor-opened',function (event) { emit('editor_otevren',event.target); });
  document.addEventListener('cb:smeny-selection-changed',function (event) {
    event.target.querySelectorAll('[data-smeny-choice-from],[data-smeny-choice-to],[data-smeny-ignore],[data-smeny-ignore-branch]').forEach(function (field) { values.set(field,value(field)); });
  });
  // Automatické odškrtnutí není druhý ruční úkon; další klik a editace ale potřebují správný výchozí stav.
  document.addEventListener('cb:smeny-anytime-synced',function (event) { values.set(event.target,value(event.target)); });
  document.addEventListener('cb:smeny-request-prefilled',function (event) {
    event.target.querySelectorAll('input').forEach(function (field) { if(fieldName(field)) values.set(field,value(field)); });
  });
  document.addEventListener('cb:main-before-load',leave);
  document.addEventListener('cb:menu-no-effect',function (event) { if(event.detail?.module==='smeny') emit('menu_stejne',current); });
  window.addEventListener('pagehide',leave);
  document.addEventListener('cb:smeny-planning-ended',function (event) { emit('planovani_ukonceno',current,{duvod:String(event.detail?.reason || 'ukonceno').slice(0,100)}); logger.flush(); });
  document.addEventListener('DOMContentLoaded',init);
  document.addEventListener('cb:main-swapped',init);
  init();
}());
