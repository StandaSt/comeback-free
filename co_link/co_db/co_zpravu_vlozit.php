<?php
/* Co-Link: jednim INSERT ulozi zpravu a vrati jeji ID bez dalsiho SQL dotazu. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Vola se az po overeni pristupu, klientskych udaju a pripadne rodicovske zpravy. */
function co_zpravu_vlozit(int $cid, int $user, string $client, ?int $parent, string $text): int
{
    co_sql('INSERT INTO co_zprava(konverzace,autor,klient_id,rodic,text) VALUES(?,?,?,?,?)', [$cid, $user, $client, $parent, $text])->close();
    return (int)db()->insert_id;
}
