<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
/* Účel: Načítá veřejné směny a vlastní oznámení, formátuje jejich neměnný detail a stručné texty pro existující Web Push. */
require_once __DIR__.'/../../common/lib/smeny_verejny_zdroj.php';

/** Vlastní směny jsou přes všechny pobočky, cizí pouze z oprávněných poboček plánovače. */
function cb_smeny_verejne_smeny(mysqli $db,string $week,int $person,array $branches,bool $all): array
{
    if($all && (!cb_smeny_planovani_vidi() || $branches===[]))return [];
    if(!$all && $person<=0)return [];
    if($all)return cb_smeny_verejne_pobocky($db,$week,$branches);
    $sql='SELECT v.*,p.nazev pobocka,cs.slot,' . cb_osoba_jmeno_sql('ou.id_person',true) . ' pracovnik FROM ('.cb_smeny_verejny_zdroj_sql().') v JOIN pobocka p ON p.id_pob=v.id_pob JOIN cis_slot cs ON cs.id_slot=v.id_slot LEFT JOIN ' . cb_osoby_zdroj_sql() . ' ou ON ou.id_person=v.id_person WHERE v.start_day=? AND v.id_person=? ORDER BY v.zacatek,p.nazev,cs.slot,pracovnik';
    $stmt=cb_smeny_planovani_sql($db,$sql,'si',[$week,$person]);$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Načte směny pouze z poboček povolených uživateli; případné zúžení zajišťuje místní filtr stránky. */
function cb_smeny_verejne_pobocky(mysqli $db,string $week,array $branches): array
{
    if($branches===[])return [];
    $ids=implode(',',array_map('intval',array_keys($branches)));
    $sql='SELECT v.*,p.nazev pobocka,cs.slot,' . cb_osoba_jmeno_sql('ou.id_person',true) . ' pracovnik FROM ('.cb_smeny_verejny_zdroj_sql().') v JOIN pobocka p ON p.id_pob=v.id_pob JOIN cis_slot cs ON cs.id_slot=v.id_slot LEFT JOIN ' . cb_osoby_zdroj_sql() . ' ou ON ou.id_person=v.id_person WHERE v.start_day=? AND v.id_pob IN ('.$ids.') ORDER BY v.zacatek,p.nazev,cs.slot,pracovnik';
    $stmt=cb_smeny_planovani_sql($db,$sql,'s',[$week]);$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Nepotvrzené zprávy neomezujeme vybraným týdnem, aby nezmizela starší změna. */
function cb_smeny_verejne_oznameni(mysqli $db,int $person): array
{
    $current=cb_smeny_souhlasy_schema($db)?' AND o.nahrazeno IS NULL':'';
    $stmt=cb_smeny_planovani_sql($db,'SELECT o.*,p.nazev pobocka FROM smeny_oznameni o JOIN smeny_rozpis r ON r.id_smeny_rozpis=o.id_smeny_rozpis JOIN pobocka p ON p.id_pob=r.id_pob WHERE o.id_person=? AND o.potvrzeno IS NULL'.$current.' ORDER BY o.id_smeny_oznameni LIMIT 100','i',[$person]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Push záměrně neprozrazuje detaily změny; úplný otisk se otevře až v autorizovaném modálu. */
function cb_smeny_oznameni_push_text(string $type,array $content): array
{
    $zone=new DateTimeZone('Europe/Prague');
    if($type==='tyden') {
        $start=new DateTimeImmutable($content['tyden_od'],$zone);$end=$start->modify('+6 days');
        $shifts=$content['smeny']??[];$count=count($shifts);$minutes=0;
        // Počítáme plánované místní hodiny stejně jako souhrn plánovače, nikoli uplynulé hodiny přes změnu času.
        foreach($shifts as $shift)$minutes+=(int)((strtotime($shift['konec'].' UTC')-strtotime($shift['zacatek'].' UTC'))/60);
        $wholeHours=(int)($minutes/60);
        $hours=$minutes%60===0?cb_format('i',$wholeHours).' '.($wholeHours===1?'hodina':($wholeHours>=2 && $wholeHours<=4?'hodiny':'hodin')):cb_format('h',$minutes/60);
        return ['title'=>'Vaše směny '.($content['pobocka']??'').' '.$start->format($start->format('m')===$end->format('m')?'j.':'j.m.').' - '.$end->format('j.m.Y'),
            'body'=>$count===0?'Na tento týden nemáte přidělenou žádnou směnu.':$count.' '.($count===1?'směna':($count<=4?'směny':'směn')).', celkem '.$hours];
    }
    $shift=$type==='odebrana'?$content['pred']:$content['po'];
    $date=(new DateTimeImmutable(cb_smeny_souhlasy_den($shift),$zone))->format('j.m.Y');
    return match($type) {
        'zmenena'=>['title'=>'Vaše směna dne '.$date.' se změnila.','body'=>''],
        'odebrana'=>['title'=>'Vaše směna '.$date.' byla odebrána.','body'=>''],
        'pridana'=>['title'=>'Byla vám přidělena směna '.$date.'.','body'=>'Prohlédněte si novou směnu a potvrďte, že o změně víte.'],
        default=>throw new RuntimeException('Neznámý typ oznámení směn.'),
    };
}

/** Vyjadřuje přesný obsah změny pro stránku i push; nikdy nečte novější pracovní kopii. */
function cb_smeny_oznameni_text(string $type,array $content): string
{
    $label=static function(array $s):string {
        $start=new DateTimeImmutable($s['zacatek']);$end=new DateTimeImmutable($s['konec']);
        return $start->format('j. n. Y H:i').'–'.$end->format($start->format('Y-m-d')===$end->format('Y-m-d')?'H:i':'j. n. H:i').(!empty($s['pozice'])?' ('.$s['pozice'].')':'');
    };
    return match($type) {
        'tyden'=>'Byly zveřejněny vaše směny na týden od '.(new DateTimeImmutable($content['tyden_od']))->format('j. n. Y').'.',
        'pridana'=>'Máte nově přidanou směnu '.$label($content['po']).'.',
        'odebrana'=>'Byla vám odebrána směna '.$label($content['pred']).'.',
        'zmenena'=>'Vaše směna byla změněna z '.$label($content['pred']).' na '.$label($content['po']).'.',
        default=>throw new RuntimeException('Neznámý typ oznámení směn.'),
    };
}

