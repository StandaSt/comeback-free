<?php
/* Co-Link: čte pouze označení konverzace a jména přímých příjemců pro audit bez obsahu komunikace. */
declare(strict_types=1);
require_once __DIR__.'/../co_lib/co_rows.php';
require_once __DIR__.'/../../common/lib/osoba_jmeno.php';

/** Přímí účastníci mají pevné členství; kanál se v auditu označuje vlastním názvem. */
function co_audit_prijemce_nacist(int $cid, int $user): array
{
    return co_rows('SELECT c.uzivatel,COALESCE(NULLIF('.cb_osoba_jmeno_sql('p.id_person',true).",''),CONCAT('Uživatel #',c.uzivatel)) jmeno FROM co_clen c LEFT JOIN hr_person p ON p.id_user=c.uzivatel WHERE c.konverzace=? AND c.uzivatel<>? ORDER BY c.uzivatel",[$cid,$user]);
}

/** Autor je načten přes vazbu účtu a osoby, včetně neaktivních historických účtů. */
function co_audit_autora_nacist(int $user): string
{
    $rows=co_rows('SELECT '.cb_osoba_jmeno_sql('p.id_person',true).' jmeno FROM hr_person p WHERE p.id_user=? LIMIT 1',[$user]);
    return trim((string)($rows[0]['jmeno']??'')) ?: 'Uživatel #'.$user;
}
