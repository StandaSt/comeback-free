<?php
/* Co-Link: jednim dotazem overi pristup osoby do konverzace. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_pristup_overit(int $user, int $conversation): bool
{
    return co_rows('SELECT k.id FROM co_konverzace k WHERE k.id=? AND ' . co_pristup_sql($user), [$conversation]) !== [];
}
