<?php
declare(strict_types=1);

/**
 * DB operace pro HR pozadavky pobocky.
 */

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
function hr_uloz_pozadavek(mysqli $db, int $idPob, int $idSlot, int $pocet, string $upresneni, int $zadalPerson): void
{
    if ($zadalPerson <= 0) {
        throw new RuntimeException('Chybí HR osoba zadavatele požadavku.');
    }
    if ($idPob <= 0 || $idSlot <= 0 || $pocet <= 0) {
        throw new RuntimeException('Chybí povinné údaje požadavku.');
    }

    $stmt = $db->prepare("
        INSERT INTO hr_pozadavek (id_pob, id_slot, id_pozadavek_stav, upresneni, zadal, zadano)
        VALUES (?, ?, 1, ?, ?, NOW())
    ");

    for ($i = 0; $i < $pocet; $i++) {
        $stmt->bind_param('iisi', $idPob, $idSlot, $upresneni, $zadalPerson);
        $stmt->execute();
    }

    $stmt->close();
}

/**
 * Zrusi otevreny HR pozadavek podle opravneni role.
 */
function hr_zrus_pozadavek(mysqli $db, int $idPozadavek, int $idPob, int $zrusilPerson, int $idRole): void
{
    if ($zrusilPerson <= 0) {
        throw new RuntimeException('Chybí HR osoba pro zrušení požadavku.');
    }

    $stmt = $db->prepare("
        UPDATE hr_pozadavek
        SET id_pozadavek_stav = 4,
            uzavreno = NOW(),
            uzavrel = ?
        WHERE id_pozadavek = ?
          AND id_pozadavek_stav = 1
          AND (? = 1 OR zadal = ? OR (? = 5 AND id_pob = ?))
    ");
    $stmt->bind_param('iiiiii', $zrusilPerson, $idPozadavek, $idRole, $zrusilPerson, $idRole, $idPob);
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
            hp.id_pozadavek,
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
          AND hp.id_pozadavek_stav = ?
        ORDER BY hp.zadano ASC, hp.id_pozadavek ASC
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
 * Nacte HR pozadavky vsech pobocek podle stavu.
 */
function hr_nacti_pozadavky_podle_stavu(mysqli $db, int $stav): array
{
    $stmt = $db->prepare("
        SELECT
            hp.id_pozadavek,
            hp.id_slot,
            hp.upresneni,
            hp.zadal,
            hp.zadano,
            hp.id_pob,
            cs.slot,
            p.nazev AS pobocka
        FROM hr_pozadavek hp
        INNER JOIN cis_slot cs
            ON cs.id_slot = hp.id_slot
        INNER JOIN pobocka p
            ON p.id_pob = hp.id_pob
        WHERE hp.id_pozadavek_stav = ?
        ORDER BY p.nazev ASC, hp.zadano ASC, hp.id_pozadavek ASC
    ");
    $stmt->bind_param('i', $stav);
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
