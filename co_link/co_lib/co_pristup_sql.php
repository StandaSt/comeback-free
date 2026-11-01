<?php
/* Co-Link: sestavi sdilenou podminku pristupu podle clenstvi a aktualnich HR slotu a funkci. */
declare(strict_types=1);

/** Clenstvi kanalu je soucet vyberu; zmena HR udaju odebere i pristup k historii a priloham. */
function co_pristup_sql(int $user, string $alias = 'k'): string
{
    $user = max(0,$user);
    return "(EXISTS(SELECT 1 FROM co_clen c WHERE c.konverzace=$alias.id AND c.uzivatel=$user)
      OR ($alias.typ='kanal' AND ($alias.pristup_vsem=1
        OR EXISTS(SELECT 1 FROM co_pristup r JOIN hr_slot s ON s.id_slot=r.hodnota AND s.id_person=$user
          WHERE r.konverzace=$alias.id AND r.druh='slot' AND s.platny=1 AND s.zruseno IS NULL
          AND (s.platnost_od IS NULL OR s.platnost_od<=CURDATE()) AND (s.platnost_do IS NULL OR s.platnost_do>=CURDATE()))
        OR EXISTS(SELECT 1 FROM co_pristup r JOIN hr_funkce f ON f.id_funkce=r.hodnota AND f.id_person=$user
          WHERE r.konverzace=$alias.id AND r.druh='funkce' AND f.platny=1
          AND (f.platnost_od IS NULL OR f.platnost_od<=CURDATE()) AND (f.platnost_do IS NULL OR f.platnost_do>=CURDATE())))))";
}
