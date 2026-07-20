<?php
declare(strict_types=1);

/**
 * Nacte posledni dokumenty nahrane k uchazecum.
 */
function hr_fetch_dashboard_documents(mysqli $db, int $limit = 5): array
{
    $limit = max(1, min($limit, 20));
    $sql = "
        SELECT
            d.id_uchazec_dokument,
            d.puvodni_nazev,
            d.zadano,
            dt.nazev AS typ,
            u.jmeno,
            u.prijmeni
        FROM hr_uchazec_dokument d
        INNER JOIN hr_uchazec u
            ON u.id_uchazec = d.id_uchazec
        INNER JOIN hr_uchazec_dokument_typ dt
            ON dt.id_uchazec_dokument_typ = d.id_uchazec_dokument_typ
        WHERE d.aktivni = 1
          AND d.smazano IS NULL
        ORDER BY d.zadano DESC, d.id_uchazec_dokument DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id_uchazec_dokument' => (int)$row['id_uchazec_dokument'],
            'nazev' => (string)$row['puvodni_nazev'],
            'typ' => (string)$row['typ'],
            'osoba' => trim((string)$row['prijmeni'] . ' ' . (string)$row['jmeno']),
            'zadano' => (string)$row['zadano'],
        ];
    }
    $stmt->close();

    return $rows;
}
