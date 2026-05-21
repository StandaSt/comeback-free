<?php
// db/db_dr_pracovni_osoby.php * K10 osoby pracovního reportu
declare(strict_types=1);

/*
 * DB: dr_pracovni_osoby
 *
 * Účel:
 * - malé funkce pro osoby v pracovním denním reportu
 * - bez finálního ukládání do ostrých tabulek
 */

function cb_db_dr_pracovni_osoby_list(mysqli $conn, int $idDr): array
{
    if ($idDr <= 0) {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT dpo.id_dr_osoby, dpo.id_dr, dpo.id_user, dpo.id_slot,
               dpo.smena_od, dpo.smena_do, dpo.pauza, dpo.odpracovano,
               dpo.rozvozu_manual, dpo.vlastni_vuz, dpo.vyplatit_phm, dpo.poradi,
               u.jmeno, u.prijmeni
        FROM dr_pracovni_osoby dpo
        INNER JOIN user u ON u.id_user = dpo.id_user
        WHERE dpo.id_dr = ?
        ORDER BY dpo.id_slot ASC, COALESCE(dpo.smena_od, "00:00:00") ASC, COALESCE(dpo.smena_do, "00:00:00") ASC, u.jmeno ASC, u.prijmeni ASC
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit cteni dr_pracovni_osoby.');
    }

    $stmt->bind_param('i', $idDr);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

function cb_db_dr_pracovni_osoby_insert(mysqli $conn, int $idDr, int $idUser, int $idSlot, ?string $smenaOd, ?string $smenaDo, ?float $pauza, ?float $odpracovano): int
{
    if ($idDr <= 0 || $idUser <= 0 || !in_array($idSlot, [1, 2], true)) {
        throw new RuntimeException('Neplatna osoba pracovního reportu.');
    }

    $stmt = $conn->prepare('
        INSERT INTO dr_pracovni_osoby (id_dr, id_user, id_slot, smena_od, smena_do, pauza, odpracovano)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            smena_od = VALUES(smena_od),
            smena_do = VALUES(smena_do),
            pauza = VALUES(pauza),
            odpracovano = VALUES(odpracovano)
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit zapis dr_pracovni_osoby.');
    }

    $stmt->bind_param('iiissdd', $idDr, $idUser, $idSlot, $smenaOd, $smenaDo, $pauza, $odpracovano);
    $stmt->execute();
    $idOsoby = (int)$conn->insert_id;
    $stmt->close();

    return $idOsoby;
}

function cb_db_dr_pracovni_osoby_delete(mysqli $conn, int $idDr, int $idUser, int $idSlot): void
{
    if ($idDr <= 0 || $idUser <= 0 || !in_array($idSlot, [1, 2], true)) {
        throw new RuntimeException('Neplatne mazani osoby pracovního reportu.');
    }

    $stmt = $conn->prepare('DELETE FROM dr_pracovni_osoby WHERE id_dr = ? AND id_user = ? AND id_slot = ?');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit mazani dr_pracovni_osoby.');
    }

    $stmt->bind_param('iii', $idDr, $idUser, $idSlot);
    $stmt->execute();
    $stmt->close();
}

function cb_db_dr_pracovni_osoby_update_time(mysqli $conn, int $idDrOsoby, ?string $smenaOd, ?string $smenaDo, ?float $pauza, ?float $odpracovano): void
{
    if ($idDrOsoby <= 0) {
        throw new RuntimeException('Neplatny radek osoby pracovního reportu.');
    }

    $stmt = $conn->prepare('
        UPDATE dr_pracovni_osoby
        SET smena_od = ?, smena_do = ?, pauza = ?, odpracovano = ?
        WHERE id_dr_osoby = ?
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit update casu dr_pracovni_osoby.');
    }

    $stmt->bind_param('ssddi', $smenaOd, $smenaDo, $pauza, $odpracovano, $idDrOsoby);
    $stmt->execute();
    $stmt->close();
}

function cb_db_dr_pracovni_osoby_update_kuryr(mysqli $conn, int $idDrOsoby, ?int $rozvozuManual, int $vlastniVuz, float $vyplatitPhm): void
{
    if ($idDrOsoby <= 0) {
        throw new RuntimeException('Neplatny kuryr pracovního reportu.');
    }

    $car = $vlastniVuz === 1 ? 1 : 0;
    $stmt = $conn->prepare('
        UPDATE dr_pracovni_osoby
        SET rozvozu_manual = ?, vlastni_vuz = ?, vyplatit_phm = ?
        WHERE id_dr_osoby = ?
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit update kurýra dr_pracovni_osoby.');
    }

    $stmt->bind_param('iidi', $rozvozuManual, $car, $vyplatitPhm, $idDrOsoby);
    $stmt->execute();
    $stmt->close();
}

// db/db_dr_pracovni_osoby.php * Konec souboru
