/* Co-Link: koncepty, editor, zminky, prilohy a spolehliva fronta odesilani. */
(window.CoLinkParts ||= Object.create(null)).psani=function(ctx){
  'use strict';
  const {$,state}=ctx;
  // Vazby mezi oblastmi se vyhodnoti az pri volani; vsechny pouzivaji jednu instanci stavu.
  const node=(...args)=>ctx.node(...args);
  const button=(...args)=>ctx.button(...args);
  const fail=(...args)=>ctx.fail(...args);
  const notice=(...args)=>ctx.notice(...args);
  const api=(...args)=>ctx.api(...args);
  const refresh=(...args)=>ctx.refresh(...args);
  const panel=(...args)=>ctx.panel(...args);

  // Oddeli lokalni data jednotlivych firemnich uctu.
  function storageKey(){return 'co-link:'+state.me.id;}
  // Obnovi jen cekajici zpravy prihlaseneho uzivatele.
  function restore(){try{const saved=JSON.parse(localStorage.getItem(storageKey())||'{}');state.queue=Array.isArray(saved.queue)?saved.queue:[];}catch(_){state.queue=[];}}
  // Uchova frontu i pri zavreni aplikace nebo vypadku.
  function persist(){try{localStorage.setItem(storageKey(),JSON.stringify({queue:state.queue}));}catch(_){notice('Telefon nemůže uložit čekající zprávy. Nezavírejte Co-Link, dokud server nepotvrdí odeslání.');}}
  // Rozlisi rozepsany text kanalu a vlakna.
  function draftKey(){return storageKey()+':draft:'+state.cid+':'+state.thread;}
  // Ulozi rozepsany text pred prechodem jinam.
  function saveDraft(){if(state.me&&state.cid){try{localStorage.setItem(draftKey(),ctx.composerEditor.value);}catch(_){/* Zpravu stale drzi otevrene pole. */}}}
  // Vrati rozepsany text konkretni konverzace.
  function loadDraft(){try{ctx.composerEditor.value=localStorage.getItem(draftKey())||'';}catch(_){ctx.composerEditor.clear();}CoEmoji.close();resizeComposer();}
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
  function mentions(){const before=ctx.composerEditor.beforeCaret(),match=before.match(/(?:^|\s)@([^\s@\[\]]{0,40})$/u),box=$('mention-list');box.replaceChildren();box.hidden=!match;if(!match)return;const query=match[1].toLocaleLowerCase('cs');state.people.filter(p=>p.jmeno.toLocaleLowerCase('cs').includes(query)).slice(0,6).forEach(p=>box.append(button(p.jmeno,()=>{ctx.composerEditor.replaceBefore(match[1].length+1,'@['+p.jmeno+']('+p.id+') ');box.hidden=true;saveDraft();})));}
  // Propoji editor, prilohy a odeslani jednou pri inicializaci aplikace.
  function bindComposer(){
    ctx.composerEditor=CoEditor.create($('message-editor'),{id:'message-text',onChange:()=>{saveDraft();resizeComposer();mentions();},onMention:mentions});
    ctx.composerEditor.footer.prepend($('attach'));ctx.composerEditor.footer.append($('send-message'));
    $('message-text').addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey&&!e.isComposing&&matchMedia('(pointer:fine)').matches){e.preventDefault();$('composer').requestSubmit();}});
    $('composer').addEventListener('submit',e=>{e.preventDefault();const text=ctx.composerEditor.value;if(!ctx.composerEditor.text.trim()&&!state.staged.length)return;if(ctx.composerEditor.text.length>20000){notice('Zpráva může mít nejvýše 20 000 znaků.');return;}if($('attach').disabled){notice('Počkejte na dokončení nahrávání příloh.');return;}const id=crypto.randomUUID?crypto.randomUUID():Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');state.queue.push({klient_id:id,konverzace:state.cid,rodic:state.thread||null,text,prilohy:state.staged.map(f=>Number(f.id)),failed:false});persist();ctx.composerEditor.clear();CoEmoji.close();saveDraft();state.staged=[];renderStaged();resizeComposer();pending();flush();});
    $('attach').addEventListener('click',()=>{const p=panel('Přiložit ke zprávě');p.append(button('Vybrat fotografie nebo soubory',()=>{$('panel').close();$('file').click();}),button('Vyfotit na telefonu',()=>{$('panel').close();$('camera').click();}));});$('file').addEventListener('change',()=>upload($('file')).catch(fail));$('camera').addEventListener('change',()=>upload($('camera')).catch(fail));
  }

  return {storageKey,restore,persist,draftKey,saveDraft,loadDraft,resizeComposer,pending,flush,renderStaged,upload,mentions,bindComposer};
};
