/* Co-Link: adresar kolegu a sdilene filtry osob pro vyber prijemcu. */
(window.CoLinkParts ||= Object.create(null)).adresar=function(ctx){
  'use strict';
  const {$,state}=ctx;
  // Vazby mezi oblastmi se vyhodnoti az pri volani; vsechny pouzivaji jednu instanci stavu.
  const appLocation=(...args)=>ctx.appLocation(...args);
  const node=(...args)=>ctx.node(...args);
  const personAvatar=(...args)=>ctx.personAvatar(...args);
  const button=(...args)=>ctx.button(...args);
  const api=(...args)=>ctx.api(...args);
  const saveDraft=(...args)=>ctx.saveDraft(...args);
  const savePosition=(...args)=>ctx.savePosition(...args);
  const conversationList=(...args)=>ctx.conversationList(...args);
  const refresh=(...args)=>ctx.refresh(...args);
  const openChat=(...args)=>ctx.openChat(...args);
  const field=(...args)=>ctx.field(...args);
  const check=(...args)=>ctx.check(...args);

  // Nacte povolene kontakty a ciselniky pro vyber pristupu.
  async function contacts(){const d=await api('lide');state.people=d.lide;state.slots=d.sloty;state.functions=d.funkce;state.branches=d.pobocky||[];}
  // Filtry sdili adresar a vyber clenu; adresar muze vlozit hledani pred skupinu do jednoho radku.
  function peopleFilters(parent,changed,searchField=null){
    const groupArea=searchField?node('div',undefined,'directory-group-filter'):parent;
    if(searchField){
      const row=node('div',undefined,'directory-groups-row');searchField.classList.add('directory-search');
      row.append(searchField,groupArea);parent.append(row);
    }
    groupArea.append(node('p','Skupina','section-label'));
    const groups=node('div',undefined,'recipient-filters'),groupInputs=new Map();
    const groupTips={manager:'Ve skupině jsou všichni manažeři.\nNepište jim zbytečnosti, díky.',vedouci:'Skupina zahrnuje restaurant manažery, jejich zástupce i vedoucí směn.'};
    for(const [key,label]of [['manager','Manager'],['vedouci','Ved. pobočky'],['kuryr','Kurýr'],['pizzar','Pizzař']]){
      const input=check(groups,label);groupInputs.set(key,input);
      // Spolecny tooltip IS prevezme text i chovani; bublina sleduje kurzor zhora.
      if(groupTips[key]){
        const label=input.closest('label');label.title=groupTips[key];
        label.dataset.cbTooltipPosition='pointer-top';label.dataset.cbTooltipMultiline='1';
      }
    }
    window.CB_TOOLTIP?.init(groups);
    const allGroups=check(groups,'Všichni',true);groupArea.append(groups);parent.append(node('p','MAIN pobočky','section-label'));
    const branches=node('div',undefined,'recipient-filters'),branchInputs=new Map();
    for(const branch of state.branches)branchInputs.set(Number(branch.id),check(branches,branch.nazev));
    const allBranches=check(branches,'Všechny pobočky',true);parent.append(branches);
    for(const input of groupInputs.values())input.addEventListener('change',()=>{allGroups.checked=![...groupInputs.values()].some(i=>i.checked);changed();});
    allGroups.addEventListener('change',()=>{for(const input of groupInputs.values())input.checked=false;allGroups.checked=true;changed();});
    for(const input of branchInputs.values())input.addEventListener('change',()=>{allBranches.checked=![...branchInputs.values()].some(i=>i.checked);changed();});
    allBranches.addEventListener('change',()=>{for(const input of branchInputs.values())input.checked=false;allBranches.checked=true;changed();});
    return ()=>({groups:[...groupInputs].filter(([,i])=>i.checked).map(([id])=>id),branches:[...branchInputs].filter(([,i])=>i.checked).map(([id])=>id)});
  }
  // Uvnitr bloku plati OR; mezi skupinou a pobockou plati AND.
  function personMatches(person,filters){return (!filters.groups.length||(person.skupiny||[]).some(g=>filters.groups.includes(g)))&&(!filters.branches.length||(person.pobocky||[]).some(id=>filters.branches.includes(Number(id))));}
  // Vyhledavani jmen funguje i bez diakritiky a bez ohledu na velikost pismen.
  function nameKey(value){return String(value).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('cs').trim();}
  // Z adresare se otevre existujici soukromy chat nebo bezpecne vznikne jedna nova konverzace dvojice.
  async function writePerson(person){
    const r=await api('soukroma',{lide:[Number(person.id)]});await refresh();await openChat(Number(r.id));
  }
  // Adresar patri do prave plochy; vlevo zustavaji existujici kanaly a konverzace.
  async function directory(){
    saveDraft();savePosition();clearInterval(ctx.homeQrTimer);ctx.homeQrTimer=null;state.cid=0;state.thread=0;state.view='directory';
    $('app').classList.remove('show-home');$('app').classList.add('chat-open');
    $('messages').classList.remove('home-screen');$('messages').classList.add('directory-screen');$('messages').setAttribute('role','region');$('messages').setAttribute('aria-label','Adresář');
    for(const id of ['composer','search-form','search-toggle','thread-banner','chat-info','mute'])$(id).hidden=true;
    $('pending').replaceChildren();$('chat-name').textContent='Adresář';$('chat-subtitle').textContent='';
    history.replaceState(null,'',appLocation());conversationList();
    const area=$('messages');area.replaceChildren(node('p','Načítám kolegy…','hint'));
    await contacts();if(state.view!=='directory')return;
    area.replaceChildren();
    const controls=node('div',undefined,'directory-controls'),search=field(controls,'Hledat podle jména','','search');search.placeholder='Jméno kolegy…';search.autocomplete='off';
    const filters=peopleFilters(controls,()=>render(),search.closest('.field'));
    const count=node('p',undefined,'recipient-summary'),cards=node('div',undefined,'directory-grid');area.append(controls,count,cards);
    // Karty ukazuji jen firemni zarazeni a pobocky, nikoli soukrome udaje z HR.
    const render=()=>{
      if(state.view!=='directory')return;
      const query=nameKey(search.value),selection=filters();
      const people=state.people.filter(person=>nameKey(person.jmeno).includes(query)&&personMatches(person,selection));
      count.textContent='Zobrazeno '+people.length+' z '+state.people.length;cards.replaceChildren();
      for(const person of people){
        const card=node('article',undefined,'person-card');card.setAttribute('aria-label',person.jmeno);
        const avatar=personAvatar(person.jmeno,person.foto,'directory-avatar');
        const heading=node('div',undefined,'person-heading');heading.append(node('h2',person.jmeno));card.append(avatar,heading);
        const labels=person.zarazeni||((person.skupiny||[]).map(g=>({manager:'Manager',vedouci:'Ved. pobočky',kuryr:'Kurýr',pizzar:'Pizzař'}[g]||g)));
        const branches=(person.pobocky||[]).map(id=>state.branches.find(b=>Number(b.id)===Number(id))?.nazev).filter(Boolean);
        // Pod jmenem je jeden radek: vsechny pobocky, za nimi sloty a funkce v puvodnim poradi.
        const details=node('p',[branches.join(', '),labels.join(' · ')||'Uživatel Co-Linku'].filter(Boolean).join(' · '),'person-details');card.append(details);
        // Pevna vyska karty neztrati delsi obsah: cely text zpřístupní spolecny tooltip IS nad kurzorem.
        for(const element of [heading.querySelector('h2'),details]){
          element.title=element.textContent;element.dataset.cbTooltipPosition='pointer-top';element.dataset.cbTooltipMultiline='1';
        }
        // Oznaceni vlastniho uctu zustava u jmena, aby nevytvarelo treti radek karty.
        if(Number(person.id)===Number(state.me.id))heading.append(node('span','Váš účet','person-self'));
        // Ikona vedle jmena zachova stejnou akci bez dalsiho radku na karte.
        else{const send=button('',async()=>{send.disabled=true;try{await writePerson(person);}finally{send.disabled=false;}},'person-write');send.title='Napsat zprávu: '+person.jmeno;send.setAttribute('aria-label',send.title);send.innerHTML='<svg class="chat-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 5H5v15h15v-8M16 3l5 5-9 9-5 1 1-5Z"></path></svg>';heading.append(send);}
        cards.append(card);
      }
      window.CB_TOOLTIP?.init(cards);
      if(!people.length)cards.append(node('p','Tomuto hledání a filtrům neodpovídá žádný kolega.','hint'));
    };
    search.addEventListener('input',render);render();
  }

  return {contacts,peopleFilters,personMatches,nameKey,writePerson,directory};
};
