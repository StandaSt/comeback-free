<?php
/* Co-Link: cteni a zapis limitu prilohy; opravneni a transakci zajistuje sluzba nastaveni. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Vrati puvodni radek nastaveni bez doplnovani vychozich hodnot. */
function co_nastaveni_db_nacist(bool $lock = false): array
{
    return co_rows('SELECT priloha_mb FROM co_nastaveni WHERE id=1'.($lock?' FOR UPDATE':''));
}

/** Jediny parametr zustava ciselny limit overeny sluzbou. */
function co_nastaveni_db_ulozit(int $mb): void
{
    co_sql('UPDATE co_nastaveni SET priloha_mb=? WHERE id=1', [$mb])->close();
}
