/* Co-Link: jednoucelovy vizualni editor textu se zachovanim kurzoru, formatovani, emoji a bezpecnym vlozenim textu. */
(() => {
  'use strict';
  // Kazda instance ma vlastni vyber, obsah i ovladani; pouziva se pro novou zpravu i jeji upravu.
  function create(host,{id='',label='Zpráva',onChange=()=>{},onMention=null}={}){
    host.classList.add('rich-editor');const toolbar=document.createElement('div');toolbar.className='editor-toolbar';toolbar.setAttribute('role','toolbar');toolbar.setAttribute('aria-label','Formátování textu');
    const body=document.createElement('div');body.className='rich-input';body.contentEditable='true';body.role='textbox';body.setAttribute('aria-label',label);body.setAttribute('aria-multiline','true');body.dataset.placeholder='Napište zprávu…';if(id)body.id=id;
    const footer=document.createElement('div');footer.className='editor-footer';host.append(toolbar,body,footer);let saved=null;
    // Vyber se uchova pri prechodu do palety emoji nebo na tlacitka listy.
    function remember(){const sel=getSelection();if(sel.rangeCount&&body.contains(sel.getRangeAt(0).commonAncestorContainer)){saved=sel.getRangeAt(0).cloneRange();for(const b of toolbar.querySelectorAll('[data-command]')){const active=document.queryCommandState(b.dataset.command);b.setAttribute('aria-pressed',String(active));b.classList.toggle('is-active',active);}}}
    function focus(){body.focus();const sel=getSelection();if(saved&&body.contains(saved.commonAncestorContainer)){sel.removeAllRanges();sel.addRange(saved);}else{const range=document.createRange();range.selectNodeContents(body);range.collapse(false);sel.removeAllRanges();sel.addRange(range);}remember();}
    function changed(){remember();onChange();}
    // Nativni prikazy zachovaji historii psani a chovani vyberu napric desktopem i mobilem.
    function command(name,value=null){focus();document.execCommand('styleWithCSS',false,false);document.execCommand(name,false,value);changed();}
    function control(text,title,action,parent=toolbar){const b=document.createElement('button');b.type='button';b.className='editor-tool';b.textContent=text;b.title=title;b.setAttribute('aria-label',title);b.addEventListener('mousedown',e=>e.preventDefault());b.addEventListener('click',action);parent.append(b);return b;}
    for(const [text,title,name]of [['B','Tučný text','bold'],['I','Kurzíva','italic'],['U','Podtržení','underline']]){const b=control(text,title,()=>command(name));b.dataset.command=name;}
    const palette=document.createElement('select');palette.className='editor-color';palette.setAttribute('aria-label','Barva textu');
    for(const [value,text]of [['','Barva textu'],['#b42318','Červená'],['#175cd3','Modrá'],['#18794e','Zelená'],['#9333a8','Fialová'],['#b45f06','Oranžová']]){const option=document.createElement('option');option.value=value;option.textContent=text;palette.append(option);}
    palette.addEventListener('pointerdown',remember);palette.addEventListener('change',()=>{command('foreColor',palette.value||getComputedStyle(body).color);});toolbar.append(palette);
    control('Tx','Odstranit formátování',()=>command('removeFormat'));
    const emoji=control('☺','Vložit emoji',()=>{remember();window.CoEmoji.open(emoji,value=>insert(value));},footer);
    if(onMention)control('@','Zmínit kolegu',()=>{insert('@');onMention();},footer);
    // Zminka ma v editoru citelne jmeno; do ulozeneho textu se vraci stabilni ID kolegy.
    function plain(host,text){for(const part of text.split(/(@\[[^\]\r\n]{1,100}\]\([0-9]+\))/g)){const match=part.match(/^@\[([^\]]+)\]\(([0-9]+)\)$/);if(match){const span=document.createElement('span');span.className='mention';span.contentEditable='false';span.dataset.mention=match[2];span.textContent='@'+match[1];host.append(span);}else host.append(document.createTextNode(part));}}
    // Pouze vlastni textove uzly se pripadne prevedou na HTML pro nativni vlozeni zminky.
    function insert(text){focus();if(/^@\[[^\]]+\]\([0-9]+\)\s*$/.test(text)){const holder=document.createElement('span');plain(holder,text);document.execCommand('insertHTML',false,holder.innerHTML);}else document.execCommand('insertText',false,text);changed();}
    body.addEventListener('paste',e=>{e.preventDefault();remember();insert((e.clipboardData?.getData('text/plain')||'').slice(0,20000));});
    body.addEventListener('drop',e=>e.preventDefault());body.addEventListener('input',changed);
    for(const event of ['keyup','mouseup','touchend','blur'])body.addEventListener(event,remember);
    // Pozice zminky se pocita ze stejneho textu jako pri serializaci noveho radku.
    function beforeCaret(){remember();if(!saved)return '';const range=saved.cloneRange();range.selectNodeContents(body);range.setEnd(saved.endContainer,saved.endOffset);return CoText.read(range.cloneContents()).text;}
    function replaceBefore(count,text){focus();for(let i=0;i<count;i++)document.execCommand('delete',false);insert(text);}
    return {body,footer,toolbar,focus,remember,insert,beforeCaret,replaceBefore,
      get value(){return CoText.read(body).value;},get text(){return CoText.read(body).text;},
      set value(value){body.replaceChildren();CoText.render(body,value,plain);saved=null;palette.value='';},
      clear(){body.replaceChildren();saved=null;palette.value='';},
      destroy(){window.CoEmoji.close();host.remove();}
    };
  }
  window.CoEditor={create};
})();
