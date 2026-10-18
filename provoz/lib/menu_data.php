<?php
declare(strict_types=1);
/* Účel: Jediná datová definice menu Provozu, filtrovaná stejnými právy při prvním zobrazení i přepínání modulů. */
require_once __DIR__.'/ai_analytik_pravidla.php';
require_once __DIR__.'/denni_report_prava.php';
require_once __DIR__.'/nastaveni_prava.php';
require_once __DIR__.'/kontrola_trzeb_pristup.php';
require_once __DIR__.'/objednavky_prava.php';

/** Vrátí menu bez načítání reportů, objednávek nebo Restie. */
function cb_provoz_menu_data(): array
{
    $items=[
        ['page'=>'prehled','label'=>'Přehled'],
        ['page'=>'denni_report','label'=>'Denní report','pravo'=>CB_DENNI_REPORT_ZOBRAZIT_PRAVO],
        ['page'=>'archiv_reportu','label'=>'Archiv reportů','pravo'=>202,'active_pages'=>['archiv_reportu','porovnani_reportu']],
        ['page'=>'kontrola_trzeb','label'=>'Kontrola tržeb','pravo'=>CB_KT_ZOBRAZIT,'active_pages'=>['kontrola_trzeb','archiv_kontrol_trzeb']],
        ['page'=>'objednavky','label'=>'Objednávky','pravo'=>CB_OBJEDNAVKY_ZOBRAZIT_PRAVO],
        ['page'=>'prehled_hodin','label'=>'Přehled hodin','pravo'=>209],
        ['page'=>'ai_analytik','label'=>'Chytrý Franta','pravo'=>CB_AI_ANALYTIK_PRAVO],
        ['page'=>'nastaveni','label'=>'Nastavení','pravo'=>CB_PROVOZ_NASTAVENI_PRAVO,'active_pages'=>['nastaveni','nastaveni_pobocky']],
    ];
    $items=array_values(array_filter($items,static function(array $item):bool {
        $right=(int)($item['pravo'] ?? 0);
        return match($right) {
            0=>true,
            CB_OBJEDNAVKY_ZOBRAZIT_PRAVO=>cb_objednavky_ma_pravo(),
            CB_KT_ZOBRAZIT=>cb_kt_ma_pravo(),
            CB_AI_ANALYTIK_PRAVO=>cb_ai_analytik_ma_pravo(),
            default=>cb_pravo_ma($right),
        };
    }));
    return ['title'=>'Provoz','aria_label'=>'Menu Provozu','items'=>$items];
}
