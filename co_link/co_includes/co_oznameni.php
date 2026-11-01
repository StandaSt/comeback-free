<?php
/* Co-Link: prostor pro stavova a chybova oznameni komunikatoru. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<div id="notice" class="notice" role="status" aria-live="polite" hidden></div>
