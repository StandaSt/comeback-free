<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
require_once __DIR__ . '/sm_pozadavky_sloty.php';

/* Nacte dostupnost osoby a obsazene dny HPP napric vsemi MAIN a sloty platnymi v den volna. */

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
        ' . cb_osoba_jmeno_sql('ou.id_person',true) . ' AS ulozil
        FROM smeny_pozadavek p
        LEFT JOIN ' . cb_osoby_zdroj_sql() . ' ou ON ou.id_person=p.ulozil_id_person
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
    if ((int)$person['je_hpp'] === 1) {
        cb_smeny_pozadavky_rezervace_schema($db);
        $slotPerson=(int)($person['id_person_sloty']??$idPerson);
        $slotTable=cb_hr_schema_table($db,'slot');
        // Rezervace druhe osoby se porovna s kazdym nasim slotem ucinnym v den volna.
        $stmt = $db->prepare('SELECT DISTINCT r.datum AS volno_datum FROM smeny_pozadavek_volno_slot r
            JOIN smeny_pozadavek p ON p.id_smeny_pozadavek=r.id_smeny_pozadavek
            JOIN '.$slotTable.' s ON s.id_slot=r.id_slot AND s.id_person=? AND s.platny=1 AND s.zruseno IS NULL
                AND (s.platnost_od IS NULL OR s.platnost_od<=r.datum)
                AND (s.platnost_do IS NULL OR s.platnost_do>=r.datum)
            WHERE p.tyden_od=? AND p.id_person<>?
                AND EXISTS (SELECT 1 FROM hr_pracoviste_main m
                    JOIN hr_person hp ON hp.id_person=m.id_person
                    JOIN pobocka b ON b.id_pob=m.id_pob AND b.id_firma=hp.id_firma AND b.aktivni=1
                    WHERE m.id_person=? AND m.id_pob=r.id_pob AND m.platny=1 AND m.zruseno IS NULL
                        AND (m.platnost_od IS NULL OR m.platnost_od<=r.datum)
                        AND (m.platnost_do IS NULL OR m.platnost_do>=r.datum))');
        $stmt->bind_param('isii', $slotPerson, $startDay, $idPerson, $slotPerson);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data['occupied'][(string)$row['volno_datum']] = true;
        }
        $stmt->close();
    }
    return $data;
}
