<?php
declare(strict_types=1);
/* Účel: Čte aktivní členy prvních šesti rolí a jejich dnešní pracovní zařazení, bez změny dat. */
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

/** Načte role hromadně; osoba s více rolemi se zobrazí v každé skutečně přiřazené roli. */
function cb_admin_role_uzivatele(mysqli $db): array
{
    $people = []; $members = []; $out = [];
    $result = $db->query('SELECT DISTINCT r.id_role,p.id_person,p.aktivni,p.pristup_vsechny_pobocky,
        ou.jmeno,ou.prijmeni
        FROM hr_pristupovy_profil r JOIN cis_role cr ON cr.id_role=r.id_role AND cr.aktivni=1
        JOIN hr_person p ON p.id_person=r.id_person JOIN user u ON u.id_user=p.id_user
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(o.id_osobni_udaje)
            FROM hr_osobni_udaje o WHERE o.id_person=p.id_person AND o.platny=1)
        WHERE r.id_role IN (1,2,3,4,5,7) AND p.aktivni=1 ORDER BY ou.prijmeni,ou.jmeno,p.id_person');
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $id = (int)$row['id_person'];
        $people[$id] = $row + ['funkce'=>[], 'sloty'=>[], 'pobocky'=>[], 'main'=>[]];
        $members[(int)$row['id_role']][$id] = true;
    }
    $result->free();
    if ($people === []) return [];
    // ID pocházejí výhradně z předchozího dotazu, nikoli z požadavku prohlížeče.
    $ids = implode(',', array_map('intval', array_keys($people)));
    $queries = [
        'sloty' => 'SELECT s.id_person,c.slot AS nazev FROM hr_slot s JOIN cis_slot c USING(id_slot)
            WHERE s.id_person IN ('.$ids.') AND '.cb_hr_ucinne_sql('s').' ORDER BY c.id_slot',
        'funkce' => 'SELECT f.id_person,CONCAT(c.nazev,IF(f.v_treninku=1," (v tréninku)","")) AS nazev
            FROM hr_funkce f JOIN cis_funkce c USING(id_funkce)
            WHERE f.id_person IN ('.$ids.') AND f.platny=1
            AND (f.platnost_od IS NULL OR f.platnost_od<=CURRENT_DATE())
            AND (f.platnost_do IS NULL OR f.platnost_do>=CURRENT_DATE()) ORDER BY c.nazev',
        'pobocky' => 'SELECT w.id_person,p.nazev FROM '.cb_hr_mista_sql().' w JOIN pobocka p USING(id_pob)
            WHERE w.id_person IN ('.$ids.') AND '.cb_hr_ucinne_sql('w').' ORDER BY p.nazev',
        'main' => 'SELECT m.id_person,p.nazev FROM hr_pracoviste_main m JOIN pobocka p USING(id_pob)
            WHERE m.id_person IN ('.$ids.') AND '.cb_hr_ucinne_sql('m').' ORDER BY p.nazev',
    ];
    foreach ($queries as $key => $sql) {
        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) $people[(int)$row['id_person']][$key][(string)$row['nazev']] = true;
        $result->free();
    }
    foreach ($members as $role => $persons) {
        foreach (array_keys($persons) as $id) $out[$role][] = $people[$id];
    }
    return $out;
}
