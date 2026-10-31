/* Co-Link: komunikace od nejnovejsich zprav a psani s uchovanim rucne zvetsene vysky; dalsi skripty resi prihlaseni, editor a push. */
(() => {
  'use strict';
  const boot=JSON.parse(document.getElementById('co-boot').textContent);
  const $=id=>document.getElementById(id);
  const state={me:boot.identita,rights:{},conversations:[],people:[],slots:[],functions:[],messages:[],cid:0,thread:0,view:'home',search:'',staged:[],queue:[],sending:false,refreshing:false,windowEnd:0,more:false,readTimer:null,latestOnRender:false};
  let pollTimer=null,noticeTimer=null,panelCleanup=null,homeQrTimer=null,activeRefresh=null;
  // Rezim IS je pouze pro nas vlastni ramec stejneho puvodu, samostatna PWA ma vlastni navigaci.
  const embedded=(()=>{try{return new URL(location.href).searchParams.get('is')==='1'&&window.parent!==window&&window.parent.location.origin===location.origin;}catch(_){return false;}})();
  let shellList=null,shellSearch=null,shellDirectory=null;
  let composerEditor=null,searchOpen=false;
  // Pri navigaci uvnitr ramce zachovame jeho rezim, aby obnoveni nevratilo druhe menu.
  function appLocation(id=0){const url=new URL(location.pathname,location.origin);if(embedded)url.searchParams.set('is','1');if(id)url.searchParams.set('konverzace',id);return url;}

  // Vsechny texty od uzivatelu zustavaji textem; HTML sestavujeme jen z pevných sablon.
  // Vytvori textovy DOM uzel bez interpretace uzivatelskeho HTML.
  function node(tag,text,cls){const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;}
  // Fotografie z jiz opravnenych HR kontaktu sdili zpravy i adresar; chyba souboru ponecha inicialy.
  function personAvatar(name,photo,extra=''){
    const avatar=node('div',name.split(/\s+/).map(w=>w[0]).slice(0,2).join('').toUpperCase(),'avatar'+(extra?' '+extra:''));
    if(photo){const image=node('img');image.alt='';image.loading='lazy';image.style.opacity='0';image.addEventListener('load',()=>{avatar.replaceChildren(image);image.style.opacity='1';},{once:true});image.addEventListener('error',()=>image.remove(),{once:true});image.src=photo;avatar.append(image);}
    return avatar;
  }
  // Sjednoti obsluhu tlacitek a zobrazeni chyb.
  function button(text,fn,cls){const b=node('button',text,cls);b.type='button';b.addEventListener('click',()=>Promise.resolve().then(fn).catch(fail));return b;}
  // Prevede chybu akce na srozumitelnou zpravu.
  function fail(e){notice(e?.message||'Akci se nepodařilo dokončit.');}
  // Zobrazi docasnou zpravu bez blokovani prace.
  function notice(text){$('notice').textContent=text;$('notice').hidden=false;clearTimeout(noticeTimer);noticeTimer=setTimeout(()=>$('notice').hidden=true,7000);}
  // Zobrazi cas zpravy v ceskem formatu.
  function date(value){return value?new Date(value.replace(' ','T')).toLocaleString('cs-CZ',{day:'numeric',month:'numeric',hour:'2-digit',minute:'2-digit'}):'';}
  // Prevede velikost prilohy na citelne jednotky.
  function bytes(value){return Number(value)>=1048576?(Number(value)/1048576).toFixed(1)+' MB':Math.ceil(Number(value)/1024)+' kB';}
  // Najde prave otevrenou konverzaci.
  function current(){return state.conversations.find(c=>Number(c.id)===state.cid);}
  // Soukromy chat pojmenuje podle druheho ucastnika.
  function title(c){if(c.typ!=='soukroma')return c.nazev;const ids=(c.clenove||[]).filter(id=>Number(id)!==Number(state.me.id));return ids.map(id=>state.people.find(p=>Number(p.id)===Number(id))?.jmeno||'Uživatel').join(', ')||'Soukromá konverzace';}

  // Sitovy timeout neznamena odmitnuti serverem. Zprava si pri opakovani zachova stejne klient_id.
  async function api(action,data=null,query={}){
    const url=new URL('api.php',location.href);url.searchParams.set('akce',action);Object.entries(query).forEach(([k,v])=>url.searchParams.set(k,String(v)));
    const control=new AbortController();const timer=setTimeout(()=>control.abort(),30000);
    try{
      const headers={Accept:'application/json'};
      if(state.me?.csrf)headers['X-Co-Csrf']=state.me.csrf;
      if(action==='is_pripojit')headers['X-Comeback-Crf']=boot.is_token;
      const form=data instanceof FormData;
      if(data!==null&&!form)headers['Content-Type']='application/json';
      const response=await fetch(url,{method:data===null?'GET':'POST',credentials:'same-origin',headers,body:data===null?undefined:form?data:JSON.stringify(data),signal:control.signal,cache:'no-store'});
      let result;try{result=await response.json();}catch(_){throw new Error('Server odpověděl neplatně. Zkontrolujte připojení a velikost přílohy.');}
      if(!response.ok||!result.ok){const error=new Error(result.err||'Požadavek se nepodařilo dokončit.');error.status=response.status;throw error;}
      return result;
    }finally{clearTimeout(timer);}
  }

  // Oddeli lokalni data jednotlivych firemnich uctu.
  function storageKey(){return 'co-link:'+state.me.id;}
  // Obnovi jen cekajici zpravy prihlaseneho uzivatele.
  function restore(){try{const saved=JSON.parse(localStorage.getItem(storageKey())||'{}');state.queue=Array.isArray(saved.queue)?saved.queue:[];}catch(_){state.queue=[];}}
  // Uchova frontu i pri zavreni aplikace nebo vypadku.
  function persist(){try{localStorage.setItem(storageKey(),JSON.stringify({queue:state.queue}));}catch(_){notice('Telefon nemůže uložit čekající zprávy. Nezavírejte Co-Link, dokud server nepotvrdí odeslání.');}}
  // Rozlisi rozepsany text kanalu a vlakna.
  function draftKey(){return storageKey()+':draft:'+state.cid+':'+state.thread;}
  // Ulozi rozepsany text pred prechodem jinam.
  function saveDraft(){if(state.me&&state.cid){try{localStorage.setItem(draftKey(),composerEditor.value);}catch(_){/* Zpravu stale drzi otevrene pole. */}}}
  // Vrati rozepsany text konkretni konverzace.
  function loadDraft(){try{composerEditor.value=localStorage.getItem(draftKey())||'';}catch(_){composerEditor.clear();}CoEmoji.close();resizeComposer();}
  // Zapamatuje posledni viditelne misto v historii.
  function savePosition(){if(!state.me||!state.cid||state.search)return;const box=$('messages'),rect=box.getBoundingClientRect();const visible=[...box.querySelectorAll('.message[data-id]')].filter(el=>el.getBoundingClientRect().top<rect.bottom);const last=visible.at(-1);if(last)try{localStorage.setItem(storageKey()+':position:'+state.cid+':'+state.thread,JSON.stringify({id:Number(last.dataset.id),old:state.windowEnd}));}catch(_){/* Ulozene zpravy zustavaji na serveru. */}}
  // Obsah muze pole automaticky zvetsit; dalsi psani nesmi zrusit vysku, kterou si uzivatel nastavil rucne.
  function resizeComposer(){const t=$('message-text');t.style.height=Math.max(t.getBoundingClientRect().height,Math.min(t.scrollHeight,170))+'px';}

  // Neodeslane zpravy jsou viditelne a znovu se odesilaji jen pro aktualne prihlaseny ucet.
  function pending(){
    $('pending').replaceChildren();
    state.queue.filter(q=>Number(q.konverzace)===state.cid).forEach(q=>{
      const row=node('div',undefined,'pending-item'+(q.failed?' failed':''));row.append(node('span',(q.failed?'! Nepotvrzeno: ':'◷ Odesílá se: ')+(CoText.parse(q.text).text||'Příloha')));
      if(q.failed)row.append(button('Zkusit znovu',()=>{q.failed=false;persist();return flush();}));
      $('pending').append(row);
    });
  }
  // Odesila frontu postupne a odstrani polozku az po potvrzeni serveru.
  async function flush(){
    if(state.sending||!navigator.onLine||!state.me)return;
    state.sending=true;
    try{
      for(const q of [...state.queue]){
        if(q.failed)continue;
        try{await api('odeslat',q);state.queue=state.queue.filter(v=>v.klient_id!==q.klient_id);persist();pending();}
        catch(e){q.failed=true;persist();pending();notice(e.message);break;}
      }
    }finally{state.sending=false;await refresh().catch(fail);}
  }

  // Nacte povolene kontakty a ciselniky pro vyber pristupu.
  async function contacts(){const d=await api('lide');state.people=d.lide;state.slots=d.sloty;state.functions=d.funkce;state.branches=d.pobocky||[];}
  // V IS doplnujeme pouze seznam standardniho menu; profil, vzhled, odhlaseni i sirku resi spolecny shell.
  function shellNavigation(){
    if(!embedded)return null;
    const menu=window.parent.document.querySelector('.blok_menu[data-menu-module="co_link"]');
    const mount=menu?.querySelector(':scope > .blok_menu_list');if(!mount)return null;
    if(!shellList?.isConnected){
      mount.replaceChildren();
      const item=(label,action,target=mount)=>{const li=node('li'),b=button(label,()=>{menu.querySelector('[data-cb-menu-close]')?.click();return action();},'blok_menu_btn');li.append(b);target.append(li);return b;};
      shellDirectory=item('Adresář',directory);
      // Nastaveni patri ke sprave v zapati menu, nad odkaz na Administraci IS.
      const bottom=menu.querySelector('.blok_menu_bottom');
      if(bottom){bottom.querySelector('.co_link_menu_settings')?.remove();const settingsList=node('ul',undefined,'blok_menu_list co_link_menu_settings');bottom.prepend(settingsList);item('Nastavení Co-Linku',settings,settingsList);}
      const filterRow=node('li');shellSearch=node('input');shellSearch.type='search';shellSearch.placeholder='Najít konverzaci…';shellSearch.setAttribute('aria-label','Filtrovat konverzace');shellSearch.className='co_link_menu_filter';shellSearch.addEventListener('input',conversationList);filterRow.append(shellSearch);mount.append(filterRow);
      const listRow=node('li');shellList=node('ul',undefined,'blok_menu_list');listRow.append(shellList);mount.append(listRow);
    }
    shellDirectory.classList.toggle('is-active',state.view==='directory');
    // Obsah PP prebira aktualni barvy a font IS, vcetne uzivatelova nastaveni vzhledu.
    const theme=window.parent.getComputedStyle(menu);
    for(const [local,shared]of [['--ink','--cb-text-main'],['--muted','--cb-text-muted'],['--line','--cb-border-soft'],['--paper','--cb-bg-panel'],['--is-soft','--cb-bg-panel-soft'],['--is-control','--cb-bg-control']])document.documentElement.style.setProperty(local,theme.getPropertyValue(shared).trim());
    return {list:shellList,filter:shellSearch.value.toLocaleLowerCase('cs'),menu};
  }
  // Kanaly i soukrome chaty maji stejne kompaktni radky a aktivni stav jako ostatni moduly IS.
  function shellConversations(shell){
    const {list,filter,menu}=shell;list.replaceChildren();
    for(const [kind,label]of [['kanal','Kanály'],['soukroma','Soukromé zprávy'],['skupina','Skupiny']]){
      const group=node('li'),heading=node('div',undefined,'co_link_menu_heading');heading.append(node('span',label));
      if(kind!=='kanal'||state.rights[701]){const add=button('+',kind==='kanal'?()=>channelForm():kind==='soukroma'?directory:privateForm,'co_link_menu_add');add.setAttribute('aria-label',kind==='kanal'?'Vytvořit kanál':kind==='soukroma'?'Najít člověka v adresáři':'Vytvořit skupinu');heading.append(add);}
      group.append(heading);const rows=node('ul',undefined,'blok_menu_list');group.append(rows);list.append(group);
      const channels=state.conversations.filter(c=>c.typ===kind&&title(c).toLocaleLowerCase('cs').includes(filter));
      for(const c of channels){const li=node('li'),b=button('',()=>{menu.querySelector('[data-cb-menu-close]')?.click();return openChat(Number(c.id));},'blok_menu_btn'+(state.cid===Number(c.id)?' is-active':''));b.append(node('span',(kind==='kanal'?'# ':'')+title(c)+(Number(c.archiv)?' · archiv':''),'co_link_menu_name'));if(Number(c.neprecteno))b.append(node('span',c.neprecteno,'co_link_menu_count'));li.append(b);rows.append(li);}
      if(!channels.length){const li=node('li',filter?'Nic neodpovídá hledání.':kind==='kanal'?'Zatím žádné kanály.':kind==='soukroma'?'Kolegu najdete v adresáři.':'Zatím žádné skupiny.','co_link_menu_empty');rows.append(li);}
    }
  }
  // Sestavi dostupne kanaly a chaty s pocty neprectenych zprav.
  function conversationList(){
    const shell=shellNavigation();if(shell){shellConversations(shell);return;}
    const list=$('conversation-list');list.replaceChildren();const filter=$('filter').value.toLocaleLowerCase('cs');
    $('directory-open').classList.toggle('active',state.view==='directory');
    for(const [kind,label]of [['kanal','KANÁLY'],['soukroma','SOUKROMÉ ZPRÁVY'],['skupina','SKUPINY']]){
      const channels=state.conversations.filter(c=>c.typ===kind&&title(c).toLocaleLowerCase('cs').includes(filter));
      const heading=node('div',undefined,'list-heading');heading.append(node('h2',label,'list-title'));
      if(kind!=='kanal'||state.rights[701]){
        const add=button('＋',kind==='kanal'?()=>channelForm():kind==='soukroma'?directory:privateForm,'list-add');
        add.setAttribute('aria-label',kind==='kanal'?'Vytvořit kanál':kind==='soukroma'?'Najít člověka v adresáři':'Vytvořit skupinu');heading.append(add);
      }
      list.append(heading);
      channels.forEach(c=>{const b=button('',()=>openChat(Number(c.id)),'conversation'+(state.cid===Number(c.id)?' active':''));b.append(node('span',kind==='kanal'?'#':'○','symbol'),node('span',title(c)+(Number(c.archiv)?' · archiv':''),'label'));if(Number(c.neprecteno))b.append(node('span',c.neprecteno,'count'));list.append(b);});
      if(!channels.length)list.append(node('p',filter?'Nic neodpovídá hledání.':kind==='kanal'?'Zatím žádné kanály.':kind==='soukroma'?'Kolegu najdete v adresáři lidí.':'Zatím žádné skupiny.','list-empty'));
    }
  }

  // Pri otevreni chatu z adresare pockame i na uz bezici synchronizaci, aby stary seznam novy chat neodmitl.
  function refresh(){
    if(activeRefresh)return activeRefresh;
    activeRefresh=refreshNow().finally(()=>{activeRefresh=null;});return activeRefresh;
  }
  // Aktualizujeme pouze potrebne zmeny; stejne DOM zpravy neztrati rolovani ani vyber textu.
  async function refreshNow(){
    if(state.refreshing||!state.me||document.hidden)return;
    state.refreshing=true;
    try{
      const s=await api('stav');state.rights=s.prava;state.conversations=s.konverzace;conversationList();
      // PP aktualizuje indikaci hned pri synchronizaci; ostatni moduly ji ctou ze spolecne hlavicky.
      if(embedded)window.parent.CB_COLINK_HEADER?.setUnread(state.conversations.reduce((sum,c)=>sum+Number(c.neprecteno||0),0));
      $('connection').textContent='Připojeno';$('connection').classList.remove('offline');
      if(state.cid){
        if(!current()){$('messages').replaceChildren(node('p','Přístup do této konverzace byl ukončen.','hint'));$('composer').hidden=true;state.cid=0;return;}
        const requestedCid=state.cid,requestedThread=state.thread,requestedSearch=state.search,requestedEnd=state.windowEnd;
        const d=await api('zpravy',null,{konverzace:requestedCid,hledat:requestedSearch,okolo:requestedEnd,vlakno:requestedThread});
        // Odpoved rozbehnute synchronizace nesmi vratit predchozi chat, vlakno ani starsi historii.
        if(Number(d.kanal.id)!==state.cid||requestedThread!==state.thread||requestedSearch!==state.search||requestedEnd!==state.windowEnd)return;
        // Ulozenou vlastni zpravu lze potvrdit take zpetnym nactenim po ztrate HTTP odpovedi.
        const confirmed=new Set(d.zpravy.filter(m=>Number(m.autor)===Number(state.me.id)).map(m=>m.klient_id));
        const waiting=state.queue.length;state.queue=state.queue.filter(q=>!confirmed.has(q.klient_id));
        if(state.queue.length!==waiting){persist();pending();notice('Přijetí zprávy serverem je potvrzené.');}
        state.more=d.dalsi;
        const signature=JSON.stringify(d.zpravy);
        if(signature!==state.signature){state.messages=d.zpravy;state.signature=signature;renderMessages();}
        updateHeader();
        // Az po vykresleni a uprave hlavicky/psani zname skutecnou vysku prostoru pro zpravy.
        if(state.latestOnRender&&!state.search&&!state.windowEnd){$('messages').scrollTop=$('messages').scrollHeight;state.latestOnRender=false;}
        scheduleReads();
      }
    }catch(e){$('connection').textContent=navigator.onLine?'Spojení přerušeno':'Bez připojení';$('connection').classList.add('offline');if(e.status===401){clearTimeout(pollTimer);$('app').hidden=true;$('welcome').hidden=false;$('welcome-text').textContent=e.message;state.me=null;$('panel').close();}else if(e.status===403){state.cid=0;$('messages').replaceChildren(node('p',e.message,'hint'));$('composer').hidden=true;}}
    finally{state.refreshing=false;}
  }
  // Opakuje kratkou synchronizaci a zastavi ji pri konci prihlaseni.
  async function poll(){await refresh();if(state.me)pollTimer=setTimeout(poll,document.hidden?15000:3000);}
  // Sladi hlavicku a moznosti psani s otevrenou konverzaci.
  function updateHeader(){
    const c=current();if(!c)return;
    $('chat-name').textContent=(c.typ==='kanal'?'# ':'')+title(c);
    $('chat-subtitle').textContent=state.thread?'Odpovědi ve vlákně':Number(c.archiv)?'Archivovaná konverzace':c.typ==='kanal'?'Týmový kanál':'Soukromá komunikace';
    $('composer').hidden=!!Number(c.archiv);$('mute').hidden=false;$('chat-info').hidden=false;$('search-toggle').hidden=false;$('search-form').hidden=!searchOpen;
    $('search-toggle').setAttribute('aria-expanded',String(searchOpen));
    const muted=!!Number(c.ztiseno),muteText=(muted?'Zapnout':'Ztišit')+' oznámení z této konverzace na všech vašich zařízeních. Nepřečtené zprávy zůstanou označené. Ověření přihlášení se nemění.';
    $('mute-slash').setAttribute('visibility',muted?'visible':'hidden');$('mute').title=muteText;$('mute').setAttribute('aria-label',muteText);$('mute').setAttribute('aria-pressed',String(muted));
    $('thread-banner').hidden=!state.thread;
    if(state.thread)$('thread-banner').querySelector('span').textContent='Vlákno zprávy #'+state.thread;
  }
  // Prechod zachova koncept, ale vzdy nacte nejnovejsi zpravy misto drive ulozene historie.
  async function openChat(id){
    clearInterval(homeQrTimer);homeQrTimer=null;state.view='chat';$('app').classList.remove('show-home');$('messages').classList.remove('home-screen','directory-screen');$('messages').setAttribute('role','log');$('messages').setAttribute('aria-label','Zprávy');
    saveDraft();savePosition();state.cid=id;state.thread=0;state.search='';searchOpen=false;state.windowEnd=0;state.signature='';state.messages=[];state.staged=[];$('search').value='';$('app').classList.add('chat-open');
    state.latestOnRender=true;try{localStorage.setItem(storageKey()+':last-chat',String(id));}catch(_){}
    history.replaceState(null,'',appLocation(id));if(embedded)window.focus();loadDraft();renderStaged();renderMessages();updateHeader();pending();await refresh();
    // Pri prekryvu s predchozim pozadavkem dokoncime i nacteni nove otevrene konverzace.
    if(state.cid===id&&state.latestOnRender)await refresh();
  }
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
      if(mine&&!m.smazano)meta.append(node('span','Přečteno: '+(m.prvni_precteni?date(m.prvni_precteni):'NE'),'message-read-status'));
      const text=node('div',undefined,'message-text'+(m.smazano?' deleted':''));if(m.smazano)text.textContent='Zpráva byla smazána.';else textContent(text,m.text);body.append(text);
      const attachments=node('div',undefined,'attachments');for(const f of m.prilohy){const a=node('a','↗ '+f.nazev,'attachment');a.href='soubor.php?id='+f.id;a.append(node('small',bytes(f.velikost)));attachments.append(a);}body.append(attachments);
      if(!m.smazano){
        const tools=node('div',undefined,'message-tools');
        const archived=!!Number(current()?.archiv);
        // U vlastni zpravy zustavaji reakce ostatnich viditelne, ale nelze pridat vlastni reakci.
        for(const r of m.reakce){const b=button(r.emoji+' '+r.pocet,()=>react(m,r.emoji),'reaction'+(Number(r.moje)?' mine':''));b.disabled=archived||mine;tools.append(b);}
        if(!archived&&!mine)tools.append(button('Reagovat',()=>reactionPanel(m)));
        if(!m.rodic)tools.append(button(Number(m.odpovedi)?'Vlákno · '+m.odpovedi:'Vlákno',()=>openThread(Number(m.id))));
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
  // Sdili jeden pristupny dialog a ukoncuje jeho docasne casovace.
  function panel(title){if(panelCleanup)panelCleanup();panelCleanup=null;$('panel-title').textContent=title;$('panel-content').replaceChildren();$('panel-content').className='';if(!$('panel').open)$('panel').showModal();return $('panel-content');}
  // Skupina i kanal sdileji stejnou kompaktni kostru; odlisny zustava pouze vyznam voleb.
  function conversationFormPanel(title){const p=panel(title);p.className='conversation-form';return p;}
  function conversationFormSection(parent){const section=node('section',undefined,'conversation-form-section');parent.append(section);return section;}
  // Nabidne podporovane reakce ke zprave.
  function reactionPanel(m){const p=panel('Reakce');for(const e of ['👍','❤️','✅','😂','🙏','👀'])p.append(button(e,async()=>{await react(m,e);$('panel').close();}));}
  // Zobrazi zaznamenane cizi precteni a jeho cas.
  async function readers(m){const p=panel('Kdo zprávu přečetl');const d=await api('precteni',null,{zprava:m.id});if(!d.lide.length)p.append(node('p','Zprávu zatím nikdo další neotevřel.','hint'));for(const person of d.lide){const row=node('div',undefined,'list-row');row.append(node('span',person.jmeno),node('small',date(person.kdy)));p.append(row);}}
  // Odesle upravu s revizi pro kontrolu soubehu na serveru.
  function editMessage(m){const p=panel('Upravit zprávu'),host=node('div');p.append(host);const editor=CoEditor.create(host,{label:'Upravit text zprávy'});editor.value=m.text;p.append(node('p','Změna je možná jen do prvního přečtení jinou osobou.','hint'),button('Uložit',async()=>{await api('upravit',{zprava:Number(m.id),revize:Number(m.revize),text:editor.value});$('panel').close();state.signature='';await refresh();},'primary'));panelCleanup=()=>CoEmoji.close();}
  // Pred smazanim potvrdi konkretni zpravu a jeji prilohy.
  function deleteMessage(m){const p=panel('Smazat zprávu');p.append(node('p','Smazat tuto dosud nepřečtenou zprávu včetně jejích příloh?'),button('Smazat zprávu',async()=>{await api('smazat',{zprava:Number(m.id),revize:Number(m.revize)});$('panel').close();state.signature='';await refresh();},'primary'));}
  // Vytvori popsane pole nastavovaciho formulare.
  function field(parent,label,value,type='text'){const wrap=node('label',label,'field'),input=node('input');input.type=type;input.value=value;wrap.append(input);parent.append(wrap);return input;}
  // Vytvori dotykove dostupnou oznacenou volbu.
  function check(parent,label,checked=false){const wrap=node('label',undefined,'check'),input=node('input');input.type='checkbox';input.checked=checked;wrap.append(input,node('span',label));parent.append(wrap);return input;}
  // Sestavi vyber osob nebo pracovnich zarazeni podle jejich ID.
  function checklist(parent,label,items,selected=[]){parent.append(node('p',label,'section-label'));const list=node('div',undefined,'checks'),map=new Map();for(const item of items)map.set(Number(item.id),check(list,item.jmeno||item.nazev,selected.includes(Number(item.id))));parent.append(list);return ()=>[...map].filter(([,input])=>input.checked).map(([id])=>id);}
  // Stejne filtry pouziva adresar i vyber clenu skupiny; nikdy samy nevybiraji prijemce.
  function peopleFilters(parent,changed){
    parent.append(node('p','Skupina','section-label'));
    const groups=node('div',undefined,'recipient-filters'),groupInputs=new Map();
    for(const [key,label]of [['manager','Manager'],['vedouci','Ved. pobočky'],['kuryr','Kurýr'],['pizzar','Pizzař']])groupInputs.set(key,check(groups,label));
    const allGroups=check(groups,'Všichni',true);parent.append(groups,node('p','Pobočky','section-label'));
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
    saveDraft();savePosition();clearInterval(homeQrTimer);homeQrTimer=null;state.cid=0;state.thread=0;state.view='directory';
    $('app').classList.remove('show-home');$('app').classList.add('chat-open');
    $('messages').classList.remove('home-screen');$('messages').classList.add('directory-screen');$('messages').setAttribute('role','region');$('messages').setAttribute('aria-label','Adresář');
    for(const id of ['composer','search-form','search-toggle','thread-banner','chat-info','mute'])$(id).hidden=true;
    $('pending').replaceChildren();$('chat-name').textContent='Adresář';$('chat-subtitle').textContent='';
    history.replaceState(null,'',appLocation());conversationList();
    const area=$('messages');area.replaceChildren(node('p','Načítám kolegy…','hint'));
    await contacts();if(state.view!=='directory')return;
    area.replaceChildren();
    const controls=node('div',undefined,'directory-controls'),search=field(controls,'Hledat podle jména','','search');search.placeholder='Jméno kolegy…';search.autocomplete='off';
    const filters=peopleFilters(controls,()=>render());
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
        card.append(node('p',labels.join(' · ')||'Uživatel Co-Linku','person-role'));
        const branches=(person.pobocky||[]).map(id=>state.branches.find(b=>Number(b.id)===Number(id))?.nazev).filter(Boolean);
        if(branches.length)card.append(node('p',branches.join(', '),'person-branches'));
        if(Number(person.id)===Number(state.me.id))card.append(node('span','Váš účet','person-self'));
        // Ikona vedle jmena zachova stejnou akci bez dalsiho radku na karte.
        else{const send=button('',async()=>{send.disabled=true;try{await writePerson(person);}finally{send.disabled=false;}},'person-write');send.title='Napsat zprávu: '+person.jmeno;send.setAttribute('aria-label',send.title);send.innerHTML='<svg class="chat-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 5H5v15h15v-8M16 3l5 5-9 9-5 1 1-5Z"></path></svg>';heading.append(send);}
        cards.append(card);
      }
      if(!people.length)cards.append(node('p','Tomuto hledání a filtrům neodpovídá žádný kolega.','hint'));
    };
    search.addEventListener('input',render);render();
  }
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
  // Vlastni nastaveni linku neni administraci celeho IS.
  async function settings(){
    const p=panel('Nastavení Co-Linku');p.append(node('p',state.me.jmeno,'hint'));
    const actions=node('div',undefined,'panel-actions');actions.append(button('Přidat do mobilu',pair),button('Moje připojená zařízení',devices),button('Povolit upozornění na tomto zařízení',enablePush),button('Přidat Co-Link na plochu',install));
    if(state.rights[701]||state.rights[702])actions.append(button('Správa kanálů',manageChannels));
    if(state.rights[703])actions.append(button('Administrace Co-Linku',admin));
    actions.append(button('Odhlásit toto zařízení',logout));p.append(actions);
  }
  // Vykresli QR lokalne a odstrani jej po vyprseni platnosti.
  async function pair(){
    const p=panel('Přidat do mobilu');const d=await api('qr',{}),url=new URL(d.odkaz,location.origin).href;
    p.append(node('p','Na telefonu načtěte tento osobní QR kód. Připojí vás pod vaším firemním účtem. Nikomu ho nepředávejte.','hint'));
    const box=node('div',undefined,'qr');p.append(box);new QRCode(box,{text:url,width:240,height:240,correctLevel:QRCode.CorrectLevel.M});
    const count=node('p',undefined,'qr-countdown');p.append(count);let remaining=d.sekundy;
    const tick=()=>{count.textContent=remaining>0?'Platí ještě '+Math.floor(remaining/60)+':'+String(remaining%60).padStart(2,'0'):'Platnost skončila. Vytvořte nový QR kód.';if(remaining<=0)box.replaceChildren();remaining--;};tick();const timer=setInterval(tick,1000);panelCleanup=()=>clearInterval(timer);
    p.append(button('Vytvořit nový QR kód',pair));
  }
  // Uvitaci QR se po vyprseni sam obnovi, dokud uzivatel zustava na uvitaci plose.
  async function homeQr(){
    clearInterval(homeQrTimer);homeQrTimer=null;
    const box=$('home-qr'),status=$('home-qr-status');if(!box||!status||state.view!=='home')return;
    box.replaceChildren();status.textContent='Připravuji osobní QR kód…';
    try{
      const d=await api('qr',{});if(state.view!=='home'||!box.isConnected)return;
      const url=new URL(d.odkaz,location.origin).href;
      new QRCode(box,{text:url,width:168,height:168,correctLevel:QRCode.CorrectLevel.M});
      const expiry=Date.now()+Number(d.sekundy)*1000;
      const tick=()=>{const remaining=Math.max(0,Math.ceil((expiry-Date.now())/1000));status.textContent=remaining?'Platí ještě '+Math.floor(remaining/60)+':'+String(remaining%60).padStart(2,'0'):'Obnovuji osobní QR kód…';if(!remaining){clearInterval(homeQrTimer);homeQrTimer=null;if(state.view==='home'&&box.isConnected)homeQr();}};
      homeQrTimer=setInterval(tick,1000);tick();
    }catch(e){status.textContent=e.message;if(box.isConnected)box.replaceChildren(button('Zkusit znovu',homeQr));}
  }
  // Telefon otevre primo seznam konverzaci; uvitaci QR zustava jen na pocitaci pro pripojeni telefonu.
  async function home(){
    if(!embedded&&(/Android|iPhone|iPad|iPod/.test(navigator.userAgent)||matchMedia('(max-width:700px)').matches)){
      saveDraft();savePosition();clearInterval(homeQrTimer);homeQrTimer=null;
      state.cid=0;state.thread=0;state.messages=[];state.signature='';state.view='conversations';
      $('app').classList.remove('show-home','chat-open');
      $('messages').classList.remove('directory-screen','home-screen');$('messages').replaceChildren();
      for(const id of ['composer','search-form','search-toggle','thread-banner','chat-info','mute'])$(id).hidden=true;
      $('pending').replaceChildren();$('chat-name').textContent='Co-Link';$('chat-subtitle').textContent='';
      conversationList();return;
    }
    saveDraft();savePosition();state.cid=0;state.thread=0;state.messages=[];state.signature='';state.view='home';
    $('app').classList.add('show-home','chat-open');$('messages').classList.remove('directory-screen');$('messages').classList.add('home-screen');$('messages').setAttribute('role','region');$('messages').setAttribute('aria-label','Vítejte v Co-Linku');
    $('messages').replaceChildren($('home-template').content.cloneNode(true));
    $('composer').hidden=true;$('search-form').hidden=true;$('search-toggle').hidden=true;$('thread-banner').hidden=true;$('chat-info').hidden=true;$('mute').hidden=true;$('pending').replaceChildren();
    $('chat-name').textContent='Co-Link';$('chat-subtitle').textContent='Firemní komunikace Comeback';
    conversationList();await homeQr();
  }
  // Umoznuje odvolat vlastni pripojena zarizeni.
  async function devices(){const p=panel('Moje zařízení'),d=await api('zarizeni');for(const device of d.zarizeni){const row=node('div',undefined,'list-row'),details=node('div');details.append(node('strong',device.nazev+(Number(device.id)===Number(d.aktualni)?' · toto zařízení':'')),node('p',device.platnost_do?'Platí do '+date(device.platnost_do):'Připojeno do odhlášení zařízení'));row.append(details,button('Odhlásit',async()=>{await api('odvolat',{id:Number(device.id)});if(Number(device.id)===Number(d.aktualni))location.reload();else await devices();}));p.append(row);}}
  // Spravce linku meni pouze limit priloh; telefony nemaji pravidelne vyprseni prihlaseni.
  async function admin(){const p=panel('Administrace Co-Linku'),d=await api('nastaveni');const limit=field(p,'Maximální velikost jedné přílohy (MB)',d.nastaveni.priloha_mb,'number');limit.min=1;limit.max=2048;p.append(button('Uložit nastavení',async()=>{await api('nastaveni_ulozit',{priloha_mb:Number(limit.value)});notice('Nastavení bylo uloženo.');$('panel').close();},'primary'));}
  // Odvola toto zarizeni a odstrani jeho lokalni koncepty a frontu.
  async function logout(){await api('odhlasit',{});try{localStorage.removeItem(storageKey());for(const key of Object.keys(localStorage))if(key.startsWith(storageKey()+':'))localStorage.removeItem(key);}catch(_){}location.reload();}
  // Ukaze prilohy pripravene k dosud neodeslane zprave.
  function renderStaged(){const box=$('staged-files');box.replaceChildren();state.staged.forEach(f=>box.append(button(f.nazev+' ×',()=>{state.staged=state.staged.filter(x=>x.id!==f.id);renderStaged();})));}
  // Prenasi prilohy po malych blocich a overi limit nastaveny spravcem linku.
  async function upload(input=$('file')){
    const files=[...input.files];input.value='';if(state.staged.length+files.length>20)throw new Error('Ke zprávě lze přiložit nejvýše 20 souborů.');
    $('attach').disabled=true;
    try{const settings=await api('nastaveni');for(const file of files){
      if(file.size<1||file.size>Number(settings.nastaveni.priloha_mb)*1048576)throw new Error(file.name+' překračuje limit '+settings.nastaveni.priloha_mb+' MB.');
      const uploadId=Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');
      for(let offset=0;offset<file.size;offset+=1048576){
        notice('Nahrávám '+file.name+' · '+Math.floor(offset/file.size*100)+' %');
        const data=new FormData();data.set('soubor',file.slice(offset,offset+1048576),file.name);data.set('upload_id',uploadId);data.set('celkem',String(file.size));data.set('pozice',String(offset));data.set('nazev',file.name);
        const d=await api('nahrat',data);if(d.dokonceno){state.staged.push(d.priloha);renderStaged();}
      }
    }notice('Přílohy jsou připravené k odeslání.');}finally{$('attach').disabled=false;}
  }
  // Nabidne kolegy a do textu vlozi zminku s jejich stabilnim ID.
  function mentions(){const before=composerEditor.beforeCaret(),match=before.match(/(?:^|\s)@([^\s@\[\]]{0,40})$/u),box=$('mention-list');box.replaceChildren();box.hidden=!match;if(!match)return;const query=match[1].toLocaleLowerCase('cs');state.people.filter(p=>p.jmeno.toLocaleLowerCase('cs').includes(query)).slice(0,6).forEach(p=>box.append(button(p.jmeno,()=>{composerEditor.replaceBefore(match[1].length+1,'@['+p.jmeno+']('+p.id+') ');box.hidden=true;saveDraft();})));}
  // Po QR i z nastaveni otevirame jen nabidku; systemovou instalaci potvrzuje uzivatel tlacitkem.
  function install(){CoInstall.show(panel('Přidat na plochu'));}
  // Nabidne vlastni odber Co-Linku; obsluha tlacitka vyzada souhlas primo pri kliknuti.
  function pushSetup(host=panel('Oznámení Co-Linku')){CoPush.show(host,boot.vapid,data=>api('push',data));}
  function enablePush(){pushSetup();}
  // Po QR jsou oznameni viditelna vedle samostatne nabidky instalace, zadna vyzva se nespousti sama.
  function phoneSetup(){const host=panel('Připojení telefonu');pushSetup(host);CoInstall.show(host);}
  // Obnovi uzivatelske prostredi a posledni konverzaci.
  async function start(){
    $('welcome').hidden=true;$('app').hidden=false;$('my-name').textContent=state.me.jmeno;restore();await contacts();await refresh();
    const wanted=Number(new URL(location.href).searchParams.get('konverzace'));
    if(wanted&&state.conversations.some(c=>Number(c.id)===wanted))await openChat(wanted);else await home();
    await flush();poll();const url=new URL(location.href);if(url.searchParams.has('instalace')){url.searchParams.delete('instalace');history.replaceState(null,'',url);phoneSetup();}
  }
  // Propoji ovladani a zvoli vstup pres IS, existujici zarizeni nebo QR.
  async function init(){
    if(embedded)document.body.classList.add('is-embedded');
    composerEditor=CoEditor.create($('message-editor'),{id:'message-text',onChange:()=>{saveDraft();resizeComposer();mentions();},onMention:mentions});
    composerEditor.footer.prepend($('attach'));composerEditor.footer.append($('send-message'));
    CoPush.prepare().catch(()=>{});
    $('filter').addEventListener('input',conversationList);$('directory-open').addEventListener('click',()=>directory().catch(fail));$('settings-open').addEventListener('click',()=>settings().catch(fail));$('chat-info').addEventListener('click',()=>channelInfo().catch(fail));
    document.querySelector('.brand').addEventListener('click',e=>{e.preventDefault();history.replaceState(null,'',appLocation());home().catch(fail);});
    $('back').addEventListener('click',()=>{$('app').classList.remove('chat-open');});
    $('mute').addEventListener('click',()=>{const c=current();api('ztisit',{konverzace:state.cid,ztiseno:!Number(c.ztiseno)}).then(refresh).catch(fail);});
    $('thread-close').addEventListener('click',()=>{saveDraft();state.thread=0;state.windowEnd=0;state.signature='';state.latestOnRender=true;loadDraft();refresh();updateHeader();});
    $('messages').addEventListener('scroll',()=>{scheduleReads();savePosition();},{passive:true});document.addEventListener('visibilitychange',()=>{if(!document.hidden){refresh();scheduleReads();}else{saveDraft();savePosition();}});window.addEventListener('focus',scheduleReads);window.addEventListener('pagehide',()=>{saveDraft();savePosition();});
    $('message-text').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing&&matchMedia('(pointer:fine)').matches){e.preventDefault();$('composer').requestSubmit();}});
    $('composer').addEventListener('submit',e=>{e.preventDefault();const text=composerEditor.value;if(!composerEditor.text.trim()&&!state.staged.length)return;if(composerEditor.text.length>20000){notice('Zpráva může mít nejvýše 20 000 znaků.');return;}if($('attach').disabled){notice('Počkejte na dokončení nahrávání příloh.');return;}const id=crypto.randomUUID?crypto.randomUUID():Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');state.queue.push({klient_id:id,konverzace:state.cid,rodic:state.thread||null,text,prilohy:state.staged.map(f=>Number(f.id)),failed:false});persist();composerEditor.clear();CoEmoji.close();saveDraft();state.staged=[];renderStaged();resizeComposer();pending();flush();});
    $('attach').addEventListener('click',()=>{const p=panel('Přiložit ke zprávě');p.append(button('Vybrat fotografie nebo soubory',()=>{$('panel').close();$('file').click();}),button('Vyfotit na telefonu',()=>{$('panel').close();$('camera').click();}));});$('file').addEventListener('change',()=>upload($('file')).catch(fail));$('camera').addEventListener('change',()=>upload($('camera')).catch(fail));
    // Zavreni hledani odstrani i filtr, aby ve skrytem poli nezustal omezeny seznam zprav.
    const closeSearch=()=>{searchOpen=false;$('search').value='';state.search='';state.signature='';updateHeader();refresh();};
    $('search-toggle').addEventListener('click',()=>{if(searchOpen){closeSearch();return;}searchOpen=true;updateHeader();$('search').focus();});
    $('search-form').addEventListener('submit',e=>{e.preventDefault();state.search=$('search').value.trim();state.signature='';state.windowEnd=0;refresh();});$('clear-search').addEventListener('click',closeSearch);
    $('panel').addEventListener('close',()=>{if(panelCleanup)panelCleanup();panelCleanup=null;$('panel-content').className='';scheduleReads();});
    window.addEventListener('online',()=>{state.queue.forEach(q=>q.failed=false);persist();flush();});window.addEventListener('offline',()=>{$('connection').textContent='Bez připojení';$('connection').classList.add('offline');});
    const token=new URLSearchParams(location.hash.slice(1)).get('pripojit');
    if(token){history.replaceState(null,'',location.pathname);$('welcome').hidden=false;$('welcome-text').textContent='Připojuji vaše zařízení…';const r=await api('qr_pripojit',{token,nazev:/iPhone|iPad/.test(navigator.userAgent)?'iPhone / iPad':'Telefon'});state.me={csrf:r.csrf};location.replace('./?instalace=1');return;}
    if(state.me){await start();return;}
    $('welcome').hidden=false;
    if(!boot.pripraveno){$('welcome-text').textContent='Co-Link se připravuje. Jakmile bude spuštěný, najdete zde svou firemní komunikaci.';$('welcome-hint').textContent='';return;}
    // Start z ikony muze vynechat Strict cookie v prvnim HTML pozadavku. Overime ji z kontextu aplikace.
    $('welcome-hint').textContent='Ověřuji připojení zařízení…';
    const restored=await CoAuth.restore();if(restored){state.me=restored;await start();return;}
    if(boot.is_token){$('welcome-hint').textContent='Připojuji váš firemní účet…';await api('is_pripojit',{nazev:'Počítač'});location.reload();return;}
    $('welcome-hint').textContent='Na počítači otevřete Co-Link z přihlášeného IS. Telefon připojíte osobním QR kódem.';
  }
  init().catch(e=>{$('welcome').hidden=false;$('welcome-text').textContent=e.message;fail(e);});
})();
