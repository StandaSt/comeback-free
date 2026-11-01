<?php
/* Co-Link: jednim dotazem zjisti ztiseni konverzace osobou. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_ztiseni_overit(int $user, int $conversation): bool
{
    return co_rows('SELECT 1 FROM co_volba WHERE uzivatel=? AND konverzace=? AND ztiseno=1', [$user, $conversation]) !== [];
}
