<?php
declare(strict_types=1);

/** @return array<int,array{id_slot:int,slot:string,aktivni:int}> */
function hr_nastaveni_pozice(mysqli $db): array
{
    $result = $db->query('SELECT id_slot, slot, aktivni FROM cis_slot ORDER BY aktivni DESC, slot, id_slot');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = ['id_slot' => (int)$row['id_slot'], 'slot' => (string)$row['slot'], 'aktivni' => (int)$row['aktivni']];
    }
    $result->free();
    return $rows;
}

function hr_nastaveni_pozice_pridat(mysqli $db, string $nazev): void
{
    $nazev = trim($nazev);
    if ($nazev === '' || mb_strlen($nazev, 'UTF-8') > 100) {
        throw new RuntimeException('Zadejte název pozice v délce nejvýše 100 znaků.');
    }
    $result = $db->query('SELECT id_slot FROM cis_slot ORDER BY id_slot');
    $used = [];
    while ($row = $result->fetch_assoc()) {
        $used[(int)$row['id_slot']] = true;
    }
    $result->free();
    $idSlot = null;
    for ($id = 0; $id <= 62; $id++) {
        if (!isset($used[$id])) {
            $idSlot = $id;
            break;
        }
    }
    if ($idSlot === null) {
        throw new RuntimeException('Nelze přidat další pozici.');
    }
    $aktivni = 1;
    $stmt = $db->prepare('INSERT INTO cis_slot (id_slot, slot, aktivni) VALUES (?, ?, ?)');
    $stmt->bind_param('isi', $idSlot, $nazev, $aktivni);
    $stmt->execute();
    $stmt->close();
}

function hr_nastaveni_pozice_zmenit_stav(mysqli $db, int $idSlot, bool $aktivni): void
{
    if ($idSlot < 0 || $idSlot > 62) {
        throw new RuntimeException('Neplatná pozice.');
    }
    $stav = $aktivni ? 1 : 0;
    $stmt = $db->prepare('UPDATE cis_slot SET aktivni = ? WHERE id_slot = ?');
    $stmt->bind_param('ii', $stav, $idSlot);
    $stmt->execute();
    if ($stmt->affected_rows < 1) {
        $stmt->close();
        throw new RuntimeException('Pozici se nepodařilo změnit.');
    }
    $stmt->close();
}
