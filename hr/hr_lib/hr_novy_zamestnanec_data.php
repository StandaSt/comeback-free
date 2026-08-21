<?php
/*
 * Ucel souboru: Pripravuje ciselniky pro formular noveho zamestnance v HR.
 * Nevykresluje HTML, neresi PP layout ani nezapisuje data do databaze.
 */
declare(strict_types=1);

require_once __DIR__ . '/../hr_includes/hr_data.php';

/*
 * Vraci data potrebna pouze pro vyberove prvky formulare noveho zamestnance.
 */
function hr_novy_zamestnanec_data(mysqli $db): array
{
    return [
        'vztahy' => hr_fetch_lookup($db, 'hr_cis_pracovni_vztah_typ', 'id_pracovni_vztah_typ', 'nazev', 'id_pracovni_vztah_typ'),
        'pobocky' => hr_fetch_lookup($db, 'pobocka', 'id_pob', 'nazev'),
        'sloty' => hr_fetch_lookup($db, 'cis_slot', 'id_slot', 'slot'),
    ];
}
