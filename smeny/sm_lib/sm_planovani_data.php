<?php
declare(strict_types=1);
/* Účel: Načte týdenní hodiny, samostatné příjmení a jméno z HR a rozpis s názvy slotů a základními sloupci; šablona doplní i volitelné pozice a zachová pracovní i zveřejněné směny. */
require_once __DIR__.'/../../common/lib/smeny_verejny_zdroj.php';

/** Plánovač potřebuje i právě probíhající týden kvůli pozdějším změnám směn. */
function cb_smeny_planovani_tydny(?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
    $weeks = cb_smeny_pozadavky_tydny($now->modify('-7 days'));
    $future = cb_smeny_pozadavky_tydny($now);
    $weeks[] = $future[3];
    foreach ($weeks as $index => &$week) { $week['index'] = $index; $week['open'] = $now <= $week['deadline']; }
    unset($week);
    return $weeks;
}

/** Odvozený stav slouží UI; autoritou jsou čísla verzí a příznak připravenosti. */
function cb_smeny_planovani_stav(array $plan): string
{
    return $plan['pracovni_verze'] === null ? 'zverejneny' : ((int)$plan['pripraveno'] === 1 ? 'pripraveny' : 'rozpracovany');
}

/** Načte hlavičku a obsah z jedné verze, nikdy nespojuje pracovní a veřejné směny. */
function cb_smeny_planovani_nacist(mysqli $db, int $idBranch, string $startDay): ?array
{
    $stmt = cb_smeny_planovani_sql($db, 'SELECT r.*,p.id_firma,p.kod FROM smeny_rozpis r JOIN pobocka p ON p.id_pob=r.id_pob WHERE r.id_pob=? AND r.tyden_od=?', 'is', [$idBranch,$startDay]);
    $plan = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($plan === null) return null;
    $plan['sloty_nazvy']=cb_smeny_sloty_ciselnik($db);
    $plan['stav'] = cb_smeny_planovani_stav($plan);
    $plan['start_day'] = $plan['tyden_od'];
    $plan['verze'] = (int)($plan['pracovni_verze'] ?? $plan['zverejnena_verze']);
    $idPlan = (int)$plan['id_smeny_rozpis'];
    $stmt = cb_smeny_planovani_sql($db, 'SELECT * FROM smeny_rozpis_potreba WHERE id_smeny_rozpis=? ORDER BY den_tydne,poradi,id_smeny_rozpis_potreba', 'i', [$idPlan]);
    $plan['potreba'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    // Začátky po půlnoci do 04:00 stále patří k předchozímu provoznímu dni.
    $stmt = cb_smeny_planovani_sql($db, 'SELECT s.*,DATE(DATE_SUB(s.zacatek,INTERVAL 6 HOUR)) datum,TIME_FORMAT(s.zacatek,"%H:%i") cas_od,TIME_FORMAT(s.konec,"%H:%i") cas_do,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) pracovnik,ou.prijmeni pracovnik_prijmeni,ou.jmeno pracovnik_jmeno FROM smeny_smena s LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(o.id_osobni_udaje) FROM hr_osobni_udaje o WHERE o.id_person=s.id_person AND o.platny=1) WHERE s.id_smeny_rozpis=? AND s.verze=? ORDER BY s.zacatek,s.id_slot,s.id_smeny_smena', 'ii', [$idPlan,$plan['verze']]);
    $plan['blocks'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    cb_smeny_planovani_sloty_nacist($db,$plan);
    return $plan;
}

/** Založení probíhá v transakci se zámkem pobočky; šablona může být záměrně vynechána. */
function cb_smeny_planovani_zalozit(mysqli $db, array $branch, array $week, int $idTemplate, int $idPerson): int
{
    $idBranch = (int)$branch['id_pob'];
    $stmt = cb_smeny_planovani_sql($db, 'INSERT INTO smeny_rozpis (id_pob,tyden_od,vytvoril_id_person) VALUES (?,?,?)', 'isi', [$idBranch,$week['start_day'],$idPerson]);
    $idPlan = (int)$db->insert_id; $stmt->close();
    if ($idTemplate > 0) cb_smeny_planovani_sablonu_obnovit($db,$idPlan,$branch,$week,$idTemplate,$idPerson);
    cb_smeny_audit_zapis($db,$idPerson,'zalozen','rozpis',$idPlan,null,['tyden_od'=>$week['start_day']]);
    return $idPlan;
}

/** Zkopíruje jen hodnoty potřeby. Žádný cizí klíč nespojuje rozpis se šablonou. */
function cb_smeny_planovani_vlozit_sablonu(mysqli $db, int $idPlan, array $template): void
{
    foreach ($template['blocks'] as $day => $blocks) foreach ($blocks as $block) {
        cb_smeny_planovani_sql($db, 'INSERT INTO smeny_rozpis_potreba (id_smeny_rozpis,den_tydne,id_slot,cas_od,cas_do,poradi) VALUES (?,?,?,?,?,?)', 'iiissi', [$idPlan,$day,(int)$block['id_slot'],$block['cas_od'],$block['cas_do'],(int)$block['poradi']])->close();
    }
}

/** Změna pomůcky zachová obsazení a doplní chybějící místa nového podkladu; již přidané sloupce neodebírá. */
function cb_smeny_planovani_sablonu_obnovit(mysqli $db, int $idPlan, array $branch, array $week, int $idTemplate, int $idPerson): void
{
    $plan = cb_smeny_planovani_nacist($db,(int)$branch['id_pob'],(string)$week['start_day']);
    if ($plan === null || (int)$plan['id_smeny_rozpis'] !== $idPlan || $idTemplate < 0) {
        throw new CbUserVisibleException('Rozpis nebo vybraná šablona nejsou dostupné.');
    }
    $template = ['nazev'=>null,'blocks'=>[]];
    if ($idTemplate > 0) {
        cb_smeny_planovani_sql($db, 'SELECT id_smeny_sablona FROM smeny_sablona WHERE id_smeny_sablona=? FOR UPDATE', 'i', [$idTemplate])->close();
        $template = cb_smeny_sablona_nacist($db,$idTemplate,[(int)$branch['id_pob']=>$branch]);
        if ($template === null) throw new CbUserVisibleException('Vybraná šablona nepatří k této pobočce.');
    }
    cb_smeny_planovani_sql($db, 'DELETE FROM smeny_rozpis_potreba WHERE id_smeny_rozpis=?', 'i', [$idPlan])->close();
    cb_smeny_planovani_sql($db, 'UPDATE smeny_rozpis SET sablona_nazev=? WHERE id_smeny_rozpis=?', 'si', [$template['nazev'],$idPlan])->close();
    cb_smeny_planovani_vlozit_sablonu($db,$idPlan,$template);
    // Výslovná volba podkladu zpřístupní všechna jeho místa, stávající směny ani další sloupce nemění.
    $updated=cb_smeny_planovani_nacist($db,(int)$branch['id_pob'],(string)$week['start_day']);
    if($updated!==null && $updated['pracovni_verze']!==null) {
        cb_smeny_planovani_sloty_doplnit($updated,cb_smeny_sablony_pozice($branch),true);
        cb_smeny_planovani_sloty_pripravit($db,$updated);
    }
    cb_smeny_audit_zapis($db,$idPerson,'obnovena_sablona','rozpis',$idPlan,['nazev'=>$plan['sablona_nazev'],'bloky'=>$plan['potreba']],['nazev'=>$template['nazev'],'bloky'=>$template['blocks']]);
}

/** Otevře úpravy; veřejné řádky zůstanou nedotčené a stabilní klíče se přenesou do kopie. */
function cb_smeny_planovani_upravit(mysqli $db, array $plan, int $idPerson): void
{
    $idPlan = (int)$plan['id_smeny_rozpis'];
    if ($plan['pracovni_verze'] === null) {
        $version = (int)$plan['posledni_verze'] + 1;
        cb_smeny_planovani_sql($db, 'INSERT INTO smeny_smena (id_smeny_rozpis,verze,klic_smeny,klic_slotu,id_person,id_slot,zacatek,konec,bez_ohledu_na_pozadavky,ulozil_id_person) SELECT id_smeny_rozpis,?,klic_smeny,klic_slotu,id_person,id_slot,zacatek,konec,bez_ohledu_na_pozadavky,? FROM smeny_smena WHERE id_smeny_rozpis=? AND verze=?', 'iiii', [$version,$idPerson,$idPlan,(int)$plan['zverejnena_verze']])->close();
        cb_smeny_planovani_sql($db,'INSERT INTO smeny_rozpis_slot (id_smeny_rozpis,verze,klic_slotu,datum,id_slot,poradi) SELECT id_smeny_rozpis,?,klic_slotu,datum,id_slot,poradi FROM smeny_rozpis_slot WHERE id_smeny_rozpis=? AND verze=?','iii',[$version,$idPlan,(int)$plan['zverejnena_verze']])->close();
        cb_smeny_planovani_sql($db, 'UPDATE smeny_rozpis SET posledni_verze=?,pracovni_verze=?,pripraveno=0 WHERE id_smeny_rozpis=?', 'iii', [$version,$version,$idPlan])->close();
    } else {
        cb_smeny_planovani_sql($db, 'UPDATE smeny_rozpis SET pripraveno=0 WHERE id_smeny_rozpis=?', 'i', [$idPlan])->close();
    }
    cb_smeny_audit_zapis($db,$idPerson,'otevreny_upravy','rozpis',$idPlan,null,null);
}

/** Souhrn bere jednu aktuální interní verzi pobočky; externí plán jen tam, kde interní rozpis není. */
function cb_smeny_planovani_tydenni_hodiny(mysqli $db,array $plan): array
{
    $ids=array_values(array_unique(array_filter(array_map('intval',array_column($plan['blocks'],'id_person')))));
    if($ids===[]) return [];
    $list=implode(',',$ids);
    $sql='SELECT v.id_person,v.id_pob,p.nazev pobocka,v.id_slot,c.slot,SUM(TIMESTAMPDIFF(MINUTE,v.zacatek,v.konec)) minuty FROM (
        SELECT s.id_person,r.id_pob,s.id_slot,s.zacatek,s.konec FROM smeny_rozpis r
        JOIN smeny_smena s ON s.id_smeny_rozpis=r.id_smeny_rozpis AND s.verze=COALESCE(r.pracovni_verze,r.zverejnena_verze)
        WHERE r.tyden_od=? AND s.id_person IN ('.$list.')
        UNION ALL
        SELECT v.id_person,v.id_pob,v.id_slot,v.zacatek,v.konec FROM ('.cb_smeny_verejny_zdroj_sql().') v
        WHERE v.zdroj=1 AND v.start_day=? AND v.id_person IN ('.$list.')
        AND NOT EXISTS(SELECT 1 FROM smeny_rozpis r WHERE r.id_pob=v.id_pob AND r.tyden_od=v.start_day
            AND COALESCE(r.pracovni_verze,r.zverejnena_verze) IS NOT NULL)
        ) v JOIN pobocka p ON p.id_pob=v.id_pob LEFT JOIN cis_slot c ON c.id_slot=v.id_slot
        GROUP BY v.id_person,v.id_pob,p.nazev,v.id_slot,c.slot ORDER BY p.nazev,v.id_slot';
    $stmt=cb_smeny_planovani_sql($db,$sql,'ss',[$plan['tyden_od'],$plan['tyden_od']]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    return $rows;
}
