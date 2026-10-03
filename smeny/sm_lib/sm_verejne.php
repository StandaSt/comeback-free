<?php
declare(strict_types=1);
/* Účel: Načítá veřejné směny a vlastní oznámení a formátuje jejich neměnný obsah. */
require_once __DIR__.'/../../common/lib/smeny_verejny_zdroj.php';

/** Vlastní směny jsou přes všechny pobočky, cizí pouze z oprávněných poboček plánovače. */
function cb_smeny_verejne_smeny(mysqli $db,string $week,int $person,array $branches,bool $all): array
{
    if($all && (!cb_smeny_planovani_vidi() || $branches===[]))return [];
    if(!$all && $person<=0)return [];
    if($all)return cb_smeny_verejne_pobocky($db,$week,$branches);
    $sql='SELECT v.*,p.nazev pobocka,cs.slot,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) pracovnik FROM ('.cb_smeny_verejny_zdroj_sql().') v JOIN pobocka p ON p.id_pob=v.id_pob JOIN cis_slot cs ON cs.id_slot=v.id_slot LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(o.id_osobni_udaje) FROM hr_osobni_udaje o WHERE o.id_person=v.id_person AND o.platny=1) WHERE v.start_day=? AND v.id_person=? ORDER BY v.zacatek,p.nazev,cs.slot,pracovnik';
    $stmt=cb_smeny_planovani_sql($db,$sql,'si',[$week,$person]);$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Historie načte všechny směny pouze z poboček, které už omezil globální výběr a oprávnění uživatele. */
function cb_smeny_verejne_pobocky(mysqli $db,string $week,array $branches): array
{
    if($branches===[])return [];
    $ids=implode(',',array_map('intval',array_keys($branches)));
    $sql='SELECT v.*,p.nazev pobocka,cs.slot,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) pracovnik FROM ('.cb_smeny_verejny_zdroj_sql().') v JOIN pobocka p ON p.id_pob=v.id_pob JOIN cis_slot cs ON cs.id_slot=v.id_slot LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(o.id_osobni_udaje) FROM hr_osobni_udaje o WHERE o.id_person=v.id_person AND o.platny=1) WHERE v.start_day=? AND v.id_pob IN ('.$ids.') ORDER BY v.zacatek,p.nazev,cs.slot,pracovnik';
    $stmt=cb_smeny_planovani_sql($db,$sql,'s',[$week]);$rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Nepotvrzené zprávy neomezujeme vybraným týdnem, aby nezmizela starší změna. */
function cb_smeny_verejne_oznameni(mysqli $db,int $person): array
{
    $stmt=cb_smeny_planovani_sql($db,'SELECT o.*,p.nazev pobocka FROM smeny_oznameni o JOIN smeny_rozpis r ON r.id_smeny_rozpis=o.id_smeny_rozpis JOIN pobocka p ON p.id_pob=r.id_pob WHERE o.id_person=? AND o.potvrzeno IS NULL ORDER BY o.id_smeny_oznameni LIMIT 100','i',[$person]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
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

