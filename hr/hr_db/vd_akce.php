<?php
declare(strict_types=1);

/**
 * DB operace pro historii a ukladani akci u verejneho dotazniku.
 */

/**
 * Nacte historii akci k jednomu VD.
 */
function hr_nacti_vd_akce(mysqli $db, int $idVd): array
{
    if ($idVd <= 0) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT
            a.id_vd_akce,
            a.id_vd_akce_typ,
            a.id_person_zadal,
            a.akce_kdy,
            a.poznamka,
            t.nazev AS akce_typ_nazev,
            ou.jmeno AS zadal_jmeno,
            ou.prijmeni AS zadal_prijmeni
        FROM hr_vd_akce a
        INNER JOIN hr_cis_vd_akce_typ t
            ON t.id_vd_akce_typ = a.id_vd_akce_typ
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = a.id_person_zadal
           AND ou.platny = 1
        WHERE a.id_vd = ?
        ORDER BY a.akce_kdy DESC, a.id_vd_akce DESC
    ");
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $zadal = trim((string)($row['zadal_prijmeni'] ?? '') . ' ' . (string)($row['zadal_jmeno'] ?? ''));
        $rows[] = $row + [
            'zadal_label' => $zadal !== '' ? $zadal : '-',
            'poznamka' => trim((string)($row['poznamka'] ?? '')) !== '' ? (string)$row['poznamka'] : '-',
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * Ulozi akci personalisty a nastavi aktualni stav VD.
 */
function hr_uloz_vd_akci(mysqli $db, int $idVd, int $idVdStav, int $idVdAkceTyp, string $akceKdy, string $poznamka, int $idPersonZadal): void
{
    if ($idVd <= 0 || $idVdStav <= 0 || $idVdAkceTyp <= 0) {
        throw new RuntimeException('Chybí povinné údaje pro uložení akce.');
    }
    if ($idPersonZadal <= 0) {
        throw new RuntimeException('Chybí HR osoba přihlášeného uživatele.');
    }
    if ($akceKdy === '' || strtotime($akceKdy) === false) {
        throw new RuntimeException('Vyplňte datum a čas akce.');
    }

    $akceKdyDb = date('Y-m-d H:i:s', strtotime($akceKdy));
    $poznamkaDb = trim($poznamka) !== '' ? trim($poznamka) : null;

    $db->begin_transaction();
    try {
        // Nastavi aktualni stav verejneho dotazniku.
        $stmt = $db->prepare("
            UPDATE hr_vd
            SET id_vd_stav = ?,
                upraveno = NOW()
            WHERE id_vd = ?
              AND aktivni = 1
        ");
        $stmt->bind_param('ii', $idVdStav, $idVd);
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            throw new RuntimeException('Veřejný dotazník nebyl nalezen.');
        }
        $stmt->close();

        // Zapise udalost do historie naboru.
        $stmt = $db->prepare("
            INSERT INTO hr_vd_akce (id_vd, id_vd_akce_typ, id_person_zadal, akce_kdy, poznamka)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiiss', $idVd, $idVdAkceTyp, $idPersonZadal, $akceKdyDb, $poznamkaDb);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
