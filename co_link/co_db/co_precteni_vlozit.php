<?php
/* Co-Link: jednim INSERT IGNORE eviduje precteni konkretni zpravy danou osobou. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Opakovane potvrzeni zachovava puvodni zaznam misto vytvoreni duplicity. */
function co_precteni_vlozit(int $id, int $user): void
{
    co_sql('INSERT IGNORE INTO co_precteni(zprava,uzivatel) VALUES(?,?)', [$id, $user])->close();
}
