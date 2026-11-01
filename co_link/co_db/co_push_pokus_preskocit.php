<?php
/* Co-Link: uzavre zaznam fronty bez zvyseni poctu pokusu. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_pokus_preskocit(int $id): void
{
    co_sql('UPDATE co_push_fronta SET hotovo=NOW() WHERE id=?', [$id])->close();
}
