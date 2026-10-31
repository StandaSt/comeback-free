/* Co-Link: vyhradni registrace jeho workeru a odberu oznameni; odber ani worker 2FA nemeni. */
(() => {
  'use strict';
  const root=new URL('./',document.currentScript.src);
  let registration=null;
  // Uchovame primo registraci Co-Linku, nikoli obecne ready, ktere muze patrit IS.
  function prepare(){
    if(!('serviceWorker'in navigator))return Promise.reject(new Error('Toto zařízení nepodporuje upozornění.'));
    if(!registration)registration=navigator.serviceWorker.register(new URL('sw.js',root).href,{scope:root.href})
      .then(r=>{if(r.scope!==root.href)throw new Error('Registrace upozornění nepatří Co-Linku.');return r;})
      .catch(e=>{registration=null;throw e;});
    return registration;
  }
  // Pri prvnim pripojeni pockame na aktivaci vlastniho workeru s omezenym cekanim.
  function active(r){
    if(r.active?.state==='activated')return Promise.resolve(r);
    return new Promise((resolve,reject)=>{
      const candidate=r.installing||r.waiting||r.active;
      const done=error=>{clearTimeout(timer);candidate?.removeEventListener('statechange',check);error?reject(error):resolve(r);};
      const check=()=>{if(candidate?.state==='activated')done();else if(candidate?.state==='redundant')done(new Error('Upozornění se nepodařilo připravit. Zkuste to znovu.'));};
      const timer=setTimeout(()=>done(new Error('Příprava upozornění trvá příliš dlouho. Zkuste to znovu.')),15000);
      candidate?.addEventListener('statechange',check);check();
    });
  }
  // Souhlas vyzadujeme primo pri kliknuti, pred cekanim na worker nebo sit.
  async function enable(vapid,save){
    if(!('Notification'in window)||!('PushManager'in window)||!vapid)throw new Error('Toto zařízení nyní nepodporuje upozornění.');
    if(/iPad|iPhone|iPod/.test(navigator.userAgent)&&!matchMedia('(display-mode: standalone)').matches&&!navigator.standalone)
      throw new Error('Na iPhonu nejprve přidejte Co-Link na plochu. Potom ho otevřete jeho ikonou a povolte oznámení v nastavení Co-Linku.');
    const permission=await Notification.requestPermission();
    if(permission!=='granted')throw new Error('Oznámení nejsou povolena. Povolte je v nastavení telefonu a tohoto webu, potom to zkuste znovu.');
    const r=await active(await prepare());
    const key=Uint8Array.from(atob(vapid.replace(/-/g,'+').replace(/_/g,'/')),c=>c.charCodeAt(0));
    const subscription=await r.pushManager.getSubscription()||await r.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:key});
    await save(subscription.toJSON());
  }
  // Stav se zobrazuje u tlacitka; uspech potvrzujeme az po ulozeni odberu serverem.
  function show(container,vapid,save){
    const box=document.createElement('div'),status=document.createElement('p'),button=document.createElement('button');
    status.className='hint';status.setAttribute('role','status');status.textContent='Pro upozornění na nové zprávy povolte oznámení na tomto zařízení. Přidání ikony na plochu je samostatný krok.';
    button.type='button';button.className='primary';button.textContent='Povolit oznámení';box.append(status,button);container.append(box);
    button.addEventListener('click',async()=>{
      button.disabled=true;
      try{await enable(vapid,save);status.textContent='Oznámení Co-Linku jsou na tomto zařízení zaregistrována.';button.hidden=true;}
      catch(e){status.textContent=e.message||'Oznámení se nepodařilo zaregistrovat. Zkuste to znovu.';}
      finally{button.disabled=false;}
    });
  }
  window.CoPush={prepare,show};
})();
