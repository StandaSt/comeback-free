<?php
declare(strict_types=1);

/**
 * DB dotazy pro dokumenty zobrazovane v HR prehledu.
 */

/**
 * Nacte posledni aktualni dokumenty evidovane u VD nebo zamestnancu.
 */
function hr_fetch_prehled_documents(mysqli $db, int $limit = 5): array
{
    $limit = max(1, min($limit, 20));
    $sql = "
        SELECT
            d.id_dokument,
            d.verze,
            d.vytvoreno,
            ds.ulozeny_nazev,
            dt.nazev AS typ,
            vd.jmeno AS vd_jmeno,
            vd.prijmeni AS vd_prijmeni,
            ou.jmeno AS person_jmeno,
            ou.prijmeni AS person_prijmeni
        FROM hr_dokument d
        INNER JOIN hr_cis_dokument_typ dt
            ON dt.id_dokument_typ = d.id_dokument_typ
        LEFT JOIN hr_vd vd
            ON vd.id_vd = d.id_vd
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = d.id_person
           AND ou.platny = 1
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
        $vdJmeno = trim((string)$row['vd_prijmeni'] . ' ' . (string)$row['vd_jmeno']);
        $personJmeno = trim((string)$row['person_prijmeni'] . ' ' . (string)$row['person_jmeno']);

        // Zachova vystupni klice pro existujici sablonu prehledu.
        $rows[] = [
            'id_dokument' => (int)$row['id_dokument'],
            'verze' => (int)$row['verze'],
            'nazev' => trim((string)($row['ulozeny_nazev'] ?? '')) !== '' ? (string)$row['ulozeny_nazev'] : 'Bez souboru',
            'typ' => (string)$row['typ'],
            'osoba' => $personJmeno !== '' ? $personJmeno : ($vdJmeno !== '' ? $vdJmeno : '-'),
            'zadano' => (string)$row['vytvoreno'],
        ];
    }
    $stmt->close();

    return $rows;
}
