<?php
declare(strict_types=1);
/* Účel: Zpřístupňuje doložený audit Co-Linku bez čtení zpráv, příloh nebo přihlašovacích tajemství. */

/** Rozsah firem vychází ze stejné historie; samotné právo správy kanálů nepovoluje číst audit. */
function cb_historie_co_link_zdroj(mysqli $db, array $scope): ?array
{
    if (!cb_historie_pravo(700)) return null;
    $columns=$db->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='co_audit'")->fetch_all(MYSQLI_ASSOC);
    if ($columns===[]) return null;
    $hasDetail=in_array('detail_json',array_column($columns,'column_name'),true);
    $firms=implode(',',array_map('intval',$scope['firmy']))?:'0';
    $allowed="EXISTS (SELECT 1 FROM hr_person cp WHERE cp.id_user=t.uzivatel AND cp.id_firma IN ({$firms}))";
    // ID agendy čteme z číselníku; pořadí modulu není identifikátorem.
    $source=cb_historie_zdroj('Co-Link · audit','co_audit t','t.id','t.kdy','t.uzivatel',0,'','NULL','NULL','NULL',$allowed);
    $source['module']="(SELECT id_modul FROM cis_moduly WHERE modul='Co-Link' LIMIT 1)";
    $source['action']='t.akce'; $source['object_id']='t.objekt';
    $source['object']="CASE WHEN t.akce IN ('zprava_odeslana','zprava_prectena','zprava_smazana','zprava_upravena','priloha_pripojena','reakce_pridana','reakce_odebrana') THEN 'zprava' WHEN t.akce IN ('kanal_nastaven','kanal_vytvoren','konverzace_vytvorena','ztiseni_zmeneno') THEN 'konverzace' WHEN t.akce IN ('zarizeni_pripojeno','zarizeni_odvolano','zarizeni_odhlaseno','push_povolen') THEN 'zarizeni' ELSE '' END";
    $source['context']=$hasDetail?"CASE WHEN JSON_VALID(t.detail_json) THEN t.detail_json ELSE NULL END":'NULL';
    return $source;
}
