<?php
/* Co-Link: spolecny dialog pro obsah sestavovany JavaScriptem. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<dialog id="panel"><form method="dialog" class="dialog-head"><h2 id="panel-title"></h2><button class="icon-button" aria-label="Zavřít">×</button></form><div id="panel-content"></div></dialog>
