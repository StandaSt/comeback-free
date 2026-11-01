<?php
/* Co-Link: nazev otevrene konverzace a jeji ovladaci tlacitka. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
    <header class="chat-head"><button id="back" class="icon-button mobile-only" aria-label="Zpět na konverzace">‹</button><div class="chat-heading"><h1 id="chat-name">Vítejte v Co-Linku</h1><p id="chat-subtitle">Prostor pro váš tým.</p></div>
      <button id="search-toggle" class="icon-button" type="button" aria-label="Hledat ve zprávách" title="Hledat ve zprávách" aria-controls="search-form" aria-expanded="false" hidden><svg class="chat-action-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="6"></circle><path d="m15 15 5 5"></path></svg></button>
      <button id="chat-info" class="icon-button" aria-label="Členové a informace" hidden>ⓘ</button>
      <button id="mute" class="icon-button" type="button" aria-label="Ztišit oznámení z této konverzace na všech vašich zařízeních" aria-pressed="false" hidden><svg class="chat-action-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path><path id="mute-slash" d="m3 3 18 18" visibility="hidden"></path></svg></button>
    </header>
