<?php
// db/db_denni_report_osoby.php * K10 osoby finálního reportu
declare(strict_types=1);

/*
 * DB: denni_report_osoby
 *
 * Účel:
 * - malé funkce pro osoby ve finálním denním reportu
 */

function cb_db_denni_report_osoby_replace_from_pracovni(mysqli $conn, int $idDrFinal, int $idDrPracovni): void
{
    if ($idDrFinal <= 0 || $idDrPracovni <= 0) {
        throw new RuntimeException('Neplatne id reportu pro kopii osob.');
    }

    $stmtDelete = $conn->prepare('DELETE FROM denni_report_osoby WHERE id_dr = ?');
    if ($stmtDelete === false) {
        throw new RuntimeException('Nepodarilo se pripravit mazani denni_report_osoby.');
    }
    $stmtDelete->bind_param('i', $idDrFinal);
    $stmtDelete->execute();
    $stmtDelete->close();

    $stmtInsert = $conn->prepare('
        INSERT INTO denni_report_osoby (
            id_dr, id_user, id_slot, smena_od, smena_do, pauza, odpracovano,
            rozvozu_manual, vlastni_vuz, vyplatit_phm, poradi
        )
        SELECT
            ?, id_user, id_slot, smena_od, smena_do, pauza, odpracovano,
            rozvozu_manual, vlastni_vuz, vyplatit_phm, poradi
        FROM dr_pracovni_osoby
        WHERE id_dr = ?
        ORDER BY id_slot ASC, COALESCE(smena_od, "00:00:00") ASC, COALESCE(smena_do, "00:00:00") ASC, id_dr_osoby ASC
    ');
    if ($stmtInsert === false) {
        throw new RuntimeException('Nepodarilo se pripravit kopii denni_report_osoby.');
    }

    $stmtInsert->bind_param('ii', $idDrFinal, $idDrPracovni);
    $stmtInsert->execute();
    $stmtInsert->close();
}

function cb_db_denni_report_osoby_list(mysqli $conn, int $idDr): array
{
    if ($idDr <= 0) {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT dro.id_dr_osoby, dro.id_dr, dro.id_user, dro.id_slot,
               dro.smena_od, dro.smena_do, dro.pauza, dro.odpracovano,
               dro.rozvozu_manual, dro.vlastni_vuz, dro.vyplatit_phm, dro.poradi,
               u.jmeno, u.prijmeni
        FROM denni_report_osoby dro
        INNER JOIN user u ON u.id_user = dro.id_user
        WHERE dro.id_dr = ?
        ORDER BY dro.id_slot ASC, COALESCE(dro.smena_od, "00:00:00") ASC, COALESCE(dro.smena_do, "00:00:00") ASC, u.jmeno ASC, u.prijmeni ASC
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit cteni denni_report_osoby.');
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

// db/db_denni_report_osoby.php * Konec souboru
