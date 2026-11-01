<?php
/* Co-Link: nacte nejvyse sto zprav se splatnym pokusem. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_frontu_nacist(): array
{
    return co_rows('SELECT DISTINCT zprava FROM co_push_fronta WHERE hotovo IS NULL AND dalsi_pokus<=NOW() ORDER BY zprava LIMIT 100');
}
