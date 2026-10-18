<?php
declare(strict_types=1);
/* Účel: Udržuje základní sloupce, volitelné přidává na požádání nebo ze šablony a chrání verzované odebrání obsazeného sloupce. */

/** Pouze čtecí kontrola migrace; kód nikdy sám nemění schéma databáze. */
function cb_smeny_planovani_schema(mysqli $db): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    $result=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('smeny_rozpis','smeny_smena','smeny_rozpis_slot')");
    $columns=[];
    while($row=$result->fetch_assoc()) $columns[$row['TABLE_NAME'].'.'.$row['COLUMN_NAME']]=true;
    $result->close();
    foreach(['smeny_rozpis.planovat_kdykoliv','smeny_rozpis.bez_ohledu_na_pobocku','smeny_rozpis.sloty_pripraveny','smeny_smena.klic_slotu','smeny_rozpis_slot.klic_slotu','smeny_rozpis_slot.verze'] as $column) {
        if(!isset($columns[$column])) return $ready=false;
    }
    return $ready=true;
}

/** Čistý převod staršího rozpisu: souběžné směny mají různé sloupce, navazující mohou sdílet jeden. */
function cb_smeny_planovani_sloty_navrh(array $plan): array
{
    $columns=[]; $mapping=[];
    $base=new DateTimeImmutable($plan['tyden_od']);
    $groups=[];
    foreach($plan['potreba'] as $guide) {
        $date=$base->modify('+'.((int)$guide['den_tydne']-1).' days')->format('Y-m-d');
        $groups[$date.':'.$guide['id_slot']]['guides'][]=$guide;
    }
    foreach($plan['blocks'] as $shift) $groups[$shift['datum'].':'.$shift['id_slot']]['shifts'][]=$shift;
    foreach($groups as $groupKey=>$group) {
        [$date,$position]=explode(':',$groupKey);
        $lanes=array_fill(0,count($group['guides'] ?? []),[]);
        $shifts=$group['shifts'] ?? [];
        usort($shifts,static fn(array $a,array $b):int=>strcmp($a['zacatek'],$b['zacatek']) ?: strcmp($b['konec'],$a['konec']));
        foreach($shifts as $shift) {
            $index=null;
            foreach($lanes as $number=>$occupied) {
                $collision=false;
                foreach($occupied as $other) if($other['zacatek']<$shift['konec'] && $shift['zacatek']<$other['konec']) $collision=true;
                if(!$collision) { $index=$number; break; }
            }
            if($index===null) { $index=count($lanes); $lanes[]=[]; }
            $lanes[$index][]=$shift;
        }
        foreach($lanes as $index=>$occupied) {
            $key=substr(hash('sha256',$plan['id_smeny_rozpis'].'|'.$plan['verze'].'|'.$groupKey.'|'.$index),0,32);
            $columns[]=['klic_slotu'=>$key,'datum'=>$date,'id_slot'=>(int)$position,'poradi'=>$index+1];
            foreach($occupied as $shift) $mapping[(int)$shift['id_smeny_smena']]=$key;
        }
    }
    return ['sloty'=>$columns,'mapovani'=>$mapping];
}

/** Základ má vždy místo; volitelné pozice přibudou ze šablony pouze při jejím výslovném použití. */
function cb_smeny_planovani_sloty_doplnit(array &$plan, array $positions, bool $applyTemplate = false): void
{
    $base=new DateTimeImmutable($plan['tyden_od']);
    for($day=1;$day<=7;$day++) foreach($positions as $position=>$label) {
        $date=$base->modify('+'.($day-1).' days')->format('Y-m-d');
        $same=array_filter($plan['sloty'],static fn(array $column):bool=>$column['datum']===$date && (int)$column['id_slot']===(int)$position);
        if($same!==[] && !$applyTemplate) continue;
        $guides=array_filter($plan['potreba'],static fn(array $guide):bool=>(int)$guide['den_tydne']===$day && (int)$guide['id_slot']===(int)$position);
        $minimum=in_array((int)$position,cb_smeny_sloty_zakladni_id($plan),true)?1:0;
        if($minimum===0 && !$applyTemplate) continue;
        $orders=array_column($same,'poradi');
        for($order=1;$order<=max($minimum,count($guides));$order++) {
            if(in_array($order,$orders)) continue;
            // Stabilní klíč propojí stejný návrh při GET a následném POST; načtení stránky nikdy nezapisuje.
            $key=substr(hash('sha256','default|'.$plan['id_smeny_rozpis'].'|'.$plan['verze'].'|'.$date.'|'.$position.'|'.$order),0,32);
            $plan['sloty'][]=['klic_slotu'=>$key,'datum'=>$date,'id_slot'=>(int)$position,'poradi'=>$order,'navrzeny'=>true];
        }
    }
}

/** GET smí pouze navrhnout převod a chybějící místa, výslovný zápis je uloží pod zámkem pobočky. */
function cb_smeny_planovani_sloty_nacist(mysqli $db, array &$plan): void
{
    if(!empty($plan['sloty_pripraveny']) && cb_smeny_planovani_schema($db)) {
        $stmt=cb_smeny_planovani_sql($db,'SELECT klic_slotu,datum,id_slot,poradi FROM smeny_rozpis_slot WHERE id_smeny_rozpis=? AND verze=? ORDER BY datum,id_slot,poradi,klic_slotu','ii',[(int)$plan['id_smeny_rozpis'],(int)$plan['verze']]);
        $plan['sloty']=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    } else {
        $proposal=cb_smeny_planovani_sloty_navrh($plan);
        $plan['sloty']=$proposal['sloty'];
        foreach($plan['blocks'] as &$shift) $shift['klic_slotu']=$proposal['mapovani'][(int)$shift['id_smeny_smena']] ?? null;
        unset($shift);
    }
    cb_smeny_planovani_sloty_doplnit($plan,cb_smeny_sablony_pozice($plan));
}

/** Převádí jen pracovní verzi; původní publikované řádky zůstávají nedotčené. */
function cb_smeny_planovani_sloty_pripravit(mysqli $db, array $plan): void
{
    if($plan['pracovni_verze']===null) return;
    foreach($plan['sloty'] as $column) {
        if(!empty($plan['sloty_pripraveny']) && empty($column['navrzeny'])) continue;
        cb_smeny_planovani_sql($db,'INSERT INTO smeny_rozpis_slot (id_smeny_rozpis,verze,klic_slotu,datum,id_slot,poradi) VALUES (?,?,?,?,?,?)','iissii',[(int)$plan['id_smeny_rozpis'],(int)$plan['verze'],$column['klic_slotu'],$column['datum'],(int)$column['id_slot'],(int)$column['poradi']])->close();
    }
    if(empty($plan['sloty_pripraveny'])) foreach($plan['blocks'] as $shift) cb_smeny_planovani_sql($db,'UPDATE smeny_smena SET klic_slotu=? WHERE id_smeny_smena=? AND id_smeny_rozpis=? AND verze=?','siii',[$shift['klic_slotu'],(int)$shift['id_smeny_smena'],(int)$plan['id_smeny_rozpis'],(int)$plan['verze']])->close();
    cb_smeny_planovani_sql($db,'UPDATE smeny_rozpis SET sloty_pripraveny=1 WHERE id_smeny_rozpis=?','i',[(int)$plan['id_smeny_rozpis']])->close();
}

/** Novou směnu lze vložit jen do skutečného sloupce téhož dne, pozice a verze. */
function cb_smeny_planovani_slot_overit(array $plan, string $key, string $date, int $position): array
{
    foreach($plan['sloty'] as $column) if($column['klic_slotu']===$key && $column['datum']===$date && (int)$column['id_slot']===$position) return $column;
    throw new CbUserVisibleException('Vybraný sloupec už není v tomto pracovním plánu. Obnovte stránku.');
}

/** Otisk zahrnuje osoby i časy, aby nepotvrdil obsazení změněné v jiné kartě. */
function cb_smeny_planovani_slot_obsazeni_otisk(array $shifts): string
{
    $rows=[];
    foreach($shifts as $shift) $rows[]=[(int)$shift['id_smeny_smena'],(int)$shift['id_person'],(string)$shift['zacatek'],(string)$shift['konec']];
    usort($rows,static fn(array $a,array $b):int=>$a[0]<=>$b[0]);
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}

/** Odebrání vyžaduje potvrzení přesného obsazení a zamkne dotčené osoby ve stabilním pořadí. */
function cb_smeny_planovani_slot_zmenit(mysqli $db, array $plan, array $branch, array $week, array $post, int $person): void
{
    if($plan['stav']!=='rozpracovany') throw new CbUserVisibleException('Nejprve otevřete týden k úpravám.');
    $date=(string)($post['datum'] ?? ''); $position=(int)($post['id_slot'] ?? 0);
    if(!in_array($date,array_column($week['days'],'date'),true) || !isset(cb_smeny_sablony_pozice($branch)[$position])) throw new CbUserVisibleException('Den nebo pozice nepatří do plánovaného týdne.');
    if(($post['action'] ?? '')==='smeny_plan_slot_pridat') {
        $same=array_values(array_filter($plan['sloty'],static fn(array $column):bool=>$column['datum']===$date && (int)$column['id_slot']===$position));
        if(count($same)>=50) throw new CbUserVisibleException('Pro jednu pozici lze přidat nejvýše 50 sloupců za den.');
        $key=bin2hex(random_bytes(16)); $order=1;
        foreach($same as $column) $order=max($order,(int)$column['poradi']+1);
        cb_smeny_planovani_sql($db,'INSERT INTO smeny_rozpis_slot (id_smeny_rozpis,verze,klic_slotu,datum,id_slot,poradi) VALUES (?,?,?,?,?,?)','iissii',[(int)$plan['id_smeny_rozpis'],(int)$plan['verze'],$key,$date,$position,$order])->close();
        cb_smeny_audit_zapis($db,$person,'pridany_slot','rozpis',(int)$plan['id_smeny_rozpis'],null,['klic_slotu'=>$key,'datum'=>$date,'id_slot'=>$position]);
        return;
    }
    $key=(string)($post['klic_slotu'] ?? '');
    $column=cb_smeny_planovani_slot_overit($plan,$key,$date,$position);
    $same=array_filter($plan['sloty'],static fn(array $item):bool=>$item['datum']===$date && (int)$item['id_slot']===$position);
    if(count($same)<=1 && in_array($position,cb_smeny_sloty_zakladni_id($branch),true)) throw new CbUserVisibleException('Pro základní pracovní pozici musí v daném dni zůstat alespoň jeden sloupec.');
    $shifts=array_values(array_filter($plan['blocks'],static fn(array $shift):bool=>($shift['klic_slotu'] ?? '')===$key));
    $ids=array_map(static fn(array $shift):int=>(int)$shift['id_smeny_smena'],$shifts); sort($ids);
    // Potvrzení vážeme k načtenému obsazení, aby neodstranilo směnu přidanou mezitím jinou kartou.
    if($shifts!==[] && (($post['potvrzeno'] ?? '')!=='1' || !hash_equals(cb_smeny_planovani_slot_obsazeni_otisk($shifts),(string)($post['obsazeni_otisk'] ?? '')))) throw new CbUserVisibleException('Obsazení sloupce se změnilo nebo nebylo potvrzeno jeho odebrání. Obnovte stránku.');
    $people=array_values(array_unique(array_map(static fn(array $shift):int=>(int)$shift['id_person'],$shifts)));sort($people);
    foreach($people as $id) cb_smeny_planovani_sql($db,'SELECT id_person FROM hr_person WHERE id_person=? FOR UPDATE','i',[$id])->close();
    foreach($ids as $id) cb_smeny_planovani_odebrat($db,$plan,$id,$person);
    cb_smeny_planovani_sql($db,'DELETE FROM smeny_rozpis_slot WHERE id_smeny_rozpis=? AND verze=? AND klic_slotu=?','iis',[(int)$plan['id_smeny_rozpis'],(int)$plan['verze'],$key])->close();
    cb_smeny_audit_zapis($db,$person,'odebrany_slot','rozpis',(int)$plan['id_smeny_rozpis'],['slot'=>$column,'smeny'=>$shifts],null);
}
