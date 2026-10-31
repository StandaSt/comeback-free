/* Co-Link: obnovi existujici prihlaseni pres same-origin pozadavek, pokud vstupni navigace neposlala Strict cookie. */
(() => {
  'use strict';
  // Cte pouze overenou identitu; nevytvari ucet, zarizeni ani novy prihlasovaci token.
  async function restore(){
    const url=new URL('api.php',location.href);url.searchParams.set('akce','identita');
    const control=new AbortController(),timer=setTimeout(()=>control.abort(),15000);let response;
    try{response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:control.signal});}
    catch(_){throw new Error('Připojení se nepodařilo ověřit. Zkontrolujte internet a otevřete Co-Link znovu.');}
    finally{clearTimeout(timer);}
    if(response.status===401)return null;
    let data;try{data=await response.json();}catch(_){throw new Error('Připojení se nepodařilo ověřit. Zkuste Co-Link znovu otevřít.');}
    if(!response.ok||!data.ok)throw new Error(data.err||'Připojení se nepodařilo ověřit. Zkuste Co-Link znovu otevřít.');
    if(!data.identita?.id||!data.identita.csrf)throw new Error('Server nevrátil platné přihlášení.');
    return data.identita;
  }
  window.CoAuth={restore};
})();
