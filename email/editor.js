/* E-mail: lokalni Quill editor s ceskou listou a zachovanim vyberu pri prepinani formatu; bez externi sluzby. */
let library;

/** Quill pouziva pro odrazky take OL s data-list; prevedeme jej na skutecne UL/OL a zachovame zarovnani jednotlivych LI. */
function exportHtml(root) {
  const clone=root.cloneNode(true);
  clone.querySelectorAll('.ql-ui').forEach(node=>node.remove());
  for (const list of clone.querySelectorAll('ol')) {
    const result=document.createDocumentFragment(); let group=null, previous='';
    for (const item of Array.from(list.children)) {
      if (item.tagName!=='LI') continue;
      const type=item.dataset.list==='bullet'?'ul':'ol';
      if (previous!==type) {group=document.createElement(type);result.append(group);previous=type;}
      item.removeAttribute('data-list'); group.append(item);
    }
    list.replaceWith(result);
  }
  return clone.innerHTML;
}

/** Knihovna a jeji styly se nacitaji az pri prvnim skutecnem pouziti editoru. */
async function quillLibrary() {
  if (!library) library=new Promise((resolve,reject)=>{
    const css=document.createElement('link'); css.rel='stylesheet'; css.href=new URL('./editor/quill.snow.css',import.meta.url); document.head.append(css);
    const script=document.createElement('script'); script.src=new URL('./editor/quill.js',import.meta.url);
    script.onload=()=>{
      const Q=window.Quill;
      const font=Q.import('attributors/style/font'); font.whitelist=['Arial','Verdana','Georgia','Courier New']; Q.register(font,true);
      const size=Q.import('attributors/style/size'); size.whitelist=['10px','12px','14px','16px','18px','24px','32px']; Q.register(size,true);
      Q.register(Q.import('attributors/style/align'),true);
      resolve(Q);
    };
    script.onerror=()=>{library=null;reject(new Error('Editor se nepodařilo načíst. Obnovte stránku.'));}; document.head.append(script);
  });
  return library;
}

/** Kazdy formular ma vlastni dokument a historii; nacitani konceptu ani podpisu neoznaci formular jako rozepsany. */
export async function createEditor(host, label, onChange) {
  const Quill=await quillLibrary();
  host.classList.add('em-rich');
  const toolbar=document.createElement('div'); toolbar.className='em-rich-toolbar';
  toolbar.innerHTML=`<span class="ql-formats"><button type="button" class="ql-bold" title="Tučné" aria-label="Tučné"></button><button type="button" class="ql-italic" title="Kurzíva" aria-label="Kurzíva"></button><button type="button" class="ql-underline" title="Podtržení" aria-label="Podtržení"></button></span>
    <span class="ql-formats"><select class="ql-font" aria-label="Písmo"><option value="" selected>Arial</option><option value="Verdana">Verdana</option><option value="Georgia">Georgia</option><option value="Courier New">Courier New</option></select><select class="ql-size" aria-label="Velikost písma"><option value="10px">10 px</option><option value="12px">12 px</option><option value="" selected>14 px</option><option value="16px">16 px</option><option value="18px">18 px</option><option value="24px">24 px</option><option value="32px">32 px</option></select></span>
    <span class="ql-formats"><select class="ql-color" aria-label="Barva textu"><option value="" selected></option><option value="#e60000"></option><option value="#ff9900"></option><option value="#008a00"></option><option value="#0066cc"></option><option value="#9933ff"></option><option value="#777777"></option></select><select class="ql-align" aria-label="Zarovnání"><option value="" selected></option><option value="center"></option><option value="right"></option><option value="justify"></option></select></span>
    <span class="ql-formats"><button type="button" class="ql-list" value="bullet" title="Odrážky" aria-label="Odrážky"></button><button type="button" class="ql-list" value="ordered" title="Číslovaný seznam" aria-label="Číslovaný seznam"></button><button type="button" class="ql-link" title="Vložit nebo změnit odkaz" aria-label="Vložit nebo změnit odkaz"></button><button type="button" class="ql-clean" title="Zrušit formátování" aria-label="Zrušit formátování"></button></span>
    <span class="ql-formats"><button type="button" class="ql-undo" title="Zpět" aria-label="Zpět">↶</button><button type="button" class="ql-redo" title="Znovu" aria-label="Znovu">↷</button></span>`;
  const body=document.createElement('div'); host.replaceChildren(toolbar,body);
  // Kliknuti na format nesmi presunout fokus z textu a vynulovat aktivni stav jeste pred obsluhou Quillu.
  toolbar.addEventListener('mousedown',event=>{
    if (event.button===0 && event.target instanceof Element && event.target.closest('button')) event.preventDefault();
  });
  const quill=new Quill(body,{theme:'snow',formats:['bold','italic','underline','font','size','color','align','list','link','blockquote'],
    modules:{history:{userOnly:true},toolbar:{container:toolbar,handlers:{
      undo(){this.quill.history.undo();}, redo(){this.quill.history.redo();},
      link(value){
        if (!value) {this.quill.format('link',false,'user');return;}
        const url=prompt('Adresa odkazu (https:// nebo mailto:):',this.quill.getFormat().link || 'https://');
        if (url===null) return;
        if (url==='' || /^(https?:\/\/|mailto:)[^\s]+$/i.test(url)) this.quill.format('link',url || false,'user');
        else alert('Zadejte platnou adresu začínající https://, http:// nebo mailto:.');
      }
    }}}});
  quill.root.setAttribute('role','textbox'); quill.root.setAttribute('aria-label',label); quill.root.setAttribute('aria-multiline','true');
  quill.on('text-change',(_delta,_old,source)=>{if(source==='user')onChange();});
  // Popisky voleb Snow by jinak pouzily anglicke vychozi nazvy i pri ceskych option prvcich.
  toolbar.querySelectorAll('.ql-picker').forEach(picker=>{
    const select=picker.nextElementSibling;
    if (!(select instanceof HTMLSelectElement)) return;
    const items=picker.querySelectorAll('.ql-picker-item');
    items.forEach((item,i)=>{item.dataset.emLabel=select.options[i]?.textContent || '';});
    const current=picker.querySelector('.ql-picker-label');
    const update=()=>{if(current)current.dataset.emLabel=select.selectedOptions[0]?.textContent || '';};
    select.addEventListener('change',update); update();
  });
  return {
    setHtml(html){quill.setContents(quill.clipboard.convert({html:html || '<p><br></p>'}),'silent');quill.history.clear();quill.setSelection(0,0,'silent');},
    getHtml(){return quill.getText().trim()===''?'':exportHtml(quill.root);},
    getText(){return quill.getText().replace(/\n$/,'');},
    focus(){quill.focus();quill.setSelection(0,0,'silent');},
    enable(value){quill.enable(value);toolbar.querySelectorAll('button,select').forEach(el=>{el.disabled=!value;});}
  };
}
