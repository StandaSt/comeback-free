/* Co-Link: spusteni, navigace, API a synchronizace s vypinatelnym merenim cekani a objemu dat; casti sdileji jeden stav. */
(() => {
  'use strict';
  const parts=window.CoLinkParts ||= Object.create(null);
  // Druhe nacteni stare i nove vstupni adresy nesmi zdvojit obsluhy nebo casovace.
  if(parts.starting)return;
  parts.starting=true;
  const boot=JSON.parse(document.getElementById('co-boot').textContent);
  const $=id=>document.getElementById(id);
  const state={me:boot.identita,rights:{},conversations:[],people:[],slots:[],functions:[],messages:[],cid:0,thread:0,view:'home',search:'',staged:[],queue:[],sending:false,refreshing:false,windowEnd:0,more:false,readTimer:null,latestOnRender:false};
  // Rezim IS je pouze pro nas vlastni ramec stejneho puvodu, samostatna PWA ma vlastni navigaci.
  const embedded=(()=>{try{return new URL(location.href).searchParams.get('is')==='1'&&window.parent!==window&&window.parent.location.origin===location.origin;}catch(_){return false;}})();
  const ctx={boot,$,state,embedded,pollTimer:null,noticeTimer:null,panelCleanup:null,homeQrTimer:null,activeRefresh:null,shellList:null,shellSearch:null,shellDirectory:null,composerEditor:null,searchOpen:false};
  // Verzi menit spolecne pri zmene kterekoli casti; stare HTML pouzije tento aktualni seznam take.
  const partVersion='20261102-audit-1';
  const partNames=["rozhrani","zpravy","psani","adresar","sprava_konverzaci","nastaveni"];
  // Vstup zustava samostatne pouzitelny i pres puvodni app.js; casti se nactou pred jedinym init.
  async function loadParts(){
    await Promise.all(partNames.map(name=>new Promise((resolve,reject)=>{
      if(typeof parts[name]==='function'){resolve();return;}
      const script=document.createElement('script'),url=new URL('co_js/co_'+name+'.js',location.href);
      url.searchParams.set('v',partVersion);script.src=url.href;
      script.onload=()=>typeof parts[name]==='function'?resolve():reject(new Error('Nepodařilo se připravit část Co-Linku.'));
      script.onerror=()=>reject(new Error('Nepodařilo se načíst část Co-Linku. Zkontrolujte připojení.'));
      document.head.append(script);
    })));
    for(const name of partNames)Object.assign(ctx,parts[name](ctx));
    Object.assign(ctx,{appLocation,current,title,api,shellNavigation,shellConversations,conversationList,refresh,refreshNow,poll,updateHeader,openChat,home,start,init});
    await init();
  }
  // Vazby mezi oblastmi se vyhodnoti az pri volani; vsechny pouzivaji jednu instanci stavu.
  const node=(...args)=>ctx.node(...args);
  const button=(...args)=>ctx.button(...args);
  const fail=(...args)=>ctx.fail(...args);
  const notice=(...args)=>ctx.notice(...args);
  const storageKey=(...args)=>ctx.storageKey(...args);
  const restore=(...args)=>ctx.restore(...args);
  const persist=(...args)=>ctx.persist(...args);
  const saveDraft=(...args)=>ctx.saveDraft(...args);
  const loadDraft=(...args)=>ctx.loadDraft(...args);
  const savePosition=(...args)=>ctx.savePosition(...args);
  const pending=(...args)=>ctx.pending(...args);
  const flush=(...args)=>ctx.flush(...args);
  const contacts=(...args)=>ctx.contacts(...args);
  const renderMessages=(...args)=>ctx.renderMessages(...args);
  const scheduleReads=(...args)=>ctx.scheduleReads(...args);
  const panel=(...args)=>ctx.panel(...args);
  const directory=(...args)=>ctx.directory(...args);
  const privateForm=(...args)=>ctx.privateForm(...args);
  const channelForm=(...args)=>ctx.channelForm(...args);
  const channelInfo=(...args)=>ctx.channelInfo(...args);
  const settings=(...args)=>ctx.settings(...args);
  const homeQr=(...args)=>ctx.homeQr(...args);
  const renderStaged=(...args)=>ctx.renderStaged(...args);
  const phoneSetup=(...args)=>ctx.phoneSetup(...args);
  const bindComposer=(...args)=>ctx.bindComposer(...args);

  // Pri navigaci uvnitr ramce zachovame jeho rezim, aby obnoveni nevratilo druhe menu.
  function appLocation(id=0){const url=new URL(location.pathname,location.origin);if(embedded)url.searchParams.set('is','1');if(id)url.searchParams.set('konverzace',id);return url;}
  // Najde prave otevrenou konverzaci.
  function current(){return state.conversations.find(c=>Number(c.id)===state.cid);}
  // Soukromy chat pojmenuje podle druheho ucastnika.
  function title(c){if(c.typ!=='soukroma')return c.nazev;const ids=(c.clenove||[]).filter(id=>Number(id)!==Number(state.me.id));return ids.map(id=>state.people.find(p=>Number(p.id)===Number(id))?.jmeno||'Uživatel').join(', ')||'Soukromá konverzace';}
  // Sitovy timeout neznamena odmitnuti serverem. Zprava si pri opakovani zachova stejne klient_id.
  async function api(action,data=null,query={},diagnosticGroup=null){
    const url=new URL('api.php',location.href);url.searchParams.set('akce',action);Object.entries(query).forEach(([k,v])=>url.searchParams.set(k,String(v)));
    const control=new AbortController();const timer=setTimeout(()=>control.abort(),30000);
    window.CB_DIAG?.identity(state.me?.csrf);
    const ownDiagnosticGroup=!diagnosticGroup && ['odeslat','nahrat','upravit','smazat','reakce','ztisit'].includes(action)
      ?window.CB_DIAG?.action('co_link',action,{conversation:Number(data?.konverzace || 0)}):null;
    diagnosticGroup=diagnosticGroup || ownDiagnosticGroup;
    const measure=window.CB_DIAG?.request('co_link',action,url.href,{conversation:Number(query.konverzace || data?.konverzace || 0),search_chars:String(query.hledat || '').length,background:!diagnosticGroup && ['stav','zpravy'].includes(action)?1:0},diagnosticGroup);
    let measureStatus='error';
    try{
      const headers={Accept:'application/json',...measure?.headers};
      if(state.me?.csrf)headers['X-Co-Csrf']=state.me.csrf;
      if(action==='is_pripojit')headers['X-Comeback-Crf']=boot.is_token;
      const form=data instanceof FormData;
      if(data!==null&&!form)headers['Content-Type']='application/json';
      const response=await fetch(url,{method:data===null?'GET':'POST',credentials:'same-origin',headers,body:data===null?undefined:form?data:JSON.stringify(data),signal:control.signal,cache:'no-store'});
      window.CB_DIAG?.adopt(response,'co_link');measure?.received(response);
      let result;try{result=await response.json();}catch(_){throw new Error('Server odpověděl neplatně. Zkontrolujte připojení a velikost přílohy.');}
      if(!response.ok||!result.ok){const error=new Error(result.err||'Požadavek se nepodařilo dokončit.');error.status=response.status;throw error;}
      measureStatus='ok';return result;
    }catch(error){measureStatus=error.name==='AbortError'?'timeout':'error';throw error;}finally{clearTimeout(timer);measure?.end(measureStatus);ownDiagnosticGroup?.end(measureStatus);}
  }
  // V IS doplnujeme pouze seznam standardniho menu; profil, vzhled, odhlaseni i sirku resi spolecny shell.
  function shellNavigation(){
    if(!embedded)return null;
    const menu=window.parent.document.querySelector('.blok_menu[data-menu-module="co_link"]');
    const mount=menu?.querySelector(':scope > .blok_menu_list');if(!mount)return null;
    if(!ctx.shellList?.isConnected){
      mount.replaceChildren();
      const item=(label,action,target=mount)=>{const li=node('li'),b=button(label,()=>{menu.querySelector('[data-cb-menu-close]')?.click();return action();},'blok_menu_btn');li.append(b);target.append(li);return b;};
      ctx.shellDirectory=item('Adresář',directory);
      // Nastaveni patri ke sprave v zapati menu, nad odkaz na Administraci IS.
      const bottom=menu.querySelector('.blok_menu_bottom');
      if(bottom){bottom.querySelector('.co_link_menu_settings')?.remove();const settingsList=node('ul',undefined,'blok_menu_list co_link_menu_settings');bottom.prepend(settingsList);item('Nastavení Co-Linku',settings,settingsList);}
      const filterRow=node('li');ctx.shellSearch=node('input');ctx.shellSearch.type='search';ctx.shellSearch.placeholder='Najít konverzaci…';ctx.shellSearch.setAttribute('aria-label','Filtrovat konverzace');ctx.shellSearch.className='co_link_menu_filter';ctx.shellSearch.addEventListener('input',conversationList);filterRow.append(ctx.shellSearch);mount.append(filterRow);
      const listRow=node('li');ctx.shellList=node('ul',undefined,'blok_menu_list');listRow.append(ctx.shellList);mount.append(listRow);
    }
    ctx.shellDirectory.classList.toggle('is-active',state.view==='directory');
    // Obsah PP prebira aktualni barvy a font IS, vcetne uzivatelova nastaveni vzhledu.
    const theme=window.parent.getComputedStyle(menu);
    for(const [local,shared]of [['--ink','--cb-text-main'],['--muted','--cb-text-muted'],['--line','--cb-border-soft'],['--paper','--cb-bg-panel'],['--is-soft','--cb-bg-panel-soft'],['--is-control','--cb-bg-control']])document.documentElement.style.setProperty(local,theme.getPropertyValue(shared).trim());
    return {list:ctx.shellList,filter:ctx.shellSearch.value.toLocaleLowerCase('cs'),menu};
  }
  // Konverzace maji vlastni kompaktni radky; Adresar a sprava si ponechaji rozmery standardniho menu.
  function shellConversations(shell){
    const {list,filter,menu}=shell;list.replaceChildren();
    for(const [kind,label]of [['kanal','Kanály'],['soukroma','Soukromé zprávy'],['skupina','Skupiny']]){
      const group=node('li'),heading=node('div',undefined,'co_link_menu_heading');heading.append(node('span',label));
      if(kind!=='kanal'||state.rights[701]){const add=button('+',kind==='kanal'?()=>channelForm():kind==='soukroma'?directory:privateForm,'co_link_menu_add');add.setAttribute('aria-label',kind==='kanal'?'Vytvořit kanál':kind==='soukroma'?'Najít člověka v adresáři':'Vytvořit skupinu');heading.append(add);}
      group.append(heading);const rows=node('ul',undefined,'blok_menu_list co_link_menu_conversations');group.append(rows);list.append(group);
      const channels=state.conversations.filter(c=>c.typ===kind&&title(c).toLocaleLowerCase('cs').includes(filter));
      for(const c of channels){const li=node('li'),b=button('',()=>{menu.querySelector('[data-cb-menu-close]')?.click();return openChat(Number(c.id));},'blok_menu_btn co_link_menu_conversation'+(state.cid===Number(c.id)?' is-active':''));b.append(node('span',(kind==='kanal'?'# ':'')+title(c)+(Number(c.archiv)?' · archiv':''),'co_link_menu_name'));if(Number(c.neprecteno))b.append(node('span',c.neprecteno,'co_link_menu_count'));li.append(b);rows.append(li);}
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
  function refresh(diagnosticGroup=null){
    if(ctx.activeRefresh)return ctx.activeRefresh;
    ctx.activeRefresh=refreshNow(diagnosticGroup).finally(()=>{ctx.activeRefresh=null;});return ctx.activeRefresh;
  }
  // Aktualizujeme pouze potrebne zmeny; stejne DOM zpravy neztrati rolovani ani vyber textu.
  async function refreshNow(diagnosticGroup=null){
    if(state.refreshing||!state.me||document.hidden)return;
    state.refreshing=true;
    try{
      const s=await api('stav',null,{},diagnosticGroup);state.rights=s.prava;state.conversations=s.konverzace;conversationList();
      // PP aktualizuje indikaci hned pri synchronizaci; ostatni moduly ji ctou ze spolecne hlavicky.
      if(embedded)window.parent.CB_COLINK_HEADER?.setUnread(state.conversations.reduce((sum,c)=>sum+Number(c.neprecteno||0),0));
      $('connection').textContent='Připojeno';$('connection').classList.remove('offline');
      if(state.cid){
        if(!current()){$('messages').replaceChildren(node('p','Přístup do této konverzace byl ukončen.','hint'));$('composer').hidden=true;state.cid=0;return;}
        const requestedCid=state.cid,requestedThread=state.thread,requestedSearch=state.search,requestedEnd=state.windowEnd;
        const d=await api('zpravy',null,{konverzace:requestedCid,hledat:requestedSearch,okolo:requestedEnd,vlakno:requestedThread},diagnosticGroup);
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
    }catch(e){diagnosticGroup?.outcome('error');$('connection').textContent=navigator.onLine?'Spojení přerušeno':'Bez připojení';$('connection').classList.add('offline');if(e.status===401){clearTimeout(ctx.pollTimer);$('app').hidden=true;$('welcome').hidden=false;$('welcome-text').textContent=e.message;state.me=null;$('panel').close();}else if(e.status===403){state.cid=0;$('messages').replaceChildren(node('p',e.message,'hint'));$('composer').hidden=true;}}
    finally{state.refreshing=false;}
  }
  // Opakuje kratkou synchronizaci a zastavi ji pri konci prihlaseni.
  async function poll(){await refresh();if(state.me)ctx.pollTimer=setTimeout(poll,document.hidden?15000:3000);}
  // Sladi hlavicku a moznosti psani s otevrenou konverzaci.
  function updateHeader(){
    const c=current();if(!c)return;
    $('chat-name').textContent=(c.typ==='kanal'?'# ':'')+title(c);
    $('chat-subtitle').textContent=state.thread?'Odpovědi ve vlákně':Number(c.archiv)?'Archivovaná konverzace':c.typ==='kanal'?'Týmový kanál':'Soukromá komunikace';
    $('composer').hidden=!!Number(c.archiv);$('mute').hidden=false;$('chat-info').hidden=false;$('search-toggle').hidden=false;$('search-form').hidden=!ctx.searchOpen;
    $('search-toggle').setAttribute('aria-expanded',String(ctx.searchOpen));
    const muted=!!Number(c.ztiseno),muteText=(muted?'Zapnout':'Ztišit')+' oznámení z této konverzace na všech vašich zařízeních. Nepřečtené zprávy zůstanou označené. Ověření přihlášení se nemění.';
    $('mute-slash').setAttribute('visibility',muted?'visible':'hidden');$('mute').title=muteText;$('mute').setAttribute('aria-label',muteText);$('mute').setAttribute('aria-pressed',String(muted));
    $('thread-banner').hidden=!state.thread;
    if(state.thread)$('thread-banner').querySelector('span').textContent='Vlákno zprávy #'+state.thread;
  }
  // Prechod zachova koncept, ale vzdy nacte nejnovejsi zpravy misto drive ulozene historie.
  // Cela navigace ma vlastni ID; chyby refresh zustavaji obslouzene puvodnim rozhranim.
  async function openChat(id){
    const task=group=>openChatCore(id,group);
    return window.CB_DIAG?window.CB_DIAG.run('co_link','open_chat',{conversation:id},task):task(null);
  }
  async function openChatCore(id,diagnosticGroup=null){
    clearInterval(ctx.homeQrTimer);ctx.homeQrTimer=null;state.view='chat';$('app').classList.remove('show-home');$('messages').classList.remove('home-screen','directory-screen');$('messages').setAttribute('role','log');$('messages').setAttribute('aria-label','Zprávy');
    saveDraft();savePosition();state.cid=id;state.thread=0;state.search='';ctx.searchOpen=false;state.windowEnd=0;state.signature='';state.messages=[];state.staged=[];$('search').value='';$('app').classList.add('chat-open');
    state.latestOnRender=true;try{localStorage.setItem(storageKey()+':last-chat',String(id));}catch(_){}
    history.replaceState(null,'',appLocation(id));if(embedded)window.focus();loadDraft();renderStaged();renderMessages();updateHeader();pending();await refresh(diagnosticGroup);
    // Pri prekryvu s predchozim pozadavkem dokoncime i nacteni nove otevrene konverzace.
    if(state.cid===id&&state.latestOnRender)await refresh(diagnosticGroup);
  }
  // Telefon otevre primo seznam konverzaci; uvitaci QR zustava jen na pocitaci pro pripojeni telefonu.
  async function home(){
    if(!embedded&&(/Android|iPhone|iPad|iPod/.test(navigator.userAgent)||matchMedia('(max-width:700px)').matches)){
      saveDraft();savePosition();clearInterval(ctx.homeQrTimer);ctx.homeQrTimer=null;
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
  // Obnovi uzivatelske prostredi a posledni konverzaci.
  // Prvni priprava modulu ma vlastni cas; jednotlive API se zpracovavaji puvodnim poradim.
  async function start(){
    window.CB_DIAG?.identity(state.me?.csrf);
    const task=group=>startCore(group);
    return window.CB_DIAG?window.CB_DIAG.run('co_link','open_colink',{},task):task(null);
  }
  async function startCore(diagnosticGroup=null){
    $('welcome').hidden=true;$('app').hidden=false;$('my-name').textContent=state.me.jmeno;restore();await contacts();await refresh(diagnosticGroup);
    const wanted=Number(new URL(location.href).searchParams.get('konverzace'));
    if(wanted&&state.conversations.some(c=>Number(c.id)===wanted))await openChat(wanted);else await home();
    // První dokončené otevření zapisujeme jednou za instanci aplikace, nikdy při pollingu.
    if(!ctx.openAuditStarted){ctx.openAuditStarted=true;api('otevreno',{prostredi:embedded?'is':'pwa'}).catch(()=>{ctx.openAuditStarted=false;});}
    await flush();poll();const url=new URL(location.href);if(url.searchParams.has('instalace')){url.searchParams.delete('instalace');history.replaceState(null,'',url);phoneSetup();}
  }
  // Propoji ovladani a zvoli vstup pres IS, existujici zarizeni nebo QR.
  async function init(){
    if(embedded)document.body.classList.add('is-embedded');
    bindComposer();
    CoPush.prepare().catch(()=>{});
    $('filter').addEventListener('input',conversationList);$('directory-open').addEventListener('click',()=>directory().catch(fail));$('settings-open').addEventListener('click',()=>settings().catch(fail));$('chat-info').addEventListener('click',()=>channelInfo().catch(fail));
    document.querySelector('.brand').addEventListener('click',e=>{e.preventDefault();history.replaceState(null,'',appLocation());home().catch(fail);});
    $('back').addEventListener('click',()=>{$('app').classList.remove('chat-open');});
    $('mute').addEventListener('click',()=>{const c=current();api('ztisit',{konverzace:state.cid,ztiseno:!Number(c.ztiseno)}).then(refresh).catch(fail);});
    $('thread-close').addEventListener('click',()=>{saveDraft();state.thread=0;state.windowEnd=0;state.signature='';state.latestOnRender=true;loadDraft();refresh();updateHeader();});
    $('messages').addEventListener('scroll',()=>{scheduleReads();savePosition();},{passive:true});document.addEventListener('visibilitychange',()=>{if(!document.hidden){refresh();scheduleReads();}else{saveDraft();savePosition();}});window.addEventListener('focus',scheduleReads);window.addEventListener('pagehide',()=>{saveDraft();savePosition();});
    // Zavreni hledani odstrani i filtr, aby ve skrytem poli nezustal omezeny seznam zprav.
    const closeSearch=()=>{ctx.searchOpen=false;$('search').value='';state.search='';state.signature='';updateHeader();refresh();};
    $('search-toggle').addEventListener('click',()=>{if(ctx.searchOpen){closeSearch();return;}ctx.searchOpen=true;updateHeader();$('search').focus();});
    $('search-form').addEventListener('submit',e=>{e.preventDefault();state.search=$('search').value.trim();state.signature='';state.windowEnd=0;refresh();});$('clear-search').addEventListener('click',closeSearch);
    $('panel').addEventListener('close',()=>{if(ctx.panelCleanup)ctx.panelCleanup();ctx.panelCleanup=null;$('panel-content').className='';scheduleReads();});
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

  loadParts().catch(e=>{$('welcome').hidden=false;$('welcome-text').textContent=e.message;if(ctx.fail)ctx.fail(e);});
})();
