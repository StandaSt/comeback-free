<?php
declare(strict_types=1);
/* Účel: Připraví dokončené týdny a samostatný výběr pobočky pro stránku Historie směn. */

require_once __DIR__.'/../../common/lib/smeny_verejny_zdroj.php';

/** Historie nabízí posledních 26 dokončených týdnů; běžné přehledy tím nedostávají volný výběr data. */
function cb_smeny_historie_tydny(?DateTimeImmutable $now=null): array
{
    $zone=new DateTimeZone('Europe/Prague');
    $now=($now??new DateTimeImmutable('now',$zone))->setTimezone($zone);
    $lastMonday=$now->modify('monday this week')->setTime(0,0)->modify('-7 days');
    $names=['Pondělí','Úterý','Středa','Čtvrtek','Pátek','Sobota','Neděle'];
    $weeks=[];
    for($index=0;$index<26;$index++) {
        $start=$lastMonday->modify('-'.$index.' weeks');$days=[];
        foreach($names as $day=>$name) {
            $date=$start->modify('+'.$day.' days');
            $days[]=['name'=>$name,'date'=>$date->format('Y-m-d'),'date_label'=>$date->format('j. n. Y')];
        }
        $weeks[]=['index'=>$index,'start'=>$start,'start_day'=>$start->format('Y-m-d'),'end'=>$start->modify('+6 days'),'days'=>$days];
    }
    return $weeks;
}

/** Neplatný index bezpečně vrací nejnovější dokončený týden. */
function cb_smeny_historie_tyden(array $weeks, array $query): array
{
    $index=filter_var($query['week']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>count($weeks)-1]]);
    return $weeks[$index===false?0:$index];
}

/** Vyšší vedení začíná nad rolí Vedoucí pobočky, do které patří Restaurant manager. */
function cb_smeny_historie_vidi_vsechny_pobocky(mysqli $db,int $idPerson): bool
{
    if($idPerson<=0)return false;
    $stmt=$db->prepare('SELECT MIN(id_role) id_role FROM hr_pristupovy_profil WHERE id_person=?');
    $stmt->bind_param('i',$idPerson);$stmt->execute();
    $role=(int)($stmt->get_result()->fetch_assoc()['id_role']??0);$stmt->close();
    return $role>0 && $role<5;
}

/** Vrátí aktivní pobočky firem, které smí vyšší vedení spravovat. */
function cb_smeny_historie_vsechny_pobocky(mysqli $db,int $idPerson): array
{
    $firmy=cb_firemni_pristup_firmy($db,$idPerson);
    if($firmy===[])return [];
    $ids=implode(',',array_map('intval',$firmy));
    $result=$db->query('SELECT id_pob,id_firma,kod,nazev FROM pobocka WHERE aktivni=1 AND id_firma IN ('.$ids.') ORDER BY id_pob');
    $rows=[];while($row=$result->fetch_assoc()){$row['id_pob']=(int)$row['id_pob'];$row['id_firma']=(int)$row['id_firma'];$rows[(int)$row['id_pob']]=$row;}$result->free();
    return $rows;
}

/** Běžnému uživateli nabídne jen povolené pobočky, kde měl ve zvoleném týdnu směnu. */
function cb_smeny_historie_osobni_pobocky(mysqli $db,string $week,int $idPerson): array
{
    if($idPerson<=0)return [];
    $stmt=$db->prepare('SELECT p.id_pob FROM hr_person osoba JOIN pobocka p ON p.id_firma=osoba.id_firma AND p.aktivni=1 LEFT JOIN hr_pracoviste prac ON prac.id_person=osoba.id_person AND prac.id_pob=p.id_pob AND prac.platny=1 AND (prac.platnost_od IS NULL OR prac.platnost_od<=CURDATE()) AND (prac.platnost_do IS NULL OR prac.platnost_do>=CURDATE()) WHERE osoba.id_person=? AND (prac.id_pob IS NOT NULL OR osoba.pristup_vsechny_pobocky=1) GROUP BY p.id_pob');
    $stmt->bind_param('i',$idPerson);$stmt->execute();$result=$stmt->get_result();$allowed=[];
    while($row=$result->fetch_assoc()){$allowed[(int)$row['id_pob']]=true;}$stmt->close();
    if($allowed===[])return [];
    $stmt=$db->prepare('SELECT DISTINCT v.id_pob FROM ('.cb_smeny_verejny_zdroj_sql().') v WHERE v.start_day=? AND v.id_person=?');
    $stmt->bind_param('si',$week,$idPerson);$stmt->execute();$result=$stmt->get_result();$worked=[];
    while($row=$result->fetch_assoc()){$id=(int)$row['id_pob'];if(isset($allowed[$id]))$worked[$id]=true;}$stmt->close();
    if($worked===[])return [];
    $ids=implode(',',array_map('intval',array_keys($worked)));
    $result=$db->query('SELECT id_pob,id_firma,kod,nazev FROM pobocka WHERE aktivni=1 AND id_pob IN ('.$ids.') ORDER BY id_pob');
    $rows=[];while($row=$result->fetch_assoc()){$row['id_pob']=(int)$row['id_pob'];$row['id_firma']=(int)$row['id_firma'];$rows[(int)$row['id_pob']]=$row;}$result->free();
    return $rows;
}

/** Hlavní pobočka slouží jen jako výchozí volba; při chybějícím záznamu se použije první dostupná. */
function cb_smeny_historie_hlavni_pobocka(mysqli $db,int $idPerson): int
{
    if($idPerson<=0)return 0;
    $stmt=$db->prepare('SELECT id_pob FROM hr_pracoviste WHERE id_person=? AND hlavni=1 AND platny=1 AND (platnost_od IS NULL OR platnost_od<=CURDATE()) AND (platnost_do IS NULL OR platnost_do>=CURDATE()) ORDER BY id_pracoviste DESC LIMIT 1');
    $stmt->bind_param('i',$idPerson);$stmt->execute();$id=(int)($stmt->get_result()->fetch_assoc()['id_pob']??0);$stmt->close();return $id;
}

/** Sestaví místní výběr historie, který je nezávislý na globální pobočce v hlavičce. */
function cb_smeny_historie_kontext(mysqli $db,string $week,array $query): array
{
    $idPerson=cb_smeny_sablony_id_person($db);
    $all=cb_smeny_historie_vidi_vsechny_pobocky($db,$idPerson);
    $branches=$all?cb_smeny_historie_vsechny_pobocky($db,$idPerson):cb_smeny_historie_osobni_pobocky($db,$week,$idPerson);
    $requested=filter_var($query['branch']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $main=cb_smeny_historie_hlavni_pobocka($db,$idPerson);
    $selected=$requested!==false && isset($branches[(int)$requested])?(int)$requested:(isset($branches[$main])?$main:(int)(array_key_first($branches)??0));
    return ['id_person'=>$idPerson,'vsechny_pobocky'=>$all,'pobocky'=>$branches,'id_pob'=>$selected];
}
