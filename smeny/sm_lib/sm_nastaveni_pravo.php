<?php
declare(strict_types=1);

/* Ucel souboru: Overuje existujici pravo 410 k zobrazeni a ulozeni nastaveni Smen. */

function cb_smeny_nastaveni_ma_pravo(): bool
{
    return function_exists('cb_pravo_ma') && array_key_exists(410, $_SESSION['prava_stav'] ?? []) && cb_pravo_ma(410);
}

