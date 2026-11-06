/* E-mail: MAIN vedoucich a volba treninku ve sdilenych schrankach; osobni pripojeni, sprava pristupu, validace vyberu a konkretni chybovy kontext bez obsahu posty. */
(function () {
  'use strict';
  let root = null, nav = null, state = null, account = 0, aggregate = false, folder = 'INBOX', folders = [], special = {};
  let messages = [], selected = null, page = 1, total = 0, compose = null, dirty = false, sending = false, generation = 0, busy = false;
  let controller = null, listSequence = 0, messageSequence = 0, accountSequence = 0, backgroundRunning = false;
  let folderOrder = [], orderDraft = [], orderReset = false, orderDragged = -1, settingsDirty = false;
  let editorModule = null, composeEditor = null, signatureEditor = null;
  let composeEditorTask = null, signatureEditorTask = null;
  let pendingMove = null, moving = false;
  let sharedAdmin=null,sharedAdminDirty=false,sharedAdminAccessDirty=false,sharedAdminBusy=false;
  let accessReportSequence=0;
  let personalConnections=null,pendingDisconnect=null;
  let attachmentUrl=null, attachmentSequence=0;
  let settingsTab='signature', accountOrderDraft=[], settingsSignatureDirty=false, settingsOptionsDirty=false, settingsOrderDirty=false, settingsFoldersDirty=false;
  let appliedSearch='', appliedBody=false, listLoading=false, listAbort=null;
  let pageSize=50, pagePreferenceOwner='', listCache=null;
  const viewCaches=new Map(), folderCaches=new Map();let cacheOwner='';
  let recipientInput=null, recipientItems=[], recipientActive=0, recipientSequence=0, recipientTimer=null;
  const recipientRefreshed=new Map();
  let noticeError=null;
  let activeOperations=0;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const node = selector => root && root.querySelector(selector);
  const ref = m => ({account:m.account, folder:m.folder, validity:m.validity, uid:m.uid});
  const address = values => (values || []).map(a => a.email).join(', ');
  const label = values => (values || []).map(a => a.name ? a.name + ' <' + a.email + '>' : a.email).join(', ');

  // XHR poskytuje skutecne bajty uploadu; odpoved dal prochazi stejnou autorizaci a diagnostikou jako fetch.
  function uploadRequest(url,options,progress) {
    return new Promise((resolve,reject)=>{
      const xhr=new XMLHttpRequest(),signal=options.signal;
      const cleanup=()=>signal?.removeEventListener('abort',abort);
      const abort=()=>xhr.abort();
      xhr.open(options.method || 'POST',url.href);xhr.withCredentials=true;
      for(const [key,value] of Object.entries(options.headers || {}))xhr.setRequestHeader(key,value);
      xhr.upload.onprogress=event=>progress(event.loaded,event.lengthComputable?event.total:null);
      xhr.onload=()=>{
        cleanup();
        try{
          if(!xhr.status)throw new Error('Spojení při nahrávání se přerušilo.');
          const headers=new Headers();
          for(const line of xhr.getAllResponseHeaders().trim().split(/[\r\n]+/).filter(Boolean)){
            const colon=line.indexOf(':');if(colon>0)headers.append(line.slice(0,colon),line.slice(colon+1).trim());
          }
          resolve(new Response([204,205,304].includes(xhr.status)?null:xhr.responseText,{status:xhr.status,statusText:xhr.statusText,headers}));
        }catch(e){reject(e);}
      };
      xhr.onerror=()=>{cleanup();reject(new Error('Spojení při nahrávání se přerušilo.'));};
      xhr.onabort=()=>{cleanup();reject(new DOMException('Nahrávání bylo přerušeno.','AbortError'));};
      if(signal?.aborted){reject(new DOMException('Změna modulu','AbortError'));return;}
      signal?.addEventListener('abort',abort,{once:true});
      try{xhr.send(options.body);}catch(e){cleanup();reject(e);}
    });
  }
  // Kazdy pozadavek nese identitu okna IS; zadne heslo se neuklada do URL ani localStorage.
  async function api(action, data = {}, write = false, files = null, requestSignal = null, diagnosticGroup = null, uploadProgress = null) {
    if (!root || !controller) throw new Error('Modul již není otevřený.');
    const ownGeneration = generation;
    const errorContext=['state','connect','personal_connections','shared_admin'].includes(action)
      ?{account:0,folder:'',scope:'none'}:mailErrorContext(Number(data.account || data.shared_id) || 0,data.folder);
    const url = new URL(root.dataset.api, location.href);
    url.searchParams.set('action', action);
    const options = {credentials:'same-origin', cache:'no-store', signal:requestSignal || controller.signal,
      headers:{'Accept':'application/json', 'X-Comeback-Pc-Session':String(window.CB_PC_SESSION_TOKEN || '')}};
    if (write) {
      options.method = 'POST'; options.headers['X-Comeback-Crf'] = String(window.CB_CRF_TOKEN || '');
      if (files) {
        const form = new FormData(); form.set('data', JSON.stringify(data));
        Array.from(files).forEach(file => form.append('attachments[]', file)); options.body = form;
      } else { options.headers['Content-Type']='application/json'; options.body=JSON.stringify(data); }
    } else Object.entries(data).forEach(([key,value]) => url.searchParams.set(key, String(value)));
    // Puvodni telo i AbortSignal zpracovava klient beze zmen; diagnostika cte pouze casy a hlavicky.
    const ownDiagnosticGroup=!diagnosticGroup && ['send','draft','move','flag','claim','done','release','folder_create','folder_rename','folder_delete','unlock','logout'].includes(action)
      ?window.CB_DIAG?.action('email',action,data):null;
    diagnosticGroup=diagnosticGroup || ownDiagnosticGroup;
    const measure=window.CB_DIAG?.request('email',action,url.href,{...data,search_chars:String(data.search || '').length},diagnosticGroup);
    if(measure)Object.assign(options.headers,measure.headers);
    let measureStatus='error',measureBytes=null,responseStatus=0;
    try {
      const response = uploadProgress?await uploadRequest(url,options,uploadProgress):await fetch(url,options);
      responseStatus=response.status;
      window.CB_DIAG?.adopt(response,'email');measure?.received(response);
      if (generation !== ownGeneration) throw new DOMException('Změna modulu', 'AbortError');
      if ([401,403,409].includes(response.status)) {
        viewCaches.clear();folderCaches.clear();listCache=null;
        messages=[]; selected=null; compose=null; dirty=false;
        clearAttachment();clearMailFrames(root);
        settingsDirty=false; composeEditor?.setHtml(''); signatureEditor?.setHtml('');
        root.querySelectorAll('dialog').forEach(d => {d.querySelector('form')?.reset(); if (d.open) d.close();});
        node('[data-em-reader]').replaceChildren(); node('[data-em-list]').replaceChildren();
        if (nav) nav.replaceChildren();
        root.querySelectorAll('button,input,select,textarea').forEach(el=>{el.disabled=true;}); state=null;
      }
      if (['attachment','original'].includes(action) && response.ok) {
        const blob=await response.blob();measureBytes=blob.size;measureStatus='ok';
        return {blob,officeUrl:response.headers.get('X-Comeback-Office-Url'),officeScheme:response.headers.get('X-Comeback-Office-Scheme'),officeLabel:response.headers.get('X-Comeback-Office-Label')};
      }
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) {
        const error=new Error(payload.error || payload.err || 'Server IS nevrátil platnou odpověď na poštovní operaci.');
        error.emPublic=true;error.emDetail=payload.error_detail || '';error.emLogged=payload.error_logged ?? !!payload.error_detail;
        error.emKind='response';throw error;
      }
      if(write)updateCachedMail(action,data,payload);
      if(write && payload.unread)applyUnreadCounts(payload.unread);
      measureStatus='ok';return payload;
    } catch(error) {
      measureStatus=error.name==='AbortError'?'abort':'error';
      if(error.name==='AbortError')throw error;
      if(!error.emPublic){
        if(responseStatus){error.emKind=['TypeError','ReferenceError','SyntaxError'].includes(error.name)?error.name:'Error';Object.defineProperty(error,'message',{value:'Při zpracování odpovědi pošty nastala chyba aplikace v prohlížeči.',configurable:true});}
        else {error=new Error('Spojení mezi prohlížečem a IS se přerušilo. Výsledek operace není potvrzený.');error.emKind='network';}
        error.emPublic=true;
      }
      error.emStatus=responseStatus;error.emContext=errorContext;showError(error,action);throw error;
    }
    finally {measure?.end(measureStatus,measureBytes);ownDiagnosticGroup?.end(measureStatus);}
  }

  // Jednotny detail patri i do otevreneho formulare; technicka odpoved se nikdy neinterpretuje jako HTML.
  function errorContent(target,error,prefix='') {
    if(!target)return;target.textContent=prefix+error.message;
    // Potvrzeni nezobrazime, dokud klient nebo server nevi, ze se zaznam podarilo ulozit.
    if(error.emLogged || error.emDetail?.includes('Záznam chyby byl uložen.')){const info=document.createElement('div');info.className='em-error-info';info.textContent='Není třeba posílat adminovi printscreen chyby. Vše je logováno.';target.append(info);}
    if(error.emDetail){const detail=document.createElement('details'),summary=document.createElement('summary'),text=document.createElement('pre');summary.textContent='Detail chyby';text.textContent=error.emDetail;detail.append(summary,text);target.append(detail);}
  }
  // Bezna potvrzeni zustavaji skryta; vyjimkou je vysledek pripojeni cizi osobni schranky bez zmeny vlastni posty.
  function notice(message, error = false, confirmation = false) {
    const n = node('[data-em-notice]'); if (!n) return;
    noticeError=message instanceof Error?message:null;
    if(noticeError)errorContent(n,noticeError);else n.textContent=message;
    n.hidden=!error && !confirmation;n.classList.toggle('is-error',error);
    root.classList.toggle('em-has-error',!!error || confirmation);n.setAttribute('role',error?'alert':'status');n.setAttribute('aria-live',error?'assertive':'polite');
  }
  // Identita plochy se zachyti pred cekanim; souhrn nesmi predstirat jednu konkretni schranku.
  function mailErrorContext(id=0,path=null) {
    const chosen=id>0?id:aggregate?0:account;
    return {account:chosen,folder:chosen>0?String(path ?? folder):'',scope:chosen>0?'account':aggregate?'aggregate':'none'};
  }
  // Klientske chyby hlasime jednou bez obsahu vyjimky, URL, formulare nebo zasobniku. Selhani hlaseni nespousti dalsi hlaseni.
  function reportClientError(error,operation) {
    if(error.emReporting || error.emLogged || !root || !controller || operation==='client_error')return;
    error.emReporting=true;
    const url=new URL(root.dataset.api,location.href);url.searchParams.set('action','client_error');
    const ownGeneration=generation,abort=new AbortController(),timer=setTimeout(()=>abort.abort(),5000);
    const line=Number(/email\.js(?:\?[^\s:)]*)?:(\d+):\d+/.exec(error.stack || '')?.[1] || 0);
    const kind=error.emKind || (['TypeError','ReferenceError','SyntaxError'].includes(error.name)?error.name:'Error');
    error.emDetail=error.emDetail || 'Operace: '+operation+'\nTyp: '+kind+(line?'\nMísto: email.js:'+line:'')+'\nPokus o uložení záznamu chyby…';
    fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',signal:abort.signal,headers:{'Accept':'application/json','Content-Type':'application/json','X-Comeback-Pc-Session':String(window.CB_PC_SESSION_TOKEN || ''),'X-Comeback-Crf':String(window.CB_CRF_TOKEN || '')},body:JSON.stringify({kind,operation,line,status:error.emStatus || 0,...(error.emContext || mailErrorContext())})})
      .then(response=>response.json()).then(result=>{if(!result.ok || !result.error_detail)throw new Error();error.emLogged=result.error_logged ?? true;error.emDetail=result.error_detail;})
      .catch(()=>{error.emDetail='Operace: '+operation+'\nTyp: '+kind+(error.emStatus?'\nHTTP: '+error.emStatus:'')+'\nZáznam chyby se nepodařilo potvrdit na serveru.';})
      .finally(()=>{clearTimeout(timer);if(generation===ownGeneration && noticeError===error)notice(error,true);});
  }
  // Programatorske vyjimky prelozime; ocekavane zpravy aplikace a serveru zachovame.
  function showError(error,operation='client') {
    if(!error.emPublic && ['TypeError','ReferenceError','SyntaxError'].includes(error.name)){Object.defineProperty(error,'message',{value:'Při práci s poštou nastala chyba aplikace v prohlížeči.',configurable:true});error.emPublic=true;}
    reportClientError(error,operation);notice(error,true);
  }
  // Zamek patri cele operaci vcetne navazujiciho prekresleni, ne pouze jednomu HTTP pozadavku.
  // Vnorene nacitani obrazku ma vlastniho drzitele; vnejsi operace mu zamek neuvolni predcasne.
  async function run(task,operation=null) {
    const pageBusy=window.CB_PAGE_BUSY;let handle=null;
    const errorContext=mailErrorContext();
    activeOperations++;
    try {
      if(root?.isConnected && pageBusy)handle=pageBusy.start('Pracuji s poštou…','Počkejte na dokončení akce.');
      await task();
    } catch (e) { if (e.name !== 'AbortError') {e.emContext??=errorContext;showError(e,operation || task.name || 'client');} }
    finally {activeOperations--;if(handle!==null)pageBusy.stop(handle);}
  }
  // Posledni sit pro chyby naseho skriptu mimo run(); cizi moduly ani obsah prijateho e-mailu nesbirame.
  window.addEventListener('error',event=>{
    if(root && /\/email\/email\.js(?:\?|$)/.test(event.filename || '') && event.error instanceof Error)showError(event.error,'client');
  });
  window.addEventListener('unhandledrejection',event=>{
    if(root && event.reason instanceof Error && /\/email\/email\.js(?:\?[^\s:]*)?:\d+/.test(event.reason.stack || ''))showError(event.reason,'client');
  });
  function currentAccount() { return (state?.accounts || []).find(a => a.id===account); }
  // Pamet nikdy nesdilime mezi dvema loginy. Pri mutaci zrusime jen prehledy dotcenych schranek.
  function invalidateMailCache(ids = null) {
    for(const [key,cache] of viewCaches)if(!ids || cache.sources.some(s=>ids.includes(s.account.id)))viewCaches.delete(key);
    for(const [key,entry] of folderCaches)if(!ids || ids.includes(entry.account))folderCaches.delete(key);
  }
  // Lokalne provedene precteni/oznaceni promítneme i do jiz navstivenych prehledu bez stahovani hlavicek.
  function updateCachedMail(action,data,payload) {
    if(['read','flag','claim','release','done','renew'].includes(action)) {
      const seen=new Set();let previousSeen=action==='read'?(messages.find(m=>m.account===Number(data.account) && m.uid===Number(data.uid) && m.folder===data.folder)?.seen??null):null;
      for(const [key,cache] of viewCaches) {
        if(action==='read' && cache.unseen && cache.sources.some(s=>s.account.id===Number(data.account))){viewCaches.delete(key);continue;}
        const rows=[...Array.from(cache.pages.values()).flatMap(p=>p.messages),...Array.from(cache.rows?.values() || [])];
        for(const m of rows)if(!seen.has(m) && m.account===Number(data.account) && m.uid===Number(data.uid) && m.folder===data.folder && m.validity===Number(data.validity)) {
          seen.add(m);if(action==='read'){previousSeen??=m.seen;m.seen=!!data.value;}else if(action==='flag')m.flagged=!!data.value;else if(payload.work)m.work=payload.work;
        }
      }
      if(action==='read' && previousSeen!==null && previousSeen!==!!data.value) {
        for(const entry of folderCaches.values())if(entry.account===Number(data.account)){const f=entry.data.folders.find(f=>f.path===data.folder);if(f)f.unseen=Math.max(0,f.unseen+(data.value?-1:1));}
        const a=state?.accounts.find(a=>a.id===Number(data.account));
        if(data.folder==='INBOX' && typeof a?.unread==='number')applyUnreadCounts({[a.id]:Math.max(0,a.unread+(data.value?-1:1))});
        else folderNavigation();
      }
    } else if(['move','delete','send','draft','repair_sent','folder_create','folder_rename','folder_delete','connect','disconnect','personal_disconnect','logout','unlock','shared_active','shared_access','shared_password','shared_remove'].includes(action))invalidateMailCache(data.account?[Number(data.account)]:null);
  }
  // Prubezne ziskame jen priznaky a pracovni stav viditelnych radku; stare hlavicky a struktury zustanou v pameti.
  async function refreshVisibleStates() {
    const seq=listSequence,groups=new Map(),shown=messages.slice();
    for(const m of shown){const key=m.account+':'+m.folder+':'+m.validity;if(!groups.has(key))groups.set(key,[]);groups.get(key).push(m);}
    const tasks=[];for(const rows of groups.values())for(let offset=0;offset<rows.length;offset+=50)tasks.push(rows.slice(offset,offset+50));
    let next=0,hasNew=false;
    const worker=async()=>{
      while(next<tasks.length && state && root?.isConnected && seq===listSequence) {
        const rows=tasks[next++],first=rows[0];
        try {
          const result=await api('row_status',{account:first.account,folder:first.folder,validity:first.validity,uids:rows.map(m=>m.uid).join(',')});
          if(seq!==listSequence || !root?.isConnected)return;
          const snapshot=aggregate?listCache?.sources.find(s=>s.account.id===first.account)?.snapshot:listCache?.snapshot;
          if(snapshot?.max_uid && result.uidnext>snapshot.max_uid+1)hasNew=true;
          for(const fresh of result.rows) {
            for(const cache of viewCaches.values()) {
              const cached=[...Array.from(cache.pages.values()).flatMap(p=>p.messages),...Array.from(cache.rows?.values() || [])];
              for(const m of cached)if(m.account===fresh.account && m.uid===fresh.uid && m.folder===fresh.folder && m.validity===fresh.validity)Object.assign(m,fresh);
            }
          }
        }catch { /* Docasny vypadek nesmaze jiz nactenou postu; prava nadale hlida API. */ }
      }
    };
    await Promise.all(Array.from({length:Math.min(2,tasks.length)},worker));
    if(root?.isConnected && state && seq===listSequence){renderList();if(hasNew)notice('Máte nové zprávy. Načtěte je tlačítkem Obnovit.');}
  }
  function workText(work) {
    return ({nove:'Nové',resi:'Řeší ' + (work?.jmeno || 'uživatel'),odpovezeno:'Odpovězeno',vyrizeno:'Vyřízeno bez odpovědi'})[work?.stav] || 'Nové';
  }

  // Ve vyberu firemnich schranek setrime misto domenou; skutecna adresa uctu zustava zachovana.
  function mailboxSelectName(a) {
    return String(a.name || a.email || '').replace(/@pizzacomeback\.cz$/i,'');
  }
  // Sidebar pouziva bezne menu a zapati IS; nevytvari druhou prazdnou navigaci vedle klienta.
  function navigation() {
    if (!nav) return;
    window.CB_COLUMN_SELECT?.destroy(nav.querySelector('[data-em-accounts]'));
    const accounts = state.accounts || [];
    let options = '';
    const personal = accounts.filter(a => a.type==='osobni'), shared = orderedAccounts();
    if (personal.length) options += '<optgroup label="----- Moje pošta -----">' + personal.map(a => `<option value="${a.id}"${!aggregate && account===a.id?' selected':''}>${esc(mailboxSelectName(a))}</option>`).join('') + '</optgroup>';
    if (shared.length) options += '<optgroup label="----- Společná pošta -----">' + shared.map(a => `<option value="${a.id===0?'all':a.id}"${a.id===0?aggregate?' selected':'':!aggregate && account===a.id?' selected':''}>${esc(mailboxSelectName(a))}</option>`).join('') + '</optgroup>';
    if(shared.length)options+='<option disabled></option><option value="settings_order">Upravit pořadí schránek…</option>';
    nav.innerHTML = `<button class="em-new" data-em-action="new"${!accounts.length?' disabled':''}>＋ Napsat</button>
      <select aria-label="Výběr schránky" data-em-accounts${!accounts.length?' disabled':''}>${options || '<option>Žádná připojená schránka</option>'}</select>
      <div class="em-folders" data-em-folders></div>
      <div class="em-folder-actions"><button data-em-action="folder_create">＋ Složka</button><button data-em-action="folder_rename">Přejmenovat</button><button data-em-action="folder_delete">Smazat</button></div><div class="em-settings-gap" aria-hidden="true"></div><button data-em-action="signature">Podpis a nastavení</button><hr>
      ${state.manager || state.manage_shared || personal.length ? '<button data-em-action="connect">Připojení a ověřování</button>' : ''}
      ${state.manage_shared ? '<button data-em-action="manage_shared">Správa sdílených schránek</button>' : ''}`;
    renderMailboxCounts();folderNavigation();
  }
  // Pocty predame spolecnemu vyberu jako druhy sloupec; hodnota selectu a jeho change zustavaji puvodni.
  function renderMailboxCounts() {
    const select=nav?.querySelector('[data-em-accounts]');if(!select || !state)return;
    const shared=state.accounts.filter(a=>a.type!=='osobni');
    const sharedCount=shared.every(a=>typeof a.unread==='number')?shared.reduce((sum,a)=>sum+a.unread,0):null;
    const countText=count=>typeof count==='number'?count.toLocaleString('cs-CZ'):'—';
    for(const option of select.options) {
      const a=state.accounts.find(a=>a.id===Number(option.value));
      const name=option.value==='all'?'Pobočky a info':a?mailboxSelectName(a):null;
      if(name===null)continue;
      const count=countText(option.value==='all'?sharedCount:a.unread);
      option.textContent=name;option.dataset.cbSelectDetail=count;
    }
    window.CB_COLUMN_SELECT?.enhance(select);
  }
  // Stejna obnova upravi pocet uctu i INBOX v jiz zapamatovanem menu, bez znovunacitani zprav.
  function applyUnreadCounts(counts) {
    if(!state)return;
    for(const [id,value] of Object.entries(counts || {})) {
      const a=state.accounts.find(a=>a.id===Number(id));if(!a || !a.unlocked || !Number.isInteger(value) || value<0)continue;a.unread=value;
      for(const entry of folderCaches.values())if(entry.account===a.id){const inbox=entry.data.folders.find(f=>f.path==='INBOX');if(inbox)inbox.unseen=value;}
    }
    renderMailboxCounts();folderNavigation();
  }
  function folderNavigation() {
    const n = nav?.querySelector('[data-em-folders]'); if (!n) return;
    const names = {INBOX:'Příchozí pošta', [special.sent]:'Odeslané', [special.drafts]:'Rozepsané', [special.trash]:'Koš', [special.junk]:'Spam'};
    n.innerHTML = (aggregate ? [{path:'INBOX',name:'Příchozí pošta',selectable:true}] : orderedFolders()).map(f => `<button data-em-folder="${esc(f.path)}"${!f.selectable?' disabled':''} class="${folder===f.path?'is-active':''}"><span>▱ ${esc(names[f.path] || f.name)}</span>${f.unseen?'<b>'+Number(f.unseen)+'</b>':''}</button>`).join('');
    nav.querySelectorAll('.em-folder-actions button').forEach(b => { b.disabled=aggregate || !account; });
  }

  // Vychozi rad zvyrazni dorucenou postu; obe historicke varianty Kose zustanou az za vlastnimi slozkami.
  function orderedFolders(order = folderOrder) {
    const rank=f=>{
      const name=f.name.toLocaleLowerCase('cs-CZ');
      if (f.path==='INBOX') return 0;
      if (f.path===special.trash || ['koš','trash'].includes(name)) return 9;
      if (f.path===special.drafts || ['rozepsané','drafts'].includes(name)) return 1;
      if (f.path===special.sent || ['odeslané','sent'].includes(name)) return 2;
      if (f.path===special.junk || ['spam','junk'].includes(name)) return 3;
      return 4;
    };
    const compare=(a,b)=>rank(a)-rank(b) || a.name.localeCompare(b.name,'cs',{numeric:true,sensitivity:'base'}) || a.path.localeCompare(b.path);
    if (!order.length) return [...folders].sort(compare);
    const indexes=new Map(order.map((path,index)=>[path,index]));
    return [...folders].sort((a,b)=>(indexes.get(a.path)??Number.MAX_SAFE_INTEGER)-(indexes.get(b.path)??Number.MAX_SAFE_INTEGER) || compare(a,b));
  }

  // Editor se nacte az pri psani nebo nastaveni podpisu; ostatni moduly nemusi stahovat knihovnu Quill.
  async function richEditor(type) {
    const ownGeneration=generation;
    editorModule??=import(root.dataset.editorSrc);
    const module=await editorModule;
    if (ownGeneration!==generation) throw new DOMException('Změna modulu','AbortError');
    if (type==='compose') {
      composeEditorTask??=module.createEditor(node('[data-em-compose-editor]'),'Zpráva',()=>{dirty=true;renderComposeFiles();},{validate:validateEditorImages,error:message=>{const error=node('[data-em-compose-form] .em-form-error');if(error)error.textContent=message;}});
      const editor=await composeEditorTask;
      if (ownGeneration!==generation) throw new DOMException('Změna modulu','AbortError');
      composeEditor=editor;
      return composeEditor;
    }
    signatureEditorTask??=module.createEditor(node('[data-em-signature-editor]'),'Podpis',()=>{settingsDirty=true;settingsSignatureDirty=true;});
    const editor=await signatureEditorTask;
    if (ownGeneration!==generation) throw new DOMException('Změna modulu','AbortError');
    signatureEditor=editor;
    return signatureEditor;
  }

  // Ulozene ID urcuji poradi jen dostupnych sdilenych uctu; nove ucty se pripoji na konec.
  function orderedAccounts() {
    const ranks=new Map((state?.preferences?.account_order || []).map((id,i)=>[id,i]));
    if(!ranks.has(0))ranks.set(0,-1); // Starsi ulozene poradi zachova puvodni souhrn nahore.
    const shared=(state?.accounts || []).filter(a=>a.type!=='osobni');
    if(state?.shared && shared.length)shared.unshift({id:0,email:'Pobočky a info'});
    return shared.sort((a,b)=>(ranks.get(a.id)??Infinity)-(ranks.get(b.id)??Infinity));
  }
  async function signatureDialog(tab='signature') {
    accountOrderDraft=orderedAccounts();settingsSignatureDirty=false;settingsOptionsDirty=false;settingsOrderDirty=false;settingsFoldersDirty=false;
    orderDraft=aggregate?[]:orderedFolders();orderReset=false;renderOrder();
    const canOrder=canOrderFolders();
    node('[data-em-folder-order-hint]').textContent=canOrder?'Schránka: '+currentAccount().email+'. Složky přetáhněte nebo posouvejte šipkami. Pořadí je vaše osobní.':'Pro pořadí složek nejprve otevřete konkrétní odemčenou schránku v nabídce pošty.';
    node('[data-em-action=order_reset]').disabled=!canOrder || !state.preferences?.ready;
    const form=node('[data-em-signature-form]');
    form.elements.show_images.value=state.preferences?.show_images?'1':'0';
    form.elements.confirm_delete.value=state.preferences?.confirm_delete===false?'0':'1';
    form.querySelectorAll('select').forEach(el=>{el.disabled=!state.preferences?.settings_ready;});
    renderAccountOrder();settingsDirty=false;
    const dialog=node('[data-em-signature]');dialog.showModal();await settingsTabSelect(tab);
  }
  // Zalozky sdileji rozepsane hodnoty; editor podpisu se nacte az pri prvni navsteve zalozky.
  async function settingsTabSelect(tab) {
    settingsTab=tab;const dialog=node('[data-em-signature]');
    dialog.querySelectorAll('[data-em-settings-tab]').forEach(el=>{const active=el.dataset.emSettingsTab===tab;el.setAttribute('aria-selected',String(active));el.tabIndex=active?0:-1;});
    dialog.querySelectorAll('[data-em-settings-panel]').forEach(el=>{el.hidden=el.dataset.emSettingsPanel!==tab;});
    const ready=tab==='signature'?state.preferences?.ready:tab==='folders'?state.preferences?.ready && canOrderFolders():state.preferences?.settings_ready;
    dialog.querySelector('[type=submit]').disabled=!ready;
    dialog.querySelector('.em-form-error').textContent=ready || (tab==='folders' && !canOrderFolders())?'':(['signature','folders'].includes(tab)?'Osobní nastavení zatím není připravené k ukládání.':'Nová nastavení čekají na spuštění SQL pro osobní nastavení pošty.');
    if(tab==='signature') {
      const editor=await richEditor('signature');
      if(!settingsSignatureDirty)editor.setHtml(state.preferences?.signature || '');
      editor.enable(!!ready);
    }
  }
  // Sipky maji dostatecnou dotykovou plochu a nevyzaduji presne pretahovani na telefonu.
  function renderAccountOrder(focus=-1,direction='down') {
    node('[data-em-account-order]').innerHTML=accountOrderDraft.length?accountOrderDraft.map((a,i)=>`<div class="em-account-order-row"><span>${esc(a.email)}</span><button type="button" data-em-account-up="${i}" aria-label="Posunout ${esc(a.email)} nahoru"${i===0 || !state.preferences?.settings_ready?' disabled':''}>↑</button><button type="button" data-em-account-down="${i}" aria-label="Posunout ${esc(a.email)} dolů"${i===accountOrderDraft.length-1 || !state.preferences?.settings_ready?' disabled':''}>↓</button></div>`).join(''):'<p>Žádné dostupné společné schránky.</p>';
    if(focus>=0){const row=node('[data-em-account-order]').children[focus];(row?.querySelector('[data-em-account-'+direction+']:not(:disabled)') || row?.querySelector('button:not(:disabled)'))?.focus();}
  }
  function moveAccountOrder(from,to,direction) {
    if(!state.preferences?.settings_ready || to<0 || to>=accountOrderDraft.length)return;
    accountOrderDraft.splice(to,0,accountOrderDraft.splice(from,1)[0]);settingsDirty=true;settingsOrderDirty=true;renderAccountOrder(to,direction);
  }
  // Dilci preference ulozime bez prepisu podpisu nebo poradi; aktualizace UI nastane az po uspechu.
  async function savePreferences(values) {
    const result=await api('save_preferences',values,true);state.preferences=result.preferences;
    navigation();return result;
  }

  // Poradi slozek patri konkretni odemcene schrance, souhrn zadne vlastni slozky nema.
  function canOrderFolders() {return !aggregate && !!account && !!currentAccount()?.unlocked && folders.length>0;}
  function renderOrder(focusIndex = -1) {
    const names={INBOX:'Příchozí pošta',[special.sent]:'Odeslané',[special.drafts]:'Rozepsané',[special.junk]:'Spam',[special.trash]:'Koš'};
    node('[data-em-order-list]').innerHTML=orderDraft.map((f,i)=>`<div class="em-order-row" draggable="${!!state.preferences?.ready}" data-em-order-index="${i}"><span aria-hidden="true">⠿</span><span>${esc(names[f.path] || f.name)}</span><button type="button" data-em-order-up="${i}" aria-label="Posunout ${esc(names[f.path] || f.name)} nahoru"${i===0 || !state.preferences?.ready?' disabled':''}>↑</button><button type="button" data-em-order-down="${i}" aria-label="Posunout ${esc(names[f.path] || f.name)} dolů"${i===orderDraft.length-1 || !state.preferences?.ready?' disabled':''}>↓</button></div>`).join('');
    if (focusIndex>=0) node(`[data-em-order-index="${focusIndex}"] button:not(:disabled)`)?.focus();
  }
  function moveOrder(from,to) {
    if (from<0 || to<0 || from>=orderDraft.length || to>=orderDraft.length || from===to) return;
    orderDraft.splice(to,0,orderDraft.splice(from,1)[0]); orderReset=false; settingsDirty=true; settingsFoldersDirty=true; renderOrder(to);
  }

  // Prvni otevreni zahrnuje stav, slozky i seznam; deti sdileji ID cele operace.
  async function loadState(diagnosticGroup = null, offerConnection = true) {
    const task=group=>loadStateCore(group,offerConnection);
    return window.CB_DIAG?window.CB_DIAG.run('email','open_email',{},task,diagnosticGroup):task(null);
  }
  async function loadStateCore(diagnosticGroup = null, offerConnection = true) {
    state = await api('state',{},false,null,null,diagnosticGroup);
    if(cacheOwner!==state.owner){viewCaches.clear();folderCaches.clear();cacheOwner=state.owner;}
    loadPageSize();
    if (state.installed === false || state.configured === false) {
      const error=new Error(state.message || 'Modul čeká na nastavení.');error.emDetail=state.error_detail || '';notice(error,true);
      if (nav) nav.innerHTML='<p>Schránky budou dostupné po dokončení nastavení modulu.</p>';
      return;
    }
    if (!state.shared) aggregate=false;
    if (!state.accounts.some(a => a.id===account)) account=state.accounts[0]?.id || 0;
    navigation();
    if (!account) {
      // Po vypnuti posledniho pripojeni nesmi zustat zobrazeny predchozi seznam nebo obsah zpravy.
      messages=[]; selected=null; total=0; renderList();
      node('[data-em-reader]').innerHTML='<div class="em-empty">Žádná dostupná schránka.</div>';
      root.querySelectorAll('[data-em-toolbar] button').forEach(b=>{b.disabled=true;});
      notice(state.manage_shared ? 'Zatím nemáte schránku dostupnou ke čtení. Společná připojení můžete nastavit ve správě.' : (state.manager ? 'Připojte svou firemní schránku.' : 'Zatím nemáte dostupnou připojenou schránku.'));
      // Po vedomem odpojeni nenutime uzivatele ihned znovu pripojovat schranku.
      if (offerConnection) {if (state.manage_shared) await connectDialog('shared'); else if (state.manager) await connectDialog();}
      return;
    }
    await loadAccount(diagnosticGroup);
  }
  // Zmena schranky zrusi stare hledani jeste pred nacitanim jejiho seznamu slozek.
  // Vypinatelne mereni cele operace zachova puvodni vysledek i chybu.
  async function loadAccount(diagnosticGroup = null) {
    const sequence=accountSequence+1,ownGeneration=generation;
    // Po chybe slozek nesmi prazdny seznam zustat oznaceny jako stale probihajici nacitani.
    const task=async group=>{try{return await loadAccountCore(group);}catch(e){
      // Chybejici seznam po opusteni plochy nesmi prepsat puvodni pricinu dalsim TypeError.
      const list=node('[data-em-list]');
      if(e.name!=='AbortError' && sequence===accountSequence && generation===ownGeneration && root?.isConnected && list){list.innerHTML='<div class="em-empty">Schránku se nepodařilo načíst. Příčina je uvedena v chybovém hlášení.</div>';}
      throw e;
    }};
    return window.CB_DIAG?window.CB_DIAG.run('email','loadaccount',{account,aggregate:aggregate?1:0},task,diagnosticGroup):task(null);
  }
  async function loadAccountCore(diagnosticGroup = null) {
    const ownSequence=++accountSequence, ownAccount=account;
    listAbort?.abort();listLoading=false;
    listSequence++; messageSequence++;
    clearMailFrames(root);selected=null; messages=[]; folders=[]; folderOrder=[]; root.classList.remove('em-has-message','em-reader-max');
    // Stare radky nesmi byt klikatelne, kdyz uz jejich data nepatri aktualni schrance.
    total=0;node('[data-em-list]').innerHTML='<div class="em-empty">Načítám schránku…</div>';node('[data-em-list]').setAttribute('aria-busy','false');pageControls();folderNavigation();
    node('[data-em-reader]').innerHTML='<div class="em-empty">Vyberte zprávu ze seznamu.</div>';
    root.querySelectorAll('[data-em-toolbar] button').forEach(b => { b.disabled=true; });
    if (!currentAccount()?.unlocked) { node('[data-em-list]').innerHTML='<div class="em-empty">Schránka je zamčená.</div>'; unlockDialog(); return; }
    notice('Načítám složky…');
    if (!aggregate) {
      const folderKey=JSON.stringify([state.owner,ownAccount]);let result=folderCaches.get(folderKey)?.data;
      if(!result){result=await api('folders',{account:ownAccount},false,null,null,diagnosticGroup);if(ownSequence===accountSequence){folderCaches.set(folderKey,{account:ownAccount,data:result});const inbox=result.folders.find(f=>f.path==='INBOX');if(inbox)applyUnreadCounts({[ownAccount]:inbox.unseen});}}
      if (ownSequence!==accountSequence) {diagnosticGroup?.outcome('superseded');return;}
      folders=result.folders; special=result.special; folderOrder=result.order || [];
      if (!folders.some(f => f.path===folder)) folder='INBOX';
    }
    if (ownSequence!==accountSequence) {diagnosticGroup?.outcome('superseded');return;}
    folderNavigation(); await loadList(diagnosticGroup,false,true);
  }
  // Rozpracovany text neni filtr: vyhledavani potvrzuje pouze submit (Enter nebo lupa).
  function submitSearch() {
    const form=node('[data-em-search]');appliedSearch=form.elements.search.value.trim();appliedBody=form.elements.body.checked;page=1;
    searchIndicator();
    selected=null;messageSequence++;root.classList.remove('em-has-message');
    node('[data-em-reader]').innerHTML='<div class="em-empty">Vyberte zprávu ze seznamu.</div>';
    root.querySelectorAll('[data-em-toolbar] button').forEach(button=>{button.disabled=true;});
    return loadList();
  }
  // Aktivni filtr zustava viditelny i po otevreni zpravy, kdy se horni stavova hlaska zmeni.
  function searchIndicator() {
    const indicator=node('[data-em-search-active]');if(!indicator)return;
    searchClearButton();
    indicator.hidden=!appliedSearch;
    node('[data-em-search-term]').textContent='Hledání: „'+appliedSearch+'“'+(appliedBody?' · i v obsahu':'');
  }
  // Cerveny krizek se nabidne i pro rozepsany, dosud nepotvrzeny text.
  function searchClearButton() {
    const button=node('[data-em-search-clear]');if(button)button.hidden=!node('[data-em-search]').elements.search.value;
  }
  // Zruseni textoveho hledani ihned obnovi seznam; filtr Neprectene zustava podle sveho checkboxu.
  function clearSearch() {
    node('[data-em-search]').elements.search.value='';return submitSearch();
  }
  // Souhrn dela nejvyse dva samostatne dotazy soucasne; pomala schranka nesmaze uspesne vysledky ostatnich.
  // Vypinatelne mereni cele operace zachova puvodni vysledek i chybu.
  async function loadList(diagnosticGroup = null, background = false, reuse = false) {
    const task=group=>loadListCore(group,background,reuse);
    return window.CB_DIAG?window.CB_DIAG.run('email','loadlist',{account,page,aggregate:aggregate?1:0,search_chars:appliedSearch.length,body:appliedBody?1:0,background:background?1:0},task,diagnosticGroup):task(null);
  }
  // Lokalni uloziste pamatuje pouze velikost stranky daneho uzivatele, nikoli postu nebo filtr.
  function loadPageSize() {
    const owner=String(state?.owner || '').split(':')[0];
    if(owner!==pagePreferenceOwner){pagePreferenceOwner=owner;pageSize=50;try{const saved=Number(localStorage.getItem('cb.email.page_size.'+owner));if([25,50,100].includes(saved))pageSize=saved;}catch{}}
    node('[data-em-page-size]').value=String(pageSize);
  }
  // Shodne datum rozhodne ucet a UID vzestupne, v ramci schranky stejne jako serverovy SORT.
  function compareMessages(a,b) {return (Date.parse(b.date)||0)-(Date.parse(a.date)||0) || a.account-b.account || a.uid-b.uid;}
  // Paticka odlisuje zobrazeny rozsah od celkoveho poctu shod na serveru.
  function pageControls() {
    const start=messages.length?(page-1)*pageSize+1:0,end=messages.length?start+messages.length-1:0;
    node('[data-em-page]').textContent=(start?start+'–'+end:'0')+' z '+total.toLocaleString('cs-CZ')+' '+(appliedSearch?'výsledků':'zpráv');
    node('[data-em-action="prev"]').disabled=listLoading || page<=1;
    node('[data-em-action="next"]').disabled=listLoading || page*pageSize>=total;
    const select=node('[data-em-action="select"]');if(select)select.disabled=listLoading || messages.length===0;
    syncBulkActions();
  }
  // Hromadna akce vyzaduje zaskrtnuty aktualni radek; pouhe otevreni zpravy vyber nevytvari.
  function syncBulkActions() {
    const hasSelection=!!root && [...root.querySelectorAll('[data-em-check]:checked')].some(c=>!!messages[Number(c.dataset.emCheck)]);
    root?.querySelectorAll('[data-em-action="bulk_move"],[data-em-action="bulk_trash"]').forEach(b=>{b.disabled=listLoading || !hasSelection;});
  }
  // Pamet patri jednomu prehledu; obnoveni nebo zmena dat ji zrusi. Sdilene proudy se slucuji postupne.
  async function loadListCore(diagnosticGroup = null, background = false, reuse = false) {
    if (!account || !state) return;
    const seq=++listSequence;listAbort?.abort();const abort=new AbortController();listAbort=abort;listLoading=true;
    const shared=aggregate,targetPage=page,size=pageSize,search=appliedSearch,body=appliedBody;
    const accounts=shared?state.accounts.filter(a=>a.type!=='osobni'):[currentAccount()];
    const request={background:background?1:0,folder:shared?'INBOX':folder,search,body:body?1:0,unseen:node('[data-em-search]').elements.unseen.checked?1:0,paged:1};
    const key=JSON.stringify([state.owner,accounts.map(a=>a.id),shared,request.folder,search,body,request.unseen]);
    const cache=reuse && viewCaches.has(key)?viewCaches.get(key):{key,unseen:request.unseen,merged:[],rows:new Map(),pages:new Map(),snapshot:null,total:0,errors:new Map(),sources:accounts.map(a=>({account:a,items:[],position:0,nextPage:1,total:null,snapshot:null,done:false}))};
    viewCaches.set(key,cache);
    cache.errorDetails ||= new Map();
    listCache=cache;messages=[];total=cache.total;renderList();node('[data-em-list]').setAttribute('aria-busy','true');
    node('[data-em-page]').textContent='…';notice(search?'Hledám zprávy…':'Načítám zprávy…');
    const active=()=>!abort.signal.aborted && seq===listSequence;
    // Casovy limit i zruseni patri pouze tomuto cteni, odesilani se nemeni.
    const fetchPage=async(a,serverPage,limit,snapshot,extra={})=>{
      const one=new AbortController();let timedOut=false;const stop=()=>one.abort();abort.signal.addEventListener('abort',stop,{once:true});
      const timeout=setTimeout(()=>{timedOut=true;one.abort();},35000);
      try {
        const data={...request,account:a.id,page:serverPage,limit,...(shared?{aggregate_part:1}:{}),...(snapshot?{snapshot_validity:snapshot.validity,max_uid:snapshot.max_uid}:{}),...extra};
        const result=await api('list',data,false,null,one.signal,diagnosticGroup);
        if(!active()){diagnosticGroup?.outcome('superseded');return null;}
        if(result.errors.length){cache.errors.set(a.id,result.errors.join(' '));cache.errorDetails.set(a.id,(result.error_details || []).join('\n\n'));diagnosticGroup?.outcome('incomplete');}
        return result;
      } catch(e) {
        if(!active()){diagnosticGroup?.outcome('superseded');return null;}
        // Casovy limit je skutecna chyba; vedome zruseni pri prepnuti schranky se naproti tomu nehlasi.
        if(timedOut){const timeoutError=new Error('Načítání pošty překročilo časový limit.');timeoutError.emKind='network';reportClientError(timeoutError,'list');e=timeoutError;}
        if(!state){abort.abort();listLoading=false;notice(e,true);return null;}
        diagnosticGroup?.outcome(timedOut?'timeout':'incomplete');cache.errors.set(a.id,a.email+': '+(timedOut?'vypršel čas načítání.':e.message));cache.errorDetails.set(a.id,e.emDetail || '');return null;
      } finally {clearTimeout(timeout);abort.signal.removeEventListener('abort',stop);}
    };
    // Pro porovnani schranek cteme jen datum a UID; predmety, adresy, prilohy a pracovni stavy zatim ne.
    const fetchSource=async source=>{
      const result=await fetchPage(source.account,source.nextPage,50,source.snapshot,{index_only:1});if(!active())return;
      if(!result){source.done=true;return;}
      source.items.push(...result.messages);source.total=result.total;source.snapshot=result.snapshot;source.nextPage++;
      source.done=!!result.errors.length || result.messages.length<50 || source.items.length>=source.total;
      cache.total=cache.sources.reduce((n,s)=>n+(s.total || 0),0);total=cache.total;
      notice('Zjišťuji nejnovější zprávy · '+cache.sources.filter(s=>s.total!==null || s.done).length+'/'+accounts.length+' schránek');
    };
    // Nejvyse dva IMAP pozadavky soucasne, vcetne doplnovani starsich stran.
    const fetchSources=async sources=>{
      let next=0;const worker=async()=>{while(next<sources.length && active())await fetchSource(sources[next++]);};
      await Promise.all(Array.from({length:Math.min(2,sources.length)},worker));
    };
    try {
      if(!shared) {
        const pageKey=size+':'+targetPage;let result=cache.pages.get(pageKey);
        if(!result){result=await fetchPage(accounts[0],targetPage,size,cache.snapshot);if(result && active()){cache.pages.set(pageKey,result);cache.snapshot=result.snapshot;cache.total=result.total;}}
        if(!active())return;messages=result?.messages || [];total=cache.total;
      } else {
        await fetchSources(cache.sources.filter(s=>s.total===null && !s.done));
        // Pred vyberem dalsi zpravy musime znat celo kazde nevycerpane schranky. Strany zpet pouziji hotovy prefix.
        while(active() && cache.merged.length<targetPage*size) {
          await fetchSources(cache.sources.filter(s=>s.position>=s.items.length && !s.done));if(!active())return;
          const heads=cache.sources.filter(s=>s.position<s.items.length);if(!heads.length)break;
          heads.sort((a,b)=>compareMessages(a.items[a.position],b.items[b.position]));
          const source=heads[0];cache.merged.push(source.items[source.position++]);
        }
        if(!active())return;
        const selectedIndex=cache.merged.slice((targetPage-1)*size,targetPage*size),tasks=[];
        // Uplne radky nacitame az po globalnim vyberu, dohromady nejvyse velikost prave zobrazovane stranky.
        for(const source of cache.sources) {
          const missing=selectedIndex.filter(m=>m.account===source.account.id && !cache.rows.has(m.account+':'+m.uid));
          for(let offset=0;offset<missing.length;offset+=50)tasks.push({source,uids:missing.slice(offset,offset+50).map(m=>m.uid)});
        }
        let taskIndex=0;const detailsWorker=async()=>{
          while(taskIndex<tasks.length && active()) {
            const task=tasks[taskIndex++];const result=await fetchPage(task.source.account,1,50,task.source.snapshot,{uids:task.uids.join(',')});
            if(result && active())for(const m of result.messages)cache.rows.set(m.account+':'+m.uid,m);
          }
        };
        if(tasks.length)notice('Načítám stránku · '+selectedIndex.length+' zpráv');
        await Promise.all(Array.from({length:Math.min(2,tasks.length)},detailsWorker));if(!active())return;
        messages=selectedIndex.map(m=>cache.rows.get(m.account+':'+m.uid)).filter(Boolean);total=cache.total;
      }
      listLoading=false;renderList();const errors=Array.from(cache.errors.values());
      if(errors.length){const error=new Error('Neúplné výsledky · '+errors.join(' '));error.emDetail=Array.from(cache.errorDetails.values()).filter(Boolean).join('\n\n');notice(error,true);}
      else notice((shared?'Společná pošta':currentAccount()?.email || '')+(search?' · Hledání „'+search+'“':'')+' · '+total.toLocaleString('cs-CZ')+' zpráv');
      if(errors.length && !messages.length)node('[data-em-list]').innerHTML='<div class="em-empty">Seznam se nepodařilo načíst celý. Zkuste přehled obnovit.</div>';
    } finally {
      if(active() && root?.isConnected){listLoading=false;node('[data-em-list]').setAttribute('aria-busy','false');pageControls();}
    }
  }
  // Cekani a prazdny hotovy vysledek maji odlisny text; rozpracovany souhrn zatim nelze strankovat.
  function renderList() {
    // Nazev akce vychazi ze skutecne slozky seznamu a otevrene zpravy.
    node('[data-em-action="bulk_trash"]').textContent=!aggregate && folder===special.trash?'Smazat':'Do koše';
    node('[data-em-action="trash"]').textContent=selected && selected.folder===special.trash?'Smazat':'♲ Do koše';
    node('[data-em-list]').innerHTML=messages.map((m,i) => `<div class="em-message-row${m.seen?'':' is-unseen'}${selected && selected.account===m.account && selected.uid===m.uid?' is-selected':''}" role="listitem"><input type="checkbox" data-em-check="${i}" aria-label="Vybrat ${esc(m.subject)}"><button data-em-message="${i}"><span class="em-message-top"><span class="em-sender">${esc(label(m.from))}</span><time>${esc(m.date?new Date(m.date).toLocaleString('cs-CZ',{day:'numeric',month:'numeric',hour:'2-digit',minute:'2-digit'}):'')}</time></span><span class="em-subject">${m.answered?'↶ ':''}${m.flagged?'⚑ ':''}${esc(m.subject || '(bez předmětu)')}</span>${Number(m.attachment_count)>0?'<span class="em-attachment-count" aria-label="Počet příloh: '+Number(m.attachment_count)+'"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 11-9 9a6 6 0 0 1-8.5-8.5l10-10a4 4 0 0 1 5.7 5.7l-10 10a2 2 0 0 1-2.8-2.8l9-9"/></svg><small>'+Number(m.attachment_count)+'</small></span>':''}${aggregate?'<span class="em-mailbox-label">'+esc(m.mailbox)+'</span>':''}${m.shared && m.work?.stav!=='nove'?'<span class="em-work-label">'+esc(workText(m.work))+'</span>':''}</button></div>`).join('') || '<div class="em-empty">'+(listLoading?(appliedSearch?'Hledám zprávy…':'Načítám zprávy…'):'Ve vybraném přehledu nejsou žádné zprávy.')+'</div>';
    pageControls();
  }
  // Neplatny nebo uz nahrazeny radek nesmi otevrit jinou zpravu ani cist vlastnosti undefined.
  async function openMessage(row, remote = null, diagnosticGroup = null) {
    if(!row || !row.account || !row.uid || !row.validity || !row.folder){notice('Seznam zpráv se mezitím změnil. Vyberte zprávu z aktuálního seznamu.',true);return;}
    remote??=row.images_visible ?? state?.preferences?.show_images ?? false;
    const task=group=>openMessageCore(row, remote, group);
    return window.CB_DIAG?window.CB_DIAG.run('email','openmessage',{account:row.account,uid:row.uid},task,diagnosticGroup):task(null);
  }
  async function openMessageCore(row, remote = false, diagnosticGroup = null) {
    const seq=++messageSequence;
    notice('Načítám zprávu…');
    const result=await api('message',{...ref(row),remote:remote?1:0,encoding:row.encoding?.selected || 'auto'},false,null,null,diagnosticGroup);
    if (seq!==messageSequence) {diagnosticGroup?.outcome('superseded');return;}
    if(!selected || selected.account!==result.message.account || selected.folder!==result.message.folder || selected.uid!==result.message.uid)root.classList.remove('em-reader-max');
    selected=result.message; selected.images_visible=remote; renderMessage(); renderList();
    if (!selected.seen || selected.shared) {
      await api('read',{...ref(selected),value:true},true,null,null,diagnosticGroup);
      selected.seen=true; const m=messages.find(m => m.account===selected.account && m.uid===selected.uid); if (m) m.seen=true; renderList();
    }
    notice('Zpráva načtena.');
  }
  // Prilohy maji kratky textovy seznam; plny nazev poskytne spolecny tooltip a CID duplicity skryjeme az po zobrazeni.
  function renderMessageAttachments(m) {
    const files=m.attachments.filter(a=>!(m.images_visible && a.cid && m.inline_shown?.has(a.cid)));
    const host=node('[data-em-reader] .em-attachments'); if(!host)return;
    host.hidden=!files.length;
    host.innerHTML='<b class="em-attachments-label">Přílohy:</b>'+files.map(a=>`<button data-em-attachment="${esc(a.part)}" title="${esc(a.name)}" aria-label="Otevřít přílohu ${esc(a.name)}"><span class="em-attachment-name">${esc(a.name)}</span><small>(${Math.ceil(a.size/1024)} kB)</small></button>`).join('');
    window.CB_TOOLTIP?.init(host);
  }
  // Adresy i ovladani sdileji radek; zobrazeni obrazku je oddelene od ulozene osobni volby.
  function renderMessage(remote = selected?.images_visible ?? false) {
    const m=selected; if (!m) return;
    node('[data-em-image-choice]')?.hidePopover();
    m.images_visible=remote; m.inline_shown=new Set();
    root.classList.add('em-has-message');
    root.querySelectorAll('[data-em-toolbar] button').forEach(b => { b.disabled=false; });
    clearMailFrames(node('[data-em-reader]'));
    const date=m.date?new Date(m.date).toLocaleString('cs-CZ',{day:'numeric',month:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}):'';
    const hasImages=/<img\b/i.test(m.html || '');
    const imageButton=hasImages?'<button class="em-images-toggle" data-em-action="remote" aria-pressed="'+String(remote)+'">'+(remote?'Skrýt obr. ve zprávě':'Zobrazit obr. ve zprávě')+'</button>':'';
    node('[data-em-reader]').innerHTML=`<div class="em-reader-normal"><button data-em-action="normal" aria-label="Vrátit normální zobrazení zprávy">Normal</button></div><header class="em-message-header">
      <div class="em-message-title"><h2>${esc(m.subject || '(bez předmětu)')}</h2><time>${esc(date)}</time></div>
      <div class="em-message-addresses"><p><b>Od:</b> ${esc(label(m.from))}</p><p><b>Komu:</b> ${esc(label(m.to))}</p>${m.cc?.length?'<p><b>Kopie:</b> '+esc(label(m.cc))+'</p>':''}</div>
      <div class="em-message-actions"><button data-em-action="back">‹ Seznam</button><button data-em-action="move">Přesunout</button>${m.folder===special.drafts?'<button data-em-action="edit_draft">Upravit koncept</button>':''}
      <span class="em-message-mailbox" title="${esc(m.mailbox)}"><b>Schránka:</b><span>${esc(m.mailbox)}</span></span><button data-em-action="original" title="Stáhnout originál zprávy ve formátu .eml">Originál (.eml)</button><label class="em-message-encoding">Kódování <select data-em-encoding>${[['auto','Automaticky'],['UTF-8','UTF-8'],['Windows-1250','Windows-1250'],['ISO-8859-2','ISO-8859-2'],['repair_utf8','Obnovit poškozenou češtinu']].map(([v,t])=>`<option value="${v}"${(m.encoding?.selected || 'auto')===v?' selected':''}>${t}</option>`).join('')}</select></label>${imageButton}${!m.shared?'<button class="em-max" data-em-action="max" title="Zvětšit prostor pro zprávu">Max</button>':''}</div>
      ${m.shared?'<div class="em-work"><span>'+esc(workText(m.work))+'</span><button data-em-action="claim">Převzít</button><button data-em-action="done">Vyřízeno bez odpovědi</button><button data-em-action="release">Vrátit k řešení</button><button class="em-max" data-em-action="max" title="Zvětšit prostor pro zprávu">Max</button></div>':''}
      </header><div class="em-attachments"></div>`;
    renderMessageAttachments(m);window.CB_TOOLTIP?.init(node('[data-em-reader] .em-message-header'));
    if (m.html) {
      renderMailFrame(node('[data-em-reader]'),m,remote);
    } else { const pre=document.createElement('pre'); pre.className='em-plain-body'; pre.textContent=m.text || ''; node('[data-em-reader]').append(pre); }
  }

  // Odstraneni ramce uvolni take jeho vlozena obrazkova data pri zmene zpravy nebo modulu.
  function clearMailFrames(host) {
    host?.querySelectorAll('iframe[data-em-mail-frame]').forEach(frame=>frame.remove());
  }
  // CID obrazky se stahuji jen jako overene rastrove prilohy. Ramci nepovolujeme skripty ani stejny puvod s IS.
  function renderMailFrame(host,m,remote=false) {
    const frame=document.createElement('iframe');frame.dataset.emMailFrame='1';frame.className='em-message-body';frame.title='Obsah e-mailu';frame.setAttribute('sandbox','allow-popups allow-popups-to-escape-sandbox');frame.referrerPolicy='no-referrer';
    const wrap=html=>`<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src ${remote?'data: https:':"'none'"}; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><style>body{font:14px/1.6 Arial,sans-serif;margin:18px;color:#30383c;overflow-wrap:anywhere}img{max-width:100%}${remote?'img[data-em-cid]:not([src]){display:none}':'img{display:none!important}'}table{max-width:100%}pre{white-space:pre-wrap}blockquote{border-left:3px solid #ddd;margin-left:0;padding-left:15px}</style></head><body>${html}</body></html>`;
    const doc=new DOMParser().parseFromString(m.html,'text/html');
    // Nahled konceptu muze obsahovat drive povolene URL; skryty rezim nepreda ramci zadne obrazkove zdroje.
    if(!remote)doc.querySelectorAll('img').forEach(img=>{img.removeAttribute('src');img.removeAttribute('srcset');});
    frame.srcdoc=wrap([...doc.head.querySelectorAll('style')].map(style=>style.outerHTML).join('')+doc.body.innerHTML);host.append(frame);
    const images=[...doc.querySelectorAll('img[data-em-cid]')];
    if(remote && images.length)run(async()=>{
      const byCid=new Map();
      for(const img of images){const cid=img.dataset.emCid,part=m.attachments.find(a=>a.cid===cid);if(!part)continue;
        try{if(!byCid.has(cid)){const {blob}=await api('attachment',{...ref(m),part:part.part,inline:1});if(!frame.isConnected)return;
          // Data URI z overeneho rastru funguje i v nepruhlednem sandboxu, ktery nepovoluje blob URL puvodu IS.
          const data=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=()=>reject(new Error('Obrázek nelze načíst.'));reader.readAsDataURL(blob);});
          // Poskozeny soubor nesmi zmizet ze seznamu jen proto, ze jeho stazeni vratilo uspech.
          const check=document.createElement('img');check.src=data;await check.decode();if(!frame.isConnected)return;byCid.set(cid,data);
        }img.setAttribute('src',byCid.get(cid));if(selected===m)m.inline_shown.add(cid);}catch(e){if(e.name==='AbortError')return;img.setAttribute('alt',(img.alt || 'Obrázek')+' (nedostupný)');}
      }
      if(frame.isConnected){frame.srcdoc=wrap([...doc.head.querySelectorAll('style')].map(style=>style.outerHTML).join('')+doc.body.innerHTML);if(selected===m)renderMessageAttachments(m);}
    });
  }

  // Docasny obsah uvolnime pri zavreni nebo zmene modulu; opozdena odpoved uz nesmi otevrit jinou prilohu.
  function clearAttachment() {
    attachmentSequence++;
    if (attachmentUrl) URL.revokeObjectURL(attachmentUrl);
    attachmentUrl=null;
    node('[data-em-attachment-preview]')?.replaceChildren();
    const save=node('[data-em-attachment-save]'); if (save) {save.hidden=true;save.removeAttribute('href');save.removeAttribute('download');}
    const office=node('[data-em-attachment-office]'); if (office) {office.hidden=true;office.removeAttribute('href');}
  }
  // Podporovane formaty otevreme primo v IS; ostatni zustavaji dostupne ke stazeni do vlastni aplikace.
  // Vypinatelne mereni cele operace zachova puvodni vysledek i chybu.
  async function openAttachment(part, diagnosticGroup = null) {
    const task=group=>openAttachmentCore(part, group);
    return window.CB_DIAG?window.CB_DIAG.run('email','openattachment',{account:selected?.account,uid:selected?.uid},task,diagnosticGroup):task(null);
  }
  async function openAttachmentCore(part, diagnosticGroup = null) {
    const message=selected, attachment=message?.attachments.find(a=>String(a.part)===String(part));
    if (!attachment) throw new Error('Příloha již není dostupná.');
    clearAttachment(); const seq=attachmentSequence;
    const dialog=node('[data-em-attachment-dialog]'), preview=node('[data-em-attachment-preview]');
    node('[data-em-attachment-title]').textContent=attachment.name;
    preview.textContent='Načítám přílohu…'; dialog.showModal();
    try {
      const result=await api('attachment',{...ref(message),part},false,null,null,diagnosticGroup), blob=result.blob;
      if (seq!==attachmentSequence) {diagnosticGroup?.outcome('superseded');return;}
      // PHP muze k textovemu MIME pridat charset; druh nahledu urcuje samotny typ bez parametru.
      const type=blob.type.split(';',1)[0].trim().toLowerCase();
      attachmentUrl=URL.createObjectURL(blob);
      const save=node('[data-em-attachment-save]');save.href=attachmentUrl;save.download=attachment.name;save.hidden=false;
      preview.replaceChildren();
      if (['image/png','image/jpeg','image/gif','image/webp','image/avif'].includes(type)) {
        const image=document.createElement('img');image.alt=attachment.name;image.src=attachmentUrl;preview.append(image);
      } else if (type==='application/pdf') {
        const frame=document.createElement('iframe');frame.title=attachment.name;frame.src=attachmentUrl;preview.append(frame);
      } else if (['audio/mpeg','audio/wav','audio/x-wav','audio/ogg','video/mp4','video/webm','video/ogg'].includes(type)) {
        const media=document.createElement(type.startsWith('video/')?'video':'audio');media.controls=true;media.preload='metadata';media.src=attachmentUrl;preview.append(media);
      } else if (type==='text/plain') {
        const text=await blob.text();if (seq!==attachmentSequence) return;
        const pre=document.createElement('pre');pre.textContent=text;preview.append(pre);
      } else if (result.officeUrl && ['ms-word','ms-excel','ms-powerpoint'].includes(result.officeScheme)) {
        const url=new URL(result.officeUrl,location.href);
        if (url.origin!==location.origin) throw new Error('Neplatný odkaz na přílohu.');
        const office=node('[data-em-attachment-office]');office.href=result.officeScheme+':ofv|u|'+url.href;office.textContent='Otevřít v aplikaci '+result.officeLabel;office.hidden=false;
        preview.textContent='Přílohu otevřete tlačítkem níže v aplikaci '+result.officeLabel+' v počítači. Prohlížeč může vyžádat potvrzení. Pokud se aplikace neotevře, můžete přílohu uložit a otevřít ručně.';
      } else preview.textContent='Pro tento typ souboru není v prohlížeči dostupný náhled. Přílohu můžete uložit a otevřít v příslušné aplikaci.';
    } catch(e) {diagnosticGroup?.outcome(e.name==='AbortError'?'abort':'error');if (seq===attachmentSequence && e.name!=='AbortError') errorContent(preview,e);}
  }

  // Sprava je samostatny formular; pripojovaci dialog zustava ve sve puvodni podobe.
  async function sharedAdminDialog() {
    sharedAdmin=await api('shared_admin');sharedAdminDirty=false;sharedAdminBusy=false;
    renderSharedAdmin(sharedAdmin.accounts[0]?.id || 0);node('[data-em-shared-admin]').showModal();
  }
  // Vyber ma jediny nazev/adresu; role a uzivatele lze menit nezavisle pro kazdou schranku.
  function renderSharedAdmin(id) {
    sharedAdminAccessDirty=false;
    const form=node('[data-em-shared-admin-form]');
    form.elements.managed_account.innerHTML=sharedAdmin.accounts.map(a=>`<option value="${Number(a.id)}">${esc(a.email)}</option>`).join('') || '<option value="">Žádná připojená schránka</option>';
    form.elements.managed_account.value=String(id);form.elements.managed_account.dataset.previous=String(id);
    const a=sharedAdmin.accounts.find(a=>Number(a.id)===Number(id)),rule=sharedAdmin.access?.[id];
    node('[data-em-admin-empty]').hidden=!!a;node('[data-em-admin-content]').hidden=!a;
    form.querySelector('[data-em-action="shared_delete"]').hidden=!a;
    // Konkretni adresa odlisuje odebirane pripojeni od ostatnich schranek v seznamu.
    form.querySelector('[data-em-action="shared_delete"]').textContent='Odebrat email '+(a?.email || '')+' z IS';
    form.elements.managed_account.disabled=!a;form.elements.managed_password.value='';
    // Adresa v nadpisu potvrzuje, ke ktere vybrane schrance se nove heslo vztahuje.
    form.querySelector('#em-admin-password-title').textContent='Upravit schránku '+(a?.email || '');
    form.querySelector('.em-form-error').replaceChildren();node('[data-em-admin-feedback]').textContent='';
    node('[data-em-admin-access]').disabled=!sharedAdmin.access_ready;
    form.querySelector('[data-em-action="shared_save_access"]').disabled=!a || !sharedAdmin.access_ready;
    node('[data-em-admin-access-hint]').textContent=!sharedAdmin.access_ready?'Pro nastavení přístupu je potřeba spustit připravené SQL. Dosavadní přístup zůstává zachovaný.':rule?'Přístup určují vybrané role a uživatelé.':'Zatím platí dosavadní přístup podle 801 a pobočky MAIN. Uložením jej nahradíte tímto výběrem.';
    node('[data-em-admin-roles]').innerHTML=sharedAdmin.roles.map(r=>`<label><input type="checkbox" name="access_role" value="${Number(r.id_role)}"${(rule?rule.roles.includes(Number(r.id_role)):a?.typ==='pobocka' && [5,7].includes(Number(r.id_role)))?' checked':''}><span>${esc(r.role)}</span></label>`).join('');
    node('[data-em-admin-users]').innerHTML=sharedAdmin.users.map(u=>`<label><input type="checkbox" name="access_user" value="${Number(u.id_user)}"${rule?.users.includes(Number(u.id_user))?' checked':''}><span>${esc(u.jmeno || 'Uživatel '+u.id_user)}</span></label>`).join('');
    node('[data-em-admin-filter]').value='';node('[data-em-admin-main]').hidden=a?.typ!=='pobocka';
    form.elements.access_training.checked=a?.typ==='pobocka' && !!rule?.training;
  }
  // Prehled vzdy cte ulozeny stav. Otevreni ani zavreni nemeni rozpracovany vyber pristupu v rodicovskem okne.
  async function sharedAccessReport() {
    const form=node('[data-em-shared-admin-form]'),id=Number(form.elements.managed_account.value),a=sharedAdmin?.accounts.find(a=>Number(a.id)===id);
    if(!a || sharedAdminBusy)return;
    const dialog=node('[data-em-access-report]'),button=form.querySelector('[data-em-action="shared_access_report"]'),sequence=++accessReportSequence;
    const unsaved=sharedAdminAccessDirty;
    button.disabled=true;node('[data-em-access-account]').textContent=a.email;node('[data-em-access-summary]').textContent='Načítám uložené přístupy…';
    node('[data-em-access-results]').hidden=true;node('[data-em-access-users]').replaceChildren();node('[data-em-access-unresolved]').hidden=true;dialog.querySelector('.em-form-error').replaceChildren();dialog.showModal();
    try{
      const result=await api('shared_access_report',{shared_id:id});
      if(sequence!==accessReportSequence || !dialog.open || !root?.isConnected)return;
      const count=result.users.length,label=count===1?'účet':count>=2 && count<=4?'účty':'účtů';
      node('[data-em-access-summary]').textContent='Uložený stav · '+count+' '+label+' · '+result.checked_at+'.'+(unsaved?' Neuložené změny nejsou zahrnuté.':'');
      node('[data-em-access-users]').innerHTML=result.users.map(u=>`<tr><td>${esc(u.name || 'Uživatel '+u.id)}</td><td><ul>${u.reasons.map(r=>`<li>${esc(r)}</li>`).join('')}</ul></td></tr>`).join('') || '<tr><td colspan="2">Přístup nemá žádný uživatel.</td></tr>';
      node('[data-em-access-results]').hidden=false;
      const warning=node('[data-em-access-unresolved]');warning.hidden=!result.unresolved.length;
      warning.textContent=result.unresolved.length?'Nepodařilo se ověřit HR zařazení: '+result.unresolved.map(u=>u.name || 'Uživatel '+u.id).join(', ')+'.':'';
    }catch(e){if(e.name!=='AbortError' && sequence===accessReportSequence && dialog.open){node('[data-em-access-summary]').textContent='';errorContent(dialog.querySelector('.em-form-error'),e);}}
    finally{if(button.isConnected)button.disabled=false;}
  }
  // Kazdy zapis nese revizi; pri chybe zustanou rozpracovane hodnoty a opakovany klik neposle druhy pozadavek.
  async function sharedAdminChange(kind) {
    const form=node('[data-em-shared-admin-form]'),id=Number(form.elements.managed_account.value),a=sharedAdmin?.accounts.find(a=>Number(a.id)===id);
    if(!a || sharedAdminBusy)return;let action='',data={shared_id:id,revision:Number(a.revize)};
    if(kind==='shared_save_access'){
      if(form.elements.managed_password.value && !confirm('Neuložené heslo se zahodí. Pokračovat uložením přístupů?'))return;
      if(!sharedAdmin.access_ready)return;action='shared_access';data.roles=[...form.querySelectorAll('[name=access_role]:checked')].map(i=>Number(i.value));data.users=[...form.querySelectorAll('[name=access_user]:checked')].map(i=>Number(i.value));data.training=a.typ==='pobocka' && form.elements.access_training.checked;
      if(!data.roles.length && !data.users.length){if(!confirm('Ke schránce nebude mít v IS nikdo přístup. Opravdu uložit prázdný seznam?'))return;data.confirm_empty=true;}
    }else if(kind==='shared_update_password'){
      if(form.elements.managed_password.value===''){errorContent(form.querySelector('.em-form-error'),new Error('Vyplňte heslo nastavené na poštovním serveru.'));return;}
      if(sharedAdminAccessDirty && !confirm('Neuložený výběr přístupů se zahodí. Pokračovat uložením hesla?'))return;
      action='shared_password';data.password=form.elements.managed_password.value;
    }else{
      if(!confirm('Opravdu chcete odebrat sdílenou schránku '+a.email+' z IS? Zprávy a složky na poštovním serveru zůstanou zachované.'))return;
      action='shared_remove';data.confirm=a.email;
    }
    sharedAdminBusy=true;let saved=false;form.querySelector('.em-form-error').replaceChildren();node('[data-em-admin-feedback]').textContent=action==='shared_password'?'Ověřuji nové heslo…':'Ukládám…';
    const controls=[...form.querySelectorAll('input,select,button')];controls.forEach(c=>c.disabled=true);
    try{
      await api(action,data,true);saved=true;sharedAdminDirty=false;form.elements.managed_password.value='';
      sharedAdmin=await api('shared_admin');renderSharedAdmin(sharedAdmin.accounts.some(a=>Number(a.id)===id)?id:sharedAdmin.accounts[0]?.id || 0);
      await loadState(null,false);node('[data-em-admin-feedback]').textContent=action==='shared_remove'?'Schránka byla odebrána z IS.':action==='shared_password'?'Nové připojení bylo ověřeno a uloženo.':'Přístup byl uložen.';
    }catch(e){if(e.name!=='AbortError')errorContent(form.querySelector('.em-form-error'),saved?new Error('Změna byla uložena, ale přehled se nepodařilo obnovit. Zavřete správu a otevřete ji znovu.'):e);node('[data-em-admin-feedback]').textContent='';}
    finally{sharedAdminBusy=false;form.elements.managed_password.value='';if(!saved)sharedAdminDirty=sharedAdminAccessDirty;controls.forEach(c=>c.disabled=false);form.elements.managed_account.disabled=!sharedAdmin?.accounts.length;node('[data-em-admin-access]').disabled=!sharedAdmin?.access_ready;form.querySelector('[data-em-action="shared_save_access"]').disabled=!sharedAdmin?.access_ready || !sharedAdmin?.accounts.length;}
  }

  // Pripojeni se obnovi pri kazdem otevreni, aby zobrazilo i adresy mezitim pripojene z jineho okna.
  async function connectDialog(type = 'osobni') {
    const fresh=await api('state');
    if (!fresh.installed || fresh.configured===false) throw new Error(fresh.message || 'Nastavení schránek není dostupné.');
    state=fresh;
    personalConnections=await api('personal_connections');
    if (type==='shared' && !state.manage_shared) throw new Error('Správa společných schránek vyžaduje právo 802.');
    const dialog=node('[data-em-connect]'), form=dialog.querySelector('form'); form.reset();
    form.querySelector('.em-form-error').textContent='';
    // Předvyplňuje se pouze již připojená schránka, nikdy kontaktní adresa z HR.
    form.elements.local_part.value=currentAccount()?.type==='osobni'?currentAccount().email.split('@')[0]:'';
    form.elements.target_user.innerHTML=personalConnections.users.map(u=>`<option value="${u.id}">${esc(u.name)}</option>`).join('');
    form.elements.target_user.value=String(personalConnections.self);
    if(!form.elements.target_user.value && personalConnections.users.length)form.elements.target_user.value=String(personalConnections.users[0].id);
    form.elements.type.querySelector('[value="osobni"]').disabled=!personalConnections.users.length;
    form.elements.type.querySelector('[value="shared"]').disabled=!state.manage_shared;
    form.elements.type.value=type==='shared' || !personalConnections.users.length?'shared':'osobni';
    form.elements.mode.value=currentAccount()?.mode || 'is';
    const management=state.shared_management || {accounts:[],branches:[]};
    sharedConnections();
    form.elements.branch.innerHTML='<option value="">Vyberte pobočku</option>'+management.branches.map(b=>`<option value="${Number(b.id_pob)}">${esc(b.nazev)}</option>`).join('');
    personalAccountOptions(true);
    connectFields(true);
    node('[data-em-security-info]').hidden=true;
    node('[data-em-security-toggle]').setAttribute('aria-expanded','false');
    dialog.showModal();
  }
  // Jednoradkovy seznam zobrazuje stav a adresu; prirazeni zustava viditelne ve formulari vybraneho pripojeni.
  function sharedConnections() {
    const form=node('[data-em-connect-form]'), selected=form.elements.shared_id.value;
    const management=state.shared_management || {accounts:[],branches:[]};
    form.elements.shared_id.innerHTML='<option value="0">Připojit novou schránku</option>'+management.accounts.map(a=>`<option value="${Number(a.id)}">${esc(a.email)}${Number(a.aktivni)?'':' (vypnuto)'}</option>`).join('');
    form.elements.shared_id.value=management.accounts.some(a=>String(a.id)===selected)?selected:'0';
    node('[data-em-shared-list]').innerHTML=management.accounts.map(a=>{
      return `<button type="button" class="em-shared-row${String(a.id)===form.elements.shared_id.value?' is-selected':''}" data-em-shared-edit="${Number(a.id)}"><span class="em-shared-state">${Number(a.aktivni)?'Zapnuto':'Vypnuto'}</span><span class="em-shared-address" title="${esc(a.email)}">${esc(a.email)}</span><span>Upravit</span></button>`;
    }).join('') || '<p>Zatím není připojena žádná adresa.</p>';
  }
  // Katalog obsahuje jen metadata; osobni prihlaseni ani cizi obsah se do prohlizece spravce neposilaji.
  function personalOwner() {
    return personalConnections?.users.find(u=>u.id===Number(node('[data-em-connect-form]').elements.target_user.value));
  }
  // Pri zmene vlastnika se jeho pripojeni nactou znovu, nikdy nezustane vybrana schranka predchozi osoby.
  function personalAccountOptions(reset=false) {
    const form=node('[data-em-connect-form]'),owner=personalOwner(),previous=Number(form.elements.personal_id.value);
    const accounts=owner?.accounts || [];
    form.elements.personal_id.innerHTML='<option value="0">Připojit další schránku</option>'+accounts.map(a=>`<option value="${a.id}">${esc(a.email)}</option>`).join('');
    form.elements.personal_id.value=String(reset?(accounts.find(a=>a.id===account)?.id || accounts[0]?.id || 0):(accounts.some(a=>a.id===previous)?previous:0));
  }
  // Editace adresy znamena dalsi pripojeni; existujici adresa se pozna podle metadat a server znovu overi ID/revizi.
  function personalAddressAccount() {
    const form=node('[data-em-connect-form]'),email=form.elements.local_part.value.trim().toLowerCase()+'@pizzacomeback.cz';
    return personalOwner()?.accounts.find(a=>a.email.toLowerCase()===email);
  }
  // Automaticky pristup vyzaduje hesla vsech osobnich schranek. Rucni rezim zadne trvale neuklada.
  function personalPasswordFields() {
    const form=node('[data-em-connect-form]'),shared=form.elements.type.value==='shared',owner=personalOwner(),current=personalAddressAccount(),box=node('[data-em-personal-passwords]');
    const remembered=new Map(Array.from(box.querySelectorAll('[data-em-extra-password]'),i=>[i.dataset.emExtraPassword,i.value]));
    const required=!shared && form.elements.mode.value==='is'?(owner?.accounts || []).filter(a=>a.id!==current?.id && !a.has_secret):[];
    box.innerHTML=required.length?'<p>Pro automatický přístup doplňte také hesla k ostatním osobním schránkám:</p>'+required.map(a=>`<label>${esc(a.email)}<input type="password" data-em-extra-password="${a.id}" maxlength="1024" autocomplete="new-password" required></label>`).join(''):'';
    box.querySelectorAll('[data-em-extra-password]').forEach(i=>{i.value=remembered.get(i.dataset.emExtraPassword)||'';});box.hidden=!required.length;
    if(!shared){
      form.elements.password.required=!current || (form.elements.mode.value==='is' && !current.has_secret);
      form.elements.password.placeholder=current && (current.has_secret || form.elements.mode.value==='heslo')?'Prázdné = zachovat připojení':'';
      form.querySelector('[type=submit]').textContent=current?'Uložit změny':'Připojit';
    }
  }
  // Sdilene a osobni pripojeni maji oddelene vybery; spolecny rezim vzdy patri vybranemu uzivateli.
  function connectFields(selectAccount = false) {
    const form=node('[data-em-connect-form]'), shared=form.elements.type.value==='shared';
    const owner=personalOwner(),personal=owner?.accounts.find(a=>a.id===Number(form.elements.personal_id.value));
    const remote=!shared && !!owner && owner.id!==personalConnections.self;
    const a=shared?(state.shared_management?.accounts || []).find(a=>Number(a.id)===Number(form.elements.shared_id.value)):null;
    node('[data-em-shared-list]').querySelectorAll('[data-em-shared-edit]').forEach(b=>b.classList.toggle('is-selected',b.dataset.emSharedEdit===form.elements.shared_id.value));
    if (selectAccount) {
      form.elements.local_part.value=(shared?(a?.email || ''):(personal?.email || '')).split('@')[0];
      if(!shared)form.elements.mode.value=owner?.mode || 'is';
      form.elements.shared_kind.value=a?.typ || 'pobocka'; form.elements.branch.value=a?.id_pob || '';
      form.elements.password.value='';
      node('[data-em-personal-passwords]').replaceChildren();
    }
    node('[data-em-shared-fields]').hidden=!shared;
    node('[data-em-personal-owner]').hidden=shared || !personalConnections.admin;
    node('[data-em-personal-account]').hidden=shared;
    // Napoveda patri k osobnimu pripojeni manazera; prepnuti na spolecnou spravu ji zavre.
    node('[data-em-security-toggle]').hidden=!state.manager || shared;
    if (shared || !state.manager) {
      node('[data-em-security-info]').hidden=true;
      node('[data-em-security-toggle]').setAttribute('aria-expanded','false');
    }
    node('[data-em-branch-label]').hidden=!shared || form.elements.shared_kind.value!=='pobocka';
    form.elements.branch.required=shared && form.elements.shared_kind.value==='pobocka';
    form.elements.local_part.readOnly=shared && !!a;
    form.elements.password.required=!shared || !a;
    form.elements.password.placeholder=shared && a?'Ponechte prázdné pro zachování hesla':'';
    form.elements.mode.disabled=shared; node('[data-em-mode-label]').hidden=shared;
    const toggle=node('[data-em-action="shared_active"]'); toggle.hidden=!shared || !a;
    node('[data-em-action="disconnect"]').hidden=shared || !owner?.accounts.length;
    toggle.textContent=Number(a?.aktivni)?'Vypnout přístup v IS':'Zapnout přístup v IS';
    node('[data-em-shared-status]').textContent=a?(Number(a.aktivni)?'Schránka je dostupná oprávněným uživatelům.':'Přístup v IS je vypnutý. Pošta na serveru zůstává zachovaná.'):'';
    node('[data-em-connect-hint]').textContent=shared?'Uživatelé používají přihlášení do IS. Heslo schránky znají jen její správci.':!personalConnections.ready?'Nejprve spusťte SQL pro více osobních schránek.':(remote?'Schránka se připojí vybranému uživateli; ve vaší poště se neobjeví. ':'')+'Přístup platí pro všechny osobní schránky tohoto uživatele. Změna adresy přidá další schránku.';
    form.querySelector('[type=submit]').textContent=shared && a?'Uložit změny':'Připojit';
    form.querySelector('[type=submit]').disabled=!shared && (!personalConnections.ready || !owner?.can_connect);
    personalPasswordFields();
  }
  // Potvrzeni vzdy pojmenuje aktualne vybranou adresu, nikoli prvni osobni schranku uzivatele.
  function disconnectQuestion() {
    const a=pendingDisconnect?.accounts.find(a=>a.id===Number(node('[data-em-disconnect-account]').value));
    node('[data-em-disconnect-question]').textContent=a?'Opravdu chcete odpojit schránku '+a.email+'?':'Vyberte schránku k odpojení.';
  }
  function unlockDialog() {
    const dialog=node('[data-em-unlock]'), a=currentAccount(); if (!a) return;
    dialog.querySelector('form').reset(); dialog.querySelector('.em-form-error').textContent='';
    node('[data-em-unlock-email]').textContent=a.email;
    node('[data-em-password-label]').hidden=a.mode!=='heslo'; dialog.showModal();
  }

  // Nabidka vzdy nahrazuje jen adresu u kurzoru, predchozi i nasledujici prijemce zachova.
  function recipientToken(input) {
    const value=input.value, caret=input.selectionStart??value.length;
    const start=Math.max(value.lastIndexOf(',',caret-1),value.lastIndexOf(';',caret-1))+1;
    const tail=value.slice(caret).search(/[,;]/), end=tail<0?value.length:caret+tail;
    return {start,end,query:value.slice(start,end).trim()};
  }
  // Zavreni nabidky zneplatni i dosud nedokoncene pozadavky.
  function hideRecipients() {
    clearTimeout(recipientTimer); recipientSequence++;
    if(recipientInput) {
      recipientInput.setAttribute('aria-expanded','false');recipientInput.removeAttribute('aria-activedescendant');
      const list=recipientInput.parentElement.querySelector('[role=listbox]');list.hidden=true;list.replaceChildren();
    }
    recipientInput=null;recipientItems=[];
  }
  // Caste psani ceka kratce na ustaleni; opozdeny vysledek se nesmi zobrazit u jine adresy nebo schranky.
  async function suggestRecipients(input) {
    hideRecipients();recipientInput=input;
    const token=recipientToken(input), seq=recipientSequence, sender=node('[data-em-compose-form]').elements.account.value;
    if(token.query.length<2) return;
    try {
      const result=await api('recipients',{account:sender,query:token.query},true);
      if(seq!==recipientSequence || document.activeElement!==input || !input.closest('dialog').open || sender!==node('[data-em-compose-form]').elements.account.value || token.query!==recipientToken(input).query) return;
      const status=node('[data-em-recipient-status]');status.hidden=result.ready!==false;
      status.textContent=result.ready===false?'Našeptávání příjemců zatím není připravené. Adresu můžete zadat ručně.':'';
      const used=new Set(input.value.split(/[;,]/).map(s=>s.trim().toLowerCase()));
      recipientItems=(result.contacts||[]).filter(c=>!used.has(c.email.toLowerCase()));recipientActive=0;
      const list=input.parentElement.querySelector('[role=listbox]');
      list.innerHTML=recipientItems.map((c,i)=>`<div role="option" id="${list.id}-${i}" data-em-recipient-option="${i}" aria-selected="${i===0}"><strong>${esc(c.name||c.email)}</strong>${c.name?`<span>${esc(c.email)}</span>`:''}</div>`).join('');
      list.hidden=!recipientItems.length;input.setAttribute('aria-expanded',String(!!recipientItems.length));
      if(recipientItems.length) input.setAttribute('aria-activedescendant',list.firstElementChild.id);
    } catch(e) {
      if(seq===recipientSequence && e.name!=='AbortError') {
        const status=node('[data-em-recipient-status]');
        if(status) {status.hidden=false;status.textContent='Našeptávání je dočasně nedostupné. Adresu můžete zadat ručně.';}
      }
    }
  }
  // Vlozime pouze platnou adresu; jmeno zustava v nabidce a SMTP dale pouziva dosavadni format poli.
  function chooseRecipient(index) {
    const input=recipientInput, item=recipientItems[index]; if(!input || !item) return;
    const token=recipientToken(input), prefix=input.value.slice(0,token.start), suffix=input.value.slice(token.end);
    input.value=prefix+(prefix?' ':'')+item.email+(suffix || ', ');
    const caret=(prefix+(prefix?' ':'')+item.email+(suffix?'':', ')).length;
    hideRecipients();input.focus();input.setSelectionRange(caret,caret);dirty=true;
  }
  // Obnova hlavicek bezi mimo psani; jeden ucet obnovujeme nejvyse jednou za pet minut v otevrenem modulu.
  async function refreshRecipients() {
    hideRecipients();const sender=Number(node('[data-em-compose-form]').elements.account.value), ownGeneration=generation;
    if(!sender || Date.now()-(recipientRefreshed.get(sender)||0)<300000) return;
    recipientRefreshed.set(sender,Date.now());
    try {
      const result=await api('recipients',{account:sender,sync:true},true);
      if(ownGeneration!==generation || sender!==Number(node('[data-em-compose-form]').elements.account.value)) return;
      const status=node('[data-em-recipient-status]');status.hidden=result.ready!==false;
      status.textContent=result.ready===false?'Našeptávání příjemců zatím není připravené. Adresu můžete zadat ručně.':'';
      const input=document.activeElement;if(input?.matches('[data-em-recipient]') && root.contains(input)) suggestRecipients(input);
    } catch(e) {recipientRefreshed.delete(sender);}
  }
  // MIME metadata uvadeji prenosovou velikost; odhad base64 je jen pro orientacni soucet, server overuje skutecne bajty.
  function storedFileBytes(file) {
    if (file.encoding!=='BASE64') return Number(file.size)||0;
    const size=Number(file.size)||0;
    return Math.max(0,Math.floor((size-Math.floor(size/78)*2)/4)*3-2);
  }
  // Vlozene obrazky sdileji pocet a velikost s beznymi prilohami; stejne obrazky se prenaseji jen jednou.
  function validateEditorImages(images) {
    if(!compose)return;
    const unique=Array.from(new Map(images.map(image=>[image.src,image])).values());
    const bytes=unique.reduce((n,image)=>n+image.size,0)+compose.uploads.reduce((n,f)=>n+f.size,0)+compose.stored_files.reduce((n,f)=>n+storedFileBytes(f),0);
    const limit=Number(state.attachment_limit)||20*1024*1024;
    if(bytes>limit || unique.length+compose.uploads.length+compose.stored_files.length>20)throw new Error('Přílohy a vložené obrázky mohou mít celkem nejvýše '+(limit/1024/1024)+' MB a 20 souborů.');
  }
  // CID z konceptu nacteme pouze pri jeho uprave a jen pres autorizovane API; cizi URL se nenacitaji.
  async function draftEditorHtml(source) {
    const doc=new DOMParser().parseFromString(source.draft_editor_html || '<p><br></p>','text/html');
    const urls=new Map();
    for(const img of doc.querySelectorAll('img')) {
      const src=img.getAttribute('src') || '',cid=src.startsWith('cid:')?src.slice(4):'';
      const part=source.attachments.find(file=>file.cid===cid && cid);
      if(!part){img.remove();continue;}
      if(!urls.has(cid)) {
        const {blob}=await api('attachment',{...ref(source),part:part.part,inline:1,editor:1});
        urls.set(cid,await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=()=>reject(new Error('Obrázek konceptu se nepodařilo načíst.'));reader.readAsDataURL(blob);}));
      }
      img.setAttribute('src',urls.get(cid));
    }
    return doc.body.innerHTML;
  }
  // Seznam zachova predchozi prilohy; nad40MB upozorni na nezavisly limit serveru prijemce bez blokovani odeslani.
  function renderComposeFiles() {
    if (!compose) return;
    const limit=Number(state.attachment_limit)||20*1024*1024;
    node('[data-em-file-limit]').textContent='Přílohy (celkem nejvýše '+(limit/1024/1024)+' MB)';
    node('[data-em-compose-files]').innerHTML=[
      ...compose.stored_files.map((file,i)=>({file,index:'stored:'+i,size:storedFileBytes(file)})),
      ...compose.uploads.map((file,i)=>({file,index:'upload:'+i,size:file.size}))
    ].map(({file,index,size})=>'<div class="em-compose-file" role="listitem"><span title="'+esc(file.name)+'">'+esc(file.name)+'</span><small>('+Math.ceil(size/1024)+' kB)</small><button type="button" data-em-remove-file="'+index+'" aria-label="Odebrat přílohu '+esc(file.name)+'">×</button></div>').join('');
    window.CB_TOOLTIP?.init(node('[data-em-compose-files]'));
    const images=composeEditor?.imageSummary() || {count:0,bytes:0};
    const bytes=compose.uploads.reduce((sum,f)=>sum+f.size,0)+compose.stored_files.reduce((sum,f)=>sum+storedFileBytes(f),0)+images.bytes;
    node('[data-em-size-warning]').hidden=bytes<=40*1024*1024;
    node('[data-em-file-summary]').textContent=(compose.stored_files.length+compose.uploads.length+images.count)?'Celkem '+(bytes/1024/1024).toLocaleString('cs-CZ',{maximumFractionDigits:2})+' MB'+(images.count?' · obrázky v textu: '+images.count:'')+(compose.stored_files.length?' (orientačně)':''):'';
  }
  // Prevzeti pred otevrenim editoru umozni ostatnim videt, kdo uz odpoved pripravuje.
  async function composeMessage(kind = 'new') {
    if (dirty && !confirm('Zahodit rozepsanou zprávu?')) return;
    const source=kind==='new'?null:selected;
    if (!source && kind!=='new') return;
    let version=0;
    const draftContext=kind==='edit_draft'?source?.draft_context:null;
    const originalRef=draftContext?.source || (kind==='edit_draft'?null:(source?ref(source):null));
    if (source?.shared && originalRef) {
      const result=await api('claim',originalRef,true); source.work=result.work; version=Number(result.work.verze); renderMessage();
    }
    const editor=await richEditor('compose');
    const dialog=node('[data-em-compose]'), form=dialog.querySelector('form'); form.reset(); form.querySelector('.em-form-error').textContent='';
    const signature=state.preferences?.signature || '';
    const plainHtml=text=>String(text || '').split('\n').map(line=>`<p>${esc(line) || '<br>'}</p>`).join('');
    // Pred automatickym podpisem nechame ctyri prazdne radky pro vlastni text; ulozeny koncept se dale nacte beze zmeny.
    let bodyHtml=signature?'<p><br></p>'.repeat(4)+signature+'<p><br></p>':'<p><br></p>';
    const accounts=(state.accounts || []).filter(a => a.unlocked);
    form.elements.account.innerHTML=accounts.map(a=>`<option value="${a.id}">${esc(a.email)}</option>`).join('');
    form.elements.account.value=String(source?.account || account);form.elements.account.disabled=!!source;form.elements.account.hidden=!!source;
    node('[data-em-compose-sender]').hidden=!source;node('[data-em-compose-sender]').textContent=form.elements.account.selectedOptions[0]?.textContent || '';
    if (!form.elements.account.value) throw new Error('Nejdříve otevřete připojenou schránku.');
    node('[data-em-compose-title]').textContent=({new:'Nová zpráva',reply:'Odpověď',reply_all:'Odpověď všem',forward:'Přeposlání',edit_draft:'Úprava konceptu'})[kind];
    if (source) {
      const own=source.mailbox.toLowerCase();
      if (kind==='edit_draft') {
        form.elements.to.value=address(source.to); form.elements.cc.value=address(source.cc); form.elements.bcc.value=address(source.bcc); form.elements.subject.value=source.subject;
        // Koncept uz podpis obsahuje; pri dalsim otevreni se znovu nepridava a zachova sve HTML.
        bodyHtml=await draftEditorHtml(source);
      } else {
        const recipient=source.reply_to?.length?source.reply_to:source.from;
        const targets=kind==='reply_all'?[...recipient,...source.to]:recipient;
        if (kind!=='forward') form.elements.to.value=[...new Set(targets.map(a=>a.email).filter(a=>a.toLowerCase()!==own))].join(', ');
        if (kind==='reply_all') form.elements.cc.value=[...new Set((source.cc || []).map(a=>a.email).filter(a=>a.toLowerCase()!==own && !targets.some(t=>t.email.toLowerCase()===a.toLowerCase())))].join(', ');
        form.elements.subject.value=(kind==='forward'?'Fwd: ':'Re: ')+source.subject.replace(/^(Re|Fwd):\s*/i,'');
        // Puvodni citace neprochazi Quill editorem; server ji znovu nacte a pripoji bez ztraty tabulek a obrazku.
      }
    }
    // Nevyplnene kopie nezabiraji misto; adresy konceptu i odpovedi vsem zustanou vzdy viditelne.
    for(const field of ['cc','bcc'])setCopyField(field,!!form.elements[field].value.trim());
    // Informace o citaci se netyka obrazku napsanych primo do vlastniho textu.
    const quoteHtml=kind==='edit_draft'?(source.draft_quote_html??source.html):(source?.html || '');
    const quoteText=kind==='edit_draft' && source.draft_editor_html!==undefined?'':source?.text;
    const hasQuote=!!(quoteHtml || quoteText);
    node('[data-em-quote]').dataset.images=/<img\b[^>]*(?:data-em-cid|src=["']cid:)/i.test(quoteHtml || '')?'1':'0';
    clearMailFrames(node('[data-em-quote-preview]'));node('[data-em-quote-preview]').replaceChildren();
    node('[data-em-quote]').hidden=!source || !hasQuote;form.elements.include_quote.checked=!!source && hasQuote;syncComposeQuote();
    if(source && hasQuote){
      if(quoteHtml)renderMailFrame(node('[data-em-quote-preview]'),{...source,html:quoteHtml});
      else {const pre=document.createElement('pre');pre.textContent=quoteText;node('[data-em-quote-preview]').append(pre);}
    }
    compose={kind:kind==='edit_draft'?(draftContext?.kind || 'new'):kind,source:originalRef,draft_ref:kind==='edit_draft'?ref(source):null,version,token:crypto.randomUUID(),compose_format:4,delivery:null,account_locked:!!source,stored_files:['forward','edit_draft'].includes(kind)?source.attachments.filter(a=>!a.cid):[],uploads:[],quote_encoding:draftContext?.quote_encoding || source?.encoding?.selected || 'auto'}; dirty=false;
    editor.setHtml(bodyHtml);renderComposeFiles();syncComposeDelivery();dialog.showModal();node('[data-em-compose-content]').scrollTop=0;hideRecipients();node('[data-em-recipient-status]').hidden=true;refreshRecipients();
    if (kind==='forward' || kind==='new') form.elements.to.focus(); else editor.focus();
  }
  // Stejna volba ridi viditelnost citace pod podpisem i jeji pripojeni pri ulozeni a odeslani.
  function syncComposeQuote() {
    const attached=!node('[data-em-quote]').hidden && node('[data-em-compose-form]').elements.include_quote.checked;
    node('[data-em-quote-content]').hidden=!attached;
    node('[data-em-forward-files]').hidden=!attached || node('[data-em-quote]').dataset.images!=='1';
  }
  // Sbalit lze pouze prazdne pole; vyplneny prijemce nesmi zustat nepozorovane skryty.
  function setCopyField(field,visible) {
    if(!['cc','bcc'].includes(field))return;
    const form=node('[data-em-compose-form]');visible=visible || !!form.elements[field].value.trim();
    node('[data-em-copy-field='+field+']').hidden=!visible;
    node('[data-em-copy-toggle='+field+']').setAttribute('aria-expanded',String(visible));
    node('[data-em-copies]').hidden=['cc','bcc'].every(key=>node('[data-em-copy-field='+key+']').hidden);
  }
  async function closeCompose() {
    if (sending || compose?.delivery) return;
    if (dirty && !confirm('Zahodit rozepsanou zprávu?')) return;
    if (compose?.source && selected?.shared && compose.kind!=='draft_edit') await api('release',compose.source,true);
    hideRecipients();compose=null; dirty=false; node('[data-em-compose]').close();
    clearMailFrames(node('[data-em-quote-preview]'));
  }
  // Pocitadlo meri cestu prohlizec -> IS; nepotvrzuje jeste predani zpravy prijemcum pres SMTP.
  function composeUploadProgress(attempt,loaded,total) {
    if(compose!==attempt || !state)return;
    const status=node('[data-em-compose-form] .em-form-error'),mb=bytes=>(bytes/1048576).toLocaleString('cs-CZ',{minimumFractionDigits:1,maximumFractionDigits:1});
    status.textContent=total && loaded>=total?'Nahráno do IS '+mb(loaded)+' MB. Odesílám zprávu…':'Odesílám zprávu · nahráno do IS '+mb(loaded)+' MB'+(total?' z '+mb(total)+' MB':'');
    if(total){status.setAttribute('data-em-upload','');status.style.setProperty('--em-upload',Math.min(100,loaded/total*100)+'%');}
  }
  // Po predani SMTP nelze menit prijemce ani obsah a tlacitko opakuje vyhradne ulozeni kopie nebo kontrolu stavu.
  function syncComposeDelivery() {
    const form=node('[data-em-compose-form]');if(!form || !compose || !state)return;
    const pending=compose.delivery,locked=sending || !!pending;
    form.querySelectorAll('input,select,textarea,button').forEach(el=>{el.disabled=locked;});
    composeEditor?.enable(!locked);form.elements.account.disabled=locked || compose.account_locked;
    const submit=form.querySelector('[type=submit]');submit.disabled=sending;
    submit.textContent=pending?(sending?'Čekejte…':(['sent','expired'].includes(pending.stage)?'Zkusit uložit znovu':'Zkontrolovat stav')):'Odeslat';
    for(const button of form.querySelectorAll('[data-em-close],[data-em-action="draft"]'))button.hidden=!!pending;
    form.closest('dialog').classList.toggle('em-delivery-pending',!!pending);
    const status=form.querySelector('.em-form-error');status.setAttribute('role',pending?'status':'alert');status.setAttribute('aria-live',pending?'polite':'assertive');
    if(!pending){status.removeAttribute('data-em-upload');status.style.removeProperty('--em-upload');}
    form.setAttribute('aria-busy',String(sending));hideRecipients();
  }
  // Kratke prodlevy maji stejny AbortSignal jako API, aby neprezily konec prihlaseni nebo modulu.
  function deliveryWait(ms) {
    const signal=controller.signal;
    return new Promise((resolve,reject)=>{
      const abort=()=>{clearTimeout(timer);reject(new DOMException('Změna modulu','AbortError'));};
      const timer=setTimeout(()=>{signal.removeEventListener('abort',abort);resolve();},ms);
      if(signal.aborted)abort();else signal.addEventListener('abort',abort,{once:true});
    });
  }
  // Jen potvrzena kopie zavre editor; chyba evidence vyrizeni se nesmi vydavat za neuspesne SMTP.
  function completeDelivery(attempt,result) {
    if(compose!==attempt || !state)return;
    compose=null;dirty=false;node('[data-em-compose]').close();clearMailFrames(node('[data-em-quote-preview]'));
    notice(result.work_saved===false?'Zpráva odeslána a uložena. Stav vyřízení se nepodařilo aktualizovat; obnovte seznam.':'Zpráva odeslána a uložena do Odeslaných.',result.work_saved===false);
    if(attempt.source && selected?.shared && result.work_saved!==false){selected.work={...selected.work,stav:'odpovezeno'};renderMessage();}
    if(attempt.source)messages.forEach(m=>{if(selected && m.account===selected.account && m.uid===selected.uid){m.answered=true;if(result.work_saved!==false)m.work=selected.work;}});
    renderList();
  }
  // Ztracena HTTP odpoved vyzaduje nejprve kontrolu. Zruseni bezpecneho pokusu blokuje i jeho opozdeny upload.
  async function resolveDelivery(attempt,result=null,originalError='') {
    const error=node('[data-em-compose-form] .em-form-error');
    error.removeAttribute('data-em-upload');error.style.removeProperty('--em-upload');
    for(let retry=0;retry<3 && compose===attempt && state;retry++){
      if(retry)await deliveryWait(retry===1?2000:5000);
      if(compose!==attempt || !state)return;
      try {
        if(!result){
          error.textContent='Ověřuji stav odeslání…';
          result=await api('send_status',{account:attempt.delivery.account,token:attempt.token,cancel_safe:true},true);
        }
        if(compose!==attempt || !state)return;
        if(result.stored){completeDelivery(attempt,result);return;}
        if(result.send_state==='cancelled'){
          attempt.delivery=null;attempt.token=crypto.randomUUID();
          error.textContent=originalError || 'Zpráva nebyla odeslána. Můžete ji upravit a zkusit znovu.';return;
        }
        if(result.send_state==='uncertain'){
          attempt.delivery.stage='uncertain';
          error.textContent='Server nepotvrdil výsledek odeslání. Zprávu znovu neposílám. Ověřte doručení; zde lze znovu zkontrolovat stav.';return;
        }
        if(result.sent){
          attempt.delivery.stage=result.send_state;
          error.textContent=retry?'Zpráva je odeslaná. Znovu zkouším uložit kopii do Odeslaných…':'Zpráva je odeslaná. Ukládám kopii do Odeslaných…';
          const copied=await api('repair_sent',{account:attempt.delivery.account,token:attempt.token},true);
          if(compose!==attempt || !state)return;
          if(copied.stored){completeDelivery(attempt,copied);return;}
          if(copied.send_state==='expired'){
            attempt.delivery.stage='expired';error.textContent='Zpráva je odeslaná, ale dočasná kopie po 24 hodinách vypršela. Odeslání neopakujte; zkontrolujte Odeslané.';return;
          }
          // Busy pri oprave nemeni jiz potvrzeny uspech SMTP.
          result={send_state:'sent',sent:true,stored:false};
        }else{attempt.delivery.stage='checking';result=null;}
      }catch(e){
        if(e.name==='AbortError' || compose!==attempt || !state)throw e;
        // Pri vypadku kontroly nevime, zda SMTP probehlo; tlacitko Odeslat se nesmi obnovit.
        if(attempt.delivery.stage!=='sent' && attempt.delivery.stage!=='expired')result=null;
      }
    }
    if(compose!==attempt || !state)return;
    error.textContent=['sent','expired'].includes(attempt.delivery.stage)
      ?'Zpráva je odeslaná. Kopii se zatím nepodařilo uložit. Zkuste uložení znovu; příjemcům se již neposílá.'
      :'Stav odeslání zatím nelze ověřit. Zprávu znovu neposílám. Zkuste kontrolu stavu.';
  }
  async function send(draft = false) {
    if(sending || !compose)return;
    const form=node('[data-em-compose-form]'),attempt=compose;
    if(attempt.delivery){
      sending=true;syncComposeDelivery();
      try{await resolveDelivery(attempt,['sent','expired'].includes(attempt.delivery.stage)?{send_state:attempt.delivery.stage,sent:true,stored:false}:null);}
      catch(e){if(e.name!=='AbortError' && compose===attempt && state)errorContent(form.querySelector('.em-form-error'),e);}
      finally{sending=false;syncComposeDelivery();}return;
    }
    if(!draft && !form.reportValidity())return;
    sending=true;form.querySelectorAll('button').forEach(b=>b.disabled=true);form.querySelector('.em-form-error').textContent='';
    let submitted=false;
    try {
      await composeEditor.ready();if(compose!==attempt || !state)return;
      syncComposeDelivery();
      const content=composeEditor.mailContent();
      const {stored_files,uploads,delivery,account_locked,...context}=attempt;
      const data={...context,retained_parts:stored_files.map(f=>String(f.part)),account:Number(form.elements.account.value)};
      ['to','cc','bcc'].forEach(key=>{data[key]=form.elements[key].value.replace(/[;,\s]+$/,'');});
      data.subject=form.elements.subject.value;data.body=composeEditor.getText();data.body_html=content.html;data.inline_images=content.inline;
      data.include_quote=form.elements.include_quote.checked && !node('[data-em-quote]').hidden;
      const images=composeEditor.imageSummary(),limit=Number(state.attachment_limit)||20*1024*1024;
      if(images.count+uploads.length+stored_files.length>20 || images.bytes+uploads.reduce((n,f)=>n+f.size,0)+stored_files.reduce((n,f)=>n+storedFileBytes(f),0)>limit)throw new Error('Přílohy a vložené obrázky překročily celkový limit.');
      if(!draft){attempt.delivery={stage:'sending',account:data.account};data.started=Math.floor(Date.now()/1000);syncComposeDelivery();form.querySelector('.em-form-error').textContent='Odesílám zprávu…';}
      submitted=true;
      const progress=draft?null:(loaded,total)=>composeUploadProgress(attempt,loaded,total);
      const result=await api(draft?'draft':'send',data,true,[...content.files,...uploads],null,null,progress);
      if(compose!==attempt || !state)return;
      if(draft){
        dirty=false;
        if(result.draft_ref){attempt.draft_ref=result.draft_ref;attempt.stored_files=(result.draft_attachments || []).filter(a=>!a.cid);attempt.uploads=[];form.elements.attachments.value='';renderComposeFiles();}
        notice('Koncept uložen do schránky.');
      }else await resolveDelivery(attempt,result);
    }catch(e){
      if(e.name!=='AbortError' && compose===attempt && state){
        if(!draft && submitted)await resolveDelivery(attempt,null,e.message);
        else errorContent(form.querySelector('.em-form-error'),e);
      }
    }finally{sending=false;syncComposeDelivery();}
  }

  // Vyber cilove slozky probiha v IS; hromadny kos rozdeli souhrn podle puvodnich schranek.
  async function moveRows(rows, trash = false) {
    if (moving || node('[data-em-move]').open) return;
    rows=rows.filter(Boolean);
    // Ocekavana validace neni technicka chyba a nesmi odesilat administratorsky poplach.
    if (!rows.length) {notice(trash && folder===special.trash?'Vyberte zprávy ke smazání.':(trash?'Vyberte zprávy k přesunu do koše.':'Vyberte zprávy k přesunu.'),true);syncBulkActions();return;}
    const permanent=trash && rows.every(row=>row.folder===special.trash);
    const groups=new Map();
    for (const row of rows) {
      if (!groups.has(row.account)) groups.set(row.account,{account:row.account,messages:[]});
      groups.get(row.account).messages.push(ref(row));
    }
    if (!trash && groups.size>1) throw new Error('Pro hromadný přesun do složky vyberte zprávy z jedné schránky.');
    let options=[];
    for (const group of groups.values()) {
      const result=await api('folders',{account:group.account});
      const available=result.folders.filter(f=>f.selectable && !group.messages.some(m=>m.folder===f.path));
      if (trash) {
        if (permanent) {
          if (!group.messages.every(m=>m.folder===result.special.trash)) throw new Error('Trvale mazat lze pouze zprávy z koše.');
        } else if (!available.some(f=>f.path===result.special.trash)) throw new Error('Koš není dostupný nebo jsou vybrané zprávy již v koši.');
        group.target=result.special.trash;
      } else {
        const names={INBOX:'Příchozí pošta',[result.special.sent]:'Odeslané',[result.special.drafts]:'Rozepsané',[result.special.trash]:'Koš',[result.special.junk]:'Spam'};
        options=available.map(f=>`<option value="${esc(f.path)}">${esc(names[f.path] || f.name)}</option>`);
      }
    }
    if (!trash && !options.length) throw new Error('Není dostupná jiná cílová složka.');
    pendingMove={groups:Array.from(groups.values()),trash,permanent,total:rows.length,completed:0};
    const dialog=node('[data-em-move]'), form=dialog.querySelector('form'); form.reset();
    form.querySelector('.em-form-error').textContent='';
    node('[data-em-move-title]').textContent=permanent?'Trvale smazat zprávy':(trash?'Přesunout do koše':'Přesunout zprávy');
    node('[data-em-move-summary]').textContent='Vybráno zpráv: '+rows.length;
    node('[data-em-move-target-label]').hidden=trash; node('[data-em-move-trash-hint]').hidden=!trash;
    node('[data-em-move-trash-hint]').textContent=permanent?'Vybrané zprávy se trvale smažou z poštovního serveru pro všechny uživatele. Tuto akci nelze vrátit.':'Zprávy se přesunou do koše své schránky. Z koše je můžete přesunout zpět.';
    form.elements.target.disabled=trash; form.elements.target.innerHTML=options.join('');
    form.querySelector('[type=submit]').textContent=permanent?'Smazat':(trash?'Přesunout do koše':'Přesunout');
    node('[data-em-delete-preference]').hidden=!trash;
    form.elements.skip_confirm.disabled=!state.preferences?.settings_ready;
    if(trash && state.preferences?.confirm_delete===false){await confirmMove(form);if(pendingMove && !dialog.open)dialog.showModal();}
    else dialog.showModal();
  }

  // Zapis az po potvrzeni dialogu; dokoncene schranky pri chybe znovu nepresouvame.
  async function confirmMove(form) {
    if (!pendingMove || moving) return;
    const task=pendingMove; moving=true;
    form.querySelectorAll('button,select').forEach(b=>{b.disabled=true;});
    form.querySelector('.em-form-error').textContent='';
    try {
      if(task.trash && form.elements.skip_confirm.checked) {await savePreferences({confirm_delete:false});form.elements.skip_confirm.checked=false;}
      while (task.groups.length) {
        const group=task.groups[0];
        await api(task.permanent?'delete':'move',{account:group.account,messages:group.messages,target:task.trash?group.target:form.elements.target.value,...(task.permanent?{confirm:'smazat'}:{})},true);
        task.completed+=group.messages.length; task.groups.shift();
      }
      pendingMove=null; form.closest('dialog').close(); selected=null; page=1;
      await loadAccount(); notice((task.permanent?'Trvale smazáno zpráv: ':(task.trash?'Do koše přesunuto zpráv: ':'Přesunuto zpráv: '))+task.completed);
    } catch(e) {
      if (e.name!=='AbortError') errorContent(form.querySelector('.em-form-error'),e,task.completed?'Dokončeno pro '+task.completed+' zpráv. ':'');
    } finally {
      moving=false; form.querySelectorAll('button,select').forEach(b=>{b.disabled=false;});
      form.elements.target.disabled=task.trash;
    }
  }

  // Obsluha je delegovana jen do vlastni PP a vlastni navigace; nezasahuje do cizich formularu.
  async function action(name) {
    if(name==='max' || name==='normal'){root.classList.toggle('em-reader-max',name==='max');node(name==='max'?'[data-em-action=normal]':'[data-em-action=max]')?.focus();return;}
    if (name==='signature') return signatureDialog();
    if (name==='original' && selected) {
      const {blob}=await api('original',ref(selected));const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='zprava.eml';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);return;
    }
    if (name==='order') return signatureDialog('folders');
    if (name==='order_reset' && canOrderFolders() && state.preferences?.ready) {orderDraft=orderedFolders([]);orderReset=true;settingsDirty=true;settingsFoldersDirty=true;return renderOrder();}
    if (name==='connect') return connectDialog();
    if (name==='security') {
      if (!state.manager || node('[data-em-connect-form]').elements.type.value!=='osobni') return;
      const info=node('[data-em-security-info]'); info.hidden=!info.hidden;
      node('[data-em-security-toggle]').setAttribute('aria-expanded',String(!info.hidden));
      return;
    }
    if (name==='manage_shared') return sharedAdminDialog();
    if (name==='shared_access_report') return sharedAccessReport();
    if (['shared_save_access','shared_update_password','shared_delete'].includes(name))return sharedAdminChange(name);
    if (name==='disconnect') {
      const owner=personalOwner();if(!owner?.accounts.length)return;
      pendingDisconnect={user:owner.id,remote:owner.id!==personalConnections.self,accounts:owner.accounts.map(a=>({...a}))};
      const dialog=node('[data-em-disconnect]'),select=node('[data-em-disconnect-account]');
      select.innerHTML=pendingDisconnect.accounts.map(a=>`<option value="${a.id}">${esc(a.email)}</option>`).join('');
      select.value=String(personalAddressAccount()?.id || owner.accounts[0].id);
      node('[data-em-disconnect-choice]').hidden=owner.accounts.length<2;disconnectQuestion();
      dialog.querySelector('.em-form-error').textContent='';dialog.showModal();return;
    }
    // Cizi osobni odpojeni ma samostatny spravni pozadavek; ID z formulare neudeli pristup k poste.
    if (name==='disconnect_confirm') {
      const dialog=node('[data-em-disconnect]'),button=dialog.querySelector('[data-em-action="disconnect_confirm"]');
      const a=pendingDisconnect?.accounts.find(a=>a.id===Number(node('[data-em-disconnect-account]').value));
      if(!a || button.disabled)return;
      const buttons=Array.from(dialog.querySelectorAll('button'));buttons.forEach(b=>b.disabled=true);
      try{
        if(pendingDisconnect.remote)await api('personal_disconnect',{target_user:pendingDisconnect.user,personal_id:a.id,personal_revision:a.revision},true);
        else await api('disconnect',{account:a.id,personal_revision:a.revision},true);
        const form=node('[data-em-connect-form]');form.elements.password.value='';form.closest('dialog').close();dialog.close();
        await loadState(null,false);
      }catch(e){if(e.name!=='AbortError'){if(dialog.open)errorContent(dialog.querySelector('.em-form-error'),e);else notice('Schránka byla odpojena, ale přehled se nepodařilo obnovit. Obnovte modul E-mail.',true);}}
      finally{buttons.forEach(b=>b.disabled=false);}
      return;
    }
    if (name==='shared_active') {
      const form=node('[data-em-connect-form]');
      const a=state.shared_management.accounts.find(a=>Number(a.id)===Number(form.elements.shared_id.value));
      if (!a || !confirm(Number(a.aktivni)?'Vypnout přístup k této schránce v IS? Pošta na serveru zůstane zachovaná.':'Zapnout přístup k této schránce v IS?')) return;
      const button=node('[data-em-action="shared_active"]'); button.disabled=true;
      try { await api('shared_active',{shared_id:Number(a.id),revision:Number(a.revize),active:!Number(a.aktivni)},true); form.closest('dialog').close(); await loadState(); }
      catch(e) {errorContent(form.querySelector('.em-form-error'),e);}
      finally {button.disabled=false;}
      return;
    }
    if (['new','reply','reply_all','forward','edit_draft'].includes(name)) return composeMessage(name);
    if (name==='clear_search') return clearSearch();
    if (name==='refresh') {invalidateMailCache(aggregate?state.accounts.filter(a=>a.type!=='osobni').map(a=>a.id):[account]);return loadAccount();}
    if (name==='back') return root.classList.remove('em-has-message','em-reader-max');
    if (name==='prev' && !listLoading && page>1) {page--; return loadList(null,false,true);}
    if (name==='next' && !listLoading && page*pageSize<total) {page++; return loadList(null,false,true);}
    if (name==='draft') return send(true);
    if (name==='select') {const checks=root.querySelectorAll('[data-em-check]'); const all=Array.from(checks).every(c=>c.checked); checks.forEach(c=>c.checked=!all);syncBulkActions(); return;}
    if (name==='bulk_move') return moveRows(Array.from(root.querySelectorAll('[data-em-check]:checked')).map(c=>messages[Number(c.dataset.emCheck)]));
    if (name==='bulk_trash') return moveRows(Array.from(root.querySelectorAll('[data-em-check]:checked')).map(c=>messages[Number(c.dataset.emCheck)]),true);
    if (name==='logout') {await api('logout',{account},true); return loadState();}
    if (name.startsWith('folder_')) {
      const data={account,folder};
      if (name==='folder_delete') {if (!confirm('Trvale smazat složku „'+folder+'“ včetně všech zpráv?')) return; data.confirm=folder;}
      else { data.name=prompt(name==='folder_create'?'Název nové složky včetně případné cesty podsložky:':'Nový název složky včetně cesty:',name==='folder_rename'?folder:''); if (!data.name) return; }
      await api(name,data,true); return loadAccount();
    }
    if (!selected) return;
    if (name==='remote') {
      const show=!selected.images_visible;await openMessage(selected,show);
      if(show && state.preferences?.settings_ready && !state.preferences?.show_images) {
        const dialog=node('[data-em-image-choice]');dialog.querySelector('select').value='0';dialog.querySelector('.em-form-error').textContent='';
        const button=node('.em-images-toggle');dialog.style.top=Math.max(8,Math.min(button.getBoundingClientRect().bottom+4,window.innerHeight-240))+'px';dialog.showPopover();
      }
      return;
    }
    if (name==='move') return moveRows([selected]);
    if (name==='trash') return moveRows([selected],true);
    if (name==='unread' || name==='flag') {
      await api(name==='unread'?'read':'flag',{...ref(selected),value:name==='unread'?false:!selected.flagged},true); return loadList();
    }
    if (['claim','release','done'].includes(name)) {
      const result=await api(name,ref(selected),true); selected.work=result.work; renderMessage(); await loadList();
    }
  }

  // Pri vymene modulu zrusime i samostatne pozadavky schranek, aby neprepsaly novou obrazovku.
  function initialize() {
    const next=document.querySelector('[data-email-root]'); if (next===root) return;
    hideRecipients();recipientRefreshed.clear();
    listAbort?.abort();listAbort=null;listLoading=false;appliedSearch='';appliedBody=false;
    pageSize=50;pagePreferenceOwner='';listCache=null;
    if (controller) controller.abort(); clearAttachment(); clearMailFrames(root); generation++; root=next; controller=root?new AbortController():null;
    window.CB_COLUMN_SELECT?.destroy(nav?.querySelector('[data-em-accounts]'));
    nav=null; state=null; selected=null; compose=null; dirty=false; sending=false; account=0; aggregate=false; folder='INBOX'; page=1;
    composeEditor=null; signatureEditor=null; composeEditorTask=null; signatureEditorTask=null; settingsDirty=false; folderOrder=[];
    pendingMove=null; moving=false;sharedAdmin=null;sharedAdminDirty=false;sharedAdminAccessDirty=false;sharedAdminBusy=false;
    personalConnections=null;pendingDisconnect=null;
    accessReportSequence++;
    if (!root) return;
    const list=document.querySelector('.obal_main > .blok_menu .blok_menu_list') || document.querySelector('.blok_menu .blok_menu_list');
    if (list) {nav=document.createElement('li'); nav.className='em-navigation'; list.replaceChildren(nav);}
    run(loadState);
  }
  document.addEventListener('click',event=>{
    const option=event.target.closest?.('[data-em-recipient-option]');
    if(option && root?.contains(option)) {chooseRecipient(Number(option.dataset.emRecipientOption));return;}
    if(recipientInput && event.target!==recipientInput) hideRecipients();
    if (!root || (!(event.target instanceof Element)) || (!root.contains(event.target) && !nav?.contains(event.target))) return;
    const target=event.target.closest('button,[data-em-close]'); if (!target) return;
    if (target.hasAttribute('data-em-remove-file')) {
      event.preventDefault();if(sending || !compose)return;
      const [kind,index]=target.dataset.emRemoveFile.split(':');
      (kind==='stored'?compose.stored_files:compose.uploads).splice(Number(index),1);dirty=true;node('[data-em-compose-form] .em-form-error').textContent='';renderComposeFiles();
    }
    else if(target.hasAttribute('data-em-copy-toggle')) {
      event.preventDefault();const field=target.dataset.emCopyToggle;
      setCopyField(field,node('[data-em-copy-field='+field+']').hidden);
      if(!node('[data-em-copy-field='+field+']').hidden)node('[data-em-compose-form]').elements[field].focus();
    }
    else if(target.hasAttribute('data-em-image-dismiss')) {event.preventDefault();node('[data-em-image-choice]').hidePopover();}
    else if(target.hasAttribute('data-em-settings-tab')) {event.preventDefault();run(()=>settingsTabSelect(target.dataset.emSettingsTab));}
    else if(target.hasAttribute('data-em-account-up')) {event.preventDefault();const i=Number(target.dataset.emAccountUp);moveAccountOrder(i,i-1,'up');}
    else if(target.hasAttribute('data-em-account-down')) {event.preventDefault();const i=Number(target.dataset.emAccountDown);moveAccountOrder(i,i+1,'down');}
    else if (target.hasAttribute('data-em-shared-edit')) {event.preventDefault(); node('[data-em-connect-form]').elements.shared_id.value=target.dataset.emSharedEdit; connectFields(true);}
    else if (target.hasAttribute('data-em-order-up')) {event.preventDefault(); const i=Number(target.dataset.emOrderUp); moveOrder(i,i-1);}
    else if (target.hasAttribute('data-em-order-down')) {event.preventDefault(); const i=Number(target.dataset.emOrderDown); moveOrder(i,i+1);}
    else if (target.hasAttribute('data-em-action')) {event.preventDefault(); run(()=>action(target.dataset.emAction),target.dataset.emAction);}
    else if (target.hasAttribute('data-em-folder')) {event.preventDefault(); folder=target.dataset.emFolder; page=1; folderNavigation(); run(loadList);}
    else if (target.hasAttribute('data-em-message')) {
      // Klik musi patrit stale pripojenemu radku aktualniho seznamu, nikoli odstranenemu DOM pri prepnuti schranky.
      const row=messages[Number(target.dataset.emMessage)];
      if(!listLoading && node('[data-em-list]').contains(target) && row)run(()=>openMessage(row),'message');
    }
    else if (target.hasAttribute('data-em-attachment')) run(()=>openAttachment(target.dataset.emAttachment),'attachment');
    else if (target.hasAttribute('data-em-close')) {
      const dialog=target.closest('dialog');
      if(dialog.hasAttribute('data-em-shared-admin')){if(sharedAdminBusy || (sharedAdminDirty && !confirm('Zahodit neuložené nastavení sdílené schránky?')))return;sharedAdminDirty=false;dialog.querySelector('[name=managed_password]').value='';dialog.close();return;}
      if (dialog.hasAttribute('data-em-attachment-dialog')) {clearAttachment();dialog.close();return;}
      if (dialog.hasAttribute('data-em-move')) {if (moving) return; pendingMove=null; formResetMove(dialog); return;}
      if (dialog.hasAttribute('data-em-compose')) run(closeCompose);
      else {if (settingsDirty && !confirm('Zahodit neuložené nastavení?')) return; settingsDirty=false; dialog.querySelector('form')?.reset(); dialog.close();}
    }
  });
  // Zruseni presunu zavre jen vlastni formular a nikdy nesahne na oznacene zpravy.
  function formResetMove(dialog) {dialog.querySelector('form').reset();dialog.close();}
  document.addEventListener('change',event=>{
    if(root?.contains(event.target) && event.target.matches('[data-em-check]')){syncBulkActions();return;}
    if(root?.contains(event.target) && event.target.closest('[data-em-shared-admin-form]')){
      const form=event.target.closest('form');
      if(event.target.name==='managed_account'){
        if(sharedAdminBusy || (sharedAdminDirty && !confirm('Zahodit neuložené nastavení této schránky?'))){event.target.value=event.target.dataset.previous;return;}
        sharedAdminDirty=false;renderSharedAdmin(Number(event.target.value));
      }else if(event.target.matches('[type=checkbox]')){sharedAdminDirty=true;sharedAdminAccessDirty=true;}
      return;
    }
    if (!root) return;
    if(root.contains(event.target) && event.target.matches('[data-em-signature-form] select[name=show_images],[data-em-signature-form] select[name=confirm_delete]')){settingsDirty=true;settingsOptionsDirty=true;}
    if(root.contains(event.target) && event.target.matches('[data-em-compose-form] [name=include_quote]')){syncComposeQuote();dirty=true;return;}
    if(root.contains(event.target) && event.target.matches('[data-em-compose-form] [name=attachments]') && compose) {
      const added=Array.from(event.target.files), limit=Number(state.attachment_limit)||20*1024*1024;
      const images=composeEditor.imageSummary();
      const files=[...compose.uploads,...added], count=files.length+compose.stored_files.length+images.count;
      const bytes=files.reduce((sum,f)=>sum+f.size,0)+compose.stored_files.reduce((sum,f)=>sum+storedFileBytes(f),0)+images.bytes;
      const error=node('[data-em-compose-form] .em-form-error');
      if(count>20 || bytes>limit) error.textContent='Celková velikost příloh nesmí překročit '+(limit/1024/1024)+' MB a 20 souborů.';
      else {compose.uploads=files;dirty=true;error.textContent='';}
      event.target.value='';renderComposeFiles();return;
    }
    if(root.contains(event.target) && event.target.matches('[data-em-encoding]') && selected){selected.encoding={...selected.encoding,selected:event.target.value};run(()=>openMessage(selected));return;}
    if(root.contains(event.target) && event.target.matches('[data-em-page-size]')) {
      pageSize=Number(event.target.value);if(![25,50,100].includes(pageSize))pageSize=50;
      try {localStorage.setItem('cb.email.page_size.'+pagePreferenceOwner,String(pageSize));}catch {}
      page=1;run(()=>loadList(null,false,true));
    }
    if(root.contains(event.target) && event.target.matches('[data-em-compose-form] [name=account]')) refreshRecipients();
    if (event.target.matches('[data-em-accounts]') && nav?.contains(event.target)) {
      if(event.target.value==='settings_order'){event.target.value=aggregate?'all':String(account);renderMailboxCounts();run(()=>signatureDialog('accounts'));return;}
      // Pamatuj filtr prenese jen potvrzene hledani; rozepsany text se nikdy sam nestane dotazem.
      const searchForm=node('[data-em-search]');
      if(!searchForm.elements.remember.checked) {appliedSearch='';appliedBody=false;}
      searchForm.elements.search.value=appliedSearch;
      if(appliedSearch)searchForm.elements.body.checked=appliedBody;
      searchIndicator();
      aggregate=event.target.value==='all'; account=aggregate?state.accounts.find(a=>a.type!=='osobni').id:Number(event.target.value); folder='INBOX'; page=1;renderMailboxCounts();run(loadAccount);
    }
    if (root.contains(event.target) && event.target.matches('[name=type]')) {
      connectFields(true);
    }
    if (root.contains(event.target) && event.target.matches('[data-em-connect-form] [name=target_user]')) {personalAccountOptions(true);connectFields(true);}
    if (root.contains(event.target) && event.target.matches('[data-em-connect-form] [name=personal_id]')) connectFields(true);
    if (root.contains(event.target) && event.target.matches('[data-em-connect-form] [name=mode]')) personalPasswordFields();
    if (root.contains(event.target) && event.target.matches('[data-em-disconnect-account]')) disconnectQuestion();
    if (root.contains(event.target) && event.target.matches('[name=shared_id]')) connectFields(true);
    if (root.contains(event.target) && event.target.matches('[name=shared_kind]')) connectFields();
    if (root.contains(event.target) && event.target.matches('[name=unseen]')) {page=1;run(loadList);}
  });
  document.addEventListener('input',event=>{
    if(!root?.contains(event.target))return;
    if(event.target.matches('[data-em-connect-form] [name=local_part]'))personalPasswordFields();
    if(event.target.matches('[data-em-admin-filter]')){const term=event.target.value.toLocaleLowerCase('cs');node('[data-em-admin-users]').querySelectorAll('label').forEach(label=>label.hidden=!label.textContent.toLocaleLowerCase('cs').includes(term));}
    else if(event.target.name==='managed_password')sharedAdminDirty=true;
    else if(event.target.closest('[data-em-compose-form]'))dirty=true;
  });
  // Nativni krizek, Delete i Backspace pri uplnem vymazani pole opravdu zrusi potvrzeny filtr.
  document.addEventListener('input',event=>{
    if(root?.contains(event.target) && event.target.matches('[data-em-search] [name=search]')) {
      searchClearButton();if(!event.target.value.trim() && appliedSearch)run(clearSearch);
    }
  });
  // Prohlizece mohou po stisku nativniho krizku oznamit i samostatnou udalost search; druhy pozadavek uz nevznikne.
  document.addEventListener('search',event=>{
    if(root?.contains(event.target) && event.target.matches('[data-em-search] [name=search]')) {
      searchClearButton();if(!event.target.value.trim() && appliedSearch)run(clearSearch);
    }
  });
  // Mys zachova fokus v poli, klavesnice nabidku ovlada bez nechteneho odeslani formulare.
  document.addEventListener('mousedown',event=>{if(root?.contains(event.target) && event.target.closest?.('[data-em-recipient-option]')) event.preventDefault();});
  document.addEventListener('focusout',event=>{if(event.target===recipientInput) hideRecipients();});
  document.addEventListener('focusin',event=>{if(root?.contains(event.target) && event.target.matches('[data-em-recipient]')) suggestRecipients(event.target);});
  document.addEventListener('input',event=>{
    if(!root?.contains(event.target) || !event.target.matches('[data-em-recipient]')) return;
    hideRecipients();const input=event.target;recipientTimer=setTimeout(()=>suggestRecipients(input),180);
  });
  document.addEventListener('keydown',event=>{
    if(root?.contains(event.target) && event.target.matches('[data-em-settings-tab]') && ['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) {
      event.preventDefault();const tabs=Array.from(node('[data-em-signature]').querySelectorAll('[data-em-settings-tab]'));
      const i=tabs.indexOf(event.target),next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(i+(event.key==='ArrowRight'?1:tabs.length-1))%tabs.length;
      tabs[next].focus();run(()=>settingsTabSelect(tabs[next].dataset.emSettingsTab));return;
    }
    if(event.target!==recipientInput || !recipientItems.length) return;
    if(event.key==='Escape') {event.preventDefault();event.stopPropagation();hideRecipients();return;}
    if(['Enter','Tab'].includes(event.key)) {event.preventDefault();chooseRecipient(recipientActive);return;}
    if(['ArrowDown','ArrowUp'].includes(event.key)) {
      event.preventDefault();recipientActive=(recipientActive+(event.key==='ArrowDown'?1:-1)+recipientItems.length)%recipientItems.length;
      const list=recipientInput.parentElement.querySelector('[role=listbox]');
      Array.from(list.children).forEach((el,i)=>el.setAttribute('aria-selected',String(i===recipientActive)));
      const active=list.children[recipientActive];recipientInput.setAttribute('aria-activedescendant',active.id);active.scrollIntoView({block:'nearest'});
    }
  });
  document.addEventListener('dragstart',event=>{
    const row=event.target.closest?.('[data-em-order-index]');
    if (!row || !root?.contains(row)) return;
    orderDragged=Number(row.dataset.emOrderIndex); event.dataTransfer.effectAllowed='move'; event.dataTransfer.setData('text/plain',String(orderDragged));
  });
  document.addEventListener('dragover',event=>{if (orderDragged>=0 && root?.contains(event.target) && event.target.closest?.('[data-em-order-index]')) event.preventDefault();});
  document.addEventListener('drop',event=>{
    const row=event.target.closest?.('[data-em-order-index]'); if (!row || !root?.contains(row) || orderDragged<0) return;
    event.preventDefault(); moveOrder(orderDragged,Number(row.dataset.emOrderIndex)); orderDragged=-1;
  });
  document.addEventListener('dragend',()=>{orderDragged=-1;});
  document.addEventListener('submit',event=>{
    if (!root || !root.contains(event.target)) return;
    const form=event.target; event.preventDefault();
    if (form.matches('[data-em-search]')) run(submitSearch);
    else if (form.matches('[data-em-move-form]')) run(()=>confirmMove(form),'move');
    else if (form.matches('[data-em-compose-form]')) run(()=>send(),'send');
    else if(form.matches('[data-em-image-choice-form]')) run(async()=>{
      const button=form.querySelector('[type=submit]');button.disabled=true;
      try {await savePreferences({show_images:form.elements.show_images.value==='1'});node('[data-em-image-choice]').hidePopover();}
      catch(e){if(e.name!=='AbortError')errorContent(form.querySelector('.em-form-error'),e);}
      finally{button.disabled=false;}
    });
    else if (form.matches('[data-em-signature-form]')) run(async()=>{
      const button=form.querySelector('[type=submit]'); button.disabled=true; form.querySelector('.em-form-error').textContent='';
      try {
        if(settingsOptionsDirty || settingsOrderDirty) {
            const values={};if(settingsOptionsDirty){values.show_images=form.elements.show_images.value==='1';values.confirm_delete=form.elements.confirm_delete.value==='1';}
            if(settingsOrderDirty)values.account_order=accountOrderDraft.map(a=>a.id);
            await savePreferences(values);settingsOptionsDirty=false;settingsOrderDirty=false;
          }
        if(settingsSignatureDirty){const result=await api('save_signature',{html:signatureEditor.getHtml()},true);state.preferences.signature=result.signature;settingsSignatureDirty=false;}
        if(settingsFoldersDirty){
          const result=await api('save_order',{account,order:orderReset?[]:orderDraft.map(f=>f.path)},true);folderOrder=result.order;
          // Pri navratu ke schrance nesmi pamet vratit poradi pred poslednim ulozenim.
          const cached=folderCaches.get(JSON.stringify([state.owner,account]))?.data;if(cached)cached.order=result.order;
          settingsFoldersDirty=false;folderNavigation();
        }
        settingsDirty=false; form.closest('dialog').close(); notice('Osobní nastavení bylo uloženo.');
      } catch (e) {if(e.name!=='AbortError')errorContent(form.querySelector('.em-form-error'),e);}
      finally {button.disabled=!(settingsTab==='signature'?state?.preferences?.ready:settingsTab==='folders'?state?.preferences?.ready && canOrderFolders():state?.preferences?.settings_ready);}
    });
    else if (form.matches('[data-em-connect-form],[data-em-unlock-form]')) run(async()=>{
      const button=form.querySelector('[type=submit]'); button.disabled=true; const error=form.querySelector('.em-form-error'); error.textContent='';
      try {
        let connectedOwner=null;
        if (form.matches('[data-em-connect-form]')) {
          const a=(state.shared_management?.accounts || []).find(a=>Number(a.id)===Number(form.elements.shared_id.value));
          connectedOwner=form.elements.type.value==='osobni'?personalOwner():null;
          const personal=connectedOwner?personalAddressAccount():null;
          const extra=Object.fromEntries(Array.from(form.querySelectorAll('[data-em-extra-password]'),i=>[i.dataset.emExtraPassword,i.value]));
          await api('connect',{local_part:form.elements.local_part.value,password:form.elements.password.value,type:form.elements.type.value,mode:form.elements.mode.value,
            target_user:connectedOwner?.id,personal_id:personal?.id || 0,personal_revision:personal?.revision || 0,mode_revision:connectedOwner?.mode_revision,extra_passwords:extra,
            shared_id:Number(form.elements.shared_id.value),revision:Number(a?.revize || 0),shared_kind:form.elements.shared_kind.value,branch:Number(form.elements.branch.value)},true);
        }
        else await api('unlock',{account,password:form.elements.password.value},true);
        form.reset(); form.closest('dialog').close(); await loadState(null,false);
        if(connectedOwner && connectedOwner.id!==personalConnections.self)notice('Osobní schránka byla připojena uživateli '+connectedOwner.name+'.',false,true);
      } catch (e) {
        if (e.name!=='AbortError') {
          errorContent(error,e);
          // Po kolizi ukazeme cerstvy seznam, ale neprepiseme uzivateli rozepsane hodnoty formulare.
          if (form.matches('[data-em-connect-form]') && form.elements.type.value==='shared' && state?.manage_shared) {
            try {const fresh=await api('state'); state.shared_management=fresh.shared_management; state.manage_shared=fresh.manage_shared; sharedConnections();}
            catch (_) { /* Puvodni chyba zustava zobrazena i pri nedostupnem obnoveni seznamu. */ }
          }
        }
      } finally {button.disabled=false; form.elements.password.value='';form.querySelectorAll('[data-em-extra-password]').forEach(i=>{i.value='';});}
    });
  });
  document.addEventListener('cancel',event=>{
    if (!root?.contains(event.target)) return;
    if(event.target.hasAttribute('data-em-shared-admin')){if(sharedAdminBusy || (sharedAdminDirty && !confirm('Zahodit neuložené nastavení sdílené schránky?')))event.preventDefault();else{sharedAdminDirty=false;event.target.querySelector('[name=managed_password]').value='';}return;}
    if (event.target.hasAttribute('data-em-attachment-dialog')) {clearAttachment();return;}
    if (event.target.hasAttribute('data-em-move')) {if (moving) event.preventDefault();else pendingMove=null;return;}
    if (event.target.hasAttribute('data-em-compose')) {event.preventDefault();run(closeCompose);}
    else if (settingsDirty) {if(!confirm('Zahodit neuložené nastavení?'))event.preventDefault();else settingsDirty=false;}
  },true);
  document.addEventListener('cb:main-before-load',event=>{
    if (root && (activeOperations>0 || sending || compose?.delivery || moving || sharedAdminBusy || ((dirty || settingsDirty || sharedAdminDirty) && !confirm('Opustit E-mail a zahodit neuložené změny?')))) event.preventDefault();
  });
  // Zavreni nebo plne opusteni stranky behem operace vyzaduje i standardni upozorneni prohlizece.
  window.addEventListener('beforeunload',event=>{if (root && (activeOperations>0 || dirty || settingsDirty || sharedAdminDirty || sharedAdminBusy || sending || compose?.delivery || moving)) {event.preventDefault();event.returnValue='';}});
  document.addEventListener('cb:main-swapped',initialize);
  // Postovni kontrola bezi oddelene i mimo modul a vyuzije pouze schranky odemcene v aktualnim loginu.
  async function background() {
    const link=document.querySelector('[data-cb-email-api]');
    if (!link || document.hidden || backgroundRunning) return;
    backgroundRunning=true;const owner=state?.owner;
    let measure=null,measureStatus='error';
    try {
      const url=new URL(link.dataset.cbEmailApi,location.href); url.searchParams.set('action','background');
      measure=window.CB_DIAG?.request('email','background',url.href,{background:1});
      const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json','X-Comeback-Pc-Session':String(window.CB_PC_SESSION_TOKEN || ''),...measure?.headers}});
      window.CB_DIAG?.adopt(response,'email');measure?.received(response);measureStatus=response.ok?'headers':'error';
      if ([401,403,409].includes(response.status)) { document.querySelectorAll('[data-cb-email-unread]').forEach(dot=>{dot.hidden=true;dot.textContent='';}); return; }
      if(response.ok){const payload=await response.json();if(payload.ok && root?.isConnected && state?.owner===owner)applyUnreadCounts(payload.unread);}
      if (response.ok && window.CB_HELPDESK_HEADER) window.CB_HELPDESK_HEADER.refresh();
    } catch (_) { /* Nedostupna posta nesmi blokovat praci v ostatnich modulech. */ }
    finally {backgroundRunning=false;measure?.end(measureStatus);}
  }
  setInterval(background,60000);
  // Zamek rozepsane odpovedi ma omezenou platnost; po zavreni okna se sam uvolni.
  setInterval(()=>{
    if (!root?.isConnected || document.hidden || sending || busy || activeOperations>0 || window.CB_PAGE_BUSY?.active() || !account) return;
    busy=true;
    run(async()=>{
      if (compose?.source && selected?.shared && compose.kind!=='draft_edit') {
        const result=await api('renew',compose.source,true); compose.version=Number(result.work.verze);
      } else if (!node('dialog[open]') && currentAccount()?.unlocked && !listLoading) await refreshVisibleStates();
    }).finally(()=>{busy=false;});
  },60000);
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',initialize); else initialize();
}());
