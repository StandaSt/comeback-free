<?php
declare(strict_types=1);

/**
 * Nacte posledni aktualni dokumenty evidovane u HR osob.
 */
function hr_fetch_dashboard_documents(mysqli $db, int $limit = 5): array
{
    $limit = max(1, min($limit, 20));
    $sql = "
        SELECT
            d.id_dokument,
            d.verze,
            d.vytvoreno,
            ds.puvodni_nazev,
            dt.nazev AS typ,
            p.jmeno,
            p.prijmeni
        FROM hr_dokument d
        INNER JOIN hr_person p
            ON p.id_person = d.id_person
        INNER JOIN hr_dokument_typ dt
            ON dt.id_dokument_typ = d.id_dokument_typ
        LEFT JOIN hr_dokument_soubor ds
            ON ds.id_dokument = d.id_dokument
           AND ds.verze = d.verze
           AND ds.poradi = 1
        WHERE d.platny = 1
        ORDER BY d.vytvoreno DESC, d.id_dokument DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        // Zachova vystupni klice pro existujici dashboard sablonu.
        $rows[] = [
            'id_dokument' => (int)$row['id_dokument'],
            'verze' => (int)$row['verze'],
            'nazev' => trim((string)($row['puvodni_nazev'] ?? '')) !== '' ? (string)$row['puvodni_nazev'] : 'Bez souboru',
            'typ' => (string)$row['typ'],
            'osoba' => trim((string)$row['prijmeni'] . ' ' . (string)$row['jmeno']),
            'zadano' => (string)$row['vytvoreno'],
        ];
    }
    $stmt->close();

    return $rows;
}
