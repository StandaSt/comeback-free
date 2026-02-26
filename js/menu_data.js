// js/menu_data.js * Verze: V5 * Aktualizace: 17.2.2026
/*
 * POZN.:
 * - window.MENU obsahuje jen textové položky (L1/L2)
 * - HOME + přepínače režimu jsou mimo (includes/tlacitka_svg.php)
 */

(function () {
  const MENU = [
    // 1 Denní report (L1 klikací)
    { key: 'report', label: 'Denní report', page: 'denni_report' },

    // 2 Reporty
    {
      key: 'reporty',
      label: 'Reporty',
      level2: [
        { label: 'Oprava',     page: 'reporty_edit' },
        { label: 'Porovnání',  page: 'reporty_porovnani' }
      ]
    },

    // 3 Objednávky
    {
      key: 'objednavky',
      label: 'Objednávky',
      level2: [
        { label: 'Přehled obj.',   page: 'obj_prehled' },
        { label: 'Časové údaje',   page: 'obj_casy' },
        { label: 'Platformy',     page: 'obj_platformy' },
        { label: 'Platby',        page: 'obj_platby' },
        { label: 'Položky',       page: 'obj_polozky' },
        { label: 'Zákazníci',     page: 'obj_zakaznici' }
      ]
    },

    // 4 Top report
    {
      key: 'top',
      label: 'Top report',
      level2: [
        { label: 'Dashboard',  page: 'top_dashboard' },
        { label: 'Ziskovost',  page: 'top_ziskovost' },
        { label: 'Porovnání',  page: 'top_porovnani' },
        { label: 'Kontrola',   page: 'top_kontrola' }
      ]
    },

    // 5 HR
    {
      key: 'hr',
      label: 'HR',
      level2: [
        { label: 'Zaměstnanci', page: 'hr_uzivatele' },
        { label: 'Docházka',    page: 'hr_dochazka' },
        { label: 'Mzdy',        page: 'hr_mzdy' },
        { label: 'Smlouvy',     page: 'hr_smlouvy' }
      ]
    },

    // 6 Porovnání
    {
      key: 'porovnani',
      label: 'Porovnání',
      level2: [
        { label: 'Prodeje',  page: 'porov_prodeje' },
        { label: 'Položky',  page: 'porov_polozky' },
        { label: 'Náklady',  page: 'porov_naklady' }
      ]
    },

    // 7 Admin
    {
      key: 'admin',
      label: 'Admin',
      level2: [
        { label: 'Uživatelé nastavení',    page: 'admin_uzivatele' },
        { label: 'Přehled logování',       page: 'admin_logs' },
        { label: 'Neoprávněné přístupy',   page: 'admin_hack' },
        { label: 'Informace o systému',    page: 'admin_infoblok' },
        { label: 'Náhledy zobrazení',      page: 'admin_ukazky' },
        { label: 'Chyby',                  page: 'admin_err' }
      ]
    }
  ];

  window.MENU = MENU;
})();

// js/menu_data.js * Verze: V5 * počet řádků 90 * Aktualizace: 17.2.2026
// konec souboru