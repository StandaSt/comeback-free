<?php
declare(strict_types=1);

/* Ucel souboru: Overuje pravo k zadavani vlastnich pozadavku na smeny. */

function cb_smeny_pozadavky_ma_pravo(): bool
{
    return function_exists('cb_pravo_ma') && cb_pravo_ma(405);
}

