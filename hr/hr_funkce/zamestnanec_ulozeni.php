<?php
declare(strict_types=1);

/**
 * Zapise jednoduchou auditni udalost HR modulu.
 */
function hr_zapis_akci(mysqli $db, ?int $idPerson, string $akce, string $detail = ''): void
{
    $detailDb = $detail !== '' ? $detail : null;
    $stmt = $db->prepare('
        INSERT INTO hr_akce (id_person, akce, detail, vytvoreno)
        VALUES (?, ?, ?, NOW())
    ');
    $stmt->bind_param('iss', $idPerson, $akce, $detailDb);
    $stmt->execute();
    $stmt->close();
}

/**
 * Ulozi noveho zamestnance a jeho zakladni navazna HR data.
 */
function hr_insert_employee(mysqli $db, array $data, int $zadalPerson): int
{
    if ($zadalPerson <= 0) {
        throw new RuntimeException('Chybí HR osoba přihlášeného uživatele.');
    }

    $jmeno = trim((string)($data['jmeno'] ?? ''));
    $prijmeni = trim((string)($data['prijmeni'] ?? ''));
    $osobniCislo = trim((string)($data['osobni_cislo'] ?? ''));
    $datumNastupu = trim((string)($data['datum_nastupu'] ?? ''));
    $idVztahTyp = (int)($data['id_pracovni_vztah_typ'] ?? 0);
    $idPob = (int)($data['id_pob'] ?? 0);
    $idSlot = (int)($data['id_slot'] ?? 0);
    $telefon = trim((string)($data['telefon'] ?? ''));
    $email = trim((string)($data['email'] ?? ''));

    if ($jmeno === '' || $prijmeni === '') {
        throw new RuntimeException('Vyplňte jméno a příjmení.');
    }
    if ($datumNastupu === '' || strtotime($datumNastupu) === false) {
        throw new RuntimeException('Vyplňte datum nástupu.');
    }
    if ($idVztahTyp <= 0 || $idPob <= 0 || $idSlot <= 0) {
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
        // Zalozi jednotnou osobu rovnou jako zamestnance.
        $stmt = $db->prepare('
            INSERT INTO hr_person (vztah, osobni_cislo, jmeno, prijmeni, prvni_kontakt, zadal, zadano, aktivni)
            VALUES (2, ?, ?, ?, NOW(), ?, NOW(), 1)
        ');
        $stmt->bind_param('sssi', $osobniCisloDb, $jmeno, $prijmeni, $zadalPerson);
        $stmt->execute();
        $idPerson = (int)$db->insert_id;
        $stmt->close();

        hr_zapis_akci($db, $idPerson, 'zalozeni_osoby', 'Rucne zalozeni zamestnance. Zadal id_person #' . $zadalPerson . '.');
        hr_zapis_akci($db, $idPerson, 'zmena_vztahu', 'Nastaven vztah 2 - zamestnanec. Zadal id_person #' . $zadalPerson . '.');

        // Ulozi zakladni osobni udaje jako aktualni platny zaznam.
        $pohlavi = 'neuvedeno';
        $stmt = $db->prepare('
            INSERT INTO hr_osobni_udaje (id_person, jmeno, prijmeni, pohlavi, zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('isssi', $idPerson, $jmeno, $prijmeni, $pohlavi, $zadalPerson);
        $stmt->execute();
        $stmt->close();

        // Zalozi aktualni pracovni vztah osoby.
        $stmt = $db->prepare('
            INSERT INTO hr_pracovni_vztah (id_person, id_pracovni_vztah_typ, datum_nastupu, zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('iisi', $idPerson, $idVztahTyp, $datumNastupu, $zadalPerson);
        $stmt->execute();
        $stmt->close();

        hr_zapis_akci($db, $idPerson, 'zalozeni_pracovniho_vztahu', 'Zalozen aktualni pracovni vztah. Zadal id_person #' . $zadalPerson . '.');

        // Nastavi hlavni pracoviste osoby.
        $stmt = $db->prepare('
            INSERT INTO hr_person_pracoviste (id_person, id_pob, hlavni, platnost_od, zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('iiisi', $idPerson, $idPob, $hlavni, $datumNastupu, $zadalPerson);
        $stmt->execute();
        $stmt->close();

        // Nastavi hlavni pracovni zarazeni osoby.
        $stmt = $db->prepare('
            INSERT INTO hr_person_zarazeni (id_person, id_slot, hlavni, platnost_od, zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('iiisi', $idPerson, $idSlot, $hlavni, $datumNastupu, $zadalPerson);
        $stmt->execute();
        $stmt->close();

        if ($telefon !== '') {
            // Ulozi hlavni telefon, pokud byl vyplnen.
            $telefonNorm = preg_replace('~\s+~', '', $telefon) ?? $telefon;
            $stmt = $db->prepare('
                INSERT INTO hr_telefon (id_person, id_telefon_typ, telefon, telefon_normalizovany, hlavni, zadal, vytvoreno, platny)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ');
            $stmt->bind_param('iissiii', $idPerson, $telefonTyp, $telefon, $telefonNorm, $hlavni, $zadalPerson, $platny);
            $stmt->execute();
            $stmt->close();
        }

        if ($email !== '') {
            // Ulozi hlavni e-mail, pokud byl vyplnen.
            $stmt = $db->prepare('
                INSERT INTO hr_email (id_person, id_email_typ, email, hlavni, zadal, vytvoreno, platny)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ');
            $stmt->bind_param('iisiii', $idPerson, $emailTyp, $email, $hlavni, $zadalPerson, $platny);
            $stmt->execute();
            $stmt->close();
        }

        $db->commit();
        return $idPerson;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
