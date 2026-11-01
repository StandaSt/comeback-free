<?php
/* Co-Link: jednim dotazem zvysi pocitadlo a odlozi dalsi pokus. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_pokus_odlozit(int $id, int $seconds): void
{
    co_sql('UPDATE co_push_fronta SET pokusy=pokusy+1,dalsi_pokus=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id=?', [$seconds, $id])->close();
}
