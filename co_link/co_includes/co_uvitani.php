<?php
/* Co-Link: uvodni obrazovka pri priprave prihlaseni do komunikatoru. */
declare(strict_types=1);
// Cast sablony se vykresluje jen pres povoleny vstup komunikatoru.
if (!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<section id="welcome" class="welcome" hidden>
  <div class="welcome-card"><div class="brandmark" aria-hidden="true">Co</div><p class="eyebrow">COMEBACK · FIREMNÍ KOMUNIKACE</p>
  <h1>Co-Link</h1><p id="welcome-text">Vaši lidé. Vaše konverzace. Na jednom místě.</p>
  <p id="welcome-hint" class="hint">Připravuji vaše firemní přihlášení…</p></div>
</section>
