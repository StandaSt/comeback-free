<?php
declare(strict_types=1);
/* Účel: Jediná datová definice menu Administrace, filtrovaná aktuálním přístupovým profilem. */

/** Položky jsou shodné pro serverové zobrazení a předem připravené menu aplikace. */
function cb_administrace_menu_data(): array
{
    $items=[
        ['page'=>'uzivatele','label'=>'Uživatelé','pravo'=>107],
        ['page'=>'prava_roli','label'=>'Globální práva'],
        ['page'=>'editace_prav','label'=>'Editovat práva'],
        ['page'=>'individualni_prava','label'=>'Individuální práva uživatele','pravo'=>103],
        ['page'=>'firma_pridat','label'=>'Přidat firmu','pravo'=>105],
        ['page'=>'spousteni_scriptu','label'=>'Spouštění scriptů'],
        ['page'=>'export_db','label'=>'Export DB','pravo'=>109],
        ['page'=>'log_chyby','label'=>'Přehled chyb','pravo'=>106],
    ];
    $items=array_values(array_filter($items,static fn(array $item):bool=>!isset($item['pravo']) || cb_pravo_ma((int)$item['pravo'])));
    return ['title'=>'Administrace','aria_label'=>'Menu administrace','default_page'=>'prava_roli','items'=>$items];
}
