<?php
/* Co-Link: jednim SELECT nacte aktivni ciselnik pro filtry adresare. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Vraci puvodni pole a razeni API bez dalsiho zpracovani. */
function co_funkce_nacist(): array
{
    return co_rows('SELECT id_funkce id,nazev,zarazeni FROM cis_funkce WHERE aktivni=1 ORDER BY zarazeni,nazev');
}
