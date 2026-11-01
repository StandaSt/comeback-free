<?php
/* Co-Link: jednim SELECT nacte aktivni netestovaci osoby se zobrazovanym jmenem. */
declare(strict_types=1);
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Pravo vstupu a vysledne razeni vyhodnocuje volajici sluzba. */
function co_lidi_nacist(): array
{
    return co_rows('SELECT u.id_user,COALESCE('.cb_osoba_jmeno_sql('p.id_person').',u.email) jmeno
        FROM user u JOIN hr_person p ON p.id_person=u.id_user AND p.id_user=u.id_user AND p.aktivni=1 AND p.testovaci=0 ORDER BY u.id_user');
}
