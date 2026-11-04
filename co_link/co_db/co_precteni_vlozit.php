<?php
/* Co-Link: jednim INSERT IGNORE eviduje precteni konkretni zpravy danou osobou. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Vrátí skutečné první přečtení; opakované potvrzení nesmí zdvojit audit. */
function co_precteni_vlozit(int $id, int $user): bool
{
    $statement=co_sql('INSERT IGNORE INTO co_precteni(zprava,uzivatel) VALUES(?,?)', [$id, $user]);
    $inserted=$statement->affected_rows===1; $statement->close();
    return $inserted;
}
