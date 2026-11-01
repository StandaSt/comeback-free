<?php
/* Co-Link: jednim SELECT nacte pritomnost aktivni role osoby. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Vraci puvodni vysledek dotazu; jeho vyznam vyhodnocuje sluzba opravneni. */
function co_roli_overit(int $id): array
{
    return co_rows('SELECT 1 FROM hr_pristupovy_profil p JOIN cis_role r ON r.id_role=p.id_role AND r.aktivni=1 WHERE p.id_person=? LIMIT 1', [$id]);
}
