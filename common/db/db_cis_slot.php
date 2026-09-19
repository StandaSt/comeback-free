<?php
declare(strict_types=1);

/**
 * Vrati nazvy slotu indexovane stabilnim ID ciselniku.
 *
 * @return array<int,string>
 */
function cb_cis_slot_nazvy(mysqli $db, bool $jenAktivni = false): array
{
    static $cache = [];

    $cacheKey = spl_object_id($db) . ':' . (int)$jenAktivni;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT id_slot, slot FROM cis_slot';
    if ($jenAktivni) {
        $sql .= ' WHERE aktivni = 1';
    }
    $sql .= ' ORDER BY id_slot';

    $result = $db->query($sql);
    $nazvy = [];
    while ($row = $result->fetch_assoc()) {
        $idSlot = (int)($row['id_slot'] ?? 0);
        $nazev = trim((string)($row['slot'] ?? ''));
        if ($idSlot >= 0 && $nazev !== '') {
            $nazvy[$idSlot] = $nazev;
        }
    }
    $result->free();

    $cache[$cacheKey] = $nazvy;

    return $nazvy;
}

function cb_cis_slot_nazev(mysqli $db, int $idSlot): string
{
    $nazvy = cb_cis_slot_nazvy($db);

    return $nazvy[$idSlot] ?? ('Slot ' . $idSlot);
}

