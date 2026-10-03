<?php
declare(strict_types=1);
/* Účel: Při publikaci uzavře žádosti, jejichž směna zaměstnanci skutečně zmizela ze zveřejňovaného plánu. */

/** Stabilní klíč brání ztrátě žádosti při kopírování verzí. Pouhé schválení v pracovní kopii není dokončení. */
function cb_smeny_zruseni_publikovat(mysqli $db, array $plan, int $publisher): void
{
    $stmt=cb_smeny_planovani_sql($db,'SELECT z.*,s.klic_smeny FROM smeny_zruseni z JOIN smeny_smena s ON s.id_smeny_smena=z.id_smeny_smena WHERE s.id_smeny_rozpis=? AND z.stav IN ("odeslana","resi_se") FOR UPDATE','i',[(int)$plan['id_smeny_rozpis']]);
    $requests=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
    foreach($requests as $request) {
        $present=false;
        foreach($plan['blocks'] as $shift) if($shift['klic_smeny']===$request['klic_smeny'] && (int)$shift['id_person']===(int)$request['pozadal_id_person']) $present=true;
        if($present && $request['stav']==='resi_se') throw new CbUserVisibleException('Směna schválená ke zrušení byla znovu přidána do pracovního plánu. Před zveřejněním ji odeberte.');
        if($present) continue;
        cb_smeny_planovani_sql($db,'UPDATE smeny_zruseni SET stav="schvalena",vyrizeno=NOW(),vyridil_id_person=COALESCE(vyridil_id_person,?) WHERE id_smeny_zruseni=?','ii',[$publisher,(int)$request['id_smeny_zruseni']])->close();
        cb_smeny_audit_zapis($db,$publisher,'zruseni_zverejneno','zruseni',(int)$request['id_smeny_zruseni'],['stav'=>$request['stav']],['stav'=>'schvalena']);
    }
}
