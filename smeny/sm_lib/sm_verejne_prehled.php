<?php
declare(strict_types=1);
/* Účel: Sestaví bezpečný denní přehled zveřejněných směn, viditelné stavy akceptace, nabídku poboček a navigaci; načtení souboru neprovádí dotazy. */

/** Krátký viditelný stav zachová výhrady i změnu; chybějící ověření nikdy nevydává za souhlas. */
function cb_smeny_verejne_stav(array $row): array
{
    $state=$row['souhlas']??null;
    if (!is_array($state)) return ['label'=>'Neověřeno','kind'=>'unknown','detail'=>'Stav akceptace není k dispozici.'];
    $detail=(string)($row['souhlas_stav']??$state['text']??'');
    if (!empty($state['vyhrady'])) return ['label'=>'Výhrady','kind'=>'objection','detail'=>$detail];
    if (!empty($state['potvrzeno'])) return ['label'=>'Přijato','kind'=>'accepted','detail'=>$detail];
    return ['label'=>!empty($state['zmena'])?'Změna čeká':'Čeká','kind'=>'pending','detail'=>$detail];
}

/** Provozní pracovník čte cizí směny pouze na MAIN; vedení zachovává společný oprávněný rozsah. */
function cb_smeny_verejne_rozsah(mysqli $db): array
{
    if (!cb_smeny_planovani_vidi()) return [];
    $branches=cb_smeny_sablony_pobocky($db);
    $person=cb_smeny_planovani_rozsah_osoba($db);
    $function=cb_hr_funkce_osoby($db,$person);
    if (($function['zarazeni']??'')==='provozni') {
        $branches=array_intersect_key($branches,array_flip(cb_hr_main_ids($db,$person)));
    }
    return $branches;
}

/** Vlastní řádky mimo povolené rozpisy přidají jen vlastní osobu, nikdy kolegy z další pobočky. */
function cb_smeny_verejne_prehled(array $rows, array $ownRows, array $branches, array $query, int $person, bool $forceOwn, array $ownBranches=[]): array
{
    $filters=cb_smeny_verejne_filtry_hodnoty($query);
    $own=$forceOwn || $filters['moje'];
    // Druhá kontrola chrání přehled i při změně nebo chybném použití jeho datového zdroje.
    $ownRows=$person>0?array_values(array_filter($ownRows,static fn(array $r):bool=>(int)$r['id_person']===$person)):[];
    $rows=array_values(array_filter($rows,static fn(array $r):bool=>isset($branches[(int)$r['id_pob']])));
    $options=[];
    // Názvy dalších vlastních poboček smí dodat pouze čtecí zdroj navázaný na přihlášenou osobu.
    if ($person>0) $options=$ownBranches;
    foreach ($branches as $id=>$branch) $options[(int)$id]=(string)$branch['nazev'];
    foreach ($ownRows as $row) $options[(int)$row['id_pob']]=(string)$row['pobocka'];
    natcasesort($options);
    $filters['moje']=$own;
    if ($own) $filters['jmeno']='';
    // Neplatná pobočka z URL nedokáže rozšířit zdroj. Jedinou pobočku vybereme přímo.
    if (!isset($options[$filters['pobocka']])) $filters['pobocka']=0;
    if (count($options)===1) $filters['pobocka']=(int)array_key_first($options);
    $branch=$filters['pobocka'];
    $ids=array_keys($options);
    $requested=is_scalar($query['sm_zob_pob']??null)?(int)$query['sm_zob_pob']:0;
    $current=$branch>0?$branch:(isset($options[$requested])?$requested:(int)($ids[0]??0));
    $browse=!$own && $branch===0 && count($ids)>1;
    $index=array_search($current,$ids,true);
    $previous=$browse && $index>0?$ids[$index-1]:0;
    $next=$browse && $index!==false && $index<count($ids)-1?$ids[$index+1]:0;
    $source=$own?$ownRows:array_merge($rows,array_values(array_filter($ownRows,static fn(array $r):bool=>!isset($branches[(int)$r['id_pob']]))));
    $slots=cb_smeny_verejne_moznosti($source,'id_slot','slot');
    $applied=$filters;
    if (!$own) $applied['pobocka']=$current;
    $filtered=cb_smeny_verejne_filtrovat($source,['sm_f'=>$applied]);
    // Vlastní den sleduje časovou posloupnost přes pobočky a sloty; rozpis pobočky seskupí sloty podle ID číselníku.
    usort($filtered,static function(array $a,array $b)use($own):int {
        $date=strcmp($a['datum'],$b['datum']);
        if ($date!==0) return $date;
        if (!$own && (int)$a['id_slot']!==(int)$b['id_slot']) {
            // ID 1 a 2 patří pizzařům a kurýrům; historické či další sloty následují až za nimi.
            $rank=static fn(int $id):int=>match($id){1=>0,2=>1,default=>2+$id};
            return $rank((int)$a['id_slot'])<=>$rank((int)$b['id_slot']);
        }
        return [$a['zacatek'],$a['konec'],(int)$a['id_pob'],(string)$a['pracovnik']]
            <=>[$b['zacatek'],$b['konec'],(int)$b['id_pob'],(string)$b['pracovnik']];
    });
    $days=[];
    foreach ($filtered as $row) $days[$row['datum']][]=$row;
    return compact('filters','own','options','current','browse','previous','next','slots','days');
}

/** Odkazy šipek obsahují pouze podporované filtry; cizí parametry URL se nepřenášejí. */
function cb_smeny_verejne_odkaz(string $page, int $week, bool $internal, array $filters, int $current=0): string
{
    return cb_root_url('index.php?'.http_build_query([
        'm'=>'smeny','page'=>$page,'week'=>$week,'interni'=>$internal?'1':'',
        'sm_f'=>array_map(static fn($v)=>is_bool($v)?(int)$v:$v,$filters),'sm_zob_pob'=>$current,
    ],'', '&',PHP_QUERY_RFC3986));
}
