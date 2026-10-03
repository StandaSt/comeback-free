<?php
declare(strict_types=1);
/* Účel: Zpracuje souhlas zaměstnance s jedním neměnným oznámením směn. */

/** Potvrzuje pouze vlastní oznámení. Opakované odeslání nezmění původní čas potvrzení. */
function cb_smeny_verejne_potvrdit(mysqli $db,int $person): void
{
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST' || ($_POST['action']??'')!=='smeny_potvrdit')return;
    try {
        if($person<=0 || !cb_crf_platny())throw new CbUserVisibleException('Vypršela platnost stránky nebo chybí vazba účtu na HR.');
        $id=(int)($_POST['id_smeny_oznameni']??0);
        $stmt=cb_smeny_planovani_sql($db,'SELECT id_smeny_oznameni FROM smeny_oznameni WHERE id_smeny_oznameni=? AND id_person=?','ii',[$id,$person]);
        $own=$stmt->get_result()->fetch_assoc();$stmt->close();
        if($own===null)throw new CbUserVisibleException('Oznámení není dostupné pro váš účet.');
        cb_smeny_planovani_sql($db,'UPDATE smeny_oznameni SET potvrzeno=COALESCE(potvrzeno,NOW()),precteno=COALESCE(precteno,NOW()) WHERE id_smeny_oznameni=? AND id_person=?','ii',[$id,$person])->close();
        cb_smeny_sablony_flash_ulozit('success','Potvrzení bylo uloženo. Ostatní změny tím nejsou potvrzené.');
    } catch(Throwable $e) {
        cb_smeny_sablony_flash_ulozit('error',cb_chyba_uzivatel($e,['module'=>'smeny','action'=>'Potvrzení směn','table'=>'smeny_oznameni']));
    }
    header('Location: '.cb_root_url('index.php?m=smeny&page=me_smeny&week='.max(0,min(4,(int)($_POST['week']??0)))));exit;
}
