<?php
// Rozcestnik nastaveni Provozu; jednotlive volby lze postupne rozsirovat.
declare(strict_types=1);

require_once __DIR__ . '/../lib/nastaveni_prava.php';
if (!cb_provoz_nastaveni_ma_pravo()) {
    return;
}
?>
<div class="provoz_settings_hub">
    <a class="provoz_settings_tile" href="<?= h(cb_root_url('index.php?m=provoz&page=nastaveni_pobocky')) ?>">
        <strong>Pobočky</strong>
        <span>Názvy, adresy, kontakty a zavírací doba</span>
    </a>
</div>
