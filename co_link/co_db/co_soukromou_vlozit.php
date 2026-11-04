<?php
/* Co-Link: jednim INSERT zalozi skupinu nebo zalozi ci dohleda existujici konverzaci stejne dvojice. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** LAST_INSERT_ID vrátí existující dvojici; příznak created odliší založení pro audit od opětovného otevření. */
function co_soukromou_vlozit(string $type, string $name, int $user, ?string $pair, ?bool &$created = null): int
{
    $statement=co_sql('INSERT INTO co_konverzace(typ,nazev,autor,soukromy_klic) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)', [$type, $name, $user, $pair]);
    $created=$statement->affected_rows===1; $statement->close();
    return (int)db()->insert_id;
}
