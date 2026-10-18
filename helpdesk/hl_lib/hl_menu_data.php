<?php
declare(strict_types=1);
/* Účel: Připraví menu povolených pohledů HelpDesku; zdrojový modul doplní společná obsluha odkazů. */
require_once __DIR__.'/hl_pages.php';

/** Profil má vlastní odkaz v zápatí, nikoli duplicitní položku v hlavním seznamu. */
function cb_helpdesk_menu_data(): array
{
    $items=[];
    foreach(cb_helpdesk_views() as $view=>$label) {
        if($view!=='uprava_profilu' && cb_helpdesk_view_allowed($view)) $items[]=['page'=>$view,'label'=>$label];
    }
    return ['title'=>'HelpDesk','aria_label'=>'HelpDesk menu','key'=>'hd','default_page'=>'all','items'=>$items];
}
