<?php
declare(strict_types=1);
/* Účel: Načte pouze vlastní oznámení pro modál a sestaví sedm dní jeho uloženého týdenního rozpisu. */

/** Číselné ID v odkazu není oprávnění; cizí i neexistující zpráva vrací stejný prázdný výsledek. */
function cb_smeny_oznameni_detail(mysqli $db, int $id, int $person): ?array
{
    if ($id <= 0 || $person <= 0) return null;
    $stmt = cb_smeny_planovani_sql($db,
        'SELECT o.*,p.nazev pobocka,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) jmeno FROM smeny_oznameni o JOIN smeny_rozpis r ON r.id_smeny_rozpis=o.id_smeny_rozpis JOIN pobocka p ON p.id_pob=r.id_pob LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(x.id_osobni_udaje) FROM hr_osobni_udaje x WHERE x.id_person=o.id_person AND x.platny=1) WHERE o.id_smeny_oznameni=? AND o.id_person=?',
        'ii', [$id, $person]);
    $notice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $notice;
}

/** Čteme neměnný obsah oznámení. Směna po půlnoci patří stejně jako v plánovači k předchozímu dni. */
function cb_smeny_oznameni_dny(array $content): array
{
    $monday = new DateTimeImmutable($content['tyden_od'], new DateTimeZone('Europe/Prague'));
    $names = ['Pondělí','Úterý','Středa','Čtvrtek','Pátek','Sobota','Neděle'];
    $days = [];
    foreach ($names as $i => $name) {
        $date = $monday->modify('+'.$i.' days');
        $days[$date->format('Y-m-d')] = ['nazev'=>$name, 'datum'=>$date->format('j. n.'), 'smeny'=>[]];
    }
    $shifts = $content['smeny'] ?? [];
    usort($shifts, static fn(array $a, array $b): int => strcmp($a['zacatek'], $b['zacatek']));
    foreach ($shifts as $shift) {
        $start = new DateTimeImmutable($shift['zacatek'], new DateTimeZone('Europe/Prague'));
        $end = new DateTimeImmutable($shift['konec'], new DateTimeZone('Europe/Prague'));
        // Odečítáme místní hodinu, nikoli šest uplynulých hodin přes změnu letního času.
        $day = ((int)$start->format('H') < 6 ? $start->modify('-1 day') : $start)->format('Y-m-d');
        if (isset($days[$day])) {
            $days[$day]['smeny'][] = $start->format('H:i').'–'.$end->format('H:i')
                .($start->format('Y-m-d') !== $day ? ' ('.$start->format('j. n.').')' : '')
                .($start->format('Y-m-d') !== $end->format('Y-m-d') ? ' (do '.$end->format('j. n.').')' : '')
                .(!empty($shift['pozice']) ? ' · '.$shift['pozice'] : '');
        }
    }
    return array_values($days);
}
