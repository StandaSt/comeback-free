/* Účel: Zobrazuje celá sdělení v kompaktní třísloupcové liště všech modulů a obsluhuje jejich správu s přesným náhledem příjemců.
 * Zobrazení se zapisuje až po skutečné viditelnosti karty; přečtení pouze výslovným tlačítkem.
 */
(function () {
  'use strict';
  var messages=[], serverOffset=0, busy=false, ackPending=false, revision=0, timer=0, shown=new Set(), marking=new Set();
  var banner=document.createElement('section');banner.className='sd_banner';banner.hidden=true;
  banner.setAttribute('aria-label','Info od admina');
  var heading=document.createElement('div');heading.className='sd_banner_heading';
  var category=document.createElement('strong');category.textContent='Info od admina';
  var counter=document.createElement('span');counter.className='sd_meta';
  var meta=document.createElement('span');meta.className='sd_meta';heading.append(category,meta,counter);
  var content=document.createElement('div');content.className='sd_banner_content';
  var title=document.createElement('h2');title.className='sd_title';
  var text=document.createElement('p');text.className='sd_text';
  var actions=document.createElement('div');actions.className='sd_banner_actions';
  var ack=document.createElement('button');ack.type='button';ack.className='sd_button';ack.textContent='Přečteno, zavřít';
  var status=document.createElement('p');status.className='sd_status';status.setAttribute('role','status');
  // Oddělené sloupce drží datum vlevo, celý text uprostřed a potvrzení vpravo.
  content.append(title,text,status);actions.append(ack);banner.append(heading,content,actions);

  /** Jediný transport zachovává standardní session a CRF ochranu aplikace. */
  function request(action, data) {
    var url=new URL(window.CB_ENDPOINT||'index.php',window.location.href);url.search='';url.searchParams.set('cb_sdeleni',action);
    var opts={credentials:'same-origin',headers:{Accept:'application/json'}};
    if(data){opts.method='POST';opts.body=data;}
    return fetch(url.toString(),opts).then(function(response){return response.json().then(function(result){
      if(!response.ok||!result.ok)throw new Error(result.err||'Požadavek na sdělení se nezdařil.');
      return result;
    });});
  }
  function params(values){var p=new URLSearchParams();Object.keys(values).forEach(function(k){p.set(k,String(values[k]));});return p;}

  /** Karta se přenáší do pracovní plochy po každém přepnutí modulu, nikdy nepatří jednomu modulu. */
  function mount(){var page=document.querySelector('#obal_main .pp');if(page&&banner.parentElement!==page){page.prepend(banner);observer.disconnect();observer.observe(heading);} }
  function receive(data){messages=data.zpravy||[];serverOffset=Number(data.server_ts)*1000-Date.now();render();}
  function current(){var now=Date.now()+serverOffset;return messages.filter(function(m){return Number(m.platnost_do_ts)*1000>now;});}
  function render(){
    clearTimeout(timer);mount();var list=current();banner.hidden=!list.length;
    if(!list.length)return;
    var m=list[0];title.textContent=m.nadpis;text.textContent=m.obsah;
    counter.textContent=list.length>1?'Zbývá '+list.length+' sdělení':'';
    meta.textContent=m.odeslano_text;meta.title=m.autor||'Administrátor';
    status.textContent='';ack.disabled=ackPending;
    timer=setTimeout(render,Math.max(100,Math.min(2147483000,Number(m.platnost_do_ts)*1000-Date.now()-serverOffset+100)));
    seen();
  }
  /** Pouhé stažení dat nestačí: karta musí být na obrazovce ve viditelné záložce. */
  function seen(){
    var list=current();if(!list.length||banner.hidden||document.visibilityState!=='visible')return;
    var m=list[0],id=String(m.id_sdeleni),r=heading.getBoundingClientRect();
    if(m.zobrazeno||shown.has(id)||marking.has(id)||r.bottom<=0||r.top>=window.innerHeight||!heading.isConnected)return;
    markSeen(id);
  }
  function markSeen(id){
    if(shown.has(id)||marking.has(id))return;
    marking.add(id);
    request('zobrazeno',params({id_sdeleni:id})).then(function(){shown.add(id);}).catch(function(error){status.textContent=error.message;}).finally(function(){marking.delete(id);});
  }
  var observer=new IntersectionObserver(seen,{threshold:0});
  // Také osobní archiv eviduje první skutečné zobrazení, nikoli přednačtení všech řádků.
  var archiveObserver=new IntersectionObserver(function(entries){
    if(document.visibilityState!=='visible')return;
    entries.forEach(function(entry){if(entry.isIntersecting&&entry.target.dataset.sdeleniZobrazeno==='0')markSeen(entry.target.dataset.sdeleniArchivId);});
  },{threshold:0});
  function observeArchives(){archiveObserver.disconnect();document.querySelectorAll('[data-sdeleni-archiv-id]').forEach(function(el){archiveObserver.observe(el);});}
  ack.addEventListener('click',function(){var m=current()[0];if(!m||ack.disabled)return;ack.disabled=true;ackPending=true;revision++;
    request('precteno',params({id_sdeleni:m.id_sdeleni})).then(function(data){ackPending=false;receive(data);window.dispatchEvent(new Event('cb:sdeleni-precteno'));}).catch(function(error){status.textContent=error.message;ack.disabled=false;}).finally(function(){ackPending=false;});
  });
  // Opožděná starší odpověď nesmí vrátit již potvrzenou zprávu zpět na obrazovku.
  function refresh(){if(busy||ackPending)return;busy=true;var ticket=revision;request('seznam').then(function(data){if(ticket===revision)receive(data);}).catch(function(error){if(!banner.hidden)status.textContent=error.message;}).finally(function(){busy=false;});}

  /** Samostatná obsluha formuláře nezávislá na AJAX životním cyklu Administrace. */
  function initAdmin(){
    var root=document.querySelector('[data-sdeleni-admin]');if(!root||root.dataset.ready)return;root.dataset.ready='1';
    var form=root.querySelector('[data-sdeleni-form]'),notice=root.querySelector('[data-sdeleni-status]');
    var userList=form.querySelector('[data-sdeleni-users]'),userSummary=form.querySelector('[data-sdeleni-users-summary]');
    var preview=root.querySelector('[data-sdeleni-preview]'),previewBtn=root.querySelector('[data-sdeleni-preview-btn]'),publish=root.querySelector('[data-sdeleni-publish]');
    var token='',pending=false,sequence=0;
    function invalidate(){sequence++;token='';preview.hidden=true;}
    function mode(){var m=form.elements.rezim.value;
      var group=form.querySelector('[data-sdeleni-skupina]'),person=form.querySelector('[data-sdeleni-jednotlivec]');
      group.hidden=m!=='skupina';person.hidden=m!=='jednotlivec';
      group.querySelectorAll('input').forEach(function(i){i.disabled=m!=='skupina';});
      userList.querySelectorAll('input').forEach(function(i){i.disabled=m!=='jednotlivec';});
      var selected=userList.querySelectorAll('input:checked').length;
      userSummary.textContent=selected?'Vybráno: '+selected:'Vyberte uživatele';
    }
    form.addEventListener('input',invalidate);form.addEventListener('change',function(){invalidate();mode();});
    request('ciselniky').then(function(data){
      ['role','sloty','pobocky'].forEach(function(key){var list=form.querySelector('[data-sdeleni-list="'+key+'"]');
        data[key].forEach(function(item){var label=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.name=key+'[]';input.value=item.id;label.append(input,document.createTextNode(item.nazev));list.append(label);});
      });
      // Každý příjemce má vlastní checkbox; FormData předá stejný seznam ID jako serverový náhled.
      data.lide.forEach(function(person){var label=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.name='id_users[]';input.value=person.id_user;label.append(input,document.createTextNode(person.jmeno||'Uživatel #'+person.id_user));userList.append(label);});
      mode();previewBtn.disabled=false;
    }).catch(function(error){notice.textContent=error.message;});
    form.addEventListener('submit',function(event){event.preventDefault();if(pending||!form.reportValidity())return;
      if(form.elements.rezim.value==='jednotlivec'&&!userList.querySelector('input:checked')){notice.textContent='Vyberte alespoň jednoho uživatele.';return;}
      pending=true;invalidate();var ticket=sequence;notice.textContent='Načítám příjemce…';previewBtn.disabled=true;
      request('nahled',new URLSearchParams(new FormData(form))).then(function(data){
        if(ticket!==sequence){notice.textContent='Formulář se změnil. Obnovte náhled.';return;}
        token=data.token;root.querySelector('[data-sdeleni-preview-title]').textContent=data.nadpis;root.querySelector('[data-sdeleni-preview-body]').textContent=data.obsah;
        root.querySelector('[data-sdeleni-preview-count]').textContent='Počet příjemců: '+data.prijemci.length+'. Platnost ve dnech: '+data.dny+' (od zveřejnění).';
        var names=root.querySelector('[data-sdeleni-preview-names]');names.replaceChildren();data.prijemci.forEach(function(person){var li=document.createElement('li');li.textContent=person.jmeno||'Uživatel #'+person.id_user;names.append(li);});
        preview.hidden=false;publish.disabled=false;notice.textContent='';
      }).catch(function(error){notice.textContent=error.message;}).finally(function(){pending=false;previewBtn.disabled=false;});
    });
    publish.addEventListener('click',function(){if(pending||!token)return;pending=true;publish.disabled=true;previewBtn.disabled=true;
      // Při zveřejňování nelze měnit formulář ani omylem odeslat jiný rozpracovaný obsah.
      var enabled=Array.from(form.querySelectorAll('input,select,textarea')).filter(function(e){return !e.disabled;});enabled.forEach(function(e){e.disabled=true;});
      request('zverejnit',params({token:token})).then(function(data){token='';preview.hidden=true;notice.textContent='Sdělení bylo zveřejněno pro '+data.pocet+' uživatelů.';form.reset();refresh();
        // Archiv se obnoví stejnou navigací jako běžné stránky IS.
        var link=document.createElement('a');link.href=new URL('index.php?m=administrace&page=sdeleni',new URL(window.CB_ENDPOINT,window.location.href)).toString();link.textContent='Obnovit archiv';notice.append(document.createTextNode(' '),link);
      }).catch(function(error){notice.textContent=error.message;token='';preview.hidden=true;}).finally(function(){pending=false;previewBtn.disabled=false;enabled.forEach(function(e){e.disabled=false;});mode();});
    });
  }
  document.addEventListener('cb:main-swapped',function(){mount();observeArchives();initAdmin();refresh();});
  document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'){seen();observeArchives();refresh();}});
  window.addEventListener('focus',refresh);
  mount();observeArchives();initAdmin();refresh();setInterval(refresh,60000);
}());
