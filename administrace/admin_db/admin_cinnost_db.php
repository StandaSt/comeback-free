<?php
declare(strict_types=1);
/* Účel: Pouze čte novou historii Helpdesku a její číselníky v rozsahu současného přístupu k tiketům. */
require_once __DIR__.'/../../helpdesk/hl_lib/hl_prava.php';
require_once __DIR__.'/../../common/lib/osoba_jmeno.php';

/** Všechny dotazy používají stejné podmínky firmy a viditelnosti; parametry se vážou. */
function cb_admin_cinnost_dotaz(mysqli $db, string $sql, array $values): array
{
    $stmt=$db->prepare($sql);
    try {
        if ($values!==[]) $stmt->bind_param(str_repeat('s',count($values)),...$values);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally { $stmt->close(); }
}

/** Právo historie samo nerozšiřuje přístup k soukromému tiketu ani k jiné firmě. */
function cb_admin_cinnost_zdroj(): array
{
    if (!isset($_SESSION['prava_stav'][111],$_SESSION['prava_stav'][600]) || !cb_pravo_ma(111) || !cb_pravo_ma(600)) {
        throw new CbUserVisibleException('Nemáte oprávnění zobrazit historii Helpdesku.');
    }
    $scope=cb_helpdesk_visible_scope(cb_helpdesk_current_user_id());
    $where='c.id_firma=? AND (c.cil_id IS NULL OR (c.id_cil_typ=8 AND h.id_helpdesk IS NOT NULL
        AND COALESCE(h.id_firma,1)=? AND '.cb_helpdesk_allowed_area_condition('h').' AND '.$scope['sql'].'))';
    $company=cb_helpdesk_current_company_id();
    return ['from'=>'cinnost_helpdesk c LEFT JOIN helpdesk h ON c.id_cil_typ=8 AND h.id_helpdesk=c.cil_id',
        'where'=>$where,'params'=>[$company,$company,...$scope['params']]];
}

/** Filtry data přepočítají z českého dne do UTC ještě před porovnáním indexovaného času. */
function cb_admin_cinnost_podminky(array $source, array $f): array
{
    $where=[$source['where']]; $params=$source['params'];
    foreach (['od'=>'>=','do'=>'<'] as $key=>$operator) {
        if ($f[$key]==='') continue;
        $date=new DateTimeImmutable($f[$key],new DateTimeZone('Europe/Prague'));
        if ($key==='do') $date=$date->modify('+1 day');
        $where[]='c.cas_udalosti'.$operator.'?';
        $params[]=$date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
    foreach (['autor'=>'id_user','akce'=>'id_akce','tiket'=>'cil_id','stav'=>'id_stav'] as $key=>$column) {
        if ($f[$key]>0) { $where[]='c.'.$column.'=?'; $params[]=$f[$key]; }
    }
    return ['sql'=>implode(' AND ',$where),'params'=>$params];
}

/** Počet i seznam vyhodnotí filtry před stránkováním; detail je součástí téhož autorizovaného řádku. */
function cb_admin_cinnost_nacist(mysqli $db, array &$f): array
{
    $source=cb_admin_cinnost_zdroj(); $w=cb_admin_cinnost_podminky($source,$f);
    $total=(int)cb_admin_cinnost_dotaz($db,'SELECT COUNT(*) n FROM '.$source['from'].' WHERE '.$w['sql'],$w['params'])[0]['n'];
    $pages=max(1,(int)ceil($total/$f['pocet'])); $f['strana']=min($f['strana'],$pages);
    $offset=($f['strana']-1)*$f['pocet']; $order=$f['smer']==='asc'?'ASC':'DESC';
    $rows=cb_admin_cinnost_dotaz($db,'SELECT c.*,a.nazev akce_nazev,a.sablona,s.nazev stranka_nazev,
        p.nazev pole_nazev,p.pravidla_json pole_pravidla,p.hodnoty_povoleny,st.nazev stav_nazev,h.predmet,
        '.cb_osoba_jmeno_sql('hp.id_person',true).' autor_nazev
        FROM '.$source['from'].' JOIN cis_cinnost_akce a ON a.id_akce=c.id_akce
        JOIN cis_cinnost_stav st ON st.id_stav=c.id_stav
        LEFT JOIN cis_cinnost_stranka s ON s.id_stranka=c.id_stranka
        LEFT JOIN cis_cinnost_pole p ON p.id_pole=c.id_pole
        LEFT JOIN hr_person hp ON hp.id_user=c.id_user
        WHERE '.$w['sql'].' ORDER BY c.cas_udalosti '.$order.',c.id_cinnost '.$order.' LIMIT '.$f['pocet'].' OFFSET '.$offset,$w['params']);
    $authors=cb_admin_cinnost_dotaz($db,'SELECT DISTINCT c.id_user,'.cb_osoba_jmeno_sql('hp.id_person',true).' nazev
        FROM '.$source['from'].' LEFT JOIN hr_person hp ON hp.id_user=c.id_user WHERE '.$source['where'].' ORDER BY nazev,c.id_user',$source['params']);
    $actions=cb_admin_cinnost_dotaz($db,'SELECT DISTINCT a.id_akce,a.nazev FROM '.$source['from'].' JOIN cis_cinnost_akce a ON a.id_akce=c.id_akce
        WHERE '.$source['where'].' ORDER BY a.nazev,a.id_akce',$source['params']);
    return ['rows'=>$rows,'total'=>$total,'pages'=>$pages,'authors'=>$authors,'actions'=>$actions,
        'states'=>$db->query('SELECT id_stav,nazev FROM cis_cinnost_stav ORDER BY id_stav')->fetch_all(MYSQLI_ASSOC)];
}
