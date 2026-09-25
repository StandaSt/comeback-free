<?php
declare(strict_types=1);

/*
 * Zapíše jednu doménovou změnu modulu Směny do jeho auditní tabulky.
 */

function cb_smeny_audit_zapis(mysqli $db, int $idPerson, string $action, string $object, int $idObject, mixed $oldData, mixed $newData): void
{
    $oldJson = $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson = $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $stmt = $db->prepare('INSERT INTO smeny_audit (id_person_akce, akce, objekt, id_objektu, puvodni_data, nova_data, ip) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ississs', $idPerson, $action, $object, $idObject, $oldJson, $newJson, $ip);
    $stmt->execute();
    $stmt->close();
}
