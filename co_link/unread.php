<?php
declare(strict_types=1);
/* Co-Link: pouze cte pocet neprectenych cizich zprav pro indikator ve spolecne hlavicce IS. */
require_once __DIR__.'/data.php';

/** Stejna pristupova pravidla jako seznam chatu; ztiseni push nepotlacuje neprectene zpravy. */
function co_neprecteno_hlavicka(int $user): int
{
    if(!co_pripraveno() || !co_osoba($user) || !co_pravo($user,700))return 0;
    $rows=co_rows('SELECT COUNT(*) pocet FROM co_zprava z JOIN co_konverzace k ON k.id=z.konverzace
        WHERE z.autor<>? AND z.smazano IS NULL AND '.co_pristup_sql($user).'
        AND NOT EXISTS(SELECT 1 FROM co_precteni p WHERE p.zprava=z.id AND p.uzivatel=?)',[$user,$user]);
    return (int)($rows[0]['pocet']??0);
}
