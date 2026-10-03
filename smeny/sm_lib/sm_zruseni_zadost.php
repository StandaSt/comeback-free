<?php
declare(strict_types=1);
/* Účel: Uloží nebo stáhne vlastní žádost zaměstnance o zrušení budoucí zveřejněné interní směny. */

/** Požadavek směnu neruší. Zámek řádku pobočky zabrání závodu s publikací a duplicitě mezi verzemi. */
function cb_smeny_zruseni_zadost(mysqli $db, int $person): void
{
    $action=(string)($_POST['action']??'');
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST' || !in_array($action,['smeny_zruseni_zadat','smeny_zruseni_stahnout'],true)) return;
    $transaction=false;
    try {
        if($person<=0 || !cb_crf_platny()) throw new CbUserVisibleException('Nemáte platné přihlášení nebo vypršela platnost formuláře.');
        $withdraw=$action==='smeny_zruseni_stahnout';
        $id=(int)($_POST[$withdraw?'id_smeny_zruseni':'id_smeny_smena']??0);
        $sql=$withdraw
            ? 'SELECT r.id_pob FROM smeny_zruseni z JOIN smeny_smena s ON s.id_smeny_smena=z.id_smeny_smena JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE z.id_smeny_zruseni=? AND z.pozadal_id_person=?'
            : 'SELECT r.id_pob FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE s.id_smeny_smena=? AND s.id_person=?';
        $stmt=cb_smeny_planovani_sql($db,$sql,'ii',[$id,$person]);$branch=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($branch===null) throw new CbUserVisibleException('Směna nebo žádost není dostupná pro váš účet.');
        $db->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->begin_transaction();$transaction=true;
        cb_smeny_planovani_sql($db,'SELECT id_pob FROM pobocka WHERE id_pob=? FOR UPDATE','i',[(int)$branch['id_pob']])->close();
        if($withdraw) {
            $stmt=cb_smeny_planovani_sql($db,'UPDATE smeny_zruseni SET stav="stazena",vyrizeno=NOW() WHERE id_smeny_zruseni=? AND pozadal_id_person=? AND stav="odeslana"','ii',[$id,$person]);
            $changed=$stmt->affected_rows;$stmt->close();
            if($changed!==1) throw new CbUserVisibleException('Žádost již vyřizuje vedoucí nebo byla uzavřena.');
        } else {
            $note=trim((string)($_POST['poznamka']??''));
            if(mb_strlen($note)>500) throw new CbUserVisibleException('Důvod může mít nejvýše 500 znaků.');
            $stmt=cb_smeny_planovani_sql($db,'SELECT s.* FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis JOIN hr_person hp ON hp.id_person=s.id_person WHERE s.id_smeny_smena=? AND s.id_person=? AND hp.aktivni=1 AND s.verze=r.zverejnena_verze AND s.zacatek>NOW()','ii',[$id,$person]);
            $shift=$stmt->get_result()->fetch_assoc();$stmt->close();
            if($shift===null) throw new CbUserVisibleException('Lze požádat jen o vlastní aktuálně zveřejněnou směnu před jejím začátkem. Obnovte stránku.');
            $stmt=cb_smeny_planovani_sql($db,'SELECT z.id_smeny_zruseni FROM smeny_zruseni z JOIN smeny_smena s ON s.id_smeny_smena=z.id_smeny_smena WHERE s.id_smeny_rozpis=? AND s.klic_smeny=? AND z.pozadal_id_person=? AND z.stav IN ("odeslana","resi_se")','isi',[(int)$shift['id_smeny_rozpis'],$shift['klic_smeny'],$person]);
            $exists=$stmt->get_result()->fetch_assoc();$stmt->close();
            if($exists!==null) throw new CbUserVisibleException('Pro tuto směnu už máte otevřenou žádost.');
            cb_smeny_planovani_sql($db,'INSERT INTO smeny_zruseni (id_smeny_smena,pozadal_id_person,poznamka) VALUES (?,?,?)','iis',[$id,$person,$note])->close();
            $id=(int)$db->insert_id;
        }
        cb_smeny_audit_zapis($db,$person,$withdraw?'zadost_stazena':'zadost_zruseni','zruseni',$id,null,null);
        $db->commit();$transaction=false;
        cb_smeny_sablony_flash_ulozit('success',$withdraw?'Žádost byla stažena.':'Žádost byla odeslána vedoucímu. Směna zatím zůstává platná.');
    } catch(Throwable $e) {
        if($transaction)$db->rollback();
        cb_smeny_sablony_flash_ulozit('error',cb_chyba_uzivatel($e,['module'=>'smeny','action'=>'Žádost o zrušení směny','table'=>'smeny_zruseni']));
    }
    header('Location: '.cb_root_url('index.php?m=smeny&page=me_smeny&week='.max(0,min(4,(int)($_POST['week']??0)))));exit;
}
