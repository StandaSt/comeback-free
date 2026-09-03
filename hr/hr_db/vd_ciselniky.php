<?php
declare(strict_types=1);

/**
 * DB ciselniky pro verejne dotazniky a akce naboru.
 */

/**
 * Nacte aktivni typy akci VD pro vyber ve formulari.
 */
function hr_nacti_vd_akce_typy(mysqli $db, int $idVdStav): array
{
    $stmt = $db->prepare('
        SELECT DISTINCT t.id_vd_akce_typ AS id, t.nazev AS label
        FROM hr_cis_vd_akce_typ t
        INNER JOIN hr_cis_vd_akce_vysledek v
            ON v.id_vd_akce_typ = t.id_vd_akce_typ
        WHERE t.aktivni = 1
          AND (v.id_vychozi_vd_stav = ? OR v.id_vychozi_vd_stav IS NULL)
        ORDER BY CASE WHEN ? = 12 AND t.id_vd_akce_typ = 9 THEN 0 ELSE 1 END, t.id_vd_akce_typ
    ');
    $stmt->bind_param('ii', $idVdStav, $idVdStav);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/**
 * Nacte povolene vysledky akci VD a pravidla pro dalsi termin.
 */
function hr_nacti_vd_akce_vysledky(mysqli $db, int $idVdStav): array
{
    $stmt = $db->prepare('
        SELECT
            id_vd_akce_vysledek,
            id_vd_akce_typ,
            vysledek,
            id_cilovy_vd_stav,
            vyzaduje_termin_date,
            vyzaduje_termin_time
        FROM hr_cis_vd_akce_vysledek
        WHERE id_vychozi_vd_stav = ? OR id_vychozi_vd_stav IS NULL
        ORDER BY
            id_vd_akce_typ,
            CASE
                WHEN id_cilovy_vd_stav = 24 THEN 0
                WHEN vysledek = \'Uchazeč se nedostavil\' THEN 1
                ELSE 2
            END,
            id_vd_akce_vysledek
    ');
    $stmt->bind_param('i', $idVdStav);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

function hr_nacti_vd_podminky_ciselniky(mysqli $db): array
{
    $oblasti = [];
    $result = $db->query("SELECT DISTINCT oblast FROM pobocka WHERE aktivni = 1 AND id_pob > 0 AND oblast <> '' ORDER BY CASE WHEN oblast = 'Praha' THEN 0 WHEN oblast = 'Plzeň' THEN 1 ELSE 2 END, oblast");
    while ($row = $result->fetch_assoc()) {
        $oblast = trim((string)$row['oblast']);
        if ($oblast !== '') {
            $oblasti[] = ['id' => $oblast, 'label' => $oblast];
        }
    }
    $result->free();

    return [
        'vztahy' => hr_fetch_lookup($db, 'hr_cis_pracovni_vztah_typ', 'id_pracovni_vztah_typ', 'nazev', 'id_pracovni_vztah_typ'),
        'oblasti' => $oblasti,
        'sloty' => hr_fetch_lookup($db, 'cis_slot', 'id_slot', 'slot', 'CASE WHEN id_slot = 1 THEN 0 WHEN id_slot = 2 THEN 1 WHEN id_slot = 0 THEN 3 ELSE 2 END, id_slot'),
    ];
}
