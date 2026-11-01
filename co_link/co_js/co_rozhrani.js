/* Co-Link: sdilene prvky rozhrani, formulare, upozorneni a jeden pristupny dialog. */
(window.CoLinkParts ||= Object.create(null)).rozhrani=function(ctx){
  'use strict';
  const {$}=ctx;
  // Vazby mezi oblastmi se vyhodnoti az pri volani; vsechny pouzivaji jednu instanci stavu.

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
  function notice(text){$('notice').textContent=text;$('notice').hidden=false;clearTimeout(ctx.noticeTimer);ctx.noticeTimer=setTimeout(()=>$('notice').hidden=true,7000);}
  // Zobrazi cas zpravy v ceskem formatu.
  function date(value){return value?new Date(value.replace(' ','T')).toLocaleString('cs-CZ',{day:'numeric',month:'numeric',hour:'2-digit',minute:'2-digit'}):'';}
  // Prevede velikost prilohy na citelne jednotky.
  function bytes(value){return Number(value)>=1048576?(Number(value)/1048576).toFixed(1)+' MB':Math.ceil(Number(value)/1024)+' kB';}
  // Sdili jeden pristupny dialog a ukoncuje jeho docasne casovace.
  function panel(title){if(ctx.panelCleanup)ctx.panelCleanup();ctx.panelCleanup=null;$('panel-title').textContent=title;$('panel-content').replaceChildren();$('panel-content').className='';if(!$('panel').open)$('panel').showModal();return $('panel-content');}
  // Vytvori popsane pole nastavovaciho formulare.
  function field(parent,label,value,type='text'){const wrap=node('label',label,'field'),input=node('input');input.type=type;input.value=value;wrap.append(input);parent.append(wrap);return input;}
  // Vytvori dotykove dostupnou oznacenou volbu.
  function check(parent,label,checked=false){const wrap=node('label',undefined,'check'),input=node('input');input.type='checkbox';input.checked=checked;wrap.append(input,node('span',label));parent.append(wrap);return input;}
  // Sestavi vyber osob nebo pracovnich zarazeni podle jejich ID.
  function checklist(parent,label,items,selected=[]){parent.append(node('p',label,'section-label'));const list=node('div',undefined,'checks'),map=new Map();for(const item of items)map.set(Number(item.id),check(list,item.jmeno||item.nazev,selected.includes(Number(item.id))));parent.append(list);return ()=>[...map].filter(([,input])=>input.checked).map(([id])=>id);}

  return {node,personAvatar,button,fail,notice,date,bytes,panel,field,check,checklist};
};
