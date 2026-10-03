<?php
declare(strict_types=1);
/* Účel: Vedoucí pod zámkem pobočky zamítne žádost nebo odebere směnu pouze z pracovní kopie. */

/** Volá dispatcher uvnitř své transakce; oprávnění, verzi i vlastnictví zámku ověřil předem. */
function cb_smeny_zruseni_vyridit(mysqli $db, array $plan, int $planner, array $post): void
{
    $id=(int)($post['id_smeny_zruseni']??0);
    $stmt=cb_smeny_planovani_sql($db,'SELECT z.*,s.klic_smeny,s.id_person,s.id_slot,s.zacatek,s.konec FROM smeny_zruseni z JOIN smeny_smena s ON s.id_smeny_smena=z.id_smeny_smena WHERE z.id_smeny_zruseni=? AND s.id_smeny_rozpis=? FOR UPDATE','ii',[$id,(int)$plan['id_smeny_rozpis']]);
    $request=$stmt->get_result()->fetch_assoc();$stmt->close();
    if($request===null || $request['stav']!=='odeslana') throw new CbUserVisibleException('Žádost již není otevřená pro vyřízení. Obnovte stránku.');
    $note=trim((string)($post['vysledek_poznamka']??''));
    if(mb_strlen($note)>500) throw new CbUserVisibleException('Vyjádření může mít nejvýše 500 znaků.');
    $approve=($post['action']??'')==='smeny_plan_zruseni_schvalit';
    if($approve) {
        if(new DateTimeImmutable($request['zacatek'],new DateTimeZone('Europe/Prague'))<=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'))) throw new CbUserVisibleException('Směna již začala. Žádost lze pouze uzavřít zamítnutím.');
        // Pracovní kopii otevřeme jen při schválení. Připravenost týdne se tím zruší.
        cb_smeny_planovani_upravit($db,$plan,$planner);
        $work=cb_smeny_planovani_nacist($db,(int)$plan['id_pob'],$plan['tyden_od']);
        foreach($work['blocks'] as $shift) {
            if($shift['klic_smeny']===$request['klic_smeny'] && (int)$shift['id_person']===(int)$request['pozadal_id_person']) {
                if($shift['zacatek']!==$request['zacatek'] || $shift['konec']!==$request['konec'] || (int)$shift['id_slot']!==(int)$request['id_slot']) throw new CbUserVisibleException('Čas nebo pozice směny se od podání žádosti změnily. Žádost zamítněte s vysvětlením; zaměstnanec může požádat znovu.');
                cb_smeny_planovani_odebrat($db,$work,(int)$shift['id_smeny_smena'],$planner);
            }
        }
    }
    $state=$approve?'resi_se':'zamitnuta';
    cb_smeny_planovani_sql($db,'UPDATE smeny_zruseni SET stav=?,vyridil_id_person=?,vyrizeno=IF(?="zamitnuta",NOW(),NULL),vysledek_poznamka=? WHERE id_smeny_zruseni=?','sissi',[$state,$planner,$state,$note,$id])->close();
    cb_smeny_audit_zapis($db,$planner,$approve?'zruseni_pripraveno':'zruseni_zamitnuto','zruseni',$id,['stav'=>$request['stav']],['stav'=>$state,'poznamka'=>$note]);
}
