<?php
// Připravuje pouze čtením osobní štítky hlavičky v rozsahu modulů, firem a poboček uživatele.
declare(strict_types=1);
require_once __DIR__ . '/../db/db_user_pobocka.php';
require_once __DIR__ . '/smeny_verejny_zdroj.php';
require_once __DIR__ . '/../../helpdesk/hl_lib/hl_prava.php';
require_once __DIR__ . '/../../hr/hr_lib/hr_vd_stavy.php';
require_once __DIR__ . '/../../hr/hr_db/hr_zamestnanci_pristup.php';

/** Jednotný datový tvar; tabulky obsahují jen text, nikoli HTML z databáze. */
function cb_hlavicka_stitek(string $text, string $tooltip, array $table = []): array
{
    return ['text'=>$text, 'tooltip'=>$tooltip, 'table'=>$table];
}

/** Počítá zprávy ostatních na vlastních tiketech odděleně od dosud neotevřených nových tiketů. */
function cb_hlavicka_helpdesk(mysqli $db, int $user): array
{
    $scope=cb_helpdesk_visible_scope($user);
    $sql="SELECT
        SUM(CASE WHEN h.stav='nový' AND hr.id_helpdesk_read IS NULL THEN 1 ELSE 0 END) fresh,
        COALESCE(SUM(CASE WHEN h.id_user_zalozil=? THEN
            (SELECT COUNT(*) FROM helpdesk_zprava z WHERE z.id_helpdesk=h.id_helpdesk
             AND z.id_user<>? AND z.systemova=0 AND (hr.precteno IS NULL OR z.vytvoreno>hr.precteno))
             ELSE 0 END),0) replies
        FROM helpdesk h LEFT JOIN helpdesk_read hr ON hr.id_helpdesk=h.id_helpdesk AND hr.id_user=?
        WHERE ".cb_helpdesk_allowed_area_condition()." AND COALESCE(h.id_firma,1)=".cb_helpdesk_current_company_id()." AND ".$scope['sql'];
    $stmt=$db->prepare($sql);
    $params=[$user,$user,$user,...$scope['params']];
    $stmt->bind_param('iii'.$scope['types'],...$params);
    $stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
    $tags=[];$fresh=(int)$row['fresh'];$replies=(int)$row['replies'];
    if($fresh>0)$tags[]=cb_hlavicka_stitek($fresh.' '.($fresh===1?'nový':($fresh<5?'nové':'nových')),
        'Nové tikety ve stavu Nový, které máš oprávnění vidět a dosud jsi je neotevřel.');
    if($replies>0)$tags[]=cb_hlavicka_stitek($replies.' '.($replies===1?'odpověď':($replies<5?'odpovědi':'odpovědí')),
        'Počet nepřečtených nesystémových zpráv ostatních na tvoje vlastní tikety. Vlastní zprávy se nepočítají.');
    return $tags;
}

/** Stáří odvozuje z úspěšných online importů bez chyb; přerušený běh nesmí omladit data. */
function cb_hlavicka_provoz(mysqli $db, array $branches): array
{
    if($branches===[])return [];
    $ids=implode(',',array_map('intval',array_column($branches,'id_pob')));
    $rows=$db->query("SELECT id_pob,MAX(CASE WHEN stav='ok' AND COALESCE(pocet_chyb,0)=0 THEN dokonceno END) finished
        FROM obj_import WHERE typ_importu='online' AND id_pob IN ($ids) GROUP BY id_pob")->fetch_all(MYSQLI_ASSOC);
    if($rows===[])return [cb_hlavicka_stitek('Data —','Pro tvoje pobočky není zaznamenán úspěšný online import objednávek.')];
    $oldest=null;
    foreach($rows as $row){
        if(empty($row['finished']))return [cb_hlavicka_stitek('Data —','U některé z tvých poboček chybí úspěšný online import objednávek bez chyb.')];
        $ts=(new DateTimeImmutable($row['finished'],new DateTimeZone('Europe/Prague')))->getTimestamp();
        $oldest=$oldest===null?$ts:min($oldest,$ts);
    }
    $tag=cb_hlavicka_stitek('Data …','Stáří nejstaršího z posledních úspěšných online importů objednávek bez chyb v pobočkách, které můžeš vidět. Pobočky bez online importů se nezapočítávají.');
    $tag['updated_at']=$oldest;
    return [$tag];
}

/** Vedoucí vidí obsazení restaurací podle příjmení a slotu; zaměstnanci pouze vlastní budoucí směnu. */
function cb_hlavicka_smeny(mysqli $db, int $user, array $branches, DateTimeImmutable $now): array
{
    $ids=implode(',',array_map('intval',array_column($branches,'id_pob')));
    if($ids==='')return [];
    $source=cb_smeny_verejny_zdroj_sql();
    $management=false;
    foreach([1,2,3,4,5] as $role)$management=$management||cb_user_ma_roli($role);
    $manager=$management && cb_pravo_ma(403);
    $time=$now->format('Y-m-d H:i:s');
    if($manager){
        // Levé spojení ponechá i neobsazené restaurace; Výrobu určuje stabilní kód pobočky.
        // Příjmení bereme z posledního platného osobního záznamu, pozici ze skutečného slotu směny.
        $stmt=$db->prepare("SELECT b.id_pob,b.nazev,team.id_person,team.id_slot,team.prijmeni
            FROM pobocka b LEFT JOIN (
                SELECT s.id_pob,s.id_person,s.id_slot,ou.prijmeni
                FROM ($source) s JOIN hr_person p ON p.id_person=s.id_person
                LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(
                    SELECT MAX(o.id_osobni_udaje) FROM hr_osobni_udaje o WHERE o.id_person=p.id_person AND o.platny=1)
                WHERE s.id_pob IN ($ids) AND p.aktivni=1 AND p.testovaci=0 AND s.zacatek<=? AND s.konec>?
            ) team ON team.id_pob=b.id_pob
            WHERE b.id_pob IN ($ids) AND b.kod<>'Vyroba'
            ORDER BY b.id_pob,team.prijmeni,team.id_person");
        $stmt->bind_param('ss',$time,$time);$stmt->execute();$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
        if($rows===[])return [];
        $people=[];$byBranch=[];
        foreach($rows as $row){
            $branch=(int)$row['id_pob'];
            $byBranch[$branch]??=['name'=>$row['nazev'],1=>[],2=>[]];
            $person=(int)($row['id_person']??0);
            if($person<=0)continue;
            $people[$person]=true;
            $slot=(int)$row['id_slot'];
            if(in_array($slot,[1,2],true))$byBranch[$branch][$slot][$person]=trim((string)$row['prijmeni'])?:'—';
        }
        $table=[['Pobočka','Pizzař','Kurýr']];
        foreach($byBranch as $branch)$table[]=[$branch['name'],implode("\n",$branch[1])?:'—',implode("\n",$branch[2])?:'—'];
        return [cb_hlavicka_stitek('Ve směně '.count($people).' '.(count($people)===1?'člověk':(count($people)<5&&count($people)>0?'lidé':'lidí')),
            'Právě probíhající zveřejněné směny v povolených restauracích, bez Výroby. Tabulka uvádí příjmení pizzařů a kurýrů; počet zahrnuje všechny pozice. Jde o rozpis, nikoli potvrzenou docházku. Stav k '.$now->format('j. n. Y H:i:s').'.',$table)];
    }
    $stmt=$db->prepare("SELECT s.zacatek,s.konec,b.nazev FROM ($source) s JOIN pobocka b ON b.id_pob=s.id_pob
        WHERE s.id_pob IN ($ids) AND s.id_user=? AND s.zacatek>? ORDER BY s.zacatek,s.id_pob LIMIT 1");
    $stmt->bind_param('is',$user,$time);$stmt->execute();$shift=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$shift)return [cb_hlavicka_stitek('Žádná další směna','Ve zveřejněném rozpisu nemáš další směnu, která ještě nezačala. Probíhající směna se zde nezobrazuje.')];
    $start=new DateTimeImmutable($shift['zacatek']);$end=new DateTimeImmutable($shift['konec']);
    $day=$start->format('Y-m-d')===$now->format('Y-m-d')?'Dnes':($start->format('Y-m-d')===$now->modify('+1 day')->format('Y-m-d')?'Zítra':$start->format('j. n.'));
    return [cb_hlavicka_stitek($day.' '.$start->format('H:i').' · '.$shift['nazev'],
        'Tvoje nejbližší zveřejněná směna, která ještě nezačala: '.$shift['nazev'].', '.$start->format('j. n. Y H:i').' – '.$end->format('j. n. Y H:i').'.')];
}

/** Nepřečtení VD vychází z existující osobní historie Otevření VD, bez nového zápisu. */
function cb_hlavicka_hr(mysqli $db, int $user): array
{
    $firms=cb_firemni_pristup_firmy($db,$user);
    if($firms===[])return [];
    $ids=implode(',',array_map('intval',$firms));
    $stmt=$db->prepare("SELECT COUNT(*) cnt FROM hr_vd vd WHERE vd.aktivni=1 AND vd.id_person IS NULL
        AND vd.id_vd_stav=".HR_VD_STAV_NOVY." AND vd.id_firma IN ($ids)
        AND NOT EXISTS (SELECT 1 FROM hr_vd_akce a JOIN hr_cis_vd_akce_vysledek v ON v.id_vd_akce_vysledek=a.id_vd_akce_vysledek
            WHERE a.id_vd=vd.id_vd AND a.id_user_zadal=? AND v.id_vd_akce_typ=2)");
    $stmt->bind_param('i',$user);$stmt->execute();$count=(int)$stmt->get_result()->fetch_assoc()['cnt'];$stmt->close();
    $tags=$count>0?[cb_hlavicka_stitek($count.' '.($count===1?'nový VD':($count<5?'nové VD':'nových VD')),
        'Aktivní vstupní dotazníky ve stavu Nový z povolených firem, jejichž detail jsi dosud neotevřel.')]:[];
    // K řešení znamená aktivní neověřené zaměstnance ve stejném rozsahu jako seznam HR.
    if(cb_pravo_ma(306)){
        $scope=hr_zamestnanci_pobocka_sql($db,$user);
        $unverified=(int)$db->query("SELECT COUNT(*) cnt FROM hr_person p WHERE p.aktivni=1 AND p.overen=0 AND ($scope)")->fetch_assoc()['cnt'];
        if($unverified>0)$tags[]=cb_hlavicka_stitek($unverified.' k řešení','Aktivní neověření zaměstnanci, které máš oprávnění zobrazit v seznamu HR. Testovací osoby se nepočítají.');
    }
    return $tags;
}

/** Každý modul má vlastní kontrolu oprávnění; chybný zdroj se skryje a chyba se předá společnému logování. */
function cb_hlavicka_stitky_data(mysqli $db): array
{
    $user=(int)($_SESSION['cb_user']['id_user']??0);
    if(empty($_SESSION['login_ok'])||$user<=0)throw new RuntimeException('Nutné přihlášení.');
    $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
    $out=['server_ts'=>$now->getTimestamp(),'modules'=>[]];
    $branches=cb_db_user_pobocky($db,$user);
    foreach(['provoz','hr','smeny','helpdesk'] as $module){
        if(!cb_modul_ma_pristup($module))continue;
        try{
            $out['modules'][$module]=match($module){
                'provoz'=>cb_hlavicka_provoz($db,$branches),
                'hr'=>cb_hlavicka_hr($db,$user),
                'smeny'=>cb_hlavicka_smeny($db,$user,$branches,$now),
                'helpdesk'=>cb_hlavicka_helpdesk($db,$user),
            };
        }catch(Throwable $e){
            $out['modules'][$module]=[];
            cb_chyba_oznam($e,['module'=>strtoupper($module),'action'=>'Načtení štítků hlavičky']);
        }
    }
    return $out;
}
