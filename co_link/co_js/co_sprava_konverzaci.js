/* Co-Link: formulare skupin a kanalu, pravidla pristupu a prehled clenu. */
(window.CoLinkParts ||= Object.create(null)).sprava_konverzaci=function(ctx){
  'use strict';
  const {$,state}=ctx;
  // Vazby mezi oblastmi se vyhodnoti az pri volani; vsechny pouzivaji jednu instanci stavu.
  const node=(...args)=>ctx.node(...args);
  const button=(...args)=>ctx.button(...args);
  const notice=(...args)=>ctx.notice(...args);
  const api=(...args)=>ctx.api(...args);
  const contacts=(...args)=>ctx.contacts(...args);
  const refresh=(...args)=>ctx.refresh(...args);
  const openChat=(...args)=>ctx.openChat(...args);
  const panel=(...args)=>ctx.panel(...args);
  const field=(...args)=>ctx.field(...args);
  const check=(...args)=>ctx.check(...args);
  const checklist=(...args)=>ctx.checklist(...args);
  const peopleFilters=(...args)=>ctx.peopleFilters(...args);
  const personMatches=(...args)=>ctx.personMatches(...args);

  // Skupina i kanal sdileji stejnou kompaktni kostru; odlisny zustava pouze vyznam voleb.
  function conversationFormPanel(title){const p=panel(title);p.className='conversation-form';return p;}
  // Oddeli obsah formulare do stejne vizualni sekce pro skupinu i kanal.
  function conversationFormSection(parent){const section=node('section',undefined,'conversation-form-section');parent.append(section);return section;}
  // Vyber prijemcu pamatuje zaskrtnuta jmena i pri kombinovani filtru skupin a pobocek.
  async function privateForm(){
    await contacts();
    const p=conversationFormPanel('Nová skupinová konverzace');
    const name=field(p,'Název skupiny (pro dva lidi není potřeba)','');
    const filters=peopleFilters(conversationFormSection(p),()=>update());
    const members=conversationFormSection(p);members.append(node('p','Vyberte kolegy','section-label'));
    const status=node('p',undefined,'recipient-summary'),list=node('div',undefined,'checks'),selection=node('p',undefined,'recipient-selection');
    const rows=state.people.filter(person=>Number(person.id)!==Number(state.me.id)).map(person=>{
      const input=check(list,person.jmeno);return {person,input,label:input.parentElement};
    });
    const empty=node('p','Tomuto výběru neodpovídá žádný kolega.','hint');list.append(empty);members.append(status,list,selection);
    const submit=button('Otevřít konverzaci',async()=>{const r=await api('soukroma',{lide:rows.filter(r=>r.input.checked).map(r=>Number(r.person.id)),nazev:name.value});$('panel').close();await refresh();await openChat(Number(r.id));},'primary');
    p.append(submit);
    // Uvnitr bloku plati OR; mezi skupinou a pobockou plati AND. Filtr nikdy nemeni vyber jmen.
    const update=()=>{
      const criteria=filters();
      let visible=0;
      for(const row of rows){const match=personMatches(row.person,criteria);row.label.hidden=!match;if(match)visible++;}
      const selected=rows.filter(r=>r.input.checked);empty.hidden=visible>0;status.textContent='Zobrazeno '+visible+' z '+rows.length+' · Vybráno '+selected.length;
      selection.textContent=selected.length?'Vybraní příjemci: '+selected.map(r=>r.person.jmeno).join(', '):'';selection.hidden=!selected.length;submit.disabled=!selected.length;
    };
    for(const row of rows)row.input.addEventListener('change',update);
    update();
  }
  // Oddeluje spravu pristupu do kanalu od prava cist jeho obsah.
  function channelForm(c=null){
    const p=conversationFormPanel(c?'Nastavení kanálu':'Nový kanál'),name=field(p,'Název kanálu',c?.nazev||'');name.maxLength=100;
    const access=conversationFormSection(p),all=check(access,'Všichni uživatelé Co-Linku',!!Number(c?.pristup_vsem||0));
    access.append(node('p','Přístup získá člověk splňující alespoň jeden výběr níže. Po přidání uvidí historii, po odebrání ztratí přístup k celému kanálu.','hint'));
    const people=checklist(conversationFormSection(p),'Vybraní jednotlivci',state.people,c?.lide||[]);
    const rules=node('div',undefined,'conversation-form-rules');p.append(rules);
    const slots=checklist(conversationFormSection(rules),'Sloty',state.slots,(c?.pravidla||[]).filter(r=>r.druh==='slot').map(r=>Number(r.hodnota))),functions=checklist(conversationFormSection(rules),'Funkce a pozice',state.functions,(c?.pravidla||[]).filter(r=>r.druh==='funkce').map(r=>Number(r.hodnota)));
    const archive=c?check(p,'Archivovaný kanál (jen čtení)',!!Number(c.archiv)):null;
    p.append(button('Uložit kanál',async()=>{const r=await api('kanal_ulozit',{id:Number(c?.id||0),nazev:name.value,vsichni:all.checked,lide:people(),sloty:slots(),funkce:functions(),archiv:!!archive?.checked});$('panel').close();await refresh();if(state.conversations.some(c=>Number(c.id)===Number(r.id)))await openChat(Number(r.id));else notice('Kanál byl uložen. Podle nastaveného přístupu nejste jeho členem.');},'primary'));
  }
  // Spravce vidi nastaveni kanalu bez automatickeho cteni jejich zprav.
  async function manageChannels(){const p=panel('Správa kanálů'),d=await api('kanaly');if(state.rights[701])p.append(button('＋ Nový kanál',()=>channelForm()));for(const c of d.kanaly){const row=node('div',undefined,'list-row');row.append(node('div',c.nazev+(Number(c.archiv)?' · archiv':'')),button('Nastavit',()=>channelForm(c)));p.append(row);}if(!d.kanaly.length)p.append(node('p','Žádné kanály ke správě.','hint'));}
  // Zobrazi aktualni cleny dostupne konverzace.
  async function channelInfo(){const p=panel('Členové konverzace'),d=await api('clenove',null,{konverzace:state.cid});p.append(node('p',d.lide.length+' členů','hint'));for(const person of d.lide)p.append(node('div',person.jmeno,'list-row'));}

  return {conversationFormPanel,conversationFormSection,privateForm,channelForm,manageChannels,channelInfo};
};
