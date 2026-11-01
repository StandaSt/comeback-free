<?php
/* Co-Link: formular psani zprav s editorem, prilohami a naseptavanim osob. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
    <form id="composer" class="composer" hidden>
      <div id="staged-files" class="staged-files"></div>
      <div id="message-editor"></div>
      <div id="compose-actions" hidden><button id="attach" type="button" class="icon-button" aria-label="Přiložit soubor nebo fotografii">＋</button><button id="send-message" type="submit" class="send" aria-label="Odeslat zprávu">Odeslat</button></div>
      <input type="file" id="file" multiple hidden><input type="file" id="camera" accept="image/*" capture="environment" hidden>
      <div id="mention-list" class="mention-list" hidden></div><p class="compose-hint">Enter odešle · Shift + Enter nový řádek</p>
    </form>
