<?php
/* Co-Link: oblast zprav vcetne oznaceni vlakna a cekajicich zprav. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
    <div id="messages" class="messages" role="log" aria-label="Zprávy"></div>
    <div id="thread-banner" class="thread-banner" hidden><span></span><button id="thread-close" type="button">Zavřít vlákno ×</button></div>
    <div id="pending" class="pending" aria-live="polite"></div>
