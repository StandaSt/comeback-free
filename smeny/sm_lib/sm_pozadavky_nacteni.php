<?php
declare(strict_types=1);

/*
 * Načte dříve uložené požadavky osoby a obsazené dny volna HPP.
 */

/** @return array{blocks:array<string,array{od:string,do:string}>,own_day:string,occupied:array<string,bool>,saved:bool,saved_at:string} */
function cb_smeny_pozadavky_nacist(mysqli $db, array $person, array $week): array
{
    $data = ['blocks' => [], 'own_day' => '', 'occupied' => [], 'saved' => false, 'saved_at' => ''];
    $idFirma = (int)$person['id_firma'];
    $idPerson = (int)$person['id_person'];
    $startDay = (string)$week['start_day'];

    $stmt = $db->prepare('
        SELECT sp.id_smeny_pozadavek, DATE_FORMAT(sp.odeslano, "%Y-%m-%d %H:%i:%s") AS odeslano
        FROM smeny_tyden st
        INNER JOIN smeny_pozadavek sp ON sp.id_smeny_tyden = st.id_smeny_tyden
        WHERE st.id_firma = ? AND st.start_day = ? AND sp.id_person = ?
        LIMIT 1
    ');
    $stmt->bind_param('isi', $idFirma, $startDay, $idPerson);
    $stmt->execute();
    $requestRow = $stmt->get_result()->fetch_assoc() ?: [];
    $idRequest = (int)($requestRow['id_smeny_pozadavek'] ?? 0);
    $stmt->close();

    if ($idRequest > 0) {
        $data['saved'] = true;
        $data['saved_at'] = (string)($requestRow['odeslano'] ?? '');
        $stmt = $db->prepare('SELECT datum, TIME_FORMAT(cas_od, "%H:%i") AS cas_od, TIME_FORMAT(cas_do, "%H:%i") AS cas_do FROM smeny_pozadavek_blok WHERE id_smeny_pozadavek = ? ORDER BY datum, cas_od');
        $stmt->bind_param('i', $idRequest);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data['blocks'][(string)$row['datum']] = ['od' => (string)$row['cas_od'], 'do' => (string)$row['cas_do']];
        }
        $stmt->close();

        $stmt = $db->prepare('SELECT datum FROM smeny_hpp_volno WHERE id_smeny_pozadavek = ? LIMIT 1');
        $stmt->bind_param('i', $idRequest);
        $stmt->execute();
        $data['own_day'] = (string)($stmt->get_result()->fetch_assoc()['datum'] ?? '');
        $stmt->close();
    }

    if ((int)$person['je_hpp'] === 1 && (int)$person['id_pob'] > 0 && (int)$person['id_slot'] > 0) {
        $endDay = $week['end']->format('Y-m-d');
        $idPob = (int)$person['id_pob'];
        $idSlot = (int)$person['id_slot'];
        $stmt = $db->prepare('SELECT datum, id_person FROM smeny_hpp_volno WHERE id_pob = ? AND id_slot = ? AND datum BETWEEN ? AND ?');
        $stmt->bind_param('iiss', $idPob, $idSlot, $startDay, $endDay);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if ((int)$row['id_person'] !== $idPerson) {
                $data['occupied'][(string)$row['datum']] = true;
            }
        }
        $stmt->close();
    }
    return $data;
}
