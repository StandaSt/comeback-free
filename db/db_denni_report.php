<?php
// db/db_denni_report.php * K10 finální denní report
declare(strict_types=1);

/*
 * DB: denni_report
 *
 * Účel:
 * - malé funkce pro finální hlavičku denního reportu
 */

function cb_db_denni_report_find(mysqli $conn, int $idPob, string $datumReportu): ?array
{
    if ($idPob <= 0 || $datumReportu === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id_dr, datum_reportu, id_pob, oteviral, zaviral,
               hotovost, terminal, stravenky,
               vydaje_benzin, vydaje_auta, vydaje_suroviny, vydaje_ostatni, vydaje_phm_soukrome,
               poznamka, created_by, updated_by, finalized_by, created_at, updated_at, finalized_at
        FROM denni_report
        WHERE id_pob = ?
          AND datum_reportu = ?
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit cteni denni_report.');
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

function cb_db_denni_report_save_from_pracovni(mysqli $conn, array $draft, int $finalizedBy): int
{
    $idPob = (int)($draft['id_pob'] ?? 0);
    $datumReportu = (string)($draft['datum_reportu'] ?? '');
    if ($idPob <= 0 || $datumReportu === '') {
        throw new RuntimeException('Neplatny pracovni report pro finalni ulozeni.');
    }

    $existing = cb_db_denni_report_find($conn, $idPob, $datumReportu);
    if (is_array($existing)) {
        $idDr = (int)$existing['id_dr'];
        $stmt = $conn->prepare('
            UPDATE denni_report
            SET oteviral = ?, zaviral = ?,
                hotovost = ?, terminal = ?, stravenky = ?,
                vydaje_benzin = ?, vydaje_auta = ?, vydaje_suroviny = ?, vydaje_ostatni = ?, vydaje_phm_soukrome = ?,
                poznamka = ?, updated_by = ?, finalized_by = ?, finalized_at = NOW()
            WHERE id_dr = ?
        ');
        if ($stmt === false) {
            throw new RuntimeException('Nepodarilo se pripravit update denni_report.');
        }

        $stmt->bind_param(
            'iiddddddddsiii',
            $draft['oteviral'],
            $draft['zaviral'],
            $draft['hotovost'],
            $draft['terminal'],
            $draft['stravenky'],
            $draft['vydaje_benzin'],
            $draft['vydaje_auta'],
            $draft['vydaje_suroviny'],
            $draft['vydaje_ostatni'],
            $draft['vydaje_phm_soukrome'],
            $draft['poznamka'],
            $finalizedBy,
            $finalizedBy,
            $idDr
        );
        $stmt->execute();
        $stmt->close();
        return $idDr;
    }

    $stmt = $conn->prepare('
        INSERT INTO denni_report (
            datum_reportu, id_pob, oteviral, zaviral,
            hotovost, terminal, stravenky,
            vydaje_benzin, vydaje_auta, vydaje_suroviny, vydaje_ostatni, vydaje_phm_soukrome,
            poznamka, created_by, updated_by, finalized_by, finalized_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit insert denni_report.');
    }

    $createdBy = (int)($draft['created_by'] ?? 0);
    $updatedBy = (int)($draft['updated_by'] ?? 0);
    $stmt->bind_param(
        'siiiddddddddsiii',
        $datumReportu,
        $idPob,
        $draft['oteviral'],
        $draft['zaviral'],
        $draft['hotovost'],
        $draft['terminal'],
        $draft['stravenky'],
        $draft['vydaje_benzin'],
        $draft['vydaje_auta'],
        $draft['vydaje_suroviny'],
        $draft['vydaje_ostatni'],
        $draft['vydaje_phm_soukrome'],
        $draft['poznamka'],
        $createdBy,
        $updatedBy,
        $finalizedBy
    );
    $stmt->execute();
    $idDr = (int)$conn->insert_id;
    $stmt->close();

    return $idDr;
}

// db/db_denni_report.php * Konec souboru
