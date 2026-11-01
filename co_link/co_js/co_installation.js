/* Co-Link: nabidka instalace PWA; systemovou vyzvu spousti vyhradne skutecne kliknuti na tlacitko. */
(() => {
  'use strict';
  let deferred=null,current=null;
  // Bezne otevreni aplikace pouze pripravi nabidku, nikdy samo nevola prompt().
  window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();deferred=event;render();});
  window.addEventListener('appinstalled',()=>{deferred=null;if(current?.isConnected)current.replaceChildren(paragraph('Co-Link byl přidán na plochu.'));});
  // Jednotny textovy uzel neprenasi technickou chybu prohlizece do prihlasovani aplikace.
  function paragraph(text){const p=document.createElement('p');p.className='hint';p.textContent=text;return p;}
  // Nabidka se aktualizuje take tehdy, kdyz prohlizec pripravi instalaci az po otevreni dialogu.
  function render(){
    if(!current?.isConnected)return;
    if(matchMedia('(display-mode: standalone)').matches||navigator.standalone){current.replaceChildren(paragraph('Co-Link už používáte jako samostatnou aplikaci.'));return;}
    const iphone=/iPad|iPhone|iPod/.test(navigator.userAgent);
    current.replaceChildren(paragraph(iphone?'V nabídce Sdílet vyberte Přidat na plochu. Potom Co-Link spouštějte jeho vlastní ikonou.':'Přidejte si Co-Link na plochu telefonu. Pokud zde není instalační tlačítko, v nabídce prohlížeče vyberte Nainstalovat aplikaci nebo Přidat na plochu.'));
    if(!deferred||iphone)return;
    const button=document.createElement('button');button.type='button';button.className='primary';button.textContent='Nainstalovat Co-Link';current.append(button);
    // prompt() musi probehnout primo v obsluze kliknuti, pred jakymkoli cekanim na sit nebo jinou operaci.
    button.addEventListener('click',async()=>{
      const event=deferred,host=current;if(!event)return;deferred=null;button.disabled=true;
      try{
        await event.prompt();const choice=await event.userChoice;
        if(host.isConnected)host.replaceChildren(paragraph(choice?.outcome==='accepted'?'Instalace byla potvrzena. Co-Link můžete dál používat.':'Co-Link můžete dál používat. Instalaci lze později spustit v jeho nastavení.'));
      }catch(_){
        if(host.isConnected)host.replaceChildren(paragraph('Instalační nabídku se nepodařilo otevřít. Co-Link můžete dál používat. Pro přidání na plochu použijte nabídku prohlížeče.'));
      }
    });
  }
  // Hostitelska aplikace spravuje svuj dialog; zde je jen jeho instalacni obsah.
  function show(container){current=document.createElement('div');container.append(current);render();}
  window.CoInstall={show};
})();
