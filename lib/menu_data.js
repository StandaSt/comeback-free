/* lib/menu_data.js
 * Data menu (Dropdown + Sidebar) pro IS Comeback
 * Verze: V1
 * Aktualizace: 21.1.2026
 * Počet řádků: 49
 */

(function(){
  const MENU = [
    {
      key:'reporty', label:'Reporty',
      level2: [
        { label:'Denní',      level3:['Zadat dnešní','Zobrazit dnešní','Editovat dnešní'] },
        { label:'Týdenní',    level3:['Týdenní A','Týdenní B','Týdenní C'] },
        { label:'Měsíční',    level3:['Měsíční A','Měsíční B','Měsíční C'] },
        { label:'Kvartální',  level3:['Kvartální A','Kvartální B','Kvartální C'] },
        { label:'Roční',      level3:['Roční A','Roční B','Roční C'] }
      ]
    },
    {
      key:'prehledy', label:'Přehledy',
      level2: [
        { label:'Objednávky',     level3:['Aktuální','Dnes','Období','Vše'] },
        { label:'Položky',       level3:['Pizza','Nápoje','Ostatní'] },
        { label:'Zákazníci',  level3:['Zákazníci A','Zákazníci B','Zákazníci C'] }
      ]
    },
    {
      key:'porovnani', label:'Porovnání',
      level2: [
        { label:'Časy',        level3:['Čas A','Čas B','Čas C'] },
        { label:'Pobočky',    level3:['Pobočky A','Pobočky B','Pobočky C'] },
        { label:'Produkty',   level3:['Produkty A','Produkty B','Produkty C'] }
      ]
    },
    {
      key:'top report', label:'Top-report',
      level2: [
        { label:'Dashboard',     level3:['Systém A','Systém B','Systém C'] },
        { label:'Ziskovost',       level3:['Data A','Data B','Data C'] },
        { label:'Porovnání',     level3:['Servis A','Servis B','Servis C'] }
      ]
    },  
    {
      key:'hr', label:'HR',
      level2: [
        { label:'Zaměstnanci',     level3:['Seznam','','zatím nevím'] },
        { label:'Mzdy',       level3:['Měsíc','Rok','Celkem'] },
        { label:'Hodiny',     level3:['Odpracované','Plán vs realita',''] },
        { label:'Smlouvy',     level3:['HPP','DPP','DPČ'] }
      ]
    },
    {
      key:'admin', label:'Admin',
      level2: [
        { label:'Uživatelé',     level3:['Systém A','Systém B','Systém C'] },
        { label:'',       level3:['Data A','Data B','Data C'] },
        { label:'Servis',     level3:['Servis A','Servis B','Servis C'] },
         { label:'Servis2',     level3:['Router A','Router B','Router C'] },
          { label:'Servis3',     level3:['Tisk A','Tisk B','Tisk C'] },
           { label:'Servis4',     level3:['Servis A','Servis B','Servis C','Servis D','Servis E'] }
      ]
    }
    
  ];

  window.MENU = MENU;
})();

/* lib/menu_data.js – konec souboru */
