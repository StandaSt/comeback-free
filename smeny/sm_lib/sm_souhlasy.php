<?php
declare(strict_types=1);
/* Účel: Vyhodnocuje souhlasy nad přesnými verzemi směn a uchovává okamžik prvního definitivního schválení. Využívá existující oznámení a audit. */

/** Čtecí kontrola připravené migrace nikdy sama neupravuje schéma ani data. */
function cb_smeny_souhlasy_schema(mysqli $db): bool
{
    static $ready=[];
    $key=spl_object_id($db);
    if(isset($ready[$key]))return $ready[$key];
    $result=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('smeny_rozpis','smeny_oznameni')");
    $columns=[];
    while($row=$result->fetch_assoc())$columns[$row['TABLE_NAME'].'.'.$row['COLUMN_NAME']]=true;
    $result->close();
    foreach(['smeny_rozpis.definitivni_od','smeny_rozpis.definitivni_verze','smeny_oznameni.vyhrady_dny','smeny_oznameni.vyjadreno','smeny_oznameni.nahrazeno'] as $column) {
        if(!isset($columns[$column]))return $ready[$key]=false;
    }
    return $ready[$key]=true;
}

/** Datum provozního dne počítáme stejně jako veřejný rozpis, v místním čase i přes změnu letního času. */
function cb_smeny_souhlasy_den(array $shift): string
{
    $start=new DateTimeImmutable($shift['zacatek'],new DateTimeZone('Europe/Prague'));
    return ((int)$start->format('H')<6?$start->modify('-1 day'):$start)->format('Y-m-d');
}

/** Každá výhrada musí mířit na skutečnou směnu v neměnném týdenním oznámení. */
function cb_smeny_souhlasy_vyhrady(array $content,array $days): array
{
    $allowed=array_unique(array_map('cb_smeny_souhlasy_den',$content['smeny']??[]));
    foreach($days as $day)if(!is_string($day) || !in_array($day,$allowed,true))throw new CbUserVisibleException('Výhradu lze označit jen u dne s vaší naplánovanou směnou.');
    $days=array_values(array_unique($days));sort($days);
    if($days===[])throw new CbUserVisibleException('Označte alespoň jeden den, ke kterému máte výhrady.');
    return $days;
}

/** Pozdější oznámení přepisuje stav jen dotčené směny, souhlas platí pouze pro totožný obsah. */
function cb_smeny_souhlasy_stavy(array $shifts,array $notices): array
{
    usort($notices,static fn(array $a,array $b):int=>(int)$a['id_smeny_oznameni']<=>(int)$b['id_smeny_oznameni']);
    $known=[];
    foreach($notices as $notice) {
        if(($notice['nahrazeno']??null)!==null)continue;
        $content=json_decode($notice['obsah'],true,512,JSON_THROW_ON_ERROR);
        $days=json_decode($notice['vyhrady_dny']??'[]',true,512,JSON_THROW_ON_ERROR);
        $items=$notice['typ']==='tyden'?($content['smeny']??[]):(isset($content['po'])?[$content['po']]:[]);
        foreach($items as $shift) {
            $known[(int)$notice['id_person'].':'.$shift['klic_smeny']]=[
                'otisk'=>cb_smeny_publikace_otisk($shift),'potvrzeno'=>$notice['potvrzeno']!==null,
                'vyhrady'=>in_array(cb_smeny_souhlasy_den($shift),$days,true),
                'zmena'=>$notice['typ']!=='tyden','id_oznameni'=>(int)$notice['id_smeny_oznameni']];
        }
    }
    $result=[];
    foreach($shifts as $shift) {
        $state=$known[(int)$shift['id_person'].':'.$shift['klic_smeny']]??null;
        if($state===null || $state['otisk']!==cb_smeny_publikace_otisk($shift)) {
            $state=['potvrzeno'=>false,'vyhrady'=>false,'zmena'=>false,'id_oznameni'=>0];
        }
        $state['text']=$state['vyhrady']?'Má výhrady':($state['potvrzeno']?($state['zmena']?'Změna potvrzena':'Odsouhlaseno'):($state['zmena']?'Změna nepotvrzena':'Čeká na souhlas'));
        $result[(int)$shift['id_person'].':'.$shift['klic_smeny']]=$state;
    }
    return $result;
}

/** Načte oznámení jedné pobočky a týdne; volající rozhoduje o oprávnění zobrazit jejich obsah. */
function cb_smeny_souhlasy_oznameni(mysqli $db,int $plan): array
{
    $stmt=cb_smeny_planovani_sql($db,'SELECT * FROM smeny_oznameni WHERE id_smeny_rozpis=? ORDER BY id_smeny_oznameni','i',[$plan]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Čteným směnám doplní stav potvrzení; pracovní kopie tento veřejný stav nepoužívá. */
function cb_smeny_souhlasy_doplnit(mysqli $db,array &$rows): void
{
    if(!cb_smeny_souhlasy_schema($db))return;
    $ids=array_values(array_unique(array_filter(array_map(static fn(array $s):int=>(int)($s['id_smeny_smena']??0),$rows))));
    if($ids===[])return;
    $result=$db->query('SELECT s.*,r.definitivni_od FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE s.id_smeny_smena IN ('.implode(',',$ids).')');
    $shifts=$result->fetch_all(MYSQLI_ASSOC);$result->close();
    $plans=implode(',',array_unique(array_map('intval',array_column($shifts,'id_smeny_rozpis'))));
    $people=implode(',',array_unique(array_map('intval',array_column($shifts,'id_person'))));
    if($plans==='' || $people==='')return;
    $result=$db->query('SELECT * FROM smeny_oznameni WHERE id_smeny_rozpis IN ('.$plans.') AND id_person IN ('.$people.') ORDER BY id_smeny_oznameni');
    $notices=$result->fetch_all(MYSQLI_ASSOC);$result->close();
    // Klíč zahrnuje rozpis, protože nezávislé pobočky mají vlastní souhlasy.
    $byId=[];
    foreach(array_unique(array_column($shifts,'id_smeny_rozpis')) as $plan) {
        $planShifts=array_filter($shifts,static fn(array $s):bool=>$s['id_smeny_rozpis']===$plan);
        $states=cb_smeny_souhlasy_stavy($planShifts,array_filter($notices,static fn(array $n):bool=>$n['id_smeny_rozpis']===$plan));
        foreach($planShifts as $shift) {
            $state=$states[(int)$shift['id_person'].':'.$shift['klic_smeny']];
            $byId[(int)$shift['id_smeny_smena']]=($shift['definitivni_od']!==null?'Definitivní rozpis · ':'').$state['text'];
        }
    }
    foreach($rows as &$row)if(isset($byId[(int)($row['id_smeny_smena']??0)]))$row['souhlas_stav']=$byId[(int)$row['id_smeny_smena']];
    unset($row);
}

/** Volá se v transakci pod zámkem pobočky. První definitivní stav další jednotlivé změny neruší. */
function cb_smeny_souhlasy_definitivni(mysqli $db,int $id,int $actor): void
{
    $stmt=cb_smeny_planovani_sql($db,'SELECT * FROM smeny_rozpis WHERE id_smeny_rozpis=? FOR UPDATE','i',[$id]);
    $plan=$stmt->get_result()->fetch_assoc();$stmt->close();
    if($plan===null || $plan['zverejnena_verze']===null || $plan['definitivni_od']!==null)return;
    $stmt=cb_smeny_planovani_sql($db,'SELECT * FROM smeny_smena WHERE id_smeny_rozpis=? AND verze=?','ii',[$id,(int)$plan['zverejnena_verze']]);
    $shifts=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    foreach(cb_smeny_souhlasy_stavy($shifts,cb_smeny_souhlasy_oznameni($db,$id)) as $state) {
        if(!$state['potvrzeno'] || $state['vyhrady'])return;
    }
    cb_smeny_planovani_sql($db,'UPDATE smeny_rozpis SET definitivni_od=NOW(),definitivni_verze=zverejnena_verze WHERE id_smeny_rozpis=?','i',[$id])->close();
    cb_smeny_audit_zapis($db,$actor,'definitivni','rozpis',$id,null,['verze'=>(int)$plan['zverejnena_verze']]);
}
