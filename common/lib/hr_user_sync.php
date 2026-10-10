<?php
declare(strict_types=1);

/*
 * Obnovuje profil session a zpracuje výslovné ukončení konkrétního zaměstnance.
 * Samotný konec smlouvy ani načtení IS aktivitu zaměstnance nemění.
 * Osoba zůstává ve své firmě a její historické role, pobočky a kontakty se nemažou.
 */

/** Deaktivuje jen danou osobu po výslovném ukončení, pokud nemá další otevřený vztah. */
function cb_hr_user_sync_ukoncene(mysqli $db, int $idPerson): int
{
    if ($idPerson <= 0) {
        return 0;
    }
    $stmt = $db->prepare('
        UPDATE hr_person p
        SET p.aktivni = 0
        WHERE p.id_person = ? AND p.aktivni = 1
          AND EXISTS (
              SELECT 1
              FROM hr_pracovni_vztah pv
              INNER JOIN hr_pracovni_ukonceni u
                  ON u.id_pracovni_vztah = pv.id_pracovni_vztah
                 AND u.datum_ukonceni = pv.datum_ukonceni
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
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $changed = (int)$stmt->affected_rows;
    $stmt->close();
    return $changed;
}

/** Nacte aktualni profil prihlasene osoby; prihlasovaci e-mail zustava v USER. */
function cb_hr_session_profile_refresh(mysqli $db, int $idUser): bool
{
    if ($idUser <= 0) {
        return false;
    }
    $stmt = $db->prepare('
        SELECT p.aktivni, ou.jmeno, ou.prijmeni, u.email,
               (SELECT t.telefon FROM hr_telefon t
                WHERE t.id_person=p.id_person AND t.platny=1 AND t.hlavni=1
                ORDER BY t.id_telefon DESC LIMIT 1) AS telefon
        FROM user u
        INNER JOIN hr_person p ON p.id_person=u.id_user AND p.id_user=u.id_user
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(
            SELECT MAX(ou2.id_osobni_udaje) FROM hr_osobni_udaje ou2
            WHERE ou2.id_person=p.id_person AND ou2.platny=1
        )
        WHERE u.id_user=? LIMIT 1
    ');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $person = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($person) || (int)$person['aktivni'] !== 1) {
        return false;
    }
    $_SESSION['cb_user']['name'] = (string)($person['jmeno'] ?? '');
    $_SESSION['cb_user']['surname'] = (string)($person['prijmeni'] ?? '');
    $_SESSION['cb_user']['telefon'] = (string)($person['telefon'] ?? '');
    $_SESSION['cb_user']['email'] = (string)$person['email'];
    $_SESSION['cb_user']['active'] = true;
    return true;
}
