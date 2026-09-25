<?php
declare(strict_types=1);

/* Ucel souboru: Overuje pravo k zobrazeni nastaveni modulu Smeny. */

function cb_smeny_nastaveni_ma_pravo(): bool
{
    return function_exists('cb_pravo_ma') && cb_pravo_ma(407);
}

