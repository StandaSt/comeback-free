<?php
declare(strict_types=1);

/**
 * Nacte hlavni pobocku uzivatele pro zadani HR pozadavku.
 */
function hr_nacti_hlavni_pobocku_uzivatele(mysqli $db, int $idUser): array
{
    $stmt = $db->prepare("
        SELECT p.id_pob, p.nazev
        FROM user_pobocka up
        INNER JOIN pobocka p
            ON p.id_pob = up.id_pob
        WHERE up.id_user = ?
          AND up.main = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!is_array($row)) {
        throw new RuntimeException('Chybí hlavní pobočka uživatele.');
    }

    return [
        'id_pob' => (int)$row['id_pob'],
        'nazev' => (string)$row['nazev'],
    ];
}

/**
 * Ulozi HR pozadavek na tolik radku, kolik zamestnancu je pozadovano.
 */
function hr_uloz_pozadavek(mysqli $db, int $idPob, int $idSlot, int $pocet, string $upresneni, int $zadal): void
{
    $stmt = $db->prepare("
        INSERT INTO hr_pozadavek (id_pob, id_slot, upresneni, stav, zadal, zadano)
        VALUES (?, ?, ?, 1, ?, NOW())
    ");

    for ($i = 0; $i < $pocet; $i++) {
        $stmt->bind_param('iisi', $idPob, $idSlot, $upresneni, $zadal);
        $stmt->execute();
    }

    $stmt->close();
}

/**
 * Zrusi otevreny HR pozadavek zadavatelem nebo vedoucim stejne pobocky.
 */
function hr_zrus_pozadavek(mysqli $db, int $idPozadavek, int $idPob, int $idUser, int $idRole): void
{
    $stmt = $db->prepare("
        UPDATE hr_pozadavek
        SET stav = 0,
            zruseno_kdy = NOW(),
            zrusil = ?
        WHERE id_hr_pozadavek = ?
          AND stav = 1
          AND (zadal = ? OR (? = 5 AND id_pob = ?))
    ");
    $stmt->bind_param('iiiii', $idUser, $idPozadavek, $idUser, $idRole, $idPob);
    $stmt->execute();
    $stmt->close();
}

/**
 * Nacte HR pozadavky pro jednu pobocku podle stavu.
 */
function hr_nacti_pozadavky_pobocky_podle_stavu(mysqli $db, int $idPob, int $stav): array
{
    $stmt = $db->prepare("
        SELECT
            hp.id_hr_pozadavek,
            hp.id_slot,
            hp.upresneni,
            hp.zadal,
            hp.zadano,
            hp.id_pob,
            cs.slot
        FROM hr_pozadavek hp
        INNER JOIN cis_slot cs
            ON cs.id_slot = hp.id_slot
        WHERE hp.id_pob = ?
          AND hp.stav = ?
        ORDER BY hp.zadano ASC, hp.id_hr_pozadavek ASC
    ");
    $stmt->bind_param('ii', $idPob, $stav);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row + [
            'upresneni' => trim((string)($row['upresneni'] ?? '')) !== '' ? (string)$row['upresneni'] : '-',
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * Nacte otevrene HR pozadavky pro jednu pobocku.
 */
function hr_nacti_nove_pozadavky_pobocky(mysqli $db, int $idPob): array
{
    return hr_nacti_pozadavky_pobocky_podle_stavu($db, $idPob, 1);
}
