<?php
declare(strict_types=1);
/* Účel: Předá jednotnému rendereru ploché menu Směn podle práv, bez načítání rozpisů a zaměstnanců. */
require_once __DIR__.'/sm_pages.php';
require_once __DIR__.'/sm_pozadavky_pravo.php';
require_once __DIR__.'/sm_planovani_pravo.php';
require_once __DIR__.'/sm_sablony_pravo.php';
require_once __DIR__.'/sm_nastaveni_pravo.php';

/** Definice stránek je jediným zdrojem položek a jejich pořadí. */
function cb_smeny_menu_data(): array
{
    return ['title'=>'Směny','aria_label'=>'Menu směn','items'=>cb_smeny_pages()];
}
