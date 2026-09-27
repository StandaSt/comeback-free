<?php
declare(strict_types=1);

/* Účel souboru: Načte seznam šablon a detail jedné šablony s jejími sloty. */

/** @return array<int,array<string,mixed>> */
function cb_smeny_sablony_seznam(mysqli $db, array $branches): array
{
    if ($branches === []) {
        return [];
    }
    $ids = implode(',', array_map('intval', array_keys($branches)));
    $result = $db->query('
        SELECT s.id_smeny_sablona, s.id_firma, s.id_pob, s.nazev, s.zmeneno
        FROM smeny_sablona s
        WHERE s.aktivni = 1 AND s.id_pob IN (' . $ids . ')
        ORDER BY s.id_pob, s.nazev
    ');
    $templates = [];
    $templateIndexes = [];
    while ($row = $result->fetch_assoc()) {
        foreach (['id_smeny_sablona', 'id_firma', 'id_pob'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        $positionIds = array_keys(cb_smeny_sablony_pozice($branches[$row['id_pob']]));
        $row['souhrn_minuty'] = [];
        foreach (range(1, 7) as $day) {
            $row['souhrn_minuty'][$day] = array_fill_keys($positionIds, 0);
        }
        $templateIndexes[$row['id_smeny_sablona']] = count($templates);
        $templates[] = $row;
    }
    $result->close();

    if ($templates === []) {
        return [];
    }

    $templateIds = implode(',', array_map('intval', array_keys($templateIndexes)));
    $result = $db->query('
        SELECT id_smeny_sablona, den_tydne, id_slot,
               TIME_FORMAT(cas_od, "%H:%i") AS cas_od,
               TIME_FORMAT(cas_do, "%H:%i") AS cas_do
        FROM smeny_sablona_blok
        WHERE id_smeny_sablona IN (' . $templateIds . ')
        ORDER BY id_smeny_sablona, den_tydne, poradi, id_smeny_sablona_blok
    ');
    while ($block = $result->fetch_assoc()) {
        $idTemplate = (int)$block['id_smeny_sablona'];
        $day = (int)$block['den_tydne'];
        $idSlot = (int)$block['id_slot'];
        if (!isset($templateIndexes[$idTemplate])) {
            continue;
        }
        $templateIndex = $templateIndexes[$idTemplate];
        if (!isset($templates[$templateIndex]['souhrn_minuty'][$day][$idSlot])) {
            continue;
        }
        $start = cb_smeny_sablony_cas_minuty((string)$block['cas_od'], false);
        $end = cb_smeny_sablony_cas_minuty((string)$block['cas_do'], true);
        if ($end > $start) {
            $templates[$templateIndex]['souhrn_minuty'][$day][$idSlot] += $end - $start;
        }
    }
    $result->close();
    return $templates;
}

/** @return array<string,mixed>|null */
function cb_smeny_sablona_nacist(mysqli $db, int $idTemplate, array $branches): ?array
{
    if ($idTemplate <= 0 || $branches === []) {
        return null;
    }
    $stmt = $db->prepare('SELECT id_smeny_sablona, id_firma, id_pob, nazev, zmeneno FROM smeny_sablona WHERE id_smeny_sablona = ? AND aktivni = 1 LIMIT 1');
    $stmt->bind_param('i', $idTemplate);
    $stmt->execute();
    $template = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($template) || !isset($branches[(int)$template['id_pob']])) {
        return null;
    }
    foreach (['id_smeny_sablona', 'id_firma', 'id_pob'] as $key) {
        $template[$key] = (int)$template[$key];
    }
    $template['blocks'] = array_fill(1, 7, []);
    $stmt = $db->prepare('
        SELECT b.id_smeny_sablona_blok, b.den_tydne, b.id_slot,
               TIME_FORMAT(b.cas_od, "%H:%i") AS cas_od,
               TIME_FORMAT(b.cas_do, "%H:%i") AS cas_do,
               b.poradi, s.slot
        FROM smeny_sablona_blok b
        INNER JOIN cis_slot s ON s.id_slot = b.id_slot
        WHERE b.id_smeny_sablona = ?
        ORDER BY b.den_tydne, b.poradi, b.id_smeny_sablona_blok
    ');
    $stmt->bind_param('i', $idTemplate);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach (['id_smeny_sablona_blok', 'den_tydne', 'id_slot', 'poradi'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        $day = (int)$row['den_tydne'];
        if (isset($template['blocks'][$day])) {
            $template['blocks'][$day][] = $row;
        }
    }
    $stmt->close();
    return $template;
}
