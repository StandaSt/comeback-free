/* Co-Link: nastaveni Co-Linku, QR pripojeni, zarizeni a nabidky instalace a oznameni. */
(window.CoLinkParts ||= Object.create(null)).nastaveni=function(ctx){
  'use strict';
  const {boot,$,state}=ctx;
  // Vazby mezi oblastmi se vyhodnoti az pri volani; vsechny pouzivaji jednu instanci stavu.
  const node=(...args)=>ctx.node(...args);
  const button=(...args)=>ctx.button(...args);
  const notice=(...args)=>ctx.notice(...args);
  const date=(...args)=>ctx.date(...args);
  const api=(...args)=>ctx.api(...args);
  const storageKey=(...args)=>ctx.storageKey(...args);
  const panel=(...args)=>ctx.panel(...args);
  const field=(...args)=>ctx.field(...args);
  const manageChannels=(...args)=>ctx.manageChannels(...args);

  // Vlastni nastaveni linku neni administraci celeho IS.
  async function settings(){
    const p=panel('Nastavení Co-Linku');p.append(node('p',state.me.jmeno,'hint'));
    const actions=node('div',undefined,'panel-actions');actions.append(button('Přidat do mobilu',pair),button('Moje připojená zařízení',devices),button('Povolit upozornění na tomto zařízení',enablePush),button('Přidat Co-Link na plochu',install));
    if(state.rights[701]||state.rights[702])actions.append(button('Správa kanálů',manageChannels));
    if(state.rights[703])actions.append(button('Administrace Co-Linku',admin));
    actions.append(button('Odhlásit toto zařízení',logout));p.append(actions);
  }
  // Vykresli QR lokalne a odstrani jej po vyprseni platnosti.
  async function pair(){
    const p=panel('Přidat do mobilu');const d=await api('qr',{}),url=new URL(d.odkaz,location.origin).href;
    p.append(node('p','Na telefonu načtěte tento osobní QR kód. Připojí vás pod vaším firemním účtem. Nikomu ho nepředávejte.','hint'));
    const box=node('div',undefined,'qr');p.append(box);new QRCode(box,{text:url,width:240,height:240,correctLevel:QRCode.CorrectLevel.M});
    const count=node('p',undefined,'qr-countdown');p.append(count);let remaining=d.sekundy;
    const tick=()=>{count.textContent=remaining>0?'Platí ještě '+Math.floor(remaining/60)+':'+String(remaining%60).padStart(2,'0'):'Platnost skončila. Vytvořte nový QR kód.';if(remaining<=0)box.replaceChildren();remaining--;};tick();const timer=setInterval(tick,1000);ctx.panelCleanup=()=>clearInterval(timer);
    p.append(button('Vytvořit nový QR kód',pair));
  }
  // Uvitaci QR se po vyprseni sam obnovi, dokud uzivatel zustava na uvitaci plose.
  async function homeQr(){
    clearInterval(ctx.homeQrTimer);ctx.homeQrTimer=null;
    const box=$('home-qr'),status=$('home-qr-status');if(!box||!status||state.view!=='home')return;
    box.replaceChildren();status.textContent='Připravuji osobní QR kód…';
    try{
      const d=await api('qr',{});if(state.view!=='home'||!box.isConnected)return;
      const url=new URL(d.odkaz,location.origin).href;
      new QRCode(box,{text:url,width:168,height:168,correctLevel:QRCode.CorrectLevel.M});
      const expiry=Date.now()+Number(d.sekundy)*1000;
      const tick=()=>{const remaining=Math.max(0,Math.ceil((expiry-Date.now())/1000));status.textContent=remaining?'Platí ještě '+Math.floor(remaining/60)+':'+String(remaining%60).padStart(2,'0'):'Obnovuji osobní QR kód…';if(!remaining){clearInterval(ctx.homeQrTimer);ctx.homeQrTimer=null;if(state.view==='home'&&box.isConnected)homeQr();}};
      ctx.homeQrTimer=setInterval(tick,1000);tick();
    }catch(e){status.textContent=e.message;if(box.isConnected)box.replaceChildren(button('Zkusit znovu',homeQr));}
  }
  // Umoznuje odvolat vlastni pripojena zarizeni.
  async function devices(){const p=panel('Moje zařízení'),d=await api('zarizeni');for(const device of d.zarizeni){const row=node('div',undefined,'list-row'),details=node('div');details.append(node('strong',device.nazev+(Number(device.id)===Number(d.aktualni)?' · toto zařízení':'')),node('p',device.platnost_do?'Platí do '+date(device.platnost_do):'Připojeno do odhlášení zařízení'));row.append(details,button('Odhlásit',async()=>{await api('odvolat',{id:Number(device.id)});if(Number(device.id)===Number(d.aktualni))location.reload();else await devices();}));p.append(row);}}
  // Spravce linku meni pouze limit priloh; telefony nemaji pravidelne vyprseni prihlaseni.
  async function admin(){const p=panel('Administrace Co-Linku'),d=await api('nastaveni');const limit=field(p,'Maximální velikost jedné přílohy (MB)',d.nastaveni.priloha_mb,'number');limit.min=1;limit.max=2048;p.append(button('Uložit nastavení',async()=>{await api('nastaveni_ulozit',{priloha_mb:Number(limit.value)});notice('Nastavení bylo uloženo.');$('panel').close();},'primary'));}
  // Odvola toto zarizeni a odstrani jeho lokalni koncepty a frontu.
  async function logout(){await api('odhlasit',{});try{localStorage.removeItem(storageKey());for(const key of Object.keys(localStorage))if(key.startsWith(storageKey()+':'))localStorage.removeItem(key);}catch(_){}location.reload();}
  // Po QR i z nastaveni otevirame jen nabidku; systemovou instalaci potvrzuje uzivatel tlacitkem.
  function install(){CoInstall.show(panel('Přidat na plochu'));}
  // Nabidne vlastni odber Co-Linku; obsluha tlacitka vyzada souhlas primo pri kliknuti.
  function pushSetup(host=panel('Oznámení Co-Linku')){CoPush.show(host,boot.vapid,data=>api('push',data));}
  // Otevre nabidku; o systemovy souhlas zada az jeji skutecne tlacitko.
  function enablePush(){pushSetup();}
  // Po QR jsou oznameni viditelna vedle samostatne nabidky instalace, zadna vyzva se nespousti sama.
  function phoneSetup(){const host=panel('Připojení telefonu');pushSetup(host);CoInstall.show(host);}

  return {settings,pair,homeQr,devices,admin,logout,install,pushSetup,enablePush,phoneSetup};
};
