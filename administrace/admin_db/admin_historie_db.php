<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
/* Účel: Čte filtrované události a nabídky historie; všechny zdroje prochází omezeným obdobím a stránkuje na serveru. */
require_once __DIR__.'/admin_historie_zdroje.php';

/** Podmínky se aplikují ještě před omezením každého zdroje; pořadí je stabilní i při shodných časech. */
function cb_historie_cti(mysqli $db, array $sources, array $filters, ?array $detail = null): array
{
    $unions = []; $params = [];
    $offset = ($filters['strana']-1)*$filters['pocet'];
    foreach ($sources as $key=>$s) {
        if ($detail && $detail['zdroj'] !== $key) continue;
        if (!$detail && $filters['zdroj'] !== '' && $filters['zdroj'] !== $key) continue;
        if (!$detail && $filters['zdroj'] === '' && $filters['druh'] === 'vecne' && !empty($s['technical'])) continue;
        $where = ['('.$s['where'].')'];
        if ($detail) { $where[] = $s['pk'].'=?'; $params[] = $detail['id']; }
        else {
            $where[] = $s['time'].'>=? AND '.$s['time'].'<?'; array_push($params, $filters['od'], $filters['konec']);
            if ($filters['druh'] === 'chyby') $where[] = $s['result'].'=0';
            if ($filters['druh'] === 'vecne' && $key === 'akce') {
                $where[] = "t.id_user_akce_typ NOT IN (1,2,3,4,5,6,7,8,17,18,19) AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.detail_json,'$.puvod')),'')<>'prohlizec'";
            }
            foreach (['autor'=>'actor','osoba'=>'person','pobocka'=>'branch','modul'=>'module'] as $filter=>$column) {
                if ($filters[$filter] > 0) { $where[] = $s[$column].'=?'; $params[] = $filters[$filter]; }
            }
            if ($filters['datum'] !== '') { $where[] = $s['day'].'=?'; $params[] = $filters['datum']; }
        }
        $columns = [];
        foreach (['pk'=>'id','time'=>'cas','actor'=>'autor','module'=>'modul','action'=>'akce','person'=>'osoba','branch'=>'pobocka','day'=>'datum','object'=>'objekt','object_id'=>'objekt_id','result'=>'vysledek','type'=>'typ','context'=>'kontext'] as $field=>$alias) $columns[] = $s[$field].' AS '.$alias;
        $take = $detail ? 1 : $offset+$filters['pocet']+1;
        $unions[] = "(SELECT '{$key}' AS zdroj,".implode(',', $columns).' FROM '.$s['from'].' WHERE '.implode(' AND ', $where)." ORDER BY cas DESC,id DESC LIMIT {$take})";
    }
    if ($unions === []) return [];
    $sql = 'SELECT * FROM ('.implode(' UNION ALL ', $unions).') udalosti ORDER BY cas DESC,zdroj,id DESC LIMIT '.($detail ? 1 : $filters['pocet']+1).' OFFSET '.($detail ? 0 : $offset);
    $stmt = $db->prepare($sql);
    if ($params !== []) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return $rows;
}

/** Jména autorů a cílových osob se čtou odděleně; osoba bez účtu může být cílem historie. */
function cb_historie_jmena(mysqli $db, array $scope): array
{
    $rows = $db->query("SELECT hp.id_person,hp.id_user," . cb_osoba_jmeno_sql('ou.id_person',true) . " AS jmeno,({$scope['hr']}) AS hr_povoleno
        FROM hr_person hp LEFT JOIN " . cb_osoby_zdroj_sql() . " ou ON ou.id_person=hp.id_person
        ORDER BY jmeno,hp.id_person")->fetch_all(MYSQLI_ASSOC);
    $out = ['autori'=>[], 'osoby'=>[], 'jmena'=>[]];
    foreach ($rows as $r) {
        $name = trim((string)$r['jmeno']) ?: 'Osoba #'.$r['id_person'];
        if ((int)$r['id_user'] > 0) $out['autori'][(int)$r['id_user']] = $name;
        if ((int)$r['hr_povoleno'] === 1) { $out['osoby'][(int)$r['id_person']] = $name; $out['jmena'][(int)$r['id_person']] = $name; }
    }
    return $out;
}
