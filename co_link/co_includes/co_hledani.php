<?php
/* Co-Link: formular vyhledavani ve zpravach otevrene konverzace. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
    <form id="search-form" class="search-row" hidden><input id="search" type="search" placeholder="Hledat ve zprávách…" aria-label="Hledat ve zprávách"><button type="submit">Hledat</button><button type="button" id="clear-search">Zrušit</button></form>
