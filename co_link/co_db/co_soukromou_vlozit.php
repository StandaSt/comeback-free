<?php
/* Co-Link: jednim INSERT zalozi skupinu nebo zalozi ci dohleda existujici konverzaci stejne dvojice. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** LAST_INSERT_ID vrati pri shode klice puvodni ID; jeho prevzeti nevyzaduje dalsi SQL dotaz. */
function co_soukromou_vlozit(string $type, string $name, int $user, ?string $pair): int
{
    co_sql('INSERT INTO co_konverzace(typ,nazev,autor,soukromy_klic) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)', [$type, $name, $user, $pair])->close();
    return (int)db()->insert_id;
}
