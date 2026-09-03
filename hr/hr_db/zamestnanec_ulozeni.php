<?php
declare(strict_types=1);

/**
 * DB zapis noveho zamestnance a jeho zakladnich navaznych zaznamu.
 */

/**
 * Ulozi noveho zamestnance a jeho zakladni navazna HR data.
 */
function hr_insert_employee(mysqli $db, array $data, int $zadalUser): array
{
    if ($zadalUser <= 0) {
        throw new RuntimeException('Chybí přihlášený uživatel.');
    }

    $jmeno = trim((string)($data['jmeno'] ?? ''));
    $prijmeni = trim((string)($data['prijmeni'] ?? ''));
    $osobniCislo = trim((string)($data['osobni_cislo'] ?? ''));
    $datumNastupu = trim((string)($data['datum_nastupu'] ?? ''));
    $idVztahTyp = (int)($data['id_pracovni_vztah_typ'] ?? 0);
    $idPob = (int)($data['id_pob'] ?? 0);
    $slotVolba = (string)($data['id_slot'] ?? '');
    $idSlot = (int)$slotVolba;
    $slotJine = trim((string)($data['slot_jine'] ?? ''));
    $telefon = preg_replace('/\D+/', '', (string)($data['telefon'] ?? '')) ?? '';
    if (strlen($telefon) === 12 && str_starts_with($telefon, '420')) {
        $telefon = substr($telefon, 3);
    }
    if (strlen($telefon) === 14 && str_starts_with($telefon, '00420')) {
        $telefon = substr($telefon, 5);
    }
    $email = trim((string)($data['email'] ?? ''));

    if ($jmeno === '' || $prijmeni === '') {
        throw new RuntimeException('Vyplňte jméno a příjmení.');
    }
    if ($datumNastupu === '' || strtotime($datumNastupu) === false) {
        throw new RuntimeException('Vyplňte datum nástupu.');
    }
    if ($idVztahTyp <= 0 || $idPob <= 0 || ($idSlot <= 0 && ($slotVolba !== '__jine__' || $slotJine === ''))) {
        throw new RuntimeException('Vyberte typ vztahu, pobočku a zařazení.');
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Pro založení uživatelského účtu vyplňte platný e-mail.');
    }
    if ($telefon !== '' && strlen($telefon) !== 9) {
        throw new RuntimeException('Telefon musí být české číslo s 9 číslicemi.');
    }

    $osobniCisloDb = $osobniCislo !== '' ? $osobniCislo : null;
    $emailTyp = 1;
    $telefonTyp = 1;
    $hlavni = 1;
    $platny = 1;
    $zdroj = 'rucne';

    $db->begin_transaction();
    try {
        if ($slotVolba === '__jine__') {
            // Doplni novou polozku do ciselniku zarazeni.
            $stmt = $db->prepare('
                INSERT INTO cis_slot (slot)
                VALUES (?)
            ');
            $stmt->bind_param('s', $slotJine);
            $stmt->execute();
            $idSlot = (int)$db->insert_id;
            $stmt->close();
        }

        // Zalozi samostatny lokalni ucet; heslo se nastavi az pri prvnim vstupu.
        $aktivniUser = 1;
        $schvalenUser = 1;
        $inSystem = 0;
        $hesloHash = null;
        $stmt = $db->prepare('
            INSERT INTO user (jmeno, prijmeni, email, heslo_hash, telefon, aktivni, in_system, schvalen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('sssssiii', $jmeno, $prijmeni, $email, $hesloHash, $telefon, $aktivniUser, $inSystem, $schvalenUser);
        $stmt->execute();
        $idUser = (int)$db->insert_id;
        $stmt->close();

        // Zalozi stabilni identitu zamestnance navazanou na jeho ucet.
        $stmt = $db->prepare('
            INSERT INTO hr_person (id_user, osobni_cislo, zdroj, id_user_zadal, vytvoreno, aktivni)
            VALUES (?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('issi', $idUser, $osobniCisloDb, $zdroj, $zadalUser);
        $stmt->execute();
        $idPerson = (int)$db->insert_id;
        $stmt->close();

        // Ulozi zakladni osobni udaje jako aktualni platny zaznam.
        $pohlavi = 'neuvedeno';
        $stmt = $db->prepare('
            INSERT INTO hr_osobni_udaje (id_person, jmeno, prijmeni, pohlavi, id_user_zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('isssi', $idPerson, $jmeno, $prijmeni, $pohlavi, $zadalUser);
        $stmt->execute();
        $stmt->close();

        // Zalozi aktualni pracovni vztah osoby.
        $stmt = $db->prepare('
            INSERT INTO hr_pracovni_vztah (id_person, id_pracovni_vztah_typ, datum_nastupu, id_user_zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('iisi', $idPerson, $idVztahTyp, $datumNastupu, $zadalUser);
        $stmt->execute();
        $stmt->close();

        // Nastavi hlavni pracoviste osoby.
        $stmt = $db->prepare('
            INSERT INTO hr_pracoviste (id_person, id_pob, hlavni, platnost_od, id_user_zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('iiisi', $idPerson, $idPob, $hlavni, $datumNastupu, $zadalUser);
        $stmt->execute();
        $stmt->close();

        // Nastavi hlavni pracovni zarazeni osoby.
        $stmt = $db->prepare('
            INSERT INTO hr_zarazeni (id_person, id_slot, hlavni, platnost_od, id_user_zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('iiisi', $idPerson, $idSlot, $hlavni, $datumNastupu, $zadalUser);
        $stmt->execute();
        $stmt->close();

        if ($telefon !== '') {
            // Ulozi hlavni telefon, pokud byl vyplnen.
            $stmt = $db->prepare('
                INSERT INTO hr_telefon (id_person, id_telefon_typ, telefon, hlavni, id_user_zadal, vytvoreno, platny)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ');
            $stmt->bind_param('iisiii', $idPerson, $telefonTyp, $telefon, $hlavni, $zadalUser, $platny);
            $stmt->execute();
            $stmt->close();
        }

        if ($email !== '') {
            // Ulozi hlavni e-mail, pokud byl vyplnen.
            $stmt = $db->prepare('
                INSERT INTO hr_email (id_person, id_email_typ, email, hlavni, id_user_zadal, vytvoreno, platny)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ');
            $stmt->bind_param('iisiii', $idPerson, $emailTyp, $email, $hlavni, $zadalUser, $platny);
            $stmt->execute();
            $stmt->close();
        }

        $token = cb_prvni_vstup_vytvor_token($db, $idUser);
        $db->commit();
        return ['id_person' => $idPerson, 'token' => $token, 'email' => $email, 'jmeno' => $jmeno . ' ' . $prijmeni];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
