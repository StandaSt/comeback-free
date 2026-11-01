<?php
/* Co-Link: jednim SELECT nacte podklady globalnich prav a individualnich vyjimek. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Vyhodnoceni priority vyjimek a zavislosti prav zustava mimo DB operaci. */
function co_prava_nacist(int $id): array
{
    return co_rows('SELECT p.id_pravo,p.aktivni,
        EXISTS(SELECT 1 FROM hr_pristupovy_profil r JOIN prava_global g ON g.id_role=r.id_role WHERE r.id_person=? AND g.id_pravo=p.id_pravo) globalni,
        (SELECT v.povoleno FROM prava_vyjimky v WHERE v.id_user=? AND v.id_pravo=p.id_pravo) vyjimka
        FROM cis_prava p WHERE p.id_pravo BETWEEN 700 AND 703', [$id,$id]);
}
