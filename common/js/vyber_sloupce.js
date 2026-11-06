/* Spolecny dvousloupcovy vyber nad selectem: zachovava hodnoty a change, pridava zarovnany doplnkovy text a ovladani klavesnici. */
(() => {
  'use strict';
  const instances=new WeakMap();let sequence=0,opened=null;
  /** Vylepsi select; option.dataset.cbSelectDetail nese druhy sloupec. Opakovane volani obnovi obsah. */
  function enhance(select) {
    if(!select || typeof HTMLElement.prototype.showPopover!=='function')return null;
    if(instances.has(select)){const instance=instances.get(select);instance.refresh();return instance;}
    const events=new AbortController(),on={signal:events.signal};
    const oldTab=select.getAttribute('tabindex'),oldAria=select.getAttribute('aria-hidden');
    const wrap=document.createElement('span');wrap.className='cb-column-select';
    select.before(wrap);wrap.append(select);select.classList.add('cb-column-select-source');select.tabIndex=-1;select.setAttribute('aria-hidden','true');
    const button=document.createElement('button');button.type='button';button.className='cb-column-select-trigger';
    button.setAttribute('role','combobox');button.setAttribute('aria-haspopup','listbox');button.setAttribute('aria-expanded','false');
    const popup=document.createElement('div');popup.className='cb-column-select-popup';popup.id='cb-column-select-'+(++sequence);popup.setAttribute('popover','manual');popup.setAttribute('role','listbox');
    button.setAttribute('aria-controls',popup.id);wrap.append(button,popup);
    let rows=[],active=-1,search='',searchAt=0;
    const textSpan=(text,className)=>{const span=document.createElement('span');span.className=className;span.textContent=text;return span;};
    /** Oba sloupce vznikaji jako textove uzly, obsah option proto nemuze vlozit HTML. */
    function columns(target,option){const name=textSpan(option?.label || '', 'cb-column-select-name');name.title=option?.label || '';target.replaceChildren(name,textSpan(option?.dataset.cbSelectDetail || '', 'cb-column-select-detail'));}
    function close(focus=false){if(opened===api)opened=null;if(popup.matches(':popover-open'))popup.hidePopover();button.setAttribute('aria-expanded','false');button.removeAttribute('aria-activedescendant');if(focus && button.isConnected)button.focus();}
    // Posouvame jen obsah nabidky; scrollIntoView by mohl posunout i celou stranku a nabidku zavrit.
    function mark(index){active=index;rows.forEach((r,i)=>r.node.classList.toggle('is-active',i===active));if(rows[active]){const node=rows[active].node;button.setAttribute('aria-activedescendant',node.id);const r=node.getBoundingClientRect(),p=popup.getBoundingClientRect();if(r.top<p.top)popup.scrollTop-=p.top-r.top;else if(r.bottom>p.bottom)popup.scrollTop+=r.bottom-p.bottom;}}
    /** Nabidka se vykresli v horni vrstve i nad orezanym menu a vejde se do aktualniho viewportu. */
    function position(){const r=button.getBoundingClientRect(),v=window.visualViewport;const left=v?.offsetLeft || 0,top=v?.offsetTop || 0,w=v?.width || innerWidth,h=v?.height || innerHeight;
      popup.style.width=Math.min(r.width,w-16)+'px';
      popup.style.left=Math.max(left+8,Math.min(r.left,left+w-popup.offsetWidth-8))+'px';
      const below=top+h-r.bottom-8,above=r.top-top-8,up=below<160 && above>below;
      popup.style.maxHeight=Math.max(60,Math.min(380,up?above:below))+'px';
      popup.style.top=(up?Math.max(top+8,r.top-popup.offsetHeight-2):r.bottom+2)+'px';
    }
    function open(){if(button.disabled)return;if(opened && opened!==api)opened.close();search='';searchAt=0;refresh();popup.showPopover();opened=api;button.setAttribute('aria-expanded','true');position();const index=rows.findIndex(r=>r.option.selected);mark(index<0?0:index);}
    function choose(index){const row=rows[index];if(!row)return;const changed=select.value!==row.option.value;select.value=row.option.value;close(true);refresh();if(changed)select.dispatchEvent(new Event('change',{bubbles:true}));}
    /** Obnovi pocty, skupiny, disabled i vybranou hodnotu bez zmeny puvodniho handleru formulare. */
    function refresh(){
      const wasOpen=popup.matches(':popover-open');rows=[];popup.replaceChildren();
      button.disabled=select.disabled;const label=select.getAttribute('aria-label') || select.labels?.[0]?.textContent || 'Výběr';button.setAttribute('aria-label',label);popup.setAttribute('aria-label',label);
      columns(button,select.selectedOptions[0]);
      function add(option){if(option.hidden)return;if(!option.label.trim()){const line=document.createElement('div');line.className='cb-column-select-separator';line.setAttribute('role','presentation');popup.append(line);return;}
        const node=document.createElement('div');node.className='cb-column-select-option';node.id=popup.id+'-option-'+option.index;node.setAttribute('role','option');node.setAttribute('aria-selected',String(option.selected));columns(node,option);
        if(option.disabled || option.parentElement.disabled){node.setAttribute('aria-disabled','true');popup.append(node);return;}
        const index=rows.length;rows.push({option,node});node.addEventListener('click',()=>choose(index));popup.append(node);
      }
      for(const child of select.children){if(child.tagName==='OPTGROUP'){const group=document.createElement('div');group.className='cb-column-select-group';group.textContent=child.label;group.setAttribute('role','presentation');popup.append(group);for(const option of child.children)add(option);}else if(child.tagName==='OPTION')add(child);}
      if(wasOpen){if(select.disabled)close();else{position();mark(rows.findIndex(r=>r.option.selected));}}
    }
    button.addEventListener('click',()=>opened===api?close():open(),on);
    button.addEventListener('keydown',event=>{
      const key=event.key,isOpen=opened===api;
      if(key==='Tab'){close();return;}
      if(key==='Escape'){if(isOpen){event.preventDefault();event.stopPropagation();close();}return;}
      if(['ArrowDown','ArrowUp','Home','End','Enter',' '].includes(key)){
        event.preventDefault();if(!isOpen){open();if(key==='Home')mark(0);else if(key==='End')mark(rows.length-1);return;}
        if(key==='Enter' || key===' ')choose(active);
        else mark(key==='Home'?0:key==='End'?rows.length-1:Math.max(0,Math.min(rows.length-1,active+(key==='ArrowDown'?1:-1))));return;
      }
      // Psaní nazvu funguje i s ceskou diakritikou; po pauze zacina nove hledani.
      if(key.length===1 && !event.ctrlKey && !event.metaKey && !event.altKey){event.preventDefault();if(!isOpen)open();const now=Date.now();search=now-searchAt>700?key:search+key;searchAt=now;const fold=s=>s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('cs');const index=rows.findIndex(r=>fold(r.option.label).startsWith(fold(search)));if(index>=0)mark(index);}
    },on);
    select.addEventListener('change',refresh,on);
    // Label a reset formulare musi zustat funkcni i po nahrazeni viditelneho ovladace.
    select.addEventListener('focus',()=>button.focus(),on);
    // Reset event predchazi obnove hodnot prohlizecem, proto obnovujeme az v nasledujicim tasku.
    select.form?.addEventListener('reset',()=>setTimeout(()=>{if(instances.has(select))refresh();},0),on);
    select.addEventListener('invalid',event=>{event.preventDefault();button.focus();},on);
    const api={refresh,close,destroy(){close();events.abort();instances.delete(select);select.classList.remove('cb-column-select-source');if(oldTab===null)select.removeAttribute('tabindex');else select.setAttribute('tabindex',oldTab);if(oldAria===null)select.removeAttribute('aria-hidden');else select.setAttribute('aria-hidden',oldAria);wrap.before(select);wrap.remove();}};
    instances.set(select,api);refresh();return api;
  }
  // Jedina globalni obsluha zabrani hromadeni listeneru pri opakovanem prekreslovani menu.
  document.addEventListener('pointerdown',event=>{if(opened && !event.target.closest('.cb-column-select'))opened.close();});
  window.addEventListener('resize',()=>opened?.close());window.addEventListener('scroll',()=>opened?.close());
  document.addEventListener('cb:main-before-load',()=>opened?.close());
  window.CB_COLUMN_SELECT={enhance,destroy(select){instances.get(select)?.destroy();}};
})();
