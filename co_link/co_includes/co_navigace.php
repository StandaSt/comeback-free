<?php
/* Co-Link: postranni navigace se seznamem a filtrem konverzaci. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
  <aside class="sidebar" aria-label="Konverzace">
    <header class="sidebar-head"><a class="brand" href="./">Co-Link<span>COMEBACK</span></a><button id="settings-open" class="icon-button" aria-label="Nastavení Co-Linku">⚙</button></header>
    <button id="directory-open" class="sidebar-action" aria-controls="messages"><span aria-hidden="true">▦</span> Adresář</button>
    <label class="sr-only" for="filter">Filtrovat konverzace</label><input id="filter" class="filter" type="search" placeholder="Najít konverzaci…" autocomplete="off">
    <div id="conversation-list" class="conversation-list"></div>
    <footer class="sidebar-foot"><span id="my-name"></span><span id="connection" class="connection">Připojuji…</span></footer>
  </aside>
