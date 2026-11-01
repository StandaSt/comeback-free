<?php
/* Co-Link: jednim SELECT nacte aktualni fotografie vybranych osob. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Prazdny vyber nic nenacita; do IN vstupuji vyhradne celociselna ID. */
function co_adresar_fotografie_nacist(array $ids): array
{
    if ($ids === []) return [];
    $in = implode(',', array_map('intval', $ids));
    return co_rows('SELECT o.id_person,o.foto FROM hr_osobni_udaje o WHERE o.id_person IN ('.$in.') AND o.id_osobni_udaje=(SELECT MAX(n.id_osobni_udaje) FROM hr_osobni_udaje n WHERE n.id_person=o.id_person AND n.platny=1)');
}
