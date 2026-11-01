<?php
/* Co-Link: jednim dotazem uvolni pojmenovany zamek zpravy. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_zamek_uvolnit(string $name): void
{
    co_rows('SELECT RELEASE_LOCK(?)', [$name]);
}
