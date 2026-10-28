<?php
declare(strict_types=1);

/**
 * DB udrzba expirace nepotvrzenych verejnych dotazniku; automaticky prechod ma vlastni audit.
 */
require_once __DIR__ . '/../hr_lib/hr_audit.php';

/**
 * Nastavi expirovane nepotvrzene verejne dotazniky na stav Nepotvrdil VD.
 */
function hr_expiruj_nepotvrzene_vd(mysqli $db): void
{
    $where = "vd.id_vd_stav = " . HR_VD_STAV_NEPOTVRZENO . "
          AND vd.aktivni = 1
          AND t.aktivni = 1
          AND t.pouzito IS NULL
          AND t.platnost_do IS NOT NULL
          AND t.platnost_do < NOW()";
    hr_audit_transaction_begin($db);
    try {
        // Zámek a pevná ID zajistí, že audit popisuje pouze řádky změněné tímto během údržby.
        $rows = $db->query('SELECT DISTINCT vd.id_vd,vd.id_vd_stav FROM hr_vd vd JOIN hr_vd_token t ON t.id_vd=vd.id_vd WHERE '.$where.' FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
        if ($rows !== []) {
            $ids = implode(',',array_map(static fn(array $row):int=>(int)$row['id_vd'],$rows));
            $db->query('UPDATE hr_vd SET id_vd_stav='.(int)HR_VD_STAV_VD_NEPOTVRZENO.',upraveno=NOW() WHERE id_vd IN ('.$ids.')');
            foreach ($rows as $row) {
                $id = (int)$row['id_vd'];
                $event = ['table'=>'hr_vd','id'=>$id,'kind'=>'zmena','row'=>[],
                    'old'=>['id_vd_stav'=>$row['id_vd_stav']], 'new'=>['id_vd_stav'=>(string)HR_VD_STAV_VD_NEPOTVRZENO]];
                hr_audit_zapis($db,['person'=>0,'object'=>'hr_vd','id'=>$id,'action'=>'hr_vd_expirace',
                    'label'=>'Automaticky vypršelo potvrzení dotazníku','od'=>null,'automatic'=>true],$event,true,'');
            }
        }
        hr_audit_transaction_commit($db);
    } catch (Throwable $e) { hr_audit_transaction_rollback($db); throw $e; }
}
