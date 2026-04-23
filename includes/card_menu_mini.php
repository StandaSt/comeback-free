<?php
declare(strict_types=1);
?>
<div class="card_pref_menu_variant card_pref_menu_mini">
  <div class="card_pref_list" data-card-pref-list="1">
    <button type="button" class="card_pref_item cursor_ruka" data-card-pref-open="color" data-card-pref-url="<?= h($cardColorUrl) ?>">Barva karty</button>
    <button type="button" class="card_pref_item cursor_ruka" data-card-pref-open="ikon" data-card-pref-url="<?= h($cardIconUrl) ?>">Ikona karty</button>
    <button type="button" class="card_pref_item cursor_ruka" data-card-pref-move="1">Přesunout na pozici</button>
    <button type="button" class="card_pref_item cursor_ruka" data-card-pref-unlock-all="1">Odemkni vše</button>
  </div>
  <iframe class="card_pref_frame is-hidden" data-card-pref-frame="1" title="Nastavení karty"></iframe>
</div>
