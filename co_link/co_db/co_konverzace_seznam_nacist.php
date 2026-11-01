<?php
/* Co-Link: jednim SELECT nacte dostupne konverzace s neprectenymi pocty a ztisenim. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';
require_once __DIR__ . '/../co_lib/co_pristup_sql.php';

/** Zachovava puvodni pristupovou podminku i poradi archivace, zmeny a ID. */
function co_konverzace_seznam_nacist(int $user): array
{
    return co_rows('SELECT k.id,k.typ,k.nazev,k.autor,k.archiv,k.zmeneno,COALESCE(v.ztiseno,0) ztiseno,
        (SELECT COUNT(*) FROM co_zprava z WHERE z.konverzace=k.id AND z.smazano IS NULL AND z.autor<>?
          AND NOT EXISTS(SELECT 1 FROM co_precteni p WHERE p.zprava=z.id AND p.uzivatel=?)) neprecteno
        FROM co_konverzace k LEFT JOIN co_volba v ON v.konverzace=k.id AND v.uzivatel=?
        WHERE '.co_pristup_sql($user).' ORDER BY k.archiv,k.zmeneno DESC,k.id DESC',[$user,$user,$user]);
}
