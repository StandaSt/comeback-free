<?php
/* Co-Link: jednim SELECT nacte ciselnik aktivnich pobocek v puvodnim poradi. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Zachovava puvodni ciselnik; omezeni osoby na MAIN resi samostatny dotaz. */
function co_pobocky_nacist(): array
{
    return co_rows('SELECT id_pob id,nazev FROM pobocka WHERE aktivni=1 ORDER BY id_pob');
}
