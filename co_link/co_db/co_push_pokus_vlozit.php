<?php
/* Co-Link: jednim INSERT zaradi oznameni zpravy pro konkretni zarizeni do push fronty. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Zaznam fronty zustava soucasti transakce zpravy, samotny transport tento soubor nespousti. */
function co_push_pokus_vlozit(int $id, int $device): void
{
    co_sql('INSERT INTO co_push_fronta(zprava,zarizeni) VALUES(?,?)', [$id, $device])->close();
}
