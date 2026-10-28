<?php
declare(strict_types=1);
/* Účel: Vykreslí hlavní telefon karty jako předvolbu a číslo; hodnoty dodává příprava karty. */
?>
<div class="hr_form_label"><span class="hr_form_label_text">Telefon</span>
    <span class="hr_phone_field hr_phone_field--international" data-phone-international data-phone-short-prefixes="<?= h(json_encode(hr_telefon_kratke_predvolby(), JSON_THROW_ON_ERROR)) ?>">
        <input class="hr_phone_prefix_input" name="telefon_predvolba" aria-label="Telefonní předvolba" maxlength="6" inputmode="tel" autocomplete="tel-country-code" value="<?= h($phoneParts['predvolba']) ?>">
        <input class="hr_phone_input" name="telefon" aria-label="Telefonní číslo" maxlength="30" value="<?= h($phoneParts['cislo']) ?>" autocomplete="tel-national" inputmode="tel">
    </span>
</div>
