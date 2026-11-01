<?php
/* Co-Link: jednim INSERT ulozi hash tokenu a udaje noveho zarizeni. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Nazev, platnost a token pripravuje sluzba pripojeni; tato funkce jen provede puvodni INSERT. */
function co_zarizeni_vlozit(int $user, string $hash, ?string $pcHash, string $name, string $expiry): void
{
    co_sql('INSERT INTO co_zarizeni(uzivatel,token_hash,pc_hash,nazev,platnost_do) VALUES(?,?,?,?,?)', [$user, $hash, $pcHash, $name, $expiry])->close();
}
