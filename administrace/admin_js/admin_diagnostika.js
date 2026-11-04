/* Administrace diagnostiky: detail pod radkem a hodiny podle serveroveho casu, bez sitoveho pollingu a zmen nastaveni. */
(() => {
  'use strict';
  if(window.CB_ADMIN_DIAG_UI)return;
  window.CB_ADMIN_DIAG_UI=true;
  let timer=null,root=null;
  const clocks=new WeakMap();
  // Delky behu formatujeme stejne jako server, nezavisle na velikosti okna.
  function duration(seconds){seconds=Math.max(0,Math.floor(seconds));const hours=Math.floor(seconds/3600);return (hours?hours+' h ':'')+Math.floor(seconds%3600/60)+' min '+seconds%60+' s';}
  // Casovac uvolnime pri opusteni diagnostiky nebo po konci mereni.
  function stop(){if(timer!==null)clearInterval(timer);timer=null;}
  // Cteme pouze ulozeny stav, nikoli rozepsane hodnoty formulare.
  function tick(){
    if(!root?.isConnected){stop();root=null;return;}
    const clock=clocks.get(root),now=clock.server+(performance.now()-clock.monotonic)/1000;
    const until=Number(root.dataset.until),started=Number(root.dataset.started),running=root.dataset.enabled==='1' && now<until;
    root.querySelector('[data-diag-state]').textContent=running?'Zapnuto':'Vypnuto';
    root.querySelector('[data-diag-remaining]').textContent=running?duration(until-now):'—';
    root.querySelector('[data-diag-elapsed-label]').textContent=running?'Běží':'Poslední běh';
    if(started>0)root.querySelector('[data-diag-elapsed]').textContent=duration(Math.min(now,until)-started);
    if(!running)stop();return running;
  }
  // Jediny casovac vychazi ze serveroveho casu a monotonniho prirustku; spatne hodiny PC jej neposunou.
  function initialize(){
    stop();root=document.querySelector('[data-admin-diag]');
    if(!root || root.dataset.enabled!=='1')return;
    if(!clocks.has(root))clocks.set(root,{server:Number(root.dataset.serverNow),monotonic:performance.now()});
    if(tick())timer=setInterval(tick,1000);
  }
  // Rozbaleni jednoho detailu nesmi zvysit vysku puvodniho radku ani presunout nastaveni.
  function closeDetails(container){
    container.querySelectorAll('.admin_diag_detail_row').forEach(row=>{row.hidden=true;});
    container.querySelectorAll('[data-diag-toggle]').forEach(button=>{button.setAttribute('aria-expanded','false');button.textContent='Detail';});
  }
  document.addEventListener('click',event=>{
    const button=event.target.closest?.('[data-diag-toggle],[data-diag-close]');
    const container=button?.closest('[data-admin-diag]');if(!container)return;
    const id=button.dataset.diagToggle || button.dataset.diagClose;
    const row=document.getElementById(id);if(!row || !container.contains(row))return;
    const opening=Boolean(button.dataset.diagToggle) && row.hidden;
    closeDetails(container);
    if(opening){row.hidden=false;button.setAttribute('aria-expanded','true');button.textContent='Zavřít';}
    else if(button.dataset.diagClose)container.querySelector('[aria-controls="'+id+'"]')?.focus();
  });
  document.addEventListener('cb:main-swapped',initialize);
  window.addEventListener('pageshow',initialize);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialize);else initialize();
})();
