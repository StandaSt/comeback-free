/* E-mail: navigace, editor a podpis; manazerum vysvetluje zabezpeceni osobni schranky, spravu sdilenych adres chrani 802. */
(function () {
  'use strict';
  let root = null, nav = null, state = null, account = 0, aggregate = false, folder = 'INBOX', folders = [], special = {};
  let messages = [], selected = null, page = 1, total = 0, compose = null, dirty = false, sending = false, generation = 0, busy = false;
  let controller = null, listSequence = 0, messageSequence = 0, accountSequence = 0, backgroundRunning = false;
  let folderOrder = [], orderDraft = [], orderReset = false, orderDragged = -1, settingsDirty = false;
  let editorModule = null, composeEditor = null, signatureEditor = null;
  let composeEditorTask = null, signatureEditorTask = null;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const node = selector => root && root.querySelector(selector);
  const ref = m => ({account:m.account, folder:m.folder, validity:m.validity, uid:m.uid});
  const address = values => (values || []).map(a => a.email).join(', ');
  const label = values => (values || []).map(a => a.name ? a.name + ' <' + a.email + '>' : a.email).join(', ');

  // Kazdy pozadavek nese identitu okna IS; zadne heslo se neuklada do URL ani localStorage.
  async function api(action, data = {}, write = false, files = null) {
    if (!root || !controller) throw new Error('Modul již není otevřený.');
    const ownGeneration = generation;
    const url = new URL(root.dataset.api, location.href);
    url.searchParams.set('action', action);
    const options = {credentials:'same-origin', cache:'no-store', signal:controller.signal,
      headers:{'Accept':'application/json', 'X-Comeback-Pc-Session':String(window.CB_PC_SESSION_TOKEN || '')}};
    if (write) {
      options.method = 'POST'; options.headers['X-Comeback-Crf'] = String(window.CB_CRF_TOKEN || '');
      if (files) {
        const form = new FormData(); form.set('data', JSON.stringify(data));
        Array.from(files).forEach(file => form.append('attachments[]', file)); options.body = form;
      } else { options.headers['Content-Type']='application/json'; options.body=JSON.stringify(data); }
    } else Object.entries(data).forEach(([key,value]) => url.searchParams.set(key, String(value)));
    const response = await fetch(url, options);
    if (generation !== ownGeneration) throw new DOMException('Změna modulu', 'AbortError');
    if ([401,403,409].includes(response.status)) {
      messages=[]; selected=null; dirty=false;
      settingsDirty=false; composeEditor?.setHtml(''); signatureEditor?.setHtml('');
      root.querySelectorAll('dialog').forEach(d => {d.querySelector('form')?.reset(); if (d.open) d.close();});
      node('[data-em-reader]').replaceChildren(); node('[data-em-list]').replaceChildren();
      if (nav) nav.replaceChildren();
      root.querySelectorAll('button,input,select,textarea').forEach(el=>{el.disabled=true;}); state=null;
      if (response.status===401 || response.status===409) throw new Error('Přihlášení nebo platnost tohoto okna skončila. Přihlaste se znovu do IS.');
    }
    if (action === 'attachment' && response.ok) return response.blob();
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.ok) throw new Error(payload.error || payload.err || 'Poštovní operaci se nepodařilo dokončit.');
    return payload;
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
    folderNavigation();
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

  async function loadState() {
    state = await api('state');
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
    await loadAccount();
  }
  async function loadAccount() {
    const ownSequence=++accountSequence, ownAccount=account;
    listSequence++; messageSequence++;
    selected=null; messages=[]; folders=[]; folderOrder=[]; root.classList.remove('em-has-message');
    node('[data-em-reader]').innerHTML='<div class="em-empty">Vyberte zprávu ze seznamu.</div>';
    root.querySelectorAll('[data-em-toolbar] button').forEach(b => { b.disabled=true; });
    if (!currentAccount()?.unlocked) { node('[data-em-list]').innerHTML='<div class="em-empty">Schránka je zamčená.</div>'; unlockDialog(); return; }
    notice('Načítám složky…');
    if (!aggregate) {
      const result = await api('folders',{account:ownAccount});
      if (ownSequence!==accountSequence) return;
      folders=result.folders; special=result.special; folderOrder=result.order || [];
      if (!folders.some(f => f.path===folder)) folder='INBOX';
    }
    if (ownSequence!==accountSequence) return;
    folderNavigation(); await loadList();
  }
  async function loadList() {
    if (!account) return;
    const seq=++listSequence;
    const form=node('[data-em-search]'); notice('Načítám zprávy…');
    const result=await api('list',{account,aggregate:aggregate?1:0,folder,page,search:form.elements.search.value,unseen:form.elements.unseen.checked?1:0});
    if (seq!==listSequence) return;
    messages=result.messages; total=result.total; renderList();
    notice(result.errors.length ? result.errors.join(' ') : `${aggregate?'Společná pošta':currentAccount()?.email || ''} · ${total} zpráv`,result.errors.length>0);
  }
  function renderList() {
    node('[data-em-list]').innerHTML=messages.map((m,i) => `<div class="em-message-row${m.seen?'':' is-unseen'}${selected && selected.account===m.account && selected.uid===m.uid?' is-selected':''}" role="listitem"><input type="checkbox" data-em-check="${i}" aria-label="Vybrat ${esc(m.subject)}"><button data-em-message="${i}"><span class="em-message-top"><span class="em-sender">${esc(label(m.from))}</span><time>${esc(m.date?new Date(m.date).toLocaleString('cs-CZ',{day:'numeric',month:'numeric',hour:'2-digit',minute:'2-digit'}):'')}</time></span><span class="em-subject">${m.answered?'↶ ':''}${m.flagged?'⚑ ':''}${esc(m.subject || '(bez předmětu)')}</span>${aggregate?'<span class="em-mailbox-label">'+esc(m.mailbox)+'</span>':''}${m.shared && m.work?.stav!=='nove'?'<span class="em-work-label">'+esc(workText(m.work))+'</span>':''}</button></div>`).join('') || '<div class="em-empty">Ve vybraném přehledu nejsou žádné zprávy.</div>';
    node('[data-em-page]').textContent=`${page} · ${total} zpráv`;
    node('[data-em-action="prev"]').disabled=page<=1;
    node('[data-em-action="next"]').disabled=page*50>=total;
  }
  async function openMessage(row, remote = false) {
    const seq=++messageSequence;
    notice('Načítám zprávu…');
    const result=await api('message',{...ref(row),remote:remote?1:0});
    if (seq!==messageSequence) return;
    selected=result.message; renderMessage(remote); renderList();
    if (!selected.seen) {
      await api('read',{...ref(selected),value:true},true);
      selected.seen=true; const m=messages.find(m => m.account===selected.account && m.uid===selected.uid); if (m) m.seen=true; renderList();
    }
    notice('Zpráva načtena.');
  }
  function renderMessage(remote = false) {
    const m=selected; if (!m) return;
    root.classList.add('em-has-message');
    root.querySelectorAll('[data-em-toolbar] button').forEach(b => { b.disabled=false; });
    node('[data-em-reader]').innerHTML=`<header class="em-message-header"><button data-em-action="back">‹ Seznam</button><h2>${esc(m.subject || '(bez předmětu)')}</h2><p><b>Schránka:</b> ${esc(m.mailbox)}</p><p><b>Od:</b> ${esc(label(m.from))}</p><p><b>Komu:</b> ${esc(label(m.to))}</p>${m.cc?.length?'<p><b>Kopie:</b> '+esc(label(m.cc))+'</p>':''}<p>${esc(m.date?new Date(m.date).toLocaleString('cs-CZ'):'')}</p>
      ${m.shared?'<div class="em-work"><span>'+esc(workText(m.work))+'</span><button data-em-action="claim">Převzít</button><button data-em-action="done">Vyřízeno bez odpovědi</button><button data-em-action="release">Vrátit k řešení</button></div>':''}
      <button data-em-action="move">Přesunout</button>${m.folder===special.drafts?'<button data-em-action="edit_draft">Upravit koncept</button>':''}${m.html && !remote?'<button data-em-action="remote">Načíst vzdálené obrázky</button>':''}</header>
      <div class="em-attachments">${m.attachments.map(a => `<button data-em-attachment="${a.part}">📎 ${esc(a.name)} (${Math.ceil(a.size/1024)} kB)</button>`).join('')}</div>`;
    if (m.html) {
      const frame=document.createElement('iframe'); frame.className='em-message-body'; frame.title='Obsah e-mailu'; frame.setAttribute('sandbox','allow-popups allow-popups-to-escape-sandbox'); frame.referrerPolicy='no-referrer';
      frame.srcdoc=`<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src ${remote?'https:':'\'none\''}; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'"><style>body{font:14px/1.6 Arial,sans-serif;margin:18px;color:#30383c;overflow-wrap:anywhere}img{max-width:100%}table{max-width:100%}pre{white-space:pre-wrap}blockquote{border-left:3px solid #ddd;margin-left:0;padding-left:15px}</style></head><body>${m.html}</body></html>`;
      node('[data-em-reader]').append(frame);
    } else { const pre=document.createElement('pre'); pre.className='em-plain-body'; pre.textContent=m.text || ''; node('[data-em-reader]').append(pre); }
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
        bodyHtml=source.html || plainHtml(source.text);
      } else {
        const recipient=source.reply_to?.length?source.reply_to:source.from;
        const targets=kind==='reply_all'?[...recipient,...source.to]:recipient;
        if (kind!=='forward') form.elements.to.value=[...new Set(targets.map(a=>a.email).filter(a=>a.toLowerCase()!==own))].join(', ');
        if (kind==='reply_all') form.elements.cc.value=[...new Set((source.cc || []).map(a=>a.email).filter(a=>a.toLowerCase()!==own && !targets.some(t=>t.email.toLowerCase()===a.toLowerCase())))].join(', ');
        form.elements.subject.value=(kind==='forward'?'Fwd: ':'Re: ')+source.subject.replace(/^(Re|Fwd):\s*/i,'');
        const quoteTitle=kind==='forward'?'---------- Přeposlaná zpráva ----------':'Dne '+new Date(source.date).toLocaleString('cs-CZ')+' '+label(source.from)+' napsal(a):';
        bodyHtml+='<p>'+esc(quoteTitle)+'</p><blockquote>'+(source.html || plainHtml(source.text))+'</blockquote>';
      }
    }
    node('[data-em-forward-files]').hidden=kind!=='forward' || !source.attachments.length;
    compose={kind:kind==='edit_draft'?(draftContext?.kind || 'new'):kind,source:originalRef,draft_ref:kind==='edit_draft'?ref(source):null,version,token:crypto.randomUUID()}; dirty=false;
    editor.setHtml(bodyHtml); dialog.showModal();
    if (kind==='forward' || kind==='new') form.elements.to.focus(); else editor.focus();
  }
  async function closeCompose() {
    if (sending) return;
    if (dirty && !confirm('Zahodit rozepsanou zprávu?')) return;
    if (compose?.source && selected?.shared && compose.kind!=='draft_edit') await api('release',compose.source,true);
    compose=null; dirty=false; node('[data-em-compose]').close();
  }
  async function send(draft = false) {
    if (sending || !compose) return;
    const form=node('[data-em-compose-form]');
    if (!draft && !form.reportValidity()) return;
    sending=true; composeEditor.enable(false); form.querySelectorAll('button').forEach(b=>b.disabled=true); form.querySelector('.em-form-error').textContent='';
    try {
      const data={...compose,account:Number(form.elements.account.value)};
      ['to','cc','bcc','subject'].forEach(key=>{data[key]=form.elements[key].value;});
      data.body=composeEditor.getText(); data.body_html=composeEditor.getHtml();
      const result=await api(draft?'draft':'send',data,true,form.elements.attachments.files);
      if (draft) {
        dirty=false;
        if (result.draft_ref) {compose.draft_ref=result.draft_ref; form.elements.attachments.value='';}
        notice('Koncept uložen do schránky.');
      }
      else {
        compose=null; dirty=false; node('[data-em-compose]').close();
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

  async function moveRows(rows) {
    if (!rows.length) throw new Error('Vyberte zprávy k přesunu.');
    const id=rows[0].account;
    if (rows.some(m=>m.account!==id)) throw new Error('Pro hromadný přesun vyberte zprávy z jedné schránky.');
    const result=await api('folders',{account:id});
    const target=prompt('Přesná cílová složka:\n'+result.folders.filter(f=>f.selectable).map(f=>f.path).join('\n'));
    if (!target) return;
    if (!result.folders.some(f=>f.path===target && f.selectable)) throw new Error('Vyberte existující cílovou složku.');
    await api('move',{account:id,messages:rows.map(ref),target},true); selected=null; root.classList.remove('em-has-message'); await loadList();
  }

  // Obsluha je delegovana jen do vlastni PP a vlastni navigace; nezasahuje do cizich formularu.
  async function action(name) {
    if (name==='signature') return signatureDialog();
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
    if (name==='refresh') return loadAccount();
    if (name==='back') return root.classList.remove('em-has-message');
    if (name==='prev' && page>1) {page--; return loadList();}
    if (name==='next' && page*50<total) {page++; return loadList();}
    if (name==='draft') return send(true);
    if (name==='select') {const checks=root.querySelectorAll('[data-em-check]'); const all=Array.from(checks).every(c=>c.checked); checks.forEach(c=>c.checked=!all); return;}
    if (name==='bulk_move') return moveRows(Array.from(root.querySelectorAll('[data-em-check]:checked')).map(c=>messages[Number(c.dataset.emCheck)]));
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
    if (name==='trash') {
      const f=await api('folders',{account:selected.account});
      await api('move',{...ref(selected),target:f.special.trash},true); selected=null; return loadAccount();
    }
    if (name==='unread' || name==='flag') {
      await api(name==='unread'?'read':'flag',{...ref(selected),value:name==='unread'?false:!selected.flagged},true); return loadList();
    }
    if (['claim','release','done'].includes(name)) {
      const result=await api(name,ref(selected),true); selected.work=result.work; renderMessage(); await loadList();
    }
  }

  function initialize() {
    const next=document.querySelector('[data-email-root]'); if (next===root) return;
    if (controller) controller.abort(); generation++; root=next; controller=root?new AbortController():null;
    nav=null; state=null; selected=null; compose=null; dirty=false; sending=false; account=0; aggregate=false; folder='INBOX'; page=1;
    composeEditor=null; signatureEditor=null; composeEditorTask=null; signatureEditorTask=null; settingsDirty=false; folderOrder=[];
    if (!root) return;
    const list=document.querySelector('.obal_main > .blok_menu .blok_menu_list') || document.querySelector('.blok_menu .blok_menu_list');
    if (list) {nav=document.createElement('li'); nav.className='em-navigation'; list.replaceChildren(nav);}
    run(loadState);
  }
  document.addEventListener('click',event=>{
    if (!root || (!(event.target instanceof Element)) || (!root.contains(event.target) && !nav?.contains(event.target))) return;
    const target=event.target.closest('button,[data-em-close]'); if (!target) return;
    if (target.hasAttribute('data-em-shared-edit')) {event.preventDefault(); node('[data-em-connect-form]').elements.shared_id.value=target.dataset.emSharedEdit; connectFields(true);}
    else if (target.hasAttribute('data-em-order-up')) {event.preventDefault(); const i=Number(target.dataset.emOrderUp); moveOrder(i,i-1);}
    else if (target.hasAttribute('data-em-order-down')) {event.preventDefault(); const i=Number(target.dataset.emOrderDown); moveOrder(i,i+1);}
    else if (target.hasAttribute('data-em-action')) {event.preventDefault(); run(()=>action(target.dataset.emAction));}
    else if (target.hasAttribute('data-em-folder')) {event.preventDefault(); folder=target.dataset.emFolder; page=1; folderNavigation(); run(loadList);}
    else if (target.hasAttribute('data-em-message')) run(()=>openMessage(messages[Number(target.dataset.emMessage)]));
    else if (target.hasAttribute('data-em-attachment')) run(async()=>{
      const blob=await api('attachment',{...ref(selected),part:Number(target.dataset.emAttachment)}); const url=URL.createObjectURL(blob);
      const a=document.createElement('a'); a.href=url; a.download=selected.attachments.find(a=>a.part===Number(target.dataset.emAttachment))?.name || 'priloha'; a.click(); setTimeout(()=>URL.revokeObjectURL(url),30000);
    });
    else if (target.hasAttribute('data-em-close')) {
      const dialog=target.closest('dialog');
      if (dialog.hasAttribute('data-em-compose')) run(closeCompose);
      else {if (settingsDirty && !confirm('Zahodit neuložené nastavení?')) return; settingsDirty=false; dialog.querySelector('form').reset(); dialog.close();}
    }
  });
  document.addEventListener('change',event=>{
    if (!root) return;
    if (event.target.matches('[data-em-accounts]') && nav?.contains(event.target)) {
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
    if (form.matches('[data-em-search]')) {page=1;run(loadList);}
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
    if (event.target.hasAttribute('data-em-compose')) {event.preventDefault();run(closeCompose);}
    else if (settingsDirty) {if(!confirm('Zahodit neuložené nastavení?'))event.preventDefault();else settingsDirty=false;}
  },true);
  document.addEventListener('cb:main-before-load',event=>{
    if (root && (sending || ((dirty || settingsDirty) && !confirm('Opustit E-mail a zahodit neuložené změny?')))) event.preventDefault();
  });
  window.addEventListener('beforeunload',event=>{if (root && (dirty || settingsDirty || sending)) {event.preventDefault();event.returnValue='';}});
  document.addEventListener('cb:main-swapped',initialize);
  // Postovni kontrola bezi oddelene i mimo modul a vyuzije pouze schranky odemcene v aktualnim loginu.
  async function background() {
    const link=document.querySelector('[data-cb-email-api]');
    if (!link || document.hidden || backgroundRunning) return;
    backgroundRunning=true;
    try {
      const url=new URL(link.dataset.cbEmailApi,location.href); url.searchParams.set('action','background');
      const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json','X-Comeback-Pc-Session':String(window.CB_PC_SESSION_TOKEN || '')}});
      if ([401,403,409].includes(response.status)) { document.querySelectorAll('[data-cb-email-unread]').forEach(dot=>{dot.hidden=true;dot.textContent='';}); return; }
      if (response.ok && window.CB_HELPDESK_HEADER) window.CB_HELPDESK_HEADER.refresh();
    } catch (_) { /* Nedostupna posta nesmi blokovat praci v ostatnich modulech. */ }
    finally {backgroundRunning=false;}
  }
  setInterval(background,60000);
  // Zamek rozepsane odpovedi ma omezenou platnost; po zavreni okna se sam uvolni.
  setInterval(()=>{
    if (!root?.isConnected || document.hidden || sending || busy || !account) return;
    busy=true;
    run(async()=>{
      if (compose?.source && selected?.shared && compose.kind!=='draft_edit') {
        const result=await api('renew',compose.source,true); compose.version=Number(result.work.verze);
      } else if (!node('dialog[open]') && currentAccount()?.unlocked) await loadList();
    }).finally(()=>{busy=false;});
  },60000);
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',initialize); else initialize();
}());
