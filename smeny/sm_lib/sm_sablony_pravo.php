<?php
declare(strict_types=1);

/* Účel souboru: Ověří právo k vytváření a úpravě šablon směn. */

function cb_smeny_sablony_ma_pravo(): bool
{
    return function_exists('cb_pravo_ma') && cb_pravo_ma(404);
}

