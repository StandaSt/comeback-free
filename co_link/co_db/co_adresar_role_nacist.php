<?php
/* Co-Link: jednim SELECT nacte aktivni role vybranych osob. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Prazdny vyber nic nenacita; do IN vstupuji vyhradne celociselna ID. */
function co_adresar_role_nacist(array $ids): array
{
    if ($ids === []) return [];
    $in = implode(',', array_map('intval', $ids));
    return co_rows('SELECT p.id_person,p.id_role,r.role FROM hr_pristupovy_profil p JOIN cis_role r ON r.id_role=p.id_role AND r.aktivni=1 WHERE p.id_person IN ('.$in.')');
}
