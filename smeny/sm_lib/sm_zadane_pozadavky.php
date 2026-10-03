<?php
declare(strict_types=1);
/* Účel: Načte týdenní dostupnost pracovníků oprávněných poboček, včetně těch, kteří nic nevyplnili. */

/** Jediný dotaz vrací hlavičky i dny. Oprávnění poboček ověřujeme přes pracovní zařazení v daném týdnu. */
function cb_smeny_zadane_pozadavky(mysqli $db, array $branches, string $week): array
{
    if(!cb_smeny_planovani_vidi() || $branches===[]) return [];
    $ids=implode(',',array_map('intval',array_keys($branches)));
    $sql='SELECT hp.id_person,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) jmeno,
        p.nazev pobocka,p.end_po,p.end_ut,p.end_st,p.end_ct,p.end_pa,p.end_so,p.end_ne,
        q.id_smeny_pozadavek,q.rezim,q.volno_datum,q.ulozeno,d.den_tydne,d.cas_od,d.cas_do,
        EXISTS(SELECT 1 FROM hr_pracovni_vztah pv WHERE pv.id_person=hp.id_person AND pv.platny=1 AND pv.id_pracovni_vztah_typ=1 AND (pv.datum_nastupu IS NULL OR pv.datum_nastupu<=DATE_ADD(?,INTERVAL 6 DAY)) AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni>=?)) je_hpp
        FROM hr_person hp
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(x.id_osobni_udaje) FROM hr_osobni_udaje x WHERE x.id_person=hp.id_person AND x.platny=1)
        LEFT JOIN hr_pracoviste main ON main.id_pracoviste=(SELECT MAX(x.id_pracoviste) FROM hr_pracoviste x WHERE x.id_person=hp.id_person AND x.platny=1 AND x.hlavni=1 AND (x.platnost_od IS NULL OR x.platnost_od<=DATE_ADD(?,INTERVAL 6 DAY)) AND (x.platnost_do IS NULL OR x.platnost_do>=?))
        LEFT JOIN pobocka p ON p.id_pob=main.id_pob
        LEFT JOIN smeny_pozadavek q ON q.id_person=hp.id_person AND q.tyden_od=?
        LEFT JOIN smeny_pozadavek_den d ON d.id_smeny_pozadavek=q.id_smeny_pozadavek
        WHERE (hp.aktivni=1 OR q.id_smeny_pozadavek IS NOT NULL)
        AND EXISTS(SELECT 1 FROM hr_pracoviste pr WHERE pr.id_person=hp.id_person AND pr.platny=1 AND pr.id_pob IN ('.$ids.') AND (pr.platnost_od IS NULL OR pr.platnost_od<=DATE_ADD(?,INTERVAL 6 DAY)) AND (pr.platnost_do IS NULL OR pr.platnost_do>=?))
        ORDER BY p.nazev,jmeno,hp.id_person,d.den_tydne';
    $stmt=cb_smeny_planovani_sql($db,$sql,'sssssss',array_fill(0,7,$week));
    $people=[];
    foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $id=(int)$row['id_person'];
        if(!isset($people[$id])) $people[$id]=$row+['dny'=>[]];
        if($row['den_tydne']!==null) $people[$id]['dny'][(int)$row['den_tydne']]=substr($row['cas_od'],0,5).'–'.substr($row['cas_do'],0,5);
    }
    $stmt->close();
    return array_values($people);
}

/** HPP je požadavek na volno, nikoliv dostupnost. Prázdné odevzdané intervaly odlišíme od nezadaného týdne. */
function cb_smeny_zadane_den(array $person, array $day, int $number): string
{
    if($person['rezim']==='hpp' || ($person['id_smeny_pozadavek']===null && (int)$person['je_hpp']===1)) {
        return $person['volno_datum']===$day['date'] ? 'Chce volno' : 'HPP';
    }
    if($person['id_smeny_pozadavek']===null) return 'Nezadáno';
    if($person['rezim']==='kdykoliv') {
        $closing=substr((string)($person[$day['closing_key']]??''),0,5);
        return $closing!=='' ? '10:00–'.$closing : 'Kdykoliv (provozní doba chybí)';
    }
    return $person['dny'][$number]??'Nechce pracovat';
}
