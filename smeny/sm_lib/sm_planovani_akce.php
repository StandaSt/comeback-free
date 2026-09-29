<?php
declare(strict_types=1);

/* Účel souboru: Zpracuje změnu šablony, obsazení směny a vědomé přechody stavu rozpisu. */

function cb_smeny_planovani_akce(mysqli $db, array $branches, array $weeks): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !str_starts_with((string)($_POST['action'] ?? ''),'smeny_plan_')) return;
    if (!cb_smeny_planovani_edituje() || !cb_crf_platny()) throw new CbUserVisibleException('Nemáte právo upravit plán nebo vypršela platnost stránky.');
    $idPlan=(int)($_POST['id_smeny_rozpis'] ?? 0); $action=(string)$_POST['action'];
    $idBranch=(int)($_POST['id_pob'] ?? 0); $weekIndex=(int)($_POST['week'] ?? -1);
    $redirect=cb_root_url('index.php?m=smeny&page=planovani_smen&id_pob='.$idBranch.'&week='.max(0,$weekIndex));
    try {
        if (!isset($branches[$idBranch],$weeks[$weekIndex])) throw new CbUserVisibleException('Pobočka nebo týden nejsou dostupné.');
        $idPerson=cb_smeny_sablony_id_person($db); if($idPerson<=0) throw new CbUserVisibleException('Účet není propojený s osobou v HR.');
        $states=['smeny_plan_pripraven'=>'pripraveny','smeny_plan_upravit'=>'rozpracovany'];
        if (isset($states[$action])) {
            $state=$states[$action]; $stmt=$db->prepare('UPDATE smeny_rozpis SET stav=? WHERE id_smeny_rozpis=? AND id_pob=? AND stav IN ("rozpracovany","pripraveny")');
            $stmt->bind_param('sii',$state,$idPlan,$idBranch); $stmt->execute(); $stmt->close();
        } elseif ($action==='smeny_plan_zalozit') {
            $idTemplate=(int)($_POST['id_smeny_sablona'] ?? 0);
            $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
            if ($now < $weeks[$weekIndex]['deadline']) throw new CbUserVisibleException('Plánování začne až po středeční uzávěrce požadavků ve 20:00.');
            cb_smeny_planovani_zalozit($db,$branches[$idBranch],$weeks[$weekIndex],$idTemplate,$idPerson);
        } else {
            $plan=cb_smeny_planovani_nacist($db,$idBranch,(string)$weeks[$weekIndex]['start_day']);
            if ($plan===null || (int)$plan['id_smeny_rozpis']!==$idPlan) throw new CbUserVisibleException('Rozpis už není dostupný.');
            if ($action==='smeny_plan_sablona_obnovit') {
                cb_smeny_planovani_sablonu_obnovit($db,$idPlan,$branches[$idBranch],$weeks[$weekIndex],(int)($_POST['id_smeny_sablona']??0),$idPerson);
                cb_smeny_sablony_flash_ulozit('success','Aktuální šablona byla načtena do rozpisu.');
            } elseif ($action==='smeny_plan_priradit') {
                cb_smeny_planovani_priradit($db,$plan,$branches[$idBranch],$weeks[$weekIndex],$_POST,$idPerson);
                cb_smeny_sablony_flash_ulozit('success','Směna byla průběžně uložena.');
            }
        }
    } catch (Throwable $e) {
        cb_smeny_sablony_flash_ulozit('error',cb_chyba_uzivatel($e,['module'=>'smeny','action'=>'Plánování směn','table'=>'smeny_rozpis']));
    }
    header('Location: '.$redirect); exit;
}
