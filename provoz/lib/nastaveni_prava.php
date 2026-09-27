<?php
// Prava pro spolecnou sekci nastaveni modulu Provoz.
declare(strict_types=1);

const CB_PROVOZ_NASTAVENI_PRAVO = 217;

// Overi, zda prihlaseny uzivatel smi spravovat nastaveni pobocek.
function cb_provoz_nastaveni_ma_pravo(): bool
{
    return function_exists('cb_pravo_ma') && cb_pravo_ma(CB_PROVOZ_NASTAVENI_PRAVO);
}
