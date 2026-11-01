<?php
/* Co-Link: jednim SELECT nacte aktivni zarizeni ostatnich osob s odberem a bez ztiseni konverzace. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Pristup a aktivni osoba se overuji nasledne; puvodni vyber zarizeni zustava zachovan. */
function co_push_zarizeni_pro_zpravu_nacist(int $user, int $cid): array
{
    return co_rows('SELECT d.id,d.uzivatel FROM co_zarizeni d WHERE d.uzivatel<>? AND d.zruseno IS NULL AND (d.pc_hash IS NULL OR d.platnost_do>NOW()) AND d.endpoint IS NOT NULL
            AND NOT EXISTS(SELECT 1 FROM co_volba v WHERE v.konverzace=? AND v.uzivatel=d.uzivatel AND v.ztiseno=1)', [$user, $cid]);
}
