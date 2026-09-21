<?php
declare(strict_types=1);

/*
 * Promítne účinné ukončení posledního pracovního poměru do hr_person.aktivni.
 * Osoba zůstává ve své firmě a její historické role, pobočky a kontakty se nemažou.
 */

function cb_hr_user_sync_ukoncene(mysqli $db): int
{
    $stmt = $db->prepare('
        UPDATE hr_person p
        SET p.aktivni = 0
        WHERE p.aktivni = 1
          AND EXISTS (
              SELECT 1
              FROM hr_pracovni_vztah pv
              WHERE pv.id_person = p.id_person
                AND pv.platny = 1
                AND pv.datum_ukonceni IS NOT NULL
                AND pv.datum_ukonceni < CURDATE()
          )
          AND NOT EXISTS (
              SELECT 1
              FROM hr_pracovni_vztah pv
              WHERE pv.id_person = p.id_person
                AND pv.platny = 1
                AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
          )
    ');
    $stmt->execute();
    $changed = (int)$stmt->affected_rows;
    $stmt->close();
    return $changed;
}
