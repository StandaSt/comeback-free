/* Co-Link: bezpecny prevod textu a omezeneho formatovani; nikdy neinterpretuje ulozenou zpravu jako HTML. */
(() => {
  'use strict';
  const separator='\n\u001eCO1:';
  const colors=['#b42318','#175cd3','#18794e','#9333a8','#b45f06'];
  // Barvy z nativniho editoru sjednotime na povolenou paletu.
  function color(value){value=String(value||'').toLowerCase();const rgb=value.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);if(rgb)value='#'+rgb.slice(1).map(n=>Number(n).toString(16).padStart(2,'0')).join('');return colors.includes(value)?value:null;}
  // Text je na zacatku zaznamu souvisle, aby fungovalo existujici vyhledavani i pres zmeny stylu.
  function parse(value){
    const raw=String(value||''),at=raw.lastIndexOf(separator);if(at<0)return {text:raw,marks:[]};
    try{const text=raw.slice(0,at),marks=JSON.parse(raw.slice(at+separator.length));if(!Array.isArray(marks)||marks.length>2000)throw 0;
      let end=0;for(const m of marks){if(!m||!Number.isInteger(m.s)||!Number.isInteger(m.e)||m.s<end||m.e<=m.s||m.e>text.length||m.c&&!colors.includes(m.c))throw 0;end=m.e;}
      return {text,marks};
    }catch(_){return {text:raw,marks:[]};}
  }
  // Uklada pouze povolene styly a jejich pozice, nikoli HTML nebo vlozene objekty.
  function pack(text,marks){return marks.length?text+separator+JSON.stringify(marks):text;}
  // Jednotny prevod DOM editoru zachovava nove radky i pri psani na mobilu.
  function read(root){
    let text='';const marks=[];
    // Sousedni stejne styly sloucime, aby metadata nerostla s kazdym stiskem klavesy.
    function append(value,style){value=value.replace(/\u200b/g,'');if(!value)return;const s=text.length;text+=value;if(style.b||style.i||style.u||style.c){const m={s,e:text.length,...style},last=marks.at(-1);if(last&&last.e===s&&['b','i','u','c'].every(k=>last[k]===m[k]))last.e=m.e;else marks.push(m);}}
    // Z prohlizecoveho DOM prijimame jen text, nove radky a predem povolene styly.
    function walk(el,style={}){
      if(el.nodeType===Node.TEXT_NODE){append(el.nodeValue,style);return;}if(el.nodeType!==Node.ELEMENT_NODE&&el.nodeType!==Node.DOCUMENT_FRAGMENT_NODE)return;
      const tag=el.tagName;if(['SCRIPT','STYLE','IFRAME','OBJECT'].includes(tag))return;
      if(/^[0-9]+$/.test(el.dataset?.mention||'')){append('@['+el.textContent.replace(/^@/,'')+']('+el.dataset.mention+')',style);return;}
      if(tag==='BR'){append('\n',style);return;}
      const next={...style};if(el.style?.fontWeight==='normal'||el.style?.fontWeight==='400')delete next.b;if(el.style?.fontStyle==='normal')delete next.i;
      if(['B','STRONG'].includes(tag)||Number(el.style?.fontWeight)>=600)next.b=true;if(['I','EM'].includes(tag)||el.style?.fontStyle==='italic')next.i=true;if(tag==='U'||el.style?.textDecoration?.includes('underline'))next.u=true;
      const explicit=el.style?.color||el.getAttribute?.('color'),c=color(explicit);if(explicit){if(c)next.c=c;else delete next.c;}
      const block=['DIV','P','LI'].includes(tag)&&el!==root;if(block&&text&&!text.endsWith('\n'))append('\n',{});
      for(const child of el.childNodes)walk(child,next);
      if(block&&el.nextSibling&&!text.endsWith('\n'))append('\n',{});
    }
    walk(root);return {text,marks,value:pack(text,marks)};
  }
  // Formatovane useky vznikaji z textovych uzlu; odkazy a zminky doplnuje stavajici renderer.
  function render(container,value,plain=(host,text)=>host.append(document.createTextNode(text))){
    const {text,marks}=parse(value);let at=0;
    for(const m of marks){if(m.s>at)plain(container,text.slice(at,m.s));let outer=document.createElement('span'),host=outer;
      if(m.c)outer.style.color=m.c;for(const [key,tag]of [['b','strong'],['i','em'],['u','u']])if(m[key]){const child=document.createElement(tag);host.append(child);host=child;}
      plain(host,text.slice(m.s,m.e));container.append(outer);at=m.e;
    }
    if(at<text.length)plain(container,text.slice(at));
  }
  window.CoText={parse,pack,read,render,colors,separator};
})();
