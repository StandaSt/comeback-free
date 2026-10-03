<?php
declare(strict_types=1);

/* Účel: Načte uložený týden dostupnosti V2, volbu Kdykoliv a rezervace volna HPP. */

/** Vrátí jednotný prázdný stav pro formulář i osobu bez uloženého týdne. */
function cb_smeny_pozadavky_prazdne(): array
{
    return ['blocks' => [], 'own_day' => '', 'occupied' => [], 'saved' => false,
        'saved_at' => '', 'saved_by' => '', 'anytime' => false];
}

/** Kalendářní data se odvozují od týdne; v DB stačí číslo dne 1–7. */
function cb_smeny_pozadavky_nacist(mysqli $db, array $person, array $week): array
{
    $data = cb_smeny_pozadavky_prazdne();
    $idPerson = (int)$person['id_person'];
    $startDay = (string)$week['start_day'];
    $stmt = $db->prepare('SELECT p.id_smeny_pozadavek, p.rezim, p.volno_datum, p.ulozeno,
        TRIM(CONCAT_WS(" ", ou.prijmeni, ou.jmeno)) AS ulozil
        FROM smeny_pozadavek p
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(
            SELECT MAX(o.id_osobni_udaje) FROM hr_osobni_udaje o
            WHERE o.id_person=p.ulozil_id_person AND o.platny=1)
        WHERE p.id_person=? AND p.tyden_od=? LIMIT 1');
    $stmt->bind_param('is', $idPerson, $startDay);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row !== null) {
        $data['saved'] = true;
        $data['saved_at'] = (string)$row['ulozeno'];
        $data['saved_by'] = (string)$row['ulozil'];
        $data['anytime'] = $row['rezim'] === 'kdykoliv';
        $data['own_day'] = (string)($row['volno_datum'] ?? '');
        $idRequest = (int)$row['id_smeny_pozadavek'];
        $stmt = $db->prepare('SELECT den_tydne, TIME_FORMAT(cas_od,"%H:%i") cas_od,
            TIME_FORMAT(cas_do,"%H:%i") cas_do FROM smeny_pozadavek_den
            WHERE id_smeny_pozadavek=? ORDER BY den_tydne');
        $stmt->bind_param('i', $idRequest);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($block = $result->fetch_assoc()) {
            $date = $week['start']->modify('+' . ((int)$block['den_tydne'] - 1) . ' days')->format('Y-m-d');
            $data['blocks'][$date] = ['od' => (string)$block['cas_od'], 'do' => (string)$block['cas_do']];
        }
        $stmt->close();
    }
    if ((int)$person['je_hpp'] === 1 && (int)$person['id_pob'] > 0 && (int)$person['id_slot'] > 0) {
        $idBranch = (int)$person['id_pob'];
        $idSlot = (int)$person['id_slot'];
        $stmt = $db->prepare('SELECT volno_datum FROM smeny_pozadavek
            WHERE volno_id_pob=? AND volno_id_slot=? AND tyden_od=? AND id_person<>?');
        $stmt->bind_param('iisi', $idBranch, $idSlot, $startDay, $idPerson);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data['occupied'][(string)$row['volno_datum']] = true;
        }
        $stmt->close();
    }
    return $data;
}
