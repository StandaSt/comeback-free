<?php
/* Co-Link: jednim SELECT nacte vsechny aktualni funkce vybranych osob. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Prazdny vyber nic nenacita; do IN vstupuji vyhradne celociselna ID. */
function co_adresar_funkce_nacist(array $ids): array
{
    if ($ids === []) return [];
    $in = implode(',', array_map('intval', $ids));
    return co_rows('SELECT DISTINCT f.id_person,c.nazev FROM hr_funkce f JOIN cis_funkce c ON c.id_funkce=f.id_funkce AND c.aktivni=1 WHERE f.id_person IN ('.$in.') AND f.platny=1 AND (f.platnost_od IS NULL OR f.platnost_od<=CURRENT_DATE()) AND (f.platnost_do IS NULL OR f.platnost_do>=CURRENT_DATE())');
}
