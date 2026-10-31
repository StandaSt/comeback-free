/* Co-Link: lokalni vyber emoji s ceskym hledanim a kategoriemi, bez externich sluzeb a odesilani textu. */
(() => {
  'use strict';
  const groups=[
    ['Smajlíci','😀:úsměv 😃:radost 😄:smích 😁:zuby 😆:smích 😅:úleva 😂:smích_slzy 🤣:smích 🙂:úsměv 🙃:obrácený_úsměv 😉:mrknutí 😊:radost 😇:anděl 🥰:láska 😍:zamilovaný 🤩:nadšení 😘:polibek 😋:chuť 😛:jazyk 😜:legrace 🤪:blázen 🤗:objetí 🤭:překvapení 🤫:ticho 🤔:přemýšlení 🤐:mlčení 🤨:pochybnost 😐:neutrální 😑:bez_výrazu 🙄:oči 😏:úšklebek 😴:spánek 🥱:únava 😎:brýle 🤓:chytrý 🧐:zkoumání 😕:zmatený 😟:starost 🙁:smutek 😮:překvapení 😲:údiv 😳:rozpaky 🥺:prosba 😢:pláč 😭:pláč 😤:vztek 😠:hněv 😡:vztek 🤯:výbuch 😱:strach 😰:obavy 😓:pot 😥:smutek 🤒:nemoc 🤕:zranění 🤢:nevolnost 🤮:zvracení 🥳:oslava 🤠:kovboj 🤡:klaun 👻:duch 💩:hovínko 🤖:robot'],
    ['Gesta a lidé','👍:palec_souhlas 👎:palec_nesouhlas 👌:dobře ✌️:vítězství 🤞:štěstí 🤟:láska 🤘:rock 🤙:zavolej 👋:ahoj 🤚:stop ✋:ruka 🖐️:dlaň 🖖:pozdrav 👈:vlevo 👉:vpravo 👆:nahoru 👇:dolů ☝️:pozor 👏:potlesk 🙌:hurá 👐:otevřené_ruce 🤲:prosba 🤝:dohoda 🙏:děkuji 💪:síla 🫶:srdce_ruce 👀:oči 👂:ucho 🧠:mozek 👤:člověk 👥:lidé 🧑‍🍳:kuchař 👨‍💻:počítač 👷:pracovník 🙋:hlásím_se 🤷:nevím 🤦:ach_jo'],
    ['Srdce a symboly','❤️:srdce_láska 🧡:oranžové_srdce 💛:žluté_srdce 💚:zelené_srdce 💙:modré_srdce 💜:fialové_srdce 🖤:černé_srdce 🤍:bílé_srdce 💔:zlomené_srdce 💕:srdce 💯:sto_procent ✅:hotovo_schváleno ☑️:zaškrtnuto ✔️:potvrzeno ❌:chyba_ne ❎:zrušeno ⚠️:pozor_varování 🚫:zákaz ⛔:zákaz_vjezdu ❗:důležité ❓:otázka ⁉️:otázka_vykřičník 💬:zpráva 🔔:upozornění 🔕:ticho ⭐:hvězda 🌟:zářící_hvězda ✨:jiskry 🔥:oheň 💥:výbuch 🎉:oslava 🎊:konfety 🏆:pohár 🥇:první_místo 🏅:medaile 🟢:zelená 🔴:červená 🟡:žlutá 🔵:modrá 🟠:oranžová'],
    ['Jídlo a pití','🍕:pizza 🍔:burger 🍟:hranolky 🌭:hotdog 🥪:sendvič 🌮:taco 🌯:wrap 🥙:kebab 🥗:salát 🍝:těstoviny 🍜:nudle 🍲:polévka 🥘:jídlo 🍳:vejce 🧀:sýr 🥓:slanina 🍗:kuře 🥩:maso 🥦:brokolice 🥬:zelenina 🍅:rajče 🌶️:chilli 🫑:paprika 🧄:česnek 🧅:cibule 🍄:houby 🥒:okurka 🫒:olivy 🍍:ananas 🍎:jablko 🍌:banán 🍋:citron 🍓:jahoda 🍉:meloun 🍇:hrozny 🍒:třešně 🍑:broskev 🍊:pomeranč 🍰:dort 🎂:narozeniny 🍪:sušenka 🍩:kobliha 🍫:čokoláda 🍦:zmrzlina ☕:káva 🍵:čaj 🧃:džus 🥤:pití 🧋:nápoj 🍺:pivo 🍻:přípitek 🥂:oslava 🍷:víno 💧:voda 🍽️:talíř 🔪:nůž'],
    ['Zvířata a příroda','🐶:pes 🐱:kočka 🐭:myš 🐹:křeček 🐰:králík 🦊:liška 🐻:medvěd 🐼:panda 🐨:koala 🐯:tygr 🦁:lev 🐮:kráva 🐷:prase 🐸:žába 🐵:opice 🐔:slepice 🐧:tučňák 🐦:pták 🦆:kachna 🦉:sova 🐝:včela 🦋:motýl 🐞:beruška 🐢:želva 🐬:delfín 🐟:ryba 🌻:slunečnice 🌹:růže 🌷:tulipán 🌸:květina 🌳:strom 🌲:les 🌵:kaktus 🍀:štěstí 🌿:bylinky ☀️:slunce 🌤️:počasí ☁️:mrak 🌧️:déšť ⛈️:bouřka ❄️:sníh 🌈:duha 🌙:měsíc'],
    ['Doprava a věci','🚗:auto 🚕:taxi 🚙:auto 🚐:dodávka 🚚:rozvoz_kurýr 🚛:nákladní_auto 🛵:skútr 🚲:kolo 🏍️:motorka 🚦:semafor ⛽:benzín 🅿️:parkování 🚀:raketa ✈️:letadlo 🚆:vlak 🚑:sanitka 🚒:hasiči 🚓:policie 🏠:dům 🏢:firma 🏪:prodejna 🏭:výroba 🏖️:dovolená 🗺️:mapa 📍:místo 📦:balík 🛒:nákup 🎁:dárek 🔑:klíč 🔒:zámek 🔓:odemčeno 🧰:nářadí 🔧:oprava 🔨:kladivo 💡:nápad 🔌:elektřina 🔋:baterie 🧯:hasicí_přístroj 🧹:úklid 🧽:houba 🗑️:koš'],
    ['Práce a čas','📞:telefon 📱:mobil 💻:počítač 🖥️:monitor ⌨️:klávesnice 🖨️:tiskárna 📷:fotografie 🎥:kamera 📧:email 📝:poznámka 📄:dokument 📋:seznam 📁:složka 📊:statistika 📈:růst 📉:pokles 📅:kalendář 🗓️:rozpis ⏰:budík ⏱️:časovač ⌛:čekání 🕐:hodina 🕒:čas 🕕:šest 🕘:devět 💰:peníze 💵:hotovost 💳:karta 🧾:účtenka 🏷️:štítek 🖊️:pero ✏️:tužka 📌:připnout 📎:příloha 🔍:hledání 🚩:vlajka 🎯:cíl 💤:odpočinek 🆗:dobře 🆕:nové 🆘:pomoc']
  ];
  const items=groups.flatMap(([group,data])=>data.split(' ').map(pair=>{const [emoji,word]=pair.split(':');return {group,emoji,label:word.replaceAll('_',' ')};}));
  let popup=null,owner=null;
  // Diakritika ani velikost pismen nebrani hledani ceskeho popisu.
  function key(text){return text.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();}
  // Zavreni zahodi pouze docasny prehled, text ani vyber editoru nemeni.
  function close(){popup?.remove();popup=null;owner=null;}
  // Jeden prehled je sdileny mezi novou zpravou a dialogem upravy.
  function open(button,choose){
    if(owner===button){close();return;}close();owner=button;popup=document.createElement('div');popup.className='emoji-picker';popup.setAttribute('role','dialog');popup.setAttribute('aria-label','Výběr emoji');
    const tools=document.createElement('div');tools.className='emoji-search-row';const search=document.createElement('input');search.type='search';search.placeholder='Hledat emoji…';search.setAttribute('aria-label','Hledat emoji');
    const dismiss=document.createElement('button');dismiss.type='button';dismiss.textContent='×';dismiss.setAttribute('aria-label','Zavřít výběr emoji');dismiss.addEventListener('click',()=>{close();button.focus();});tools.append(search,dismiss);
    const categories=document.createElement('select');categories.setAttribute('aria-label','Kategorie emoji');for(const label of ['Všechny kategorie',...groups.map(g=>g[0])]){const option=document.createElement('option');option.textContent=label;option.value=label==='Všechny kategorie'?'':label;categories.append(option);}
    const grid=document.createElement('div');grid.className='emoji-grid';popup.append(tools,categories,grid);button.closest('.rich-editor').append(popup);
    // Vysledky jsou tlacitka s popisem pro ctecky a klavesnici, nikoli externi obrazky.
    function render(){grid.replaceChildren();const found=items.filter(i=>(!categories.value||i.group===categories.value)&&key(i.label+' '+i.group).includes(key(search.value)));for(const item of found){const b=document.createElement('button');b.type='button';b.textContent=item.emoji;b.title=item.label;b.setAttribute('aria-label',item.label+' '+item.emoji);b.addEventListener('click',()=>{close();choose(item.emoji);});grid.append(b);}if(!found.length)grid.textContent='Žádné emoji neodpovídá hledání.';}
    search.addEventListener('input',render);categories.addEventListener('change',render);render();search.focus();
  }
  document.addEventListener('pointerdown',e=>{if(popup&&!popup.contains(e.target)&&!owner.contains(e.target))close();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&popup){const button=owner;close();button.focus();e.preventDefault();}});
  window.CoEmoji={open,close};
})();
