<?php
declare(strict_types=1);

/*
 * Zapíše doménovou změnu Směn a ve stejné transakci ji propojí se společnou historií skutečného autora a jeho loginu.
 */
require_once __DIR__.'/sm_aktivita.php';

/** Audit je součástí transakce volajícího; neplatný JSON nesmí tiše zahodit historii. */
function cb_smeny_audit_zapis(mysqli $db, int $idPerson, string $action, string $object, int $idObject, mixed $oldData, mixed $newData): void
{
    $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('INSERT INTO smeny_audit (id_person_akce, akce, objekt, id_objektu, puvodni_data, nova_data) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ississ', $idPerson, $action, $object, $idObject, $oldJson, $newJson);
    $stmt->execute();
    $idAudit=(int)$db->insert_id;
    $stmt->close();
    // Podrobný starý/nový stav zůstává jen jednou v auditu; časová osa nese jeho jednoznačný odkaz.
    if (!empty($_SESSION['login_ok'])) {
        $details=cb_smeny_aktivita_kontext(array_merge(is_array($oldData)?$oldData:[],is_array($newData)?$newData:[]));
        $details['id_smeny_audit']=$idAudit;
        $type=($action==='zverejneno')?13:(str_contains($action,'odebran')?11:(in_array($action,['pridany_slot','pridana_smena','vytvorena','zalozen'],true)?10:12));
        if(!cb_aktivita_server_zapis('smeny',$action,$details,$type,$object,$idObject)) throw new RuntimeException('Zápis historie činnosti se nezdařil.');
    }
}
