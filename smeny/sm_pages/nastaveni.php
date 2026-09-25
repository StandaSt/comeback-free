<?php
declare(strict_types=1);

/* Ucel souboru: Zobrazi aktualni pevna pravidla a budouci nastaveni modulu Smeny. */

if (!cb_smeny_nastaveni_ma_pravo()) {
    http_response_code(403);
    ?>
    <section class="pp smeny_content" data-module="smeny" data-page="nastaveni">
        <header class="pp_header"><h1>Nastavení</h1></header>
        <p class="smeny_notice smeny_notice--error">Nemáte právo měnit nastavení Směn.</p>
    </section>
    <?php
    return;
}

?>
<section class="pp smeny_content" data-module="smeny" data-page="nastaveni">
    <header class="pp_header">
        <h1>Nastavení směn</h1>
    </header>

    <div class="smeny_intro">
        <strong>Pevná pravidla</strong>
        <p>Požadavky se uzavírají vždy ve středu ve 20:00.</p>
        <p>Pobočka začne používat interní směny automaticky ve chvíli, kdy je v IS naplánuje. Není nutné nastavovat testovací pobočky.</p>
    </div>
</section>
