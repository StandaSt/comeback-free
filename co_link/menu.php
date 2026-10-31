<?php
declare(strict_types=1);
/* Co-Link: standardni menu a zapati IS bez uvodniho tlacitka; klient doplni adresar, nastaveni a zive konverzace. */

/** Stejna definice slouzi prvnimu zobrazeni i predem pripravene sablone pri prepinani modulu. */
function cb_co_link_menu_data(): array
{
    return ['title'=>'Co-Link','aria_label'=>'Menu Co-Linku','default_page'=>'prehled',
        'items'=>[]];
}
