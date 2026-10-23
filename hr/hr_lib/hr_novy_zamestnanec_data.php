<?php
/*
 * Ucel: Pripravuje ciselniky, oblasti, clenstvi pobocek a administratorske omezeni noveho zamestnance.
 * Nevykresluje HTML, neresi PP layout ani nezapisuje data do databaze.
 */
declare(strict_types=1);

require_once __DIR__ . '/../hr_includes/hr_data.php';

/*
 * Vraci data potrebna pouze pro vyberove prvky formulare noveho zamestnance.
 */
function hr_novy_zamestnanec_data(mysqli $db): array
{
    $funkce = hr_funkce_historie($db, 0)['funkce'];
    $newBranchRegions=[];
    foreach ($db->query('SELECT id_pob,id_oblast_prace FROM pobocka_oblast_prace a WHERE '.cb_hr_ucinne_sql('a'))->fetch_all(MYSQLI_ASSOC) as $row) $newBranchRegions[(int)$row['id_pob']][]=(int)$row['id_oblast_prace'];
    return [
        'newRegions'=>$db->query('SELECT id_oblast_prace,nazev FROM cis_oblast_prace WHERE aktivni=1 ORDER BY poradi,nazev')->fetch_all(MYSQLI_ASSOC),
        'newBranchRegions'=>$newBranchRegions,
        'newPersonIsAdmin'=>hr_ciselnik_je_admin($db),
        'vztahy' => hr_fetch_lookup($db, 'hr_cis_pracovni_vztah_typ', 'id_pracovni_vztah_typ', 'nazev', 'id_pracovni_vztah_typ'),
        'pobocky' => hr_fetch_lookup($db, 'pobocka', 'id_pob', 'nazev', 'id_pob'),
        'sloty' => hr_fetch_lookup($db, 'cis_slot', 'id_slot', 'slot'),
        'funkce' => $funkce,
        'healthInsurers' => hr_fetch_health_insurers($db),
        'genders' => hr_fetch_genders($db),
        'titulyPred' => hr_fetch_employee_titles($db, 1),
        'titulyZa' => hr_fetch_employee_titles($db, 2),
    ];
}
