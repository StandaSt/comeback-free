<?php
/* Co-Link: jednim SELECT nacte aktivni ciselnik pro filtry adresare. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Vraci puvodni pole a razeni API bez dalsiho zpracovani. */
function co_sloty_nacist(): array
{
    return co_rows('SELECT id_slot id,slot nazev FROM cis_slot WHERE aktivni=1 ORDER BY slot');
}
