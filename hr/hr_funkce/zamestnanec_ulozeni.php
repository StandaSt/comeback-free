<?php
declare(strict_types=1);

/**
 * Ulozi noveho zamestnance a jeho zakladni navazna HR data.
 */
function hr_insert_employee(mysqli $db, array $data, int $zadal): int
{
    if ($zadal <= 0) {
        throw new RuntimeException('Chybí přihlášený uživatel.');
    }

    $jmeno = trim((string)($data['jmeno'] ?? ''));
    $prijmeni = trim((string)($data['prijmeni'] ?? ''));
    $osobniCislo = trim((string)($data['osobni_cislo'] ?? ''));
    $stav = (string)($data['stav'] ?? 'priprava');
    $datumNastupu = trim((string)($data['datum_nastupu'] ?? ''));
    $idVztahTyp = (int)($data['id_pracovni_vztah_typ'] ?? 0);
    $idPob = (int)($data['id_pob'] ?? 0);
    $idSlot = (int)($data['id_slot'] ?? 0);
    $telefon = trim((string)($data['telefon'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));

    if ($jmeno === '' || $prijmeni === '') {
        throw new RuntimeException('Vyplňte jméno a příjmení.');
    }
    if (!in_array($stav, ['priprava', 'aktivni', 'preruseny', 'ukonceny'], true)) {
        $stav = 'priprava';
    }
    if ($datumNastupu === '' || strtotime($datumNastupu) === false) {
        throw new RuntimeException('Vyplňte datum nástupu.');
    }
    if ($idVztahTyp <= 0 || $idPob < 0 || $idSlot <= 0) {
        throw new RuntimeException('Vyberte typ vztahu, pobočku a zařazení.');
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('E-mail nemá platný tvar.');
    }

    $osobniCisloDb = $osobniCislo !== '' ? $osobniCislo : null;
    $emailTyp = 1;
    $telefonTyp = 1;
    $hlavni = 1;
    $platny = 1;

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('INSERT INTO hr_zamestnanec (osobni_cislo, stav, zadal, aktivni) VALUES (?, ?, ?, 1)');
        $stmt->bind_param('ssi', $osobniCisloDb, $stav, $zadal);
        $stmt->execute();
        $idZamestnanec = (int)$db->insert_id;
        $stmt->close();

        $pohlavi = 'neuvedeno';
        $stmt = $db->prepare('INSERT INTO hr_osobni_udaje (id_zamestnanec, jmeno, prijmeni, pohlavi, zadal, platny) VALUES (?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('isssi', $idZamestnanec, $jmeno, $prijmeni, $pohlavi, $zadal);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO hr_pracovni_vztah (id_zamestnanec, id_pracovni_vztah_typ, datum_nastupu, zadal, platny) VALUES (?, ?, ?, ?, 1)');
        $stmt->bind_param('iisi', $idZamestnanec, $idVztahTyp, $datumNastupu, $zadal);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO hr_zamestnanec_pracoviste (id_zamestnanec, id_pob, hlavni, platnost_od, zadal, platny) VALUES (?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('iiisi', $idZamestnanec, $idPob, $hlavni, $datumNastupu, $zadal);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO hr_zamestnanec_zarazeni (id_zamestnanec, id_slot, hlavni, platnost_od, zadal, platny) VALUES (?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('iiisi', $idZamestnanec, $idSlot, $hlavni, $datumNastupu, $zadal);
        $stmt->execute();
        $stmt->close();

        if ($telefon !== '') {
            $telefonNorm = preg_replace('~\s+~', '', $telefon) ?? $telefon;
            $stmt = $db->prepare('INSERT INTO hr_telefon (id_zamestnanec, id_telefon_typ, telefon, telefon_normalizovany, hlavni, zadal, platny) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iissiii', $idZamestnanec, $telefonTyp, $telefon, $telefonNorm, $hlavni, $zadal, $platny);
            $stmt->execute();
            $stmt->close();
        }

        if ($email !== '') {
            $stmt = $db->prepare('INSERT INTO hr_email (id_zamestnanec, id_email_typ, email, hlavni, zadal, platny) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iisiii', $idZamestnanec, $emailTyp, $email, $hlavni, $zadal, $platny);
            $stmt->execute();
            $stmt->close();
        }

        $db->commit();
        return $idZamestnanec;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
