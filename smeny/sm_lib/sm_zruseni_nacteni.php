<?php
declare(strict_types=1);
/* Účel: Načte žádosti o zrušení směn pro zaměstnance nebo vedoucího oprávněné pobočky. */

/** Žádost zůstává spojená s původní publikovanou směnou; stabilní klíč ji dohledá i po kopii týdne. */
function cb_smeny_zruseni_nacist(mysqli $db, string $week, int $person, array $branches, bool $all): array
{
    if($all && (!cb_smeny_planovani_vidi() || $branches===[])) return [];
    if(!$all && $person<=0) return [];
    $filter=$all ? 'r.id_pob IN ('.implode(',',array_map('intval',array_keys($branches))).')' : 'z.pozadal_id_person=?';
    $sql='SELECT z.*,current_shift.id_smeny_smena aktualni_smena,s.klic_smeny,s.id_smeny_rozpis,s.zacatek,s.konec,s.id_person,s.id_slot,r.id_pob,r.tyden_od,p.nazev pobocka,cs.slot,
        TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) jmeno
        FROM smeny_zruseni z JOIN smeny_smena s ON s.id_smeny_smena=z.id_smeny_smena JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis
        LEFT JOIN smeny_smena current_shift ON current_shift.id_smeny_rozpis=r.id_smeny_rozpis AND current_shift.verze=r.zverejnena_verze AND current_shift.klic_smeny=s.klic_smeny AND current_shift.id_person=z.pozadal_id_person
        JOIN pobocka p ON p.id_pob=r.id_pob JOIN cis_slot cs ON cs.id_slot=s.id_slot
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(x.id_osobni_udaje) FROM hr_osobni_udaje x WHERE x.id_person=z.pozadal_id_person AND x.platny=1)
        WHERE r.tyden_od=? AND '.$filter.' ORDER BY (z.stav IN ("odeslana","resi_se")) DESC,z.pozadano DESC';
    $stmt=cb_smeny_planovani_sql($db,$sql,$all?'s':'si',$all?[$week]:[$week,$person]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Rozlišuje souhlas vedoucího v pracovní kopii od již zveřejněného zrušení. */
function cb_smeny_zruseni_stav(string $state): string
{
    return ['odeslana'=>'Čeká na vyřízení','resi_se'=>'Odebráno v pracovním plánu – čeká na zveřejnění','schvalena'=>'Zrušení zveřejněno','zamitnuta'=>'Zamítnuto','stazena'=>'Žádost stažena'][$state]??$state;
}
