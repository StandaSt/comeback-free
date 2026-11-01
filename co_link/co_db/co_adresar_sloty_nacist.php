<?php
/* Co-Link: jednim SELECT nacte vsechny aktualni sloty vybranych osob. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

/** Prazdny vyber nic nenacita; do IN vstupuji vyhradne celociselna ID. */
function co_adresar_sloty_nacist(array $ids): array
{
    if ($ids === []) return [];
    $in = implode(',', array_map('intval', $ids));
    return co_rows('SELECT DISTINCT s.id_person,s.id_slot,c.slot FROM hr_slot s JOIN cis_slot c ON c.id_slot=s.id_slot AND c.aktivni=1 WHERE s.id_person IN ('.$in.') AND '.cb_hr_ucinne_sql('s'));
}
