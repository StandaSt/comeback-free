<?php
/* Co-Link: domovska sablona s osobnim QR kodem pro pripojeni telefonu. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<template id="home-template">
  <section class="home" aria-label="Vítejte v Co-Linku">
    <div class="welcome-card">
      <div class="brandmark" aria-hidden="true">Co</div><p class="eyebrow">COMEBACK · FIREMNÍ KOMUNIKACE</p>
      <h1>Co-Link</h1><p>Vaši lidé. Vaše konverzace. Na jednom místě.</p>
      <p class="hint">Na telefonu načtěte tento osobní QR kód a připojte si Co-Link.</p>
      <div id="home-qr" class="qr" aria-label="Osobní QR kód pro připojení telefonu"></div>
      <p id="home-qr-status" class="qr-countdown" role="status">Připravuji osobní QR kód…</p>
    </div>
  </section>
</template>
