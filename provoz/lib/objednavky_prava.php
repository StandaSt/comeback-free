<?php
declare(strict_types=1);
/* Účel: Omezuje samostatnou stránku Objednávky právem 220; starší převzatá relace nesmí zablokovat Přehled Provozu. */
const CB_OBJEDNAVKY_ZOBRAZIT_PRAVO = 220;

/** Nově přidané právo před obnovou snímku relace bezpečně odmítneme, nikoliv chybou celé aplikace. */
function cb_objednavky_ma_pravo(): bool
{
    return array_key_exists(CB_OBJEDNAVKY_ZOBRAZIT_PRAVO,$_SESSION['prava_stav'] ?? [])
        && function_exists('cb_pravo_ma') && cb_pravo_ma(CB_OBJEDNAVKY_ZOBRAZIT_PRAVO);
}
