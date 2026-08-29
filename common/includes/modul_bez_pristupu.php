<?php
/*
 * Ucel souboru: Zobrazi jednotnou hlasku pri nepovolenem vstupu do modulu.
 */
declare(strict_types=1);
?>
<section class="pp cb-module-denied" data-module="<?= h((string)$cbNepovolenyModul) ?>" data-page="zakazano">
    <header class="pp_header">
        <h1>Přístup k modulu</h1>
    </header>
    <div class="blok">
        <p>Tento modul nyní nemáte povolen.</p>
    </div>
</section>
