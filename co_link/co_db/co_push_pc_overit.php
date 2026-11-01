<?php
/* Co-Link: jednim dotazem overi nezrusene prihlaseni pocitace dane osoby. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_pc_overit(string $hash, int $user): bool
{
    return co_rows('SELECT 1 FROM user_pc_session WHERE HEX(pc_hash)=UPPER(?) AND id_user=? AND zruseno IS NULL', [$hash, $user]) !== [];
}
