<?php
declare(strict_types=1);

/*
 * Zapíše jednu doménovou změnu do auditní tabulky Směny V2.
 */

/** Audit je součástí transakce volajícího; neplatný JSON nesmí tiše zahodit historii. */
function cb_smeny_audit_zapis(mysqli $db, int $idPerson, string $action, string $object, int $idObject, mixed $oldData, mixed $newData): void
{
    $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $stmt = $db->prepare('INSERT INTO smeny_audit (id_person_akce, akce, objekt, id_objektu, puvodni_data, nova_data) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ississ', $idPerson, $action, $object, $idObject, $oldJson, $newJson);
    $stmt->execute();
    $stmt->close();
}
