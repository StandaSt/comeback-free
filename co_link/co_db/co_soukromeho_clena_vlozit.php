<?php
/* Co-Link: jednim INSERT IGNORE prida clena soukrome konverzace nebo skupiny bez duplicitniho clenstvi. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Opakovane otevreni existujici dvojice zachovava puvodni clenstvi. */
function co_soukromeho_clena_vlozit(int $id, int $member): void
{
    co_sql('INSERT IGNORE INTO co_clen(konverzace,uzivatel) VALUES(?,?)', [$id, $member])->close();
}
