/* Účel: Jednotně odesílá události ovládání do ověřujícího vstupu aktivního modulu; nepotvrzené události uchová podle skutečného loginu. */
(function () {
  'use strict';
  if (window.CB_CINNOST) return;
  var queue=[], busy=false, retryAt=0, key='cb_cinnost_v1';
  try { queue=JSON.parse(sessionStorage.getItem(key)||'[]').filter(function(e){return e && e.at>Date.now()-3600000;}); } catch(e) { queue=[]; }
  // Při přechodu do jiného modulu zůstává události její původní endpoint a vlastník; server vlastníka vždy porovná.
  function persist(){try{sessionStorage.setItem(key,JSON.stringify(queue));}catch(e){console.warn('Činnost: frontu nelze uchovat při odchodu.');}}
  // Dávka se odstraní až podle potvrzených UUID; síťová chyba uchová události pro další pokus.
  function flush(urgent){
    if(busy || !queue.length || (!urgent && Date.now()<retryAt))return;
    var first=queue[0], batch=queue.filter(function(e){return e.owner===first.owner && e.url===first.url;}).slice(0,20);
    busy=true;
    fetch(first.url,{method:'POST',credentials:'same-origin',keepalive:true,headers:{'Content-Type':'application/json','Accept':'application/json','X-Comeback-Crf':window.CB_CRF_TOKEN||''},body:JSON.stringify({owner:first.owner,events:batch.map(function(e){return e.event;})})})
      .then(function(r){return r.json().then(function(data){
        if(r.status===401 || r.status===403){queue=queue.filter(function(e){return e.owner!==first.owner;});persist();return;}
        if(r.status===400){var rejected=new Set(batch.map(function(e){return e.event.parametry.udalost_uuid;}));queue=queue.filter(function(e){return !rejected.has(e.event.parametry.udalost_uuid);});persist();console.error('Činnost: server odmítl neplatnou dávku.');return;}
        if(!r.ok || !data.ok){retryAt=Date.now()+15000;console.warn('Činnost se zatím neuložila (HTTP '+r.status+').');return;}
        var sent=new Set(batch.map(function(e){return e.event.parametry.udalost_uuid;}));
        var accepted=new Set(Array.isArray(data.accepted)?data.accepted:[]);
        queue=queue.filter(function(e){return !(sent.has(e.event.parametry.udalost_uuid)&&accepted.has(e.event.parametry.udalost_uuid));});persist();
      });}).catch(function(){retryAt=Date.now()+15000;}).finally(function(){busy=false;});
  }
  // Volající dodá jen akci a parametry; endpoint i skutečný login pocházejí z vykresleného serverového kontextu.
  function zapsat(action,parameters){
    var root=document.querySelector('[data-cb-cinnost-url]');
    if(!root)return;
    var owner=root.dataset.cbCinnostOwner,url=root.dataset.cbCinnostUrl;
    queue=queue.filter(function(e){return e.owner===owner;});
    if(queue.length>=300){console.error('Činnost: fronta je plná, novou událost nelze uchovat.');return;}
    var p=Object.assign({},parameters,{udalost_uuid:crypto.randomUUID()});
    if(!p.id_stranka)p.id_stranka=Number(root.dataset.cbCinnostPage);
    queue.push({owner:owner,url:url,at:Date.now(),event:{id_akce:action,parametry:p}});persist();
    if(queue.length>=20)flush();
  }
  window.CB_CINNOST={zapsat:zapsat,flush:flush};
  setInterval(function(){flush();},1000);
  window.addEventListener('pagehide',function(){flush(true);});
  document.addEventListener('visibilitychange',function(){if(document.hidden)flush(true);});
}());
