<?php
/* Co-Link: jednim SELECT nacte vsechny aktualni MAIN ve firme osoby. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

/** Prazdny vyber nic nenacita; do IN vstupuji vyhradne celociselna ID. */
function co_adresar_main_nacist(array $ids): array
{
    if ($ids === []) return [];
    $in = implode(',', array_map('intval', $ids));
    return co_rows('SELECT DISTINCT m.id_person,m.id_pob FROM '.cb_hr_main_sql(db()).' m
            JOIN hr_person p ON p.id_person=m.id_person
            JOIN pobocka b ON b.id_pob=m.id_pob AND b.id_firma=p.id_firma AND b.aktivni=1
            WHERE m.id_person IN ('.$in.') AND '.cb_hr_ucinne_sql('m'));
}
