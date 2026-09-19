<?php
declare(strict_types=1);

/**
 * DB zapis noveho zamestnance a jeho zakladnich navaznych zaznamu.
 */

/**
 * Ulozi noveho zamestnance a jeho zakladni navazna HR data.
 */
function hr_insert_employee(mysqli $db, array $data, array $files, int $zadalUser): array
{
    if ($zadalUser <= 0) {
        throw new CbUserVisibleException('Přihlášení vypršelo. Přihlaste se prosím znovu.');
    }
    $firemniUzivatel = cb_firemni_pristup_uzivatel($db, $zadalUser);
    $idFirma = (int)$firemniUzivatel['id_firma'];
    if ($idFirma <= 0) {
        throw new CbUserVisibleException('Vyberte firmu nového zaměstnance.');
    }

    $titulPred = trim((string)($data['titul_pred'] ?? ''));
    $jmeno = trim((string)($data['jmeno'] ?? ''));
    $druheJmeno = trim((string)($data['druhe_jmeno'] ?? ''));
    $prijmeni = trim((string)($data['prijmeni'] ?? ''));
    $rodnePrijmeni = trim((string)($data['rodne_prijmeni'] ?? ''));
    $titulZa = trim((string)($data['titul_za'] ?? ''));
    $datumNarozeni = hr_employee_parse_birth_date((string)($data['datum_narozeni'] ?? ''));
    $mistoNarozeni = trim((string)($data['misto_narozeni'] ?? ''));
    $statniObcanstvi = trim((string)($data['statni_obcanstvi'] ?? ''));
    $rodneCislo = hr_employee_validate_birth_number((string)($data['rodne_cislo'] ?? ''));
    $cisloObcanskehoPrukazu = trim((string)($data['cislo_obcanskeho_prukazu'] ?? ''));
    $pohlavi = trim((string)($data['pohlavi'] ?? ''));
    $zdrPoj = (int)($data['zdr_poj'] ?? 0);
    $poznamka = trim((string)($data['poznamka'] ?? ''));
    $osobniCislo = trim((string)($data['osobni_cislo'] ?? ''));
    $datumNastupu = trim((string)($data['datum_nastupu'] ?? ''));
    $idVztahTyp = (int)($data['id_pracovni_vztah_typ'] ?? 0);
    $idPobocky = hr_employee_normalize_branch_ids($data['id_pob'] ?? []);
    $hlavniPobockaVolba = trim((string)($data['id_pob_hlavni'] ?? ''));
    $maHlavniPobocku = preg_match('/^\d+$/', $hlavniPobockaVolba) === 1;
    $idPobHlavni = $maHlavniPobocku ? (int)$hlavniPobockaVolba : -1;
    $slotVolba = trim((string)($data['id_slot'] ?? ''));
    $maPozici = preg_match('/^\d+$/', $slotVolba) === 1;
    $idSlot = $maPozici ? (int)$slotVolba : -1;
    $telefon = preg_replace('/\D+/', '', (string)($data['telefon'] ?? '')) ?? '';
    $telefonZahranicni = trim((string)($data['telefon_zahranicni'] ?? ''));
    if (strlen($telefon) === 12 && str_starts_with($telefon, '420')) {
        $telefon = substr($telefon, 3);
    }
    if (strlen($telefon) === 14 && str_starts_with($telefon, '00420')) {
        $telefon = substr($telefon, 5);
    }
    $email = trim((string)($data['email'] ?? ''));
    $idRoleHr = (int)($data['id_role_hr'] ?? 9);

    if ($jmeno === '' || $prijmeni === '') {
        throw new CbUserVisibleException('Vyplňte jméno a příjmení.');
    }
    hr_employee_validate_title($db, $titulPred, 1);
    hr_employee_validate_title($db, $titulZa, 2);
    if (!in_array($pohlavi, ['muž', 'žena', 'jiné', 'neuvedeno'], true)) {
        throw new CbUserVisibleException('Vyberte pohlaví.');
    }
    if ($datumNastupu === '' || strtotime($datumNastupu) === false) {
        throw new CbUserVisibleException('Vyplňte datum nástupu.');
    }
    if (
        $idVztahTyp <= 0
        || $idPobocky === []
        || !$maHlavniPobocku
        || !in_array($idPobHlavni, $idPobocky, true)
        || !$maPozici
    ) {
        throw new CbUserVisibleException('Vyberte typ vztahu, alespoň jednu pobočku, hlavní pobočku a pozici.');
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new CbUserVisibleException('Pro založení uživatelského účtu vyplňte platný e-mail.');
    }
    if (!in_array($idRoleHr, [3, 5, 7, 9], true)) {
        throw new CbUserVisibleException('Vyberte povolenou pracovní roli.');
    }
    if ($idRoleHr === 3 && !cb_pravo_ma(316)) {
        throw new CbUserVisibleException('Nemáte právo přidělit roli Manager.');
    }
    if ($telefon !== '' && strlen($telefon) !== 9) {
        throw new CbUserVisibleException('Telefon musí být české číslo s 9 číslicemi.');
    }
    if (mb_strlen($telefonZahranicni, 'UTF-8') > 30) {
        throw new CbUserVisibleException('Zahraniční telefon je příliš dlouhý.');
    }

    $limits = [
        'Titul před jménem' => [$titulPred, 50], 'Jméno' => [$jmeno, 60], 'Druhé jméno' => [$druheJmeno, 60],
        'Příjmení' => [$prijmeni, 80], 'Rodné příjmení' => [$rodnePrijmeni, 80], 'Titul za jménem' => [$titulZa, 50],
        'Místo narození' => [$mistoNarozeni, 120], 'Státní občanství' => [$statniObcanstvi, 100], 'Rodné číslo' => [$rodneCislo, 20],
        'Číslo občanského průkazu' => [$cisloObcanskehoPrukazu, 30], 'Poznámka' => [$poznamka, 1000],
    ];
    foreach ($limits as $label => [$value, $limit]) {
        if (mb_strlen($value, 'UTF-8') > $limit) {
            throw new CbUserVisibleException($label . ' je příliš dlouhé.');
        }
    }

    $osobniCisloDb = $osobniCislo !== '' ? $osobniCislo : null;
    $datumNarozeniDb = $datumNarozeni !== '' ? $datumNarozeni : null;
    $rodneCisloDb = $rodneCislo !== '' ? $rodneCislo : null;
    $cisloObcanskehoPrukazuDb = $cisloObcanskehoPrukazu !== '' ? $cisloObcanskehoPrukazu : null;
    $zdrPojDb = $zdrPoj > 0 ? $zdrPoj : null;
    $emailTyp = 1;
    $telefonTyp = 1;
    $hlavni = 1;
    $platny = 1;
    $zdroj = 'rucne';

    $photoPath = null;
    $transactionStarted = false;
    try {
        $photoPath = hr_store_employee_photo($files['foto'] ?? null);
        $db->begin_transaction();
        $transactionStarted = true;
        $marks = implode(',', array_fill(0, count($idPobocky), '?'));
        $types = 'i' . str_repeat('i', count($idPobocky));
        $stmt = $db->prepare('SELECT COUNT(*) AS pocet FROM pobocka WHERE id_firma = ? AND aktivni = 1 AND id_pob IN (' . $marks . ')');
        $bind = [&$types, &$idFirma];
        foreach ($idPobocky as $index => $idPob) {
            $bind[] = &$idPobocky[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $platnePobocky = (int)($stmt->get_result()->fetch_assoc()['pocet'] ?? 0);
        $stmt->close();
        if ($platnePobocky !== count($idPobocky)) {
            throw new CbUserVisibleException('Vybrané pobočky nepatří do firmy personalisty.');
        }
        if ($zdrPoj > 0) {
            $stmt = $db->prepare('SELECT id_pojistovna FROM hr_cis_pojistovny WHERE kod = ? AND aktivni = 1 LIMIT 1');
            $stmt->bind_param('i', $zdrPoj);
            $stmt->execute();
            $healthInsurer = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!is_array($healthInsurer)) {
                throw new CbUserVisibleException('Vyberte platnou zdravotní pojišťovnu.');
            }
        }
        $stmt = $db->prepare('SELECT id_slot FROM cis_slot WHERE id_slot = ? AND aktivni = 1 LIMIT 1');
        $stmt->bind_param('i', $idSlot);
        $stmt->execute();
        $platnaPozice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($platnaPozice)) {
            throw new CbUserVisibleException('Vyberte aktivní pozici.');
        }

        // Zalozi samostatny lokalni ucet; SPOJENÍ jej pozve až po kompletní přípravě.
        $aktivniUser = 1;
        $schvalenUser = 1;
        $inSystem = 0;
        $zdrojUser = 3;
        $hesloHash = null;
        $stmt = $db->prepare('
            INSERT INTO user (id_firma, jmeno, prijmeni, email, heslo_hash, telefon, aktivni, in_system, schvalen, zdroj)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->bind_param('isssssiiii', $idFirma, $jmeno, $prijmeni, $email, $hesloHash, $telefon, $aktivniUser, $inSystem, $schvalenUser, $zdrojUser);
        $stmt->execute();
        $idUser = (int)$db->insert_id;
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO user_role (id_user, id_role) VALUES (?, ?)');
        $stmt->bind_param('ii', $idUser, $idRoleHr);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('INSERT INTO user_pobocka (id_user, id_pob, main) VALUES (?, ?, ?)');
        foreach ($idPobocky as $idPob) {
            $hlavniPobocka = $idPob === $idPobHlavni ? 1 : 0;
            $stmt->bind_param('iii', $idUser, $idPob, $hlavniPobocka);
            $stmt->execute();
        }
        $stmt->close();

        // Zalozi stabilni identitu zamestnance navazanou na jeho ucet.
        $stmt = $db->prepare('
            INSERT INTO hr_person (id_firma, id_user, osobni_cislo, zdroj, id_user_zadal, vytvoreno, aktivni)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        $stmt->bind_param('iissi', $idFirma, $idUser, $osobniCisloDb, $zdroj, $zadalUser);
        $stmt->execute();
        $idPerson = (int)$db->insert_id;
        $stmt->close();

        // Ulozi kompletní osobní údaje jako aktuální platný záznam.
        $stmt = $db->prepare('
            INSERT INTO hr_osobni_udaje (id_person, titul_pred, jmeno, druhe_jmeno, prijmeni, rodne_prijmeni, titul_za, foto, datum_narozeni, rodne_cislo, cislo_obcanskeho_prukazu, zdr_poj, statni_obcanstvi, misto_narozeni, pohlavi, poznamka, id_user_zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ');
        $types = 'i' . str_repeat('s', 10) . 'i' . str_repeat('s', 4) . 'i';
        $stmt->bind_param($types, $idPerson, $titulPred, $jmeno, $druheJmeno, $prijmeni, $rodnePrijmeni, $titulZa, $photoPath, $datumNarozeniDb, $rodneCisloDb, $cisloObcanskehoPrukazuDb, $zdrPojDb, $statniObcanstvi, $mistoNarozeni, $pohlavi, $poznamka, $zadalUser);
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

        // Ulozi vsechna zvolena pracoviste a jedno z nich oznaci jako hlavni.
        $stmt = $db->prepare('
            INSERT INTO hr_pracoviste (id_person, id_pob, hlavni, platnost_od, id_user_zadal, vytvoreno, platny)
            VALUES (?, ?, ?, ?, ?, NOW(), 1)
        ');
        foreach ($idPobocky as $idPob) {
            $hlavniPobocka = $idPob === $idPobHlavni ? 1 : 0;
            $stmt->bind_param('iiisi', $idPerson, $idPob, $hlavniPobocka, $datumNastupu, $zadalUser);
            $stmt->execute();
        }
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

        if ($telefonZahranicni !== '') {
            $telefonTyp = 1;
            $hlavniTelefon = 0;
            $poznamkaTelefonu = 'Zahraniční telefon';
            $stmt = $db->prepare('INSERT INTO hr_telefon (id_person, id_telefon_typ, telefon, hlavni, poznamka, id_user_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)');
            $stmt->bind_param('iisisi', $idPerson, $telefonTyp, $telefonZahranicni, $hlavniTelefon, $poznamkaTelefonu, $zadalUser);
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

        hr_update_employee_address($db, $idPerson, $data, $zadalUser, 0, 'adresa_');
        hr_update_employee_address($db, $idPerson, $data, $zadalUser, 1, 'dorucovaci_');
        hr_update_employee_emergency_contact($db, $idPerson, $data, $zadalUser);

        $db->commit();
        $transactionStarted = false;
        return ['id_person' => $idPerson, 'id_user' => $idUser];
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $db->rollback();
        }
        if (is_string($photoPath) && $photoPath !== '') {
            $photoFile = dirname(__DIR__) . '/' . $photoPath;
            if (is_file($photoFile)) {
                unlink($photoFile);
            }
        }
        throw $e;
    }
}

/** @return int[] */
function hr_employee_normalize_branch_ids(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $value) {
        $text = trim((string)$value);
        if (preg_match('/^\d+$/', $text) !== 1) {
            continue;
        }
        $id = (int)$text;
        if ($id >= 0) {
            $ids[$id] = true;
        }
    }

    return array_map('intval', array_keys($ids));
}

function hr_employee_validate_title(mysqli $db, string $title, int $placement): void
{
    if ($title === '') {
        return;
    }

    $stmt = $db->prepare('SELECT 1 FROM hr_cis_tituly WHERE zkratka = ? AND umisteni = ? LIMIT 1');
    $stmt->bind_param('si', $title, $placement);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    if (!$valid) {
        throw new CbUserVisibleException('Vyberte platný titul.');
    }
}

function hr_employee_validate_birth_number(string $value): string
{
    $number = preg_replace('/[\s\/]/', '', trim($value)) ?? '';
    if ($number === '') {
        return '';
    }
    if (!preg_match('/^\d{9,10}$/', $number) || preg_match('/^(\d)\1+$/', $number)) {
        throw new CbUserVisibleException('Zadejte platné rodné číslo ve tvaru YYMMDD/XXX(X).');
    }

    $yearPart = (int)substr($number, 0, 2);
    $month = (int)substr($number, 2, 2);
    $day = (int)substr($number, 4, 2);
    if ($month >= 71 && $month <= 82) {
        $month -= 70;
    } elseif ($month >= 51 && $month <= 62) {
        $month -= 50;
    } elseif ($month >= 21 && $month <= 32) {
        $month -= 20;
    }
    $year = 1900 + $yearPart;
    if (strlen($number) === 10 && $yearPart <= (int)date('y')) {
        $year += 100;
    }
    if ($month < 1 || $month > 12 || !checkdate($month, $day, $year)) {
        throw new CbUserVisibleException('Rodné číslo neobsahuje platné datum narození.');
    }
    if (strlen($number) === 10 && (int)$number % 11 !== 0) {
        throw new CbUserVisibleException('Rodné číslo není dělitelné 11.');
    }

    return substr($number, 0, 6) . '/' . substr($number, 6);
}

function hr_store_employee_photo(mixed $upload): ?string
{
    if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int)($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new CbUserVisibleException('Fotografii se nepodařilo přijmout. Vyberte ji znovu.');
    }
    if ((int)($upload['size'] ?? 0) < 1 || (int)$upload['size'] > 2 * 1024 * 1024) {
        throw new CbUserVisibleException('Fotografie může mít nejvýše 2 MB.');
    }
    $temporaryFile = (string)($upload['tmp_name'] ?? '');
    if ($temporaryFile === '' || !is_uploaded_file($temporaryFile)) {
        throw new CbUserVisibleException('Nahraný soubor fotografie není platný.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryFile);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new CbUserVisibleException('Fotografie musí být JPEG, PNG nebo WebP.');
    }
    if (@getimagesize($temporaryFile) === false) {
        throw new CbUserVisibleException('Nahraný soubor není čitelný obrázek.');
    }
    $directory = dirname(__DIR__) . '/img/zamestnanci';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Nelze připravit úložiště fotografií.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryFile, $directory . '/' . $filename)) {
        throw new RuntimeException('Fotografii se nepodařilo uložit.');
    }
    return 'hr/img/zamestnanci/' . $filename;
}
