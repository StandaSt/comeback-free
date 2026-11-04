/* Diagnostika Co-Linku a E-mailu: metadata cekani a objemu dat; vypnuta nic neodesila a nesaha do tela odpovedi. */
(() => {
  'use strict';
  if (window.CB_DIAG) return;
  const script=document.currentScript;
  const endpoint=new URL('../ajax/diagnostika_vykonu.php',script.src);
  const initialUntil=Number(script.dataset.until || 0)*1000;
  const coDocument=Boolean(document.getElementById('co-boot'));
  const until={email:coDocument?0:initialUntil,co_link:coDocument?initialUntil:0};
  const queue=[];
  let timer=null, flushing=false;
  let observer=null,observerTimer=null,observerUntil=0;
  const resources=[];
  let coCsrf=(()=>{try{return JSON.parse(document.getElementById('co-boot')?.textContent || '{}').identita?.csrf || '';}catch(_){return '';}})();
  const scope=module=>module==='co_link'?coCsrf:String(window.CB_PC_SESSION_TOKEN || '')+':'+String(window.CB_CRF_TOKEN || '');
  const id=()=>globalThis.crypto?.randomUUID?.() || (Date.now().toString(36)+'_'+Math.random().toString(36).slice(2));
  const active=module=>Number(until[module])>Date.now();
  const number=value=>typeof value==='number' && Number.isFinite(value) && value>=0?Math.round(value*100)/100:null;
  const meta=data=>Object.fromEntries(['account','uid','conversation','page','search_chars','body','aggregate','aggregate_part','background','count'].filter(k=>Number.isFinite(Number(data?.[k]))).map(k=>[k,Number(data[k])]));

  // Vlastni omezeny observer zachova prenosove velikosti i po zaplneni bezne Resource Timing historie prohlizece.
  function remember(entries) {
    for(const entry of entries) {
      if(!/(?:\/email\/api\.php|\/co_link\/(?:api\.php|co_ajax\/co_api\.php))(?:\?|$)/.test(entry.name))continue;
      resources.push(entry);if(resources.length>200)resources.shift();
    }
  }
  function observe() {
    try {
      const expiry=Math.max(...Object.values(until));
      if(expiry<=Date.now()) {
        observer?.disconnect();observer=null;resources.length=0;clearTimeout(observerTimer);observerTimer=null;observerUntil=0;return;
      }
      if(!observer && typeof PerformanceObserver==='function') {
        observer=new PerformanceObserver(list=>remember(list.getEntries()));observer.observe({type:'resource'});
      }
      if(observer && observerUntil!==expiry) {clearTimeout(observerTimer);observerUntil=expiry;observerTimer=setTimeout(observe,Math.max(1,expiry-Date.now()+1));}
    } catch (_) {}
  }

  // Davky oddelujeme podle prihlaseni IS a samostatne overovaneho Co-Linku.
  async function flush() {
    if (flushing) return;
    clearTimeout(timer);timer=null;
    const module=queue[0]?.module;
    if (!module) return;
    const events=[];
    for(let i=0;i<queue.length && events.length<20;) {
      if(queue[i].module===module) {
        const {_scope,...event}=queue.splice(i,1)[0];
        if(_scope===scope(module))events.push(event);
      } else i++;
    }
    if (!active(module) || !events.length) {if(queue.length)timer=setTimeout(flush,1000);return;}
    flushing=true;
    try {
      const headers={'Accept':'application/json','Content-Type':'application/json'};
      if(module==='co_link') {
        headers['X-Co-Csrf']=coCsrf;
      } else {
        headers['X-Comeback-Crf']=String(window.CB_CRF_TOKEN || '');
        headers['X-Comeback-Pc-Session']=String(window.CB_PC_SESSION_TOKEN || '');
      }
      const control=new AbortController(),limit=setTimeout(()=>control.abort(),5000);
      try {
        await fetch(endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',headers,
          body:JSON.stringify({module,events}),signal:control.signal,keepalive:true});
      } finally {clearTimeout(limit);}
    } catch (_) { /* Bez opakovani: neuspesna diagnostika nesmi zahltit pomalou sit. */ }
    finally {flushing=false;if(queue.length)timer=setTimeout(flush,1000);}
  }

  // Omezenou frontu vyprazdnujeme mimo zpracovani odpovedi pracovniho pozadavku.
  function add(event,eventScope=scope(event.module)) {
    try {
      if(!active(event.module) || eventScope!==scope(event.module))return;
      queue.push({...event,_scope:eventScope,at:Date.now(),visible:!document.hidden});
      if(queue.length>100)queue.shift();
      if(!timer)timer=setTimeout(flush,2000);
    } catch (_) {}
  }

  // Vypinac se obnovuje z existujicich API odpovedi, bez trvaleho dotazovani navic.
  function adopt(response,module) {
    try {
      const value=response.headers.get('X-CB-Diag');
      if(value!==null) {until[module]=Number(value)*1000;observe();}
    } catch (_) {}
  }

  // Resource Timing poskytuje velikost po kompresi i po rozbaleni; nula pri neznamem prenosu neni dukazem nuloveho objemu.
  function resource(url,start,requestId) {
    try {
      if(observer)remember(observer.takeRecords());
      const entries=[...new Set([...resources.filter(e=>e.name===url),...performance.getEntriesByName(url,'resource')])];
      const matching=entries.filter(e=>e.startTime>=start-2 && e.responseEnd>=start);
      return matching.find(e=>e.serverTiming?.some(s=>s.name==='cb_req' && s.description===requestId))
        || (matching.length===1?matching[0]:null);
    } catch (_) {return null;}
  }

  // Telo odpovedi necteme ani neklonujeme: jeho zpracovani zustava plne v puvodnim modulu.
  function request(module,action,url,data={},group=null) {
    try {
      if(!active(module))return null;
      observe();
      const requestId=id(),start=performance.now(),startedAt=Date.now(),eventScope=scope(module);let response=null,ended=false;
      const headers={'X-CB-Diag-Id':requestId};
      if(group)headers['X-CB-Diag-Action']=group.id;
      return {headers,received(value){response=value;adopt(value,module);},end(status='ok',bodyBytes=null){
        if(ended)return;ended=true;
        try {
          const end=performance.now();
          // Nektere prohlizece zpristupni Resource Timing az v dalsi uloze po docteni tela.
          setTimeout(()=>{try {
          const entry=resource(url,start,response?.headers.get('X-CB-Diag-Id') || requestId);
          const serverBytes=response?.headers.get('X-CB-Diag-Body-Bytes');
          const length=response?.headers.get('Content-Length');
          const event={kind:'request',module,action,request_id:response?.headers.get('X-CB-Diag-Id') || requestId,
            action_id:group?.id || '',started_at:startedAt,meta:meta(data),status,http_status:response?.status || 0,total_ms:number(end-start),
            wait_ms:entry?.requestStart?number(entry.responseStart-entry.requestStart):null,
            download_ms:entry?.responseStart?number(entry.responseEnd-entry.responseStart):null,
            dns_ms:entry?.domainLookupStart?number(entry.domainLookupEnd-entry.domainLookupStart):null,
            connect_ms:entry?.connectStart?number(entry.connectEnd-entry.connectStart):null,
            transfer_bytes:entry?.transferSize>0?entry.transferSize:null,
            encoded_bytes:entry?.encodedBodySize>0?entry.encodedBodySize:(length!==null?Number(length):null),
            decoded_bytes:entry?.decodedBodySize>0?entry.decodedBodySize:(bodyBytes!==null?bodyBytes:(status==='ok' && serverBytes!==null?Number(serverBytes):null)),
            expected_decoded_bytes:serverBytes!==null?Number(serverBytes):null,body_complete:status==='ok',php_ms:number(entry?.serverTiming?.find(s=>s.name==='cb_php')?.duration),
            protocol:entry?.nextHopProtocol || '',service_worker:Boolean(entry?.workerStart)};
          if(group)group.record(event);
          add(event,eventScope);
          }catch(_){}},0);
        } catch (_) {}
      }};
    } catch (_) {return null;}
  }

  // Jedna uzivatelska operace muze obsahovat vice HTTP pozadavku; soucty maji i pocet chybejicich velikosti.
  function action(module,name,data={}) {
    try {
      if(!active(module))return null;
      const actionId=id(),start=performance.now(),startedAt=Date.now(),requests=[],eventScope=scope(module);let ended=false,outcome='ok';
      return {id:actionId,record(event){requests.push(event);},outcome(value){outcome=value;},end(status=outcome) {
        if(ended)return;ended=true;
        try {
        const workEnd=performance.now();
        const finish=()=>{
          try {
            const sum=key=>requests.some(e=>e[key]!==null)?requests.filter(e=>e[key]!==null).reduce((total,e)=>total+e[key],0):null;
            add({kind:'action',module,action:name,action_id:actionId,started_at:startedAt,status,meta:meta(data),
              total_ms:number(performance.now()-start),work_ms:number(workEnd-start),
              render_wait_ms:number(performance.now()-workEnd),requests:requests.length,
              transfer_bytes:requests.length?sum('transfer_bytes'):null,decoded_bytes:requests.length?sum('decoded_bytes'):null,
              unknown_sizes:requests.filter(e=>e.transfer_bytes===null).length,unknown_decoded_sizes:requests.filter(e=>e.decoded_bytes===null).length,
              failed_requests:requests.filter(e=>e.status!=='ok').length},eventScope);
          } catch (_) {}
        };
        // Dva snimky jsou orientacni dokonceni aktualizace DOM, nikoli dukaz nacteni externich obrazku.
        if(document.hidden)setTimeout(finish,0);else {
          let done=false;const once=()=>{if(!done){done=true;finish();}};
          setTimeout(once,250);requestAnimationFrame(()=>requestAnimationFrame(once));
        }
        }catch(_){}
      }};
    } catch (_) {return null;}
  }
  // Obal vraci stejny vysledek i vyjimku puvodni operace; diagnostika se na jeji dokonceni pouze pripoji.
  async function run(module,name,data,task,parent=null) {
    const group=parent || action(module,name,data);
    try {return await task(group);}
    catch(error) {group?.outcome(error?.name==='AbortError'?'abort':'error');throw error;}
    finally {try{if(!parent)group?.end();}catch(_){} }
  }
  // Zmena Co-Link identity zahodi neodeslane zaznamy predchoziho uzivatele.
  function identity(csrf) {
    const next=String(csrf || '');
    if(next!==coCsrf) {for(let i=queue.length-1;i>=0;i--)if(queue[i].module==='co_link')queue.splice(i,1);coCsrf=next;}
  }
  window.CB_DIAG={request,action,adopt,run,identity};
  window.addEventListener('pagehide',()=>{flush().catch(()=>{});});
})();
