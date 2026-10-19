<?php
declare(strict_types=1);
/* Účel: Pod společným zámkem publikace uloží vlastní souhlas, výhrady nebo přečtení odebrání; audituje vyjádření a dokončí definitivní schválení. */

/** Obsah oznámení se nemění; starý odkaz nesmí potvrdit nahrazenou podobu směn. */
function cb_smeny_verejne_potvrdit(mysqli $db,int $person): void
{
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST' || ($_POST['action']??'')!=='smeny_potvrdit')return;
    $transaction=false;
    try {
        if($person<=0 || !cb_crf_platny())throw new CbUserVisibleException('Vypršela platnost stránky nebo chybí vazba účtu na HR.');
        if(!cb_smeny_souhlasy_schema($db))throw new CbUserVisibleException('Potvrzování čeká na aktualizaci databáze pro souhlasy a výhrady.');
        $id=(int)($_POST['id_smeny_oznameni']??0);
        $stmt=cb_smeny_planovani_sql($db,'SELECT r.id_pob,r.id_smeny_rozpis FROM smeny_oznameni o JOIN smeny_rozpis r ON r.id_smeny_rozpis=o.id_smeny_rozpis WHERE o.id_smeny_oznameni=? AND o.id_person=?','ii',[$id,$person]);
        $context=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($context===null)throw new CbUserVisibleException('Oznámení není dostupné pro váš účet.');
        // Publikace i potvrzení zamykají nejprve pobočku, takže poslední souhlas nezávodí se změnou plánu.
        $db->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->begin_transaction(); $transaction=true;
        cb_smeny_planovani_sql($db,'SELECT id_pob FROM pobocka WHERE id_pob=? FOR UPDATE','i',[(int)$context['id_pob']])->close();
        $stmt=cb_smeny_planovani_sql($db,'SELECT * FROM smeny_oznameni WHERE id_smeny_oznameni=? AND id_person=? FOR UPDATE','ii',[$id,$person]);
        $own=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($own===null)throw new CbUserVisibleException('Oznámení není dostupné pro váš účet.');
        if($own['nahrazeno']!==null)throw new CbUserVisibleException('Toto oznámení nahradila novější podoba směn. Otevřete aktuální oznámení v Mé směny.');
        $content=json_decode($own['obsah'],true,512,JSON_THROW_ON_ERROR);
        $weekly=$own['typ']==='tyden' && ($content['smeny']??[])!==[];
        $answer=(string)($_POST['vyjadreni']??'');
        $allowed=$weekly?['souhlas','vyhrady']:['ok'];
        if(!in_array($answer,$allowed,true))throw new CbUserVisibleException('Vyberte platné vyjádření k oznámení.');
        $days=$_POST['vyhrady_dny']??[];
        if(!is_array($days))throw new CbUserVisibleException('Neplatný výběr dnů.');
        $days=$answer==='vyhrady'?cb_smeny_souhlasy_vyhrady($content,$days):[];
        if($answer==='souhlas' && ($_POST['vyhrady_dny']??[])!==[])throw new CbUserVisibleException('Pro souhlas bez výhrad nejprve zrušte označení nevyhovujících dnů.');
        if($own['potvrzeno']!==null) {
            // Dvojklik zachová původní souhlas; již přijatou verzi nelze dodatečně přepsat výhradami.
            if($answer==='vyhrady')throw new CbUserVisibleException('Tento rozpis jste již odsouhlasili. Případnou změnu domluvte s plánovačem.');
        } elseif($answer==='vyhrady') {
            cb_smeny_planovani_sql($db,'UPDATE smeny_oznameni SET vyhrady_dny=?,vyjadreno=NOW(),precteno=COALESCE(precteno,NOW()) WHERE id_smeny_oznameni=?','si',[json_encode($days,JSON_THROW_ON_ERROR),$id])->close();
        } else {
            cb_smeny_planovani_sql($db,'UPDATE smeny_oznameni SET potvrzeno=NOW(),vyhrady_dny=NULL,vyjadreno=NOW(),precteno=COALESCE(precteno,NOW()) WHERE id_smeny_oznameni=?','i',[$id])->close();
        }
        cb_smeny_audit_zapis($db,$person,$answer==='vyhrady'?'oznameni_vyhrady':($own['potvrzeno']===null?'oznameni_potvrzeno':'potvrzeni_opakovano'),'oznameni',$id,$own,['verze'=>(int)$own['verze'],'vyjadreni'=>$answer,'vyhrady_dny'=>$days]);
        cb_smeny_souhlasy_definitivni($db,(int)$own['id_smeny_rozpis'],$person);
        $db->commit(); $transaction=false;
        cb_smeny_sablony_flash_ulozit('success',$answer==='vyhrady'?'Výhrady k označeným směnám byly uloženy.':($weekly?'Souhlas bez výhrad byl uložen.':'Potvrzení tohoto oznámení bylo uloženo.'));
    } catch(Throwable $e) {
        if($transaction)$db->rollback();
        cb_smeny_aktivita_chyba($e);
        cb_smeny_sablony_flash_ulozit('error',cb_chyba_uzivatel($e,['module'=>'smeny','action'=>'Potvrzení směn','table'=>'smeny_oznameni']));
    }
    header('Location: '.cb_root_url('index.php?m=smeny&page=me_smeny&week='.max(0,min(4,(int)($_POST['week']??0)))));exit;
}
