<?php
/* Co-Link: jednim dotazem zjisti jiz prectenou zpravu. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_precteni_overit(int $message, int $user): bool
{
    return co_rows('SELECT 1 FROM co_precteni WHERE zprava=? AND uzivatel=?', [$message, $user]) !== [];
}
