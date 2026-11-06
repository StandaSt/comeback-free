/* Co-Link: zobrazeni historie, vlakna, viditelna precteni a akce nad zpravami. */
(window.CoLinkParts ||= Object.create(null)).zpravy=function(ctx){
  'use strict';
  const {boot,$,state,embedded}=ctx;
  // Vazby mezi oblastmi se vyhodnoti az pri volani; vsechny pouzivaji jednu instanci stavu.
  const node=(...args)=>ctx.node(...args);
  const personAvatar=(...args)=>ctx.personAvatar(...args);
  const button=(...args)=>ctx.button(...args);
  const date=(...args)=>ctx.date(...args);
  const bytes=(...args)=>ctx.bytes(...args);
  const current=(...args)=>ctx.current(...args);
  const api=(...args)=>ctx.api(...args);
  const storageKey=(...args)=>ctx.storageKey(...args);
  const saveDraft=(...args)=>ctx.saveDraft(...args);
  const loadDraft=(...args)=>ctx.loadDraft(...args);
  const refresh=(...args)=>ctx.refresh(...args);
  const panel=(...args)=>ctx.panel(...args);

  // Zapamatuje posledni viditelne misto v historii.
  function savePosition(){if(!state.me||!state.cid||state.search)return;const box=$('messages'),rect=box.getBoundingClientRect();const visible=[...box.querySelectorAll('.message[data-id]')].filter(el=>el.getBoundingClientRect().top<rect.bottom);const last=visible.at(-1);if(last)try{localStorage.setItem(storageKey()+':position:'+state.cid+':'+state.thread,JSON.stringify({id:Number(last.dataset.id),old:state.windowEnd}));}catch(_){/* Ulozene zpravy zustavaji na serveru. */}}
  // Bezpecne odlisuje zminky a webove odkazy od bezneho textu.
  function inlineText(container,text){
    const parts=text.split(/(@\[[^\]\r\n]{1,100}\]\([0-9]+\)|https?:\/\/[^\s<>]+)/g);
    for(const part of parts){
      const mention=part.match(/^@\[([^\]]+)\]\(([0-9]+)\)$/);
      if(mention){container.append(node('span','@'+mention[1],'mention'));continue;}
      if(/^https?:\/\//.test(part)){const a=node('a',part);a.href=part;a.rel='noopener noreferrer';a.target='_blank';container.append(a);}else container.append(document.createTextNode(part));
    }
  }
  // Formatovani se vykresluje z povolenych metadat; HTML od uzivatele zustava obycejnym textem.
  function textContent(container,text){CoText.render(container,text,inlineText);}
  // Vykresli zpravy a povolene akce bez posunuti ctene historie.
  function renderMessages(){
    const box=$('messages'),atBottom=box.scrollHeight-box.scrollTop-box.clientHeight<100,oldTop=box.scrollTop;
    box.replaceChildren();
    if(state.more)box.append(button('Načíst starší zprávy',async()=>{const first=state.messages[0];if(!first)return;const d=await api('zpravy',null,{konverzace:state.cid,pred:first.id,hledat:state.search,vlakno:state.thread});state.windowEnd=d.zpravy.at(-1)?.id||state.windowEnd;state.messages=d.zpravy;state.more=d.dalsi;state.signature='';renderMessages();box.scrollTop=0;},'older'));
    if(state.windowEnd)box.append(button('Zpět k nejnovějším',async()=>{state.windowEnd=0;state.signature='';await refresh();box.scrollTop=box.scrollHeight;},'older'));
    const list=state.messages.filter(m=>state.search|| (state.thread?Number(m.rodic)===state.thread||Number(m.id)===state.thread:!m.rodic));
    if(!list.length)box.append(node('div',state.search?'Žádná zpráva neodpovídá hledání.':'Zatím žádné zprávy. Napište první.','empty'));
    for(const m of list){
      const mine=Number(m.autor)===Number(state.me.id);
      const article=node('article',undefined,'message'+(mine?' mine':''));article.dataset.id=m.id;article.dataset.revize=m.revize;
      article.append(personAvatar(m.jmeno,state.people.find(p=>Number(p.id)===Number(m.autor))?.foto));
      const body=node('div',undefined,'message-body'),meta=node('div',undefined,'message-meta');meta.append(node('strong',m.jmeno),node('time',date(m.vytvoreno)));if(m.upraveno)meta.append(node('span','upraveno','edited'));body.append(meta);
      // Prvni cizi precteni je videt primo u vlastni zpravy; podrobny seznam zustava dostupny pod ni.
      if(mine&&!m.smazano){const status=node('span','Přečteno: ','message-read-status');status.append(node('span',m.prvni_precteni?date(m.prvni_precteni):'NE',m.prvni_precteni?'':'message-unread'));meta.append(status);}
      const text=node('div',undefined,'message-text'+(m.smazano?' deleted':''));if(m.smazano)text.textContent='Zpráva byla smazána.';else textContent(text,m.text);body.append(text);
      // Priloha vede pres chraneny vstup; klient nikdy nedostava primou cestu do uloziste.
      const attachments=node('div',undefined,'attachments');for(const f of m.prilohy){const a=node('a','↗ '+f.nazev,'attachment');a.href='co_ajax/co_priloha.php?id='+f.id;a.append(node('small',bytes(f.velikost)));attachments.append(a);}body.append(attachments);
      if(!m.smazano){
        const tools=node('div',undefined,'message-tools');
        const archived=!!Number(current()?.archiv);
        // U vlastni zpravy zustavaji reakce ostatnich viditelne, ale nelze pridat vlastni reakci.
        for(const r of m.reakce){const b=button(r.emoji+' '+r.pocet,()=>react(m,r.emoji),'reaction'+(Number(r.moje)?' mine':''));b.disabled=archived||mine;tools.append(b);}
        if(!archived&&!mine)tools.append(button('Reagovat',()=>reactionPanel(m)));
        // Pocet odpovedi pochazi ze serveru; prazdne vlakno zustava bez zvyrazneni.
        if(!m.rodic)tools.append(button(Number(m.odpovedi)?'Vlákno · '+m.odpovedi:'Vlákno',()=>openThread(Number(m.id)),Number(m.odpovedi)>0?'has-replies':''));
        tools.append(button(Number(m.precteno_pocet)?'Přečetli '+m.precteno_pocet:'Přečtení',()=>readers(m)));
        if(m.lze_menit&&!archived){tools.append(button('Upravit',()=>editMessage(m)),button('Smazat',()=>deleteMessage(m)));}
        body.append(tools);
      }
      article.append(body);box.append(article);
    }
    box.scrollTop=atBottom?box.scrollHeight:oldTop;scheduleReads();
  }
  // Otevre samostatny koncept a nejnovejsi konec vlakna, i kdyz dobiha predchozi synchronizace.
  async function openThread(id){saveDraft();state.thread=id;state.windowEnd=0;state.signature='';state.latestOnRender=true;loadDraft();await refresh();if(state.thread===id&&state.latestOnRender)await refresh();}
  // Skutecne viditelne zpravy potvrzujeme po kratkem ustaleni, nikoli pri prijeti dat nebo push.
  function scheduleReads(){clearTimeout(state.readTimer);state.readTimer=setTimeout(markVisible,650);}
  // Potvrzuje jen zobrazenou revizi pri aktivnim okne bez modalniho prekryti.
  async function markVisible(){
    if(document.hidden||!document.hasFocus()||$('panel').open||!state.cid||!$('app').classList.contains('chat-open')&&matchMedia('(max-width:700px)').matches)return;
    const rect=$('messages').getBoundingClientRect(),items=[];
    // Dataset vraci text, API muze vratit cislo; sjednoceni typu umozni zpravu najit a potvrdit.
    document.querySelectorAll('.message[data-id]').forEach(el=>{const r=el.getBoundingClientRect();const m=state.messages.find(x=>Number(x.id)===Number(el.dataset.id));if(m&&Number(m.autor)!==Number(state.me.id)&&!Number(m.precteno_mnou)&&!m.smazano&&r.bottom>rect.top+12&&r.top<rect.bottom-12)items.push({id:Number(m.id),revize:Number(m.revize)});});
    if(items.length){try{await api('precteno',{konverzace:state.cid,zpravy:items});items.forEach(i=>{const m=state.messages.find(x=>Number(x.id)===i.id);if(m)m.precteno_mnou=1;});}catch(_){/* Pristi synchronizace potvrzeni bezpecne zopakuje. */}}
  }
  // Prepne vlastni emoji reakci a obnovi zpravu.
  async function react(m,emoji){await api('reakce',{zprava:Number(m.id),emoji});state.signature='';await refresh();}
  // Nabidne podporovane reakce ke zprave.
  function reactionPanel(m){const p=panel('Reakce');for(const e of ['👍','❤️','✅','😂','🙏','👀'])p.append(button(e,async()=>{await react(m,e);$('panel').close();}));}
  // Zobrazi zaznamenane cizi precteni a jeho cas.
  async function readers(m){const p=panel('Kdo zprávu přečetl');const d=await api('precteni',null,{zprava:m.id});if(!d.lide.length)p.append(node('p','Zprávu zatím nikdo další neotevřel.','hint'));for(const person of d.lide){const row=node('div',undefined,'list-row');row.append(node('span',person.jmeno),node('small',date(person.kdy)));p.append(row);}}
  // Odesle upravu s revizi pro kontrolu soubehu na serveru.
  function editMessage(m){const p=panel('Upravit zprávu'),host=node('div');p.append(host);const editor=CoEditor.create(host,{label:'Upravit text zprávy'});editor.value=m.text;p.append(node('p','Změna je možná jen do prvního přečtení jinou osobou.','hint'),button('Uložit',async()=>{await api('upravit',{zprava:Number(m.id),revize:Number(m.revize),text:editor.value});$('panel').close();state.signature='';await refresh();},'primary'));ctx.panelCleanup=()=>CoEmoji.close();}
  // Pred smazanim potvrdi konkretni zpravu a jeji prilohy.
  function deleteMessage(m){const p=panel('Smazat zprávu');p.append(node('p','Smazat tuto dosud nepřečtenou zprávu včetně jejích příloh?'),button('Smazat zprávu',async()=>{await api('smazat',{zprava:Number(m.id),revize:Number(m.revize)});$('panel').close();state.signature='';await refresh();},'primary'));}

  return {savePosition,inlineText,textContent,renderMessages,openThread,scheduleReads,markVisible,react,reactionPanel,readers,editMessage,deleteMessage};
};
