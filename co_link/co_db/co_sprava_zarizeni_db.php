<?php
/* Co-Link: souvisejici dotazy pro seznam zarizeni, jejich odvolani, push odber a cas aktivity. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Zachova rozdil mezi trvale pripojenym telefonem a casove omezenym PC. */
function co_sprava_zarizeni_nacist(int $user): array
{
    return co_rows('SELECT id,nazev,vytvoreno,naposledy,CASE WHEN pc_hash IS NULL THEN NULL ELSE platnost_do END platnost_do FROM co_zarizeni WHERE uzivatel=? AND zruseno IS NULL AND (pc_hash IS NULL OR platnost_do>NOW()) ORDER BY id DESC', [$user]);
}

/** Cizi zarizeni nelze odvolat; puvodni filtr vlastnika je soucasti UPDATE. */
function co_sprava_zarizeni_odvolat(int $id, int $user): void
{
    co_sql('UPDATE co_zarizeni SET zruseno=NOW(),endpoint=NULL WHERE id=? AND uzivatel=?', [$id, $user])->close();
}

/** Aktualni zarizeni uz overila identita HTTP vstupu. */
function co_sprava_zarizeni_odhlasit(int $id): void
{
    co_sql('UPDATE co_zarizeni SET zruseno=NOW(),endpoint=NULL WHERE id=?', [$id])->close();
}

/** Sluzba pred ulozenim overi adresu endpointu i format obou klicu. */
function co_sprava_zarizeni_push_ulozit(int $id, string $endpoint, array $keys): void
{
    co_sql('UPDATE co_zarizeni SET endpoint=?,klic_public=?,klic_auth=? WHERE id=?', [$endpoint, $keys['p256dh'], $keys['auth'], $id])->close();
}

/** HTTP vstup obnovi aktivitu az po uspechu puvodne obslouzeneho POST pozadavku. */
function co_zarizeni_aktivitu_obnovit(int $id): void
{
    co_sql('UPDATE co_zarizeni SET naposledy=NOW() WHERE id=?', [$id])->close();
}
