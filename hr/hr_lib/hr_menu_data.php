<?php
declare(strict_types=1);
/* Účel: Připraví menu z konfigurace HR a aktuálních práv, bez načítání pracovních stránek. */
require_once __DIR__.'/hr_pages.php';

/** Konfigurace HR zůstává jediným zdrojem názvů a pořadí. */
function cb_hr_menu_data(): array
{
    $config=cb_hr_module_config();
    $items=array_values(array_filter($config['menu'],static fn(array $item):bool=>match($item['page']) {
        'mzdovy_prehled'=>cb_hr_mzdovy_prehled_ma_pravo(),
        'nastaveni'=>cb_hr_nastaveni_ma_pravo(),
        default=>true,
    }));
    return ['title'=>$config['title'],'aria_label'=>'Menu personalistiky','items'=>$items];
}
