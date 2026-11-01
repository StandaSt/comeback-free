<?php
/* Co-Link: jednim SELECT nacte cleny dostupnych soukromych a skupinovych konverzaci. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';
require_once __DIR__ . '/../co_lib/co_pristup_sql.php';

/** Clenove se nacitaji jen pro dostupne konverzace mimo kanaly. */
function co_konverzace_cleny_nacist(int $user): array
{
    return co_rows('SELECT c.konverzace,c.uzivatel FROM co_clen c JOIN co_konverzace k ON k.id=c.konverzace WHERE k.typ<>"kanal" AND '.co_pristup_sql($user));
}
