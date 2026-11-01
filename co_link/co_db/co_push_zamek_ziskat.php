<?php
/* Co-Link: jednim dotazem zkusi ziskat pojmenovany zamek bez cekani. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_zamek_ziskat(string $name): bool
{
    return (int)(co_rows('SELECT GET_LOCK(?,0) zamceno', [$name])[0]['zamceno'] ?? 0) === 1;
}
