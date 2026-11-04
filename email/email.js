/* E-mail: osobni precteni, izolovane HTML s CID obrazky a puvodni citace mimo editor; original a kodovani v nabidce Dalsi. */
(function () {
  'use strict';
  let root = null, nav = null, state = null, account = 0, aggregate = false, folder = 'INBOX', folders = [], special = {};
  let messages = [], selected = null, page = 1, total = 0, compose = null, dirty = false, sending = false, generation = 0, busy = false;
  let controller = null, listSequence = 0, messageSequence = 0, accountSequence = 0, backgroundRunning = false;
  let folderOrder = [], orderDraft = [], orderReset = false, orderDragged = -1, settingsDirty = false;
  let editorModule = null, composeEditor = null, signatureEditor = null;
  let composeEditorTask = null, signatureEditorTask = null;
  let pendingMove = null, moving = false;
  let attachmentUrl=null, attachmentSequence=0;
  let appliedSearch='', appliedBody=false, listLoading=false, listAbort=null;
  let pageSize=50, pagePreferenceOwner='', listCache=null;
  const viewCaches=new Map(), folderCaches=new Map();let cacheOwner='';
  let recipientInput=null, recipientItems=[], recipientActive=0, recipientSequence=0, recipientTimer=null;
  const recipientRefreshed=new Map();
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const node = selector => root && root.querySelector(selector);
  const ref = m => ({account:m.account, folder:m.folder, validity:m.validity, uid:m.uid});
  const address = values => (values || []).map(a => a.email).join(', ');
  const label = values => (values || []).map(a => a.name ? a.name + ' <' + a.email + '>' : a.email).join(', ');

  // Kazdy pozadavek nese identitu okna IS; zadne heslo se neuklada do URL ani localStorage.
  async function api(action, data = {}, write = false, files = null, requestSignal = null, diagnosticGroup = null) {
    if (!root || !controller) throw new Error('Modul již není otevřený.');
    const ownGeneration = generation;
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
    let measureStatus='error',measureBytes=null;
    try {
      const response = await fetch(url, options);
      window.CB_DIAG?.adopt(response,'email');measure?.received(response);
      if (generation !== ownGeneration) throw new DOMException('Změna modulu', 'AbortError');
      if ([401,403,409].includes(response.status)) {
        viewCaches.clear();folderCaches.clear();listCache=null;
        messages=[]; selected=null; dirty=false;
        clearAttachment();clearMailFrames(root);
        settingsDirty=false; composeEditor?.setHtml(''); signatureEditor?.setHtml('');
        root.querySelectorAll('dialog').forEach(d => {d.querySelector('form')?.reset(); if (d.open) d.close();});
        node('[data-em-reader]').replaceChildren(); node('[data-em-list]').replaceChildren();
        if (nav) nav.replaceChildren();
        root.querySelectorAll('button,input,select,textarea').forEach(el=>{el.disabled=true;}); state=null;
        if (response.status===401 || response.status===409) throw new Error('Přihlášení nebo platnost tohoto okna skončila. Přihlaste se znovu do IS.');
      }
      if (['attachment','original'].includes(action) && response.ok) {
        const blob=await response.blob();measureBytes=blob.size;measureStatus='ok';
        return {blob,officeUrl:response.headers.get('X-Comeback-Office-Url'),officeScheme:response.headers.get('X-Comeback-Office-Scheme'),officeLabel:response.headers.get('X-Comeback-Office-Label')};
      }
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.error || payload.err || 'Poštovní operaci se nepodařilo dokončit.');
      if(write)updateCachedMail(action,data,payload);
      if(write && payload.unread)applyUnreadCounts(payload.unread);
      measureStatus='ok';return payload;
    } catch(error) {measureStatus=error.name==='AbortError'?'abort':'error';throw error;}
    finally {measure?.end(measureStatus,measureBytes);ownDiagnosticGroup?.end(measureStatus);}
  }

  // Chyba uvnitr posty nemeni stav ani obsah jineho modulu.
  function notice(message, error = false) {
    const n = node('[data-em-notice]'); if (!n) return;
    n.textContent=message; n.classList.toggle('is-error',error);
  }
  async function run(task) {
    try { await task(); } catch (e) { if (e.name !== 'AbortError') notice(e.message,true); }
  }
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
    } else if(['move','delete','send','draft','repair_sent','folder_create','folder_rename','folder_delete','connect','logout','unlock','shared_active'].includes(action))invalidateMailCache(data.account?[Number(data.account)]:null);
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

  // Sidebar pouziva bezne menu a zapati IS; nevytvari druhou prazdnou navigaci vedle klienta.
  function navigation() {
    if (!nav) return;
    const accounts = state.accounts || [];
    let options = '';
    const personal = accounts.filter(a => a.type==='osobni'), shared = accounts.filter(a => a.type!=='osobni');
    if (personal.length) options += '<optgroup label="Moje pošta">' + personal.map(a => `<option value="${a.id}"${!aggregate && account===a.id?' selected':''}>${esc(a.email)}</option>`).join('') + '</optgroup>';
    if (shared.length) options += '<optgroup label="Společná pošta">' + (state.shared ? `<option value="all"${aggregate?' selected':''}>Všechny pobočky a INFO</option>` : '') + shared.map(a => `<option value="${a.id}"${!aggregate && account===a.id?' selected':''}>${esc(a.email)}</option>`).join('') + '</optgroup>';
    nav.innerHTML = `<button class="em-new" data-em-action="new"${!accounts.length?' disabled':''}>＋ Napsat</button>
      <select aria-label="Výběr schránky" data-em-accounts${!accounts.length?' disabled':''}>${options || '<option>Žádná připojená schránka</option>'}</select>
      <div class="em-folders" data-em-folders></div>
      <div class="em-folder-actions"><button data-em-action="folder_create">＋ Složka</button><button data-em-action="folder_rename">Přejmenovat</button><button data-em-action="folder_delete">Smazat</button></div><button data-em-action="order">Upravit pořadí</button><button data-em-action="signature">Můj podpis</button><hr>
      ${state.manager ? '<button data-em-action="connect">⚙ Připojení a ověřování</button>' : ''}
      ${state.manage_shared ? '<button data-em-action="manage_shared">Správa společných schránek</button>' : ''}
      ${currentAccount()?.type==='osobni' && !aggregate ? '<button data-em-action="logout">Odhlásit schránku</button>' : ''}`;
    renderMailboxCounts();folderNavigation();
  }
  // Pocet je pred adresou, aby zustal viditelny i v uzkem zavrenem selectu. Neznamy pocet neni nula.
  function renderMailboxCounts() {
    const select=nav?.querySelector('[data-em-accounts]');if(!select || !state)return;
    const shared=state.accounts.filter(a=>a.type!=='osobni');
    const sharedCount=shared.every(a=>typeof a.unread==='number')?shared.reduce((sum,a)=>sum+a.unread,0):null;
    const prefix=count=>'['+(typeof count==='number'?count.toLocaleString('cs-CZ'):'—')+'] ';
    for(const option of select.options) {
      if(option.value==='all')option.textContent=prefix(sharedCount)+'Všechny pobočky a INFO';
      else {const a=state.accounts.find(a=>a.id===Number(option.value));if(a)option.textContent=prefix(a.unread)+a.email;}
    }
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
    nav.querySelector('[data-em-action="order"]').disabled=aggregate || !account || !folders.length;
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
      composeEditorTask??=module.createEditor(node('[data-em-compose-editor]'),'Zpráva',()=>{dirty=true;});
      const editor=await composeEditorTask;
      if (ownGeneration!==generation) throw new DOMException('Změna modulu','AbortError');
      composeEditor=editor;
      return composeEditor;
    }
    signatureEditorTask??=module.createEditor(node('[data-em-signature-editor]'),'Podpis',()=>{settingsDirty=true;});
    const editor=await signatureEditorTask;
    if (ownGeneration!==generation) throw new DOMException('Změna modulu','AbortError');
    signatureEditor=editor;
    return signatureEditor;
  }

  async function signatureDialog() {
    const editor=await richEditor('signature'); editor.setHtml(state.preferences?.signature || ''); editor.enable(!!state.preferences?.ready); settingsDirty=false;
    const dialog=node('[data-em-signature]');
    dialog.querySelector('.em-form-error').textContent=state.preferences?.ready?'':'Osobní nastavení zatím není připravené k ukládání.';
    dialog.querySelector('[type=submit]').disabled=!state.preferences?.ready; dialog.showModal(); editor.focus();
  }

  function orderDialog() {
    orderDraft=orderedFolders(); orderReset=false; settingsDirty=false; renderOrder();
    const dialog=node('[data-em-order]');
    dialog.querySelector('.em-form-error').textContent=state.preferences?.ready?'':'Osobní nastavení zatím není připravené k ukládání.';
    dialog.querySelector('[type=submit]').disabled=!state.preferences?.ready; dialog.showModal();
    dialog.querySelector('[data-em-action="order_reset"]').disabled=!state.preferences?.ready;
  }
  function renderOrder(focusIndex = -1) {
    const names={INBOX:'Příchozí pošta',[special.sent]:'Odeslané',[special.drafts]:'Rozepsané',[special.junk]:'Spam',[special.trash]:'Koš'};
    node('[data-em-order-list]').innerHTML=orderDraft.map((f,i)=>`<div class="em-order-row" draggable="${!!state.preferences?.ready}" data-em-order-index="${i}"><span aria-hidden="true">⠿</span><span>${esc(names[f.path] || f.name)}</span><button type="button" data-em-order-up="${i}" aria-label="Posunout ${esc(names[f.path] || f.name)} nahoru"${i===0 || !state.preferences?.ready?' disabled':''}>↑</button><button type="button" data-em-order-down="${i}" aria-label="Posunout ${esc(names[f.path] || f.name)} dolů"${i===orderDraft.length-1 || !state.preferences?.ready?' disabled':''}>↓</button></div>`).join('');
    if (focusIndex>=0) node(`[data-em-order-index="${focusIndex}"] button:not(:disabled)`)?.focus();
  }
  function moveOrder(from,to) {
    if (from<0 || to<0 || from>=orderDraft.length || to>=orderDraft.length || from===to) return;
    orderDraft.splice(to,0,orderDraft.splice(from,1)[0]); orderReset=false; settingsDirty=true; renderOrder(to);
  }

  // Prvni otevreni zahrnuje stav, slozky i seznam; deti sdileji ID cele operace.
  async function loadState(diagnosticGroup = null) {
    const task=group=>loadStateCore(group);
    return window.CB_DIAG?window.CB_DIAG.run('email','open_email',{},task,diagnosticGroup):task(null);
  }
  async function loadStateCore(diagnosticGroup = null) {
    state = await api('state',{},false,null,null,diagnosticGroup);
    if(cacheOwner!==state.owner){viewCaches.clear();folderCaches.clear();cacheOwner=state.owner;}
    loadPageSize();
    if (state.installed === false || state.configured === false) {
      notice(state.message || 'Modul čeká na nastavení.');
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
      if (state.manage_shared) await connectDialog('shared'); else if (state.manager) await connectDialog();
      return;
    }
    await loadAccount(diagnosticGroup);
  }
  // Zmena schranky zrusi stare hledani jeste pred nacitanim jejiho seznamu slozek.
  // Vypinatelne mereni cele operace zachova puvodni vysledek i chybu.
  async function loadAccount(diagnosticGroup = null) {
    const task=group=>loadAccountCore(group);
    return window.CB_DIAG?window.CB_DIAG.run('email','loadaccount',{account,aggregate:aggregate?1:0},task,diagnosticGroup):task(null);
  }
  async function loadAccountCore(diagnosticGroup = null) {
    const ownSequence=++accountSequence, ownAccount=account;
    listAbort?.abort();listLoading=false;
    listSequence++; messageSequence++;
    clearMailFrames(root);selected=null; messages=[]; folders=[]; folderOrder=[]; root.classList.remove('em-has-message');
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
    root.querySelectorAll('[data-em-action="select"],[data-em-action="bulk_move"],[data-em-action="bulk_trash"]').forEach(b=>{b.disabled=listLoading;});
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
        if(result.errors.length){cache.errors.set(a.id,result.errors.join(' '));diagnosticGroup?.outcome('incomplete');}
        return result;
      } catch(e) {
        if(!active()){diagnosticGroup?.outcome('superseded');return null;}
        if(!state){abort.abort();listLoading=false;notice(e.message,true);return null;}
        diagnosticGroup?.outcome(timedOut?'timeout':'incomplete');cache.errors.set(a.id,a.email+': '+(timedOut?'vypršel čas načítání.':e.message));return null;
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
      notice(errors.length?'Neúplné výsledky · '+errors.join(' '):(shared?'Společná pošta':currentAccount()?.email || '')+(search?' · Hledání „'+search+'“':'')+' · '+total.toLocaleString('cs-CZ')+' zpráv',errors.length>0);
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
  // Vypinatelne mereni cele operace zachova puvodni vysledek i chybu.
  async function openMessage(row, remote = true, diagnosticGroup = null) {
    const task=group=>openMessageCore(row, remote, group);
    return window.CB_DIAG?window.CB_DIAG.run('email','openmessage',{account:row.account,uid:row.uid},task,diagnosticGroup):task(null);
  }
  async function openMessageCore(row, remote = true, diagnosticGroup = null) {
    const seq=++messageSequence;
    notice('Načítám zprávu…');
    const result=await api('message',{...ref(row),remote:remote?1:0,encoding:row.encoding?.selected || 'auto'},false,null,null,diagnosticGroup);
    if (seq!==messageSequence) {diagnosticGroup?.outcome('superseded');return;}
    selected=result.message; renderMessage(remote); renderList();
    if (!selected.seen || selected.shared) {
      await api('read',{...ref(selected),value:true},true,null,null,diagnosticGroup);
      selected.seen=true; const m=messages.find(m => m.account===selected.account && m.uid===selected.uid); if (m) m.seen=true; renderList();
    }
    notice('Zpráva načtena.');
  }
  // Vzdalene obrazky se podle nastaveni modulu standardne nacitaji i pri opakovanem vykresleni.
  function renderMessage(remote = true) {
    const m=selected; if (!m) return;
    root.classList.add('em-has-message');
    root.querySelectorAll('[data-em-toolbar] button').forEach(b => { b.disabled=false; });
    clearMailFrames(node('[data-em-reader]'));
    node('[data-em-reader]').innerHTML=`<header class="em-message-header"><button data-em-action="back">‹ Seznam</button><h2>${esc(m.subject || '(bez předmětu)')}</h2><p><b>Schránka:</b> ${esc(m.mailbox)}</p><p><b>Od:</b> ${esc(label(m.from))}</p><p><b>Komu:</b> ${esc(label(m.to))}</p>${m.cc?.length?'<p><b>Kopie:</b> '+esc(label(m.cc))+'</p>':''}<p>${esc(m.date?new Date(m.date).toLocaleString('cs-CZ'):'')}</p>
      ${m.shared?'<div class="em-work"><span>'+esc(workText(m.work))+'</span><button data-em-action="claim">Převzít</button><button data-em-action="done">Vyřízeno bez odpovědi</button><button data-em-action="release">Vrátit k řešení</button></div>':''}
      <button data-em-action="move">Přesunout</button>${m.folder===special.drafts?'<button data-em-action="edit_draft">Upravit koncept</button>':''}${m.html && !remote?'<button data-em-action="remote">Načíst vzdálené obrázky</button>':''}
      <details><summary>Další</summary><button data-em-action="original">Stáhnout originál (.eml)</button><label>Kódování <select data-em-encoding>${[['auto','Automaticky'],['UTF-8','UTF-8'],['Windows-1250','Windows-1250'],['ISO-8859-2','ISO-8859-2']].map(([v,t])=>`<option value="${v}"${(m.encoding?.selected || 'auto')===v?' selected':''}>${t}</option>`).join('')}</select></label>${m.encoding?.uncertain?'<p>Kódování nebylo jednoznačné. Pokud text není správně, vyberte jinou variantu.</p>':''}</details></header>
      <div class="em-attachments">${m.attachments.map(a => `<button data-em-attachment="${esc(a.part)}">📎 ${esc(a.name)} (${Math.ceil(a.size/1024)} kB)</button>`).join('')}</div>`;
    if (m.html) {
      renderMailFrame(node('[data-em-reader]'),m,remote);
    } else { const pre=document.createElement('pre'); pre.className='em-plain-body'; pre.textContent=m.text || ''; node('[data-em-reader]').append(pre); }
  }

  // Odstraneni ramce uvolni take jeho vlozena obrazkova data pri zmene zpravy nebo modulu.
  function clearMailFrames(host) {
    host?.querySelectorAll('iframe[data-em-mail-frame]').forEach(frame=>frame.remove());
  }
  // CID obrazky se stahuji jen jako overene rastrove prilohy. Ramci nepovolujeme skripty ani stejny puvod s IS.
  function renderMailFrame(host,m,remote=true) {
    const frame=document.createElement('iframe');frame.dataset.emMailFrame='1';frame.className='em-message-body';frame.title='Obsah e-mailu';frame.setAttribute('sandbox','allow-popups allow-popups-to-escape-sandbox');frame.referrerPolicy='no-referrer';
    const wrap=html=>`<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data: ${remote?'https:':''}; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><style>body{font:14px/1.6 Arial,sans-serif;margin:18px;color:#30383c;overflow-wrap:anywhere}img{max-width:100%}table{max-width:100%}pre{white-space:pre-wrap}blockquote{border-left:3px solid #ddd;margin-left:0;padding-left:15px}</style></head><body>${html}</body></html>`;
    frame.srcdoc=wrap(m.html);host.append(frame);
    const doc=new DOMParser().parseFromString(m.html,'text/html');
    const images=[...doc.querySelectorAll('img[data-em-cid]')];
    if(images.length)run(async()=>{
      const byCid=new Map();
      for(const img of images){const cid=img.dataset.emCid,part=m.attachments.find(a=>a.cid===cid);if(!part)continue;
        try{if(!byCid.has(cid)){const {blob}=await api('attachment',{...ref(m),part:part.part,inline:1});if(!frame.isConnected)return;
          // Data URI z overeneho rastru funguje i v nepruhlednem sandboxu, ktery nepovoluje blob URL puvodu IS.
          const data=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=()=>reject(new Error('Obrázek nelze načíst.'));reader.readAsDataURL(blob);});byCid.set(cid,data);
        }img.setAttribute('src',byCid.get(cid));}catch(e){if(e.name==='AbortError')return;img.setAttribute('alt',(img.alt || 'Obrázek')+' (nedostupný)');}
      }
      if(frame.isConnected)frame.srcdoc=wrap([...doc.head.querySelectorAll('style')].map(style=>style.outerHTML).join('')+doc.body.innerHTML);
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
    } catch(e) {diagnosticGroup?.outcome(e.name==='AbortError'?'abort':'error');if (seq===attachmentSequence && e.name!=='AbortError') preview.textContent=e.message;}
  }

  // Sprava se obnovi pri kazdem otevreni, aby zobrazila i adresy mezitim pripojene z jineho okna.
  async function connectDialog(type = 'osobni') {
    const fresh=await api('state');
    if (!fresh.installed || fresh.configured===false) throw new Error(fresh.message || 'Nastavení schránek není dostupné.');
    state=fresh;
    if (type==='shared' && !state.manage_shared) throw new Error('Správa společných schránek vyžaduje právo 802.');
    const dialog=node('[data-em-connect]'), form=dialog.querySelector('form'); form.reset();
    form.querySelector('.em-form-error').textContent='';
    // Předvyplňuje se pouze již připojená schránka, nikdy kontaktní adresa z HR.
    form.elements.local_part.value=currentAccount()?.type==='osobni'?currentAccount().email.split('@')[0]:'';
    form.elements.type.querySelector('[value="osobni"]').disabled=!state.manager;
    form.elements.type.querySelector('[value="shared"]').disabled=!state.manage_shared;
    form.elements.type.value=type==='shared' || !state.manager?'shared':'osobni';
    form.elements.mode.value=currentAccount()?.mode || 'is';
    const management=state.shared_management || {accounts:[],branches:[]};
    sharedConnections();
    form.elements.branch.innerHTML='<option value="">Vyberte pobočku</option>'+management.branches.map(b=>`<option value="${Number(b.id_pob)}">${esc(b.nazev)}</option>`).join('');
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
  // Metadata neobsahuji heslo; prazdne heslo pri uprave znamena zachovat puvodni ulozene pripojeni.
  function connectFields(selectAccount = false) {
    const form=node('[data-em-connect-form]'), shared=form.elements.type.value==='shared';
    const a=shared?(state.shared_management?.accounts || []).find(a=>Number(a.id)===Number(form.elements.shared_id.value)):null;
    node('[data-em-shared-list]').querySelectorAll('[data-em-shared-edit]').forEach(b=>b.classList.toggle('is-selected',b.dataset.emSharedEdit===form.elements.shared_id.value));
    if (selectAccount) {
      form.elements.local_part.value=shared?(a?.email || '').split('@')[0]:(state.accounts.find(a=>a.type==='osobni')?.email || '').split('@')[0];
      form.elements.shared_kind.value=a?.typ || 'pobocka'; form.elements.branch.value=a?.id_pob || '';
      form.elements.password.value='';
    }
    node('[data-em-shared-fields]').hidden=!shared;
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
    toggle.textContent=Number(a?.aktivni)?'Vypnout přístup v IS':'Zapnout přístup v IS';
    node('[data-em-shared-status]').textContent=a?(Number(a.aktivni)?'Schránka je dostupná oprávněným uživatelům.':'Přístup v IS je vypnutý. Pošta na serveru zůstává zachovaná.'):'';
    node('[data-em-connect-hint]').textContent=shared?'Uživatelé používají přihlášení do IS. Heslo schránky znají jen její správci.':'Heslo se zadává jednou za přihlášení do IS, nebo se použije zabezpečené uložené připojení podle zvoleného režimu.';
    form.querySelector('[type=submit]').textContent=shared && a?'Uložit změny':'Připojit';
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
    form.elements.account.value=String(source?.account || account); form.elements.account.disabled=!!source;
    if (!form.elements.account.value) throw new Error('Nejdříve otevřete připojenou schránku.');
    node('[data-em-compose-title]').textContent=({new:'Nová zpráva',reply:'Odpověď',reply_all:'Odpověď všem',forward:'Přeposlání',edit_draft:'Úprava konceptu'})[kind];
    if (source) {
      const own=source.mailbox.toLowerCase();
      if (kind==='edit_draft') {
        form.elements.to.value=address(source.to); form.elements.cc.value=address(source.cc); form.elements.bcc.value=address(source.bcc); form.elements.subject.value=source.subject;
        // Koncept uz podpis obsahuje; pri dalsim otevreni se znovu nepridava a zachova sve HTML.
        bodyHtml=source.draft_editor_html??'<p><br></p>';
      } else {
        const recipient=source.reply_to?.length?source.reply_to:source.from;
        const targets=kind==='reply_all'?[...recipient,...source.to]:recipient;
        if (kind!=='forward') form.elements.to.value=[...new Set(targets.map(a=>a.email).filter(a=>a.toLowerCase()!==own))].join(', ');
        if (kind==='reply_all') form.elements.cc.value=[...new Set((source.cc || []).map(a=>a.email).filter(a=>a.toLowerCase()!==own && !targets.some(t=>t.email.toLowerCase()===a.toLowerCase())))].join(', ');
        form.elements.subject.value=(kind==='forward'?'Fwd: ':'Re: ')+source.subject.replace(/^(Re|Fwd):\s*/i,'');
        // Puvodni citace neprochazi Quill editorem; server ji znovu nacte a pripoji bez ztraty tabulek a obrazku.
      }
    }
    node('[data-em-forward-files]').hidden=kind!=='forward' || !source.attachments.length;
    const quoteHtml=kind==='edit_draft'?(source.draft_quote_html??source.html):(source?.html || '');
    const quoteText=kind==='edit_draft' && source.draft_editor_html!==undefined?'':source?.text;
    const hasQuote=!!(quoteHtml || quoteText);
    clearMailFrames(node('[data-em-quote-preview]'));node('[data-em-quote-preview]').replaceChildren();
    node('[data-em-quote]').hidden=!source || !hasQuote;form.elements.include_quote.checked=!!source && hasQuote;
    if(source && hasQuote){
      if(quoteHtml)renderMailFrame(node('[data-em-quote-preview]'),{...source,html:quoteHtml});
      else {const pre=document.createElement('pre');pre.textContent=quoteText;node('[data-em-quote-preview]').append(pre);}
    }
    compose={kind:kind==='edit_draft'?(draftContext?.kind || 'new'):kind,source:originalRef,draft_ref:kind==='edit_draft'?ref(source):null,version,token:crypto.randomUUID(),compose_format:2,quote_encoding:draftContext?.quote_encoding || source?.encoding?.selected || 'auto'}; dirty=false;
    editor.setHtml(bodyHtml); dialog.showModal();hideRecipients();node('[data-em-recipient-status]').hidden=true;refreshRecipients();
    if (kind==='forward' || kind==='new') form.elements.to.focus(); else editor.focus();
  }
  async function closeCompose() {
    if (sending) return;
    if (dirty && !confirm('Zahodit rozepsanou zprávu?')) return;
    if (compose?.source && selected?.shared && compose.kind!=='draft_edit') await api('release',compose.source,true);
    hideRecipients();compose=null; dirty=false; node('[data-em-compose]').close();
    clearMailFrames(node('[data-em-quote-preview]'));
  }
  async function send(draft = false) {
    if (sending || !compose) return;
    const form=node('[data-em-compose-form]');
    if (!draft && !form.reportValidity()) return;
    sending=true; composeEditor.enable(false); form.querySelectorAll('button').forEach(b=>b.disabled=true); form.querySelector('.em-form-error').textContent='';
    try {
      const data={...compose,account:Number(form.elements.account.value)};
      // Nabidka ponechava za vybranou adresou oddelovac pro dalsiho prijemce; do SMTP prazdna posledni polozka nepatri.
      ['to','cc','bcc'].forEach(key=>{data[key]=form.elements[key].value.replace(/[;,\s]+$/,'');});
      data.subject=form.elements.subject.value;
      data.body=composeEditor.getText(); data.body_html=composeEditor.getHtml();
      data.include_quote=form.elements.include_quote.checked && !node('[data-em-quote]').hidden;
      const result=await api(draft?'draft':'send',data,true,form.elements.attachments.files);
      if (draft) {
        dirty=false;
        if (result.draft_ref) {compose.draft_ref=result.draft_ref; form.elements.attachments.value='';}
        notice('Koncept uložen do schránky.');
      }
      else {
        compose=null; dirty=false; node('[data-em-compose]').close();clearMailFrames(node('[data-em-quote-preview]'));
        notice(result.stored?'Zpráva odeslána.':'Zpráva byla odeslána, ale kopie se neuložila do Odeslaných. Odeslání neopakujte.',!result.stored);
        if (!result.stored) {
          const button=document.createElement('button'); button.textContent='Uložit kopii do Odeslaných';
          button.onclick=()=>run(async()=>{await api('repair_sent',{account:data.account,token:data.token},true); notice('Kopie uložená do Odeslaných.');});
          node('[data-em-notice]').append(button);
        }
        if (data.source && selected?.shared) { selected.work={...selected.work,stav:'odpovezeno'}; renderMessage(); }
        if (data.source) messages.forEach(m=>{if (selected && m.account===selected.account && m.uid===selected.uid) {m.answered=true; m.work=selected.work;}}); renderList();
      }
    } catch (e) { if (e.name!=='AbortError') form.querySelector('.em-form-error').textContent=e.message; }
    finally { sending=false; composeEditor?.enable(true); form.querySelectorAll('button').forEach(b=>b.disabled=false); }
  }

  // Vyber cilove slozky probiha v IS; hromadny kos rozdeli souhrn podle puvodnich schranek.
  async function moveRows(rows, trash = false) {
    if (moving || node('[data-em-move]').open) return;
    rows=rows.filter(Boolean);
    if (!rows.length) throw new Error(trash && folder===special.trash?'Vyberte zprávy ke smazání.':(trash?'Vyberte zprávy k přesunu do koše.':'Vyberte zprávy k přesunu.'));
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
    dialog.showModal();
  }

  // Zapis az po potvrzeni dialogu; dokoncene schranky pri chybe znovu nepresouvame.
  async function confirmMove(form) {
    if (!pendingMove || moving) return;
    const task=pendingMove; moving=true;
    form.querySelectorAll('button,select').forEach(b=>{b.disabled=true;});
    form.querySelector('.em-form-error').textContent='';
    try {
      while (task.groups.length) {
        const group=task.groups[0];
        await api(task.permanent?'delete':'move',{account:group.account,messages:group.messages,target:task.trash?group.target:form.elements.target.value,...(task.permanent?{confirm:'smazat'}:{})},true);
        task.completed+=group.messages.length; task.groups.shift();
      }
      pendingMove=null; form.closest('dialog').close(); selected=null; page=1;
      await loadAccount(); notice((task.permanent?'Trvale smazáno zpráv: ':(task.trash?'Do koše přesunuto zpráv: ':'Přesunuto zpráv: '))+task.completed);
    } catch(e) {
      if (e.name!=='AbortError') form.querySelector('.em-form-error').textContent=(task.completed?'Dokončeno pro '+task.completed+' zpráv. ':'')+e.message;
    } finally {
      moving=false; form.querySelectorAll('button,select').forEach(b=>{b.disabled=false;});
      form.elements.target.disabled=task.trash;
    }
  }

  // Obsluha je delegovana jen do vlastni PP a vlastni navigace; nezasahuje do cizich formularu.
  async function action(name) {
    if (name==='signature') return signatureDialog();
    if (name==='original' && selected) {
      const {blob}=await api('original',ref(selected));const url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='zprava.eml';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);return;
    }
    if (name==='order') return orderDialog();
    if (name==='order_reset') {orderDraft=orderedFolders([]);orderReset=true;settingsDirty=true;return renderOrder();}
    if (name==='connect') return connectDialog();
    if (name==='security') {
      if (!state.manager || node('[data-em-connect-form]').elements.type.value!=='osobni') return;
      const info=node('[data-em-security-info]'); info.hidden=!info.hidden;
      node('[data-em-security-toggle]').setAttribute('aria-expanded',String(!info.hidden));
      return;
    }
    if (name==='manage_shared') return connectDialog('shared');
    if (name==='shared_active') {
      const form=node('[data-em-connect-form]');
      const a=state.shared_management.accounts.find(a=>Number(a.id)===Number(form.elements.shared_id.value));
      if (!a || !confirm(Number(a.aktivni)?'Vypnout přístup k této schránce v IS? Pošta na serveru zůstane zachovaná.':'Zapnout přístup k této schránce v IS?')) return;
      const button=node('[data-em-action="shared_active"]'); button.disabled=true;
      try { await api('shared_active',{shared_id:Number(a.id),revision:Number(a.revize),active:!Number(a.aktivni)},true); form.closest('dialog').close(); await loadState(); }
      catch(e) {form.querySelector('.em-form-error').textContent=e.message;}
      finally {button.disabled=false;}
      return;
    }
    if (['new','reply','reply_all','forward','edit_draft'].includes(name)) return composeMessage(name);
    if (name==='clear_search') return clearSearch();
    if (name==='refresh') {invalidateMailCache(aggregate?state.accounts.filter(a=>a.type!=='osobni').map(a=>a.id):[account]);return loadAccount();}
    if (name==='back') return root.classList.remove('em-has-message');
    if (name==='prev' && !listLoading && page>1) {page--; return loadList(null,false,true);}
    if (name==='next' && !listLoading && page*pageSize<total) {page++; return loadList(null,false,true);}
    if (name==='draft') return send(true);
    if (name==='select') {const checks=root.querySelectorAll('[data-em-check]'); const all=Array.from(checks).every(c=>c.checked); checks.forEach(c=>c.checked=!all); return;}
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
    if (name==='remote') {if (confirm('Načíst vzdálené obrázky? Odesílatel může zjistit otevření zprávy.')) return openMessage(selected,true); return;}
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
    nav=null; state=null; selected=null; compose=null; dirty=false; sending=false; account=0; aggregate=false; folder='INBOX'; page=1;
    composeEditor=null; signatureEditor=null; composeEditorTask=null; signatureEditorTask=null; settingsDirty=false; folderOrder=[];
    pendingMove=null; moving=false;
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
    if (target.hasAttribute('data-em-shared-edit')) {event.preventDefault(); node('[data-em-connect-form]').elements.shared_id.value=target.dataset.emSharedEdit; connectFields(true);}
    else if (target.hasAttribute('data-em-order-up')) {event.preventDefault(); const i=Number(target.dataset.emOrderUp); moveOrder(i,i-1);}
    else if (target.hasAttribute('data-em-order-down')) {event.preventDefault(); const i=Number(target.dataset.emOrderDown); moveOrder(i,i+1);}
    else if (target.hasAttribute('data-em-action')) {event.preventDefault(); run(()=>action(target.dataset.emAction));}
    else if (target.hasAttribute('data-em-folder')) {event.preventDefault(); folder=target.dataset.emFolder; page=1; folderNavigation(); run(loadList);}
    else if (target.hasAttribute('data-em-message')) run(()=>openMessage(messages[Number(target.dataset.emMessage)]));
    else if (target.hasAttribute('data-em-attachment')) run(()=>openAttachment(target.dataset.emAttachment));
    else if (target.hasAttribute('data-em-close')) {
      const dialog=target.closest('dialog');
      if (dialog.hasAttribute('data-em-attachment-dialog')) {clearAttachment();dialog.close();return;}
      if (dialog.hasAttribute('data-em-move')) {if (moving) return; pendingMove=null; formResetMove(dialog); return;}
      if (dialog.hasAttribute('data-em-compose')) run(closeCompose);
      else {if (settingsDirty && !confirm('Zahodit neuložené nastavení?')) return; settingsDirty=false; dialog.querySelector('form').reset(); dialog.close();}
    }
  });
  // Zruseni presunu zavre jen vlastni formular a nikdy nesahne na oznacene zpravy.
  function formResetMove(dialog) {dialog.querySelector('form').reset();dialog.close();}
  document.addEventListener('change',event=>{
    if (!root) return;
    if(root.contains(event.target) && event.target.matches('[data-em-encoding]') && selected){selected.encoding={...selected.encoding,selected:event.target.value};run(()=>openMessage(selected));return;}
    if(root.contains(event.target) && event.target.matches('[data-em-page-size]')) {
      pageSize=Number(event.target.value);if(![25,50,100].includes(pageSize))pageSize=50;
      try {localStorage.setItem('cb.email.page_size.'+pagePreferenceOwner,String(pageSize));}catch {}
      page=1;run(()=>loadList(null,false,true));
    }
    if(root.contains(event.target) && event.target.matches('[data-em-compose-form] [name=account]')) refreshRecipients();
    if (event.target.matches('[data-em-accounts]') && nav?.contains(event.target)) {
      // Pamatuj filtr prenese jen potvrzene hledani; rozepsany text se nikdy sam nestane dotazem.
      const searchForm=node('[data-em-search]');
      if(!searchForm.elements.remember.checked) {appliedSearch='';appliedBody=false;}
      searchForm.elements.search.value=appliedSearch;
      if(appliedSearch)searchForm.elements.body.checked=appliedBody;
      searchIndicator();
      aggregate=event.target.value==='all'; account=aggregate?state.accounts.find(a=>a.type!=='osobni').id:Number(event.target.value); folder='INBOX'; page=1; run(loadAccount);
    }
    if (root.contains(event.target) && event.target.matches('[name=type]')) {
      connectFields(true);
    }
    if (root.contains(event.target) && event.target.matches('[name=shared_id]')) connectFields(true);
    if (root.contains(event.target) && event.target.matches('[name=shared_kind]')) connectFields();
    if (root.contains(event.target) && event.target.matches('[name=unseen]')) {page=1;run(loadList);}
  });
  document.addEventListener('input',event=>{if (root && event.target.closest('[data-em-compose-form]') && root.contains(event.target)) dirty=true;});
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
    else if (form.matches('[data-em-move-form]')) run(()=>confirmMove(form));
    else if (form.matches('[data-em-compose-form]')) run(()=>send());
    else if (form.matches('[data-em-signature-form],[data-em-order-form]')) run(async()=>{
      const button=form.querySelector('[type=submit]'); button.disabled=true; form.querySelector('.em-form-error').textContent='';
      try {
        if (form.matches('[data-em-signature-form]')) {
          const result=await api('save_signature',{html:signatureEditor.getHtml()},true); state.preferences.signature=result.signature;
        } else {
          const result=await api('save_order',{account,order:orderReset?[]:orderDraft.map(f=>f.path)},true); folderOrder=result.order; folderNavigation();
        }
        settingsDirty=false; form.closest('dialog').close(); notice('Osobní nastavení bylo uloženo.');
      } catch (e) {if(e.name!=='AbortError')form.querySelector('.em-form-error').textContent=e.message;}
      finally {button.disabled=!state?.preferences?.ready;}
    });
    else if (form.matches('[data-em-connect-form],[data-em-unlock-form]')) run(async()=>{
      const button=form.querySelector('[type=submit]'); button.disabled=true; const error=form.querySelector('.em-form-error'); error.textContent='';
      try {
        if (form.matches('[data-em-connect-form]')) {
          const a=(state.shared_management?.accounts || []).find(a=>Number(a.id)===Number(form.elements.shared_id.value));
          await api('connect',{local_part:form.elements.local_part.value,password:form.elements.password.value,type:form.elements.type.value,mode:form.elements.mode.value,
            shared_id:Number(form.elements.shared_id.value),revision:Number(a?.revize || 0),shared_kind:form.elements.shared_kind.value,branch:Number(form.elements.branch.value)},true);
        }
        else await api('unlock',{account,password:form.elements.password.value},true);
        form.reset(); form.closest('dialog').close(); await loadState();
      } catch (e) {
        if (e.name!=='AbortError') {
          error.textContent=e.message;
          // Po kolizi ukazeme cerstvy seznam, ale neprepiseme uzivateli rozepsane hodnoty formulare.
          if (form.matches('[data-em-connect-form]') && form.elements.type.value==='shared' && state?.manage_shared) {
            try {const fresh=await api('state'); state.shared_management=fresh.shared_management; state.manage_shared=fresh.manage_shared; sharedConnections();}
            catch (_) { /* Puvodni chyba zustava zobrazena i pri nedostupnem obnoveni seznamu. */ }
          }
        }
      } finally {button.disabled=false; form.elements.password.value='';}
    });
  });
  document.addEventListener('cancel',event=>{
    if (!root?.contains(event.target)) return;
    if (event.target.hasAttribute('data-em-attachment-dialog')) {clearAttachment();return;}
    if (event.target.hasAttribute('data-em-move')) {if (moving) event.preventDefault();else pendingMove=null;return;}
    if (event.target.hasAttribute('data-em-compose')) {event.preventDefault();run(closeCompose);}
    else if (settingsDirty) {if(!confirm('Zahodit neuložené nastavení?'))event.preventDefault();else settingsDirty=false;}
  },true);
  document.addEventListener('cb:main-before-load',event=>{
    if (root && (sending || moving || ((dirty || settingsDirty) && !confirm('Opustit E-mail a zahodit neuložené změny?')))) event.preventDefault();
  });
  window.addEventListener('beforeunload',event=>{if (root && (dirty || settingsDirty || sending || moving)) {event.preventDefault();event.returnValue='';}});
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
    if (!root?.isConnected || document.hidden || sending || busy || !account) return;
    busy=true;
    run(async()=>{
      if (compose?.source && selected?.shared && compose.kind!=='draft_edit') {
        const result=await api('renew',compose.source,true); compose.version=Number(result.work.verze);
      } else if (!node('dialog[open]') && currentAccount()?.unlocked && !listLoading) await refreshVisibleStates();
    }).finally(()=>{busy=false;});
  },60000);
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',initialize); else initialize();
}());
