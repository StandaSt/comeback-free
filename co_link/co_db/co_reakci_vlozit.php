<?php
/* Co-Link: jednim INSERT ulozi konkretni emoji reakci dane osoby ke zprave. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Volajici sluzba pred vlozenim overila emoji, pristup, archivaci a neexistenci reakce. */
function co_reakci_vlozit(int $id, int $user, string $emoji): void
{
    co_sql('INSERT INTO co_reakce(zprava,uzivatel,emoji) VALUES(?,?,?)', [$id, $user, $emoji])->close();
}
