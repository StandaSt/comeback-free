<?php
/* E-mail: standardní menu a uživatelské zápatí IS; schránky a složky doplňuje pouze klient tohoto modulu. */
declare(strict_types=1);

/** Stejná definice slouží plnému načtení i připravené šabloně při přepnutí modulu. */
function em_menu_data(): array
{
    return ['title'=>'E-mail','aria_label'=>'Menu e-mailu','default_page'=>'prehled','items'=>[]];
}
