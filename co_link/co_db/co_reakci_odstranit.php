<?php
/* Co-Link: jednim DELETE odebere konkretni reakci osoby a vrati, zda byla skutecne odstranena. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** affected_rows urcuje puvodni prepinani: pokud reakce neexistovala, sluzba ji nasledne vlozi. */
function co_reakci_odstranit(int $id, int $user, string $emoji): bool
{
    $statement = co_sql('DELETE FROM co_reakce WHERE zprava=? AND uzivatel=? AND emoji=?', [$id, $user, $emoji]);
    $removed = $statement->affected_rows > 0;
    $statement->close();
    return $removed;
}
