/* Účel: Dávkuje významové události adaptérů modulů, uchová neodeslanou frontu této karty a potvrzuje jen serverem přijatá ID. */
(function () {
  'use strict';
  var config=window.CB_AKTIVITA;
  if (!config || !config.owner || !config.url || window.CB_USER_ACTIVITY) return;
  var key='cb_aktivita:'+config.owner, queue=[], pending=new Set(), retryAt=0, stopped=false;
  var pageId=crypto.randomUUID(), sequence=0;

  // Úložiště je oddělené podle skutečného loginu; po odhlášení se dávka neposílá za jiného uživatele.
  function persist() { try { sessionStorage.setItem(key,JSON.stringify(queue)); } catch (error) { /* Fronta v paměti zůstává funkční i při zakázaném úložišti. */ } }
  try {
    Object.keys(sessionStorage).filter(function (item) { return item.indexOf('cb_aktivita:')===0 && item!==key; }).forEach(function (item) { sessionStorage.removeItem(item); });
    var saved=JSON.parse(sessionStorage.getItem(key) || '[]');
    if (Array.isArray(saved)) queue=saved.filter(function (item) { return item && typeof item.id==='string' && typeof item.cas_ms==='number' && item.cas_ms>Date.now()-3600000; }).slice(-300);
  } catch (error) { queue=[]; }

  // Jedna dávka zůstane pod limitem keepalive; při navigaci ji může server potvrdit už nové stránce.
  function flush(urgent) {
    if (stopped || (!urgent && (pending.size || Date.now()<retryAt)) || !queue.length) return;
    var batch=[];
    queue.filter(function (event) { return !pending.has(event.id); }).slice(0,30).forEach(function (event) {
      var candidate=batch.concat(event);
      // Limit měří i URL kódování češtiny, které může být větší než samotný JSON.
      if (new URLSearchParams({cb_crf:window.CB_CRF_TOKEN || '',events:JSON.stringify(candidate)}).toString().length<48000) batch=candidate;
    });
    if (!batch.length) return;
    var body=new URLSearchParams({cb_crf:window.CB_CRF_TOKEN || '',events:JSON.stringify(batch)});
    batch.forEach(function (event) { pending.add(event.id); });
    fetch(config.url,{method:'POST',body:body,credentials:'same-origin',keepalive:true,headers:{Accept:'application/json'}})
      .then(function (response) {
        if (response.status===401 || response.status===403) { stopped=true; queue=[]; persist(); return null; }
        return response.json().then(function (result) {
          var accepted=Array.isArray(result.accepted)?result.accepted:[];
          if(response.status===400) { accepted=batch.map(function (item) { return item.id; }); console.warn('Historie aktivity: server odmítl neplatnou dávku.'); }
          // Server nesmí omylem potvrdit ID, které nebylo součástí této dávky.
          var sent=batch.map(function (item) { return item.id; });
          queue=queue.filter(function (item) { return !(sent.includes(item.id) && accepted.includes(item.id)); });
          persist();
          if (!response.ok || !result.ok) retryAt=Date.now()+15000;
          return result;
        });
      }).catch(function () { retryAt=Date.now()+15000; })
      .finally(function () { batch.forEach(function (event) { pending.delete(event.id); }); });
  }

  // Adaptér předává jen známé skalární hodnoty; server znovu nezávisle ověří katalog a rozsah polí.
  function record(module,code,page,detail) {
    if (stopped) return '';
    var event={id:crypto.randomUUID(),karta:pageId,poradi:++sequence,cas_ms:Date.now(),modul:module,udalost:code,stranka:page,detail:detail || {}};
    queue.push(event);
    if (queue.length>300) { queue.shift(); console.warn('Historie aktivity: fronta dosáhla limitu, nejstarší nepotvrzená událost byla vyřazena.'); }
    persist();
    if (queue.length>=20) flush();
    return event.id;
  }
  window.CB_USER_ACTIVITY={record:record,flush:flush};
  setInterval(flush,2000);
  document.addEventListener('visibilitychange',function () { if (document.hidden) flush(true); });
  window.addEventListener('pagehide',function () { flush(true); });
  flush();
}());
