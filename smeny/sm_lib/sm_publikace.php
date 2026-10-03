<?php
declare(strict_types=1);
/* Účel: Atomicky zveřejní verzi, dokončí dotčené žádosti o zrušení a uloží neměnná oznámení. Nic neodesílá. */

/** Do potvrzované zprávy patří pouze význam směny, nikoliv technické ID kopie či čas uložení. */
function cb_smeny_publikace_otisk(array $shift): array
{
    return ['klic_smeny'=>(string)$shift['klic_smeny'],'id_person'=>(int)$shift['id_person'],
        'id_slot'=>(int)$shift['id_slot'],'zacatek'=>(string)$shift['zacatek'],'konec'=>(string)$shift['konec']];
}

/** Čistý výpočet adresných změn; přesun zaměstnance je odebrání původnímu a přidání novému. */
function cb_smeny_publikace_zmeny(array $old, array $new): array
{
    $before=[]; $after=[]; $events=[];
    foreach($old as $s) $before[$s['klic_smeny']]=cb_smeny_publikace_otisk($s);
    foreach($new as $s) $after[$s['klic_smeny']]=cb_smeny_publikace_otisk($s);
    foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $key) {
        $a=$before[$key]??null; $b=$after[$key]??null;
        if($a===$b)continue;
        if($a!==null && $b!==null && $a['id_person']===$b['id_person']) {
            $events[]=['id_person'=>$b['id_person'],'typ'=>'zmenena','klic'=>$key,'obsah'=>['pred'=>$a,'po'=>$b]];
        } else {
            if($a!==null)$events[]=['id_person'=>$a['id_person'],'typ'=>'odebrana','klic'=>$key,'obsah'=>['pred'=>$a,'po'=>null]];
            if($b!==null)$events[]=['id_person'=>$b['id_person'],'typ'=>'pridana','klic'=>$key,'obsah'=>['pred'=>null,'po'=>$b]];
        }
    }
    return $events;
}

/** Volat pouze uvnitř transakce dispatcheru pod zámkem pobočky. Push odešle dispatcher až po commitu. */
function cb_smeny_publikace_zverejnit(mysqli $db,array $plan,int $publisher): void
{
    if(!cb_smeny_planovani_publikuje())throw new CbUserVisibleException('Nemáte právo zveřejnit směny.');
    if($plan['pracovni_verze']===null || (int)$plan['pripraveno']!==1)throw new CbUserVisibleException('Nejprve označte pracovní týden jako připravený.');
    $id=(int)$plan['id_smeny_rozpis']; $version=(int)$plan['pracovni_verze'];
    // Stejné pořadí zámků osob omezuje souběh publikací a přidělování mezi pobočkami.
    $people=array_unique(array_map('intval',array_column($plan['blocks'],'id_person'))); sort($people,SORT_NUMERIC);
    foreach($people as $person) {
        $stmt=cb_smeny_planovani_sql($db,'SELECT id_person FROM hr_person WHERE id_person=? AND aktivni=1 FOR UPDATE','i',[$person]);
        $active=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($active===null)throw new CbUserVisibleException('Rozpis obsahuje neaktivního pracovníka. Otevřete úpravy.');
    }
    foreach($plan['blocks'] as $s)cb_smeny_planovani_kolize($db,$plan,(int)$s['id_person'],$s['zacatek'],$s['konec'],$s['klic_smeny']);
    $old=[];
    if($plan['zverejnena_verze']!==null) {
        $stmt=cb_smeny_planovani_sql($db,'SELECT * FROM smeny_smena WHERE id_smeny_rozpis=? AND verze=?','ii',[$id,(int)$plan['zverejnena_verze']]);
        $old=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
        $events=cb_smeny_publikace_zmeny($old,$plan['blocks']);
    } else {
        // První týden dostanou i kmenoví pracovníci bez směn; cizí pracovníci pouze pokud mají směnu.
        $stmt=cb_smeny_planovani_sql($db,'SELECT DISTINCT hp.id_person FROM hr_person hp JOIN hr_pracoviste pr ON pr.id_person=hp.id_person WHERE hp.aktivni=1 AND pr.id_pob=? AND pr.hlavni=1 AND pr.platny=1 AND (pr.platnost_od IS NULL OR pr.platnost_od<=DATE_ADD(?,INTERVAL 6 DAY)) AND (pr.platnost_do IS NULL OR pr.platnost_do>=?)','iss',[(int)$plan['id_pob'],$plan['tyden_od'],$plan['tyden_od']]);
        foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$people[]=(int)$row['id_person'];$stmt->close();
        // Převzetí externího týdne oznámíme i člověku, který v novém interním plánu směnu nemá.
        $stmt=cb_smeny_planovani_sql($db,'SELECT DISTINCT hp.id_person FROM smeny_plan e JOIN hr_person hp ON hp.id_user=e.id_user WHERE e.id_pob=? AND e.start_day=? AND e.zdroj=1','is',[(int)$plan['id_pob'],$plan['tyden_od']]);
        foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$people[]=(int)$row['id_person'];$stmt->close();
        $events=[];
        foreach(array_unique($people) as $person) {
            $shifts=array_values(array_filter($plan['blocks'],static fn(array $s):bool=>(int)$s['id_person']===$person));
            $events[]=['id_person'=>$person,'typ'=>'tyden','klic'=>'tyden','obsah'=>['smeny'=>array_map('cb_smeny_publikace_otisk',$shifts)]];
        }
    }
    // Zrušení žádostí je součástí stejné transakce jako publikace a oznámení zaměstnancům.
    cb_smeny_zruseni_publikovat($db,$plan,$publisher);
    $roles=[];
    $result=$db->query('SELECT id_slot,slot FROM cis_slot');
    while($row=$result->fetch_assoc())$roles[(int)$row['id_slot']]=$row['slot'];$result->close();
    foreach($events as $event) {
        // Čitelné názvy jsou otiskem okamžiku publikace, změna číselníku potvrzení nemění.
        foreach(['pred','po'] as $side)if(isset($event['obsah'][$side]))$event['obsah'][$side]['pozice']=$roles[$event['obsah'][$side]['id_slot']]??'Pozice';
        if(isset($event['obsah']['smeny']))foreach($event['obsah']['smeny'] as &$shift)$shift['pozice']=$roles[$shift['id_slot']]??'Pozice';
        unset($shift);
        $content=json_encode(['tyden_od'=>$plan['tyden_od'],'id_pob'=>(int)$plan['id_pob']]+$event['obsah'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        cb_smeny_planovani_sql($db,'INSERT INTO smeny_oznameni (id_smeny_rozpis,verze,id_person,typ,klic_udalosti,obsah) VALUES (?,?,?,?,?,?)','iiisss',[$id,$version,$event['id_person'],$event['typ'],$event['klic'],$content])->close();
    }
    cb_smeny_planovani_sql($db,'UPDATE smeny_rozpis SET zverejnena_verze=?,pracovni_verze=NULL,pripraveno=0,zverejnil_id_person=?,zverejneno=NOW() WHERE id_smeny_rozpis=?','iii',[$version,$publisher,$id])->close();
    cb_smeny_audit_zapis($db,$publisher,'zverejneno','rozpis',$id,['verze'=>$plan['zverejnena_verze']],['verze'=>$version,'oznameni'=>count($events)]);
}
