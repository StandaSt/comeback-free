<?php
// db/db_dr_pracovni.php * K10 pracovní denní report
declare(strict_types=1);

/*
 * DB: dr_pracovni
 *
 * Účel:
 * - malé funkce pro hlavičku pracovního denního reportu
 * - bez finálního ukládání do ostrých tabulek
 */

function cb_db_dr_pracovni_find(mysqli $conn, int $idPob, string $datumReportu): ?array
{
    if ($idPob <= 0 || $datumReportu === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id_dr, datum_reportu, id_pob, oteviral, zaviral,
               hotovost, terminal, stravenky,
               vydaje_benzin, vydaje_auta, vydaje_suroviny, vydaje_ostatni, vydaje_phm_soukrome,
               poznamka, created_by, updated_by, created_at, updated_at
        FROM dr_pracovni
        WHERE id_pob = ?
          AND datum_reportu = ?
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit cteni dr_pracovni.');
    }

    $stmt->bind_param('is', $idPob, $datumReportu);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}

function cb_db_dr_pracovni_ensure(mysqli $conn, int $idPob, string $datumReportu, int $idUser, ?int $oteviral, ?int $zaviral): int
{
    $existing = cb_db_dr_pracovni_find($conn, $idPob, $datumReportu);
    if (is_array($existing)) {
        return (int)$existing['id_dr'];
    }

    $stmt = $conn->prepare('
        INSERT INTO dr_pracovni (datum_reportu, id_pob, oteviral, zaviral, created_by, updated_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit zalozeni dr_pracovni.');
    }

    $createdBy = $idUser > 0 ? $idUser : null;
    $updatedBy = $createdBy;
    $stmt->bind_param('siiiii', $datumReportu, $idPob, $oteviral, $zaviral, $createdBy, $updatedBy);
    $stmt->execute();
    $idDr = (int)$conn->insert_id;
    $stmt->close();

    return $idDr;
}

function cb_db_dr_pracovni_update_user(mysqli $conn, int $idDr, string $field, ?int $idValue, int $idUser): void
{
    if ($idDr <= 0 || !in_array($field, ['oteviral', 'zaviral'], true)) {
        throw new RuntimeException('Neplatny pozadavek pro update dr_pracovni.');
    }

    $stmt = $conn->prepare('UPDATE dr_pracovni SET `' . $field . '` = ?, updated_by = ? WHERE id_dr = ?');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit update dr_pracovni.');
    }

    $updatedBy = $idUser > 0 ? $idUser : null;
    $stmt->bind_param('iii', $idValue, $updatedBy, $idDr);
    $stmt->execute();
    $stmt->close();
}

function cb_db_dr_pracovni_update_money(mysqli $conn, int $idDr, string $field, ?float $value, int $idUser): void
{
    $allowed = [
        'hotovost',
        'terminal',
        'stravenky',
        'vydaje_benzin',
        'vydaje_auta',
        'vydaje_suroviny',
        'vydaje_ostatni',
        'vydaje_phm_soukrome',
    ];
    if ($idDr <= 0 || !in_array($field, $allowed, true)) {
        throw new RuntimeException('Neplatny pozadavek pro update castky dr_pracovni.');
    }

    $stmt = $conn->prepare('UPDATE dr_pracovni SET `' . $field . '` = ?, updated_by = ? WHERE id_dr = ?');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit update castky dr_pracovni.');
    }

    $updatedBy = $idUser > 0 ? $idUser : null;
    $stmt->bind_param('dii', $value, $updatedBy, $idDr);
    $stmt->execute();
    $stmt->close();
}

// db/db_dr_pracovni.php * Konec souboru
