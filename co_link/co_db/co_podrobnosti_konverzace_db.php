<?php
/* Co-Link: dotazy pro dostupne cleny, precteni konkretni zpravy a uzivatelske ztiseni konverzace. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';
require_once __DIR__ . '/../co_lib/co_pristup_sql.php';
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

/** Stejne pravidlo pristupu jako drive v API; kandidat nemusi byt explicitnim clenem. */
function co_podrobnosti_clen_pristup(int $id, int $person): array
{
    return co_rows('SELECT k.id FROM co_konverzace k WHERE k.id=? AND ' . co_pristup_sql($person), [$id]);
}

/** Konverzace zpravy se zjisti pred kontrolou pristupu volajiciho. */
function co_podrobnosti_zprava_konverzace(int $id): array
{
    return co_rows('SELECT konverzace FROM co_zprava WHERE id=?', [$id]);
}

/** Osobni jmeno vcetne titulu a razeni podle casu zustava stejne jako v API. */
function co_podrobnosti_precteni_nacist(int $id): array
{
    return co_rows('SELECT p.uzivatel,p.kdy,COALESCE(' . cb_osoba_jmeno_sql('p.uzivatel') . ',"Uživatel") jmeno FROM co_precteni p WHERE p.zprava=? ORDER BY p.kdy', [$id]);
}

/** Ztiseni je spolecne pro zarizeni osoby a nemeni indikaci neprectenych zprav. */
function co_podrobnosti_ztiseni_ulozit(int $user, int $id, int $muted): void
{
    co_sql('INSERT INTO co_volba(uzivatel,konverzace,ztiseno) VALUES(?,?,?) ON DUPLICATE KEY UPDATE ztiseno=VALUES(ztiseno)', [$user, $id, $muted])->close();
}
