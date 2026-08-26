<?php
declare(strict_types=1);

/*
 * Historicky zapis zakladnich osobnich a kontaktnich udaju zamestnance.
 */

function hr_update_employee_basic_data(mysqli $db, int $idPerson, array $data, int $zadalPerson, int $zadalUser): void
{
    if ($idPerson <= 0 || $zadalPerson <= 0) {
        throw new RuntimeException('Chybí zaměstnanec nebo zapisující HR osoba.');
    }

    $jmeno = trim((string)($data['jmeno'] ?? ''));
    $druheJmeno = trim((string)($data['druhe_jmeno'] ?? ''));
    $prijmeni = trim((string)($data['prijmeni'] ?? ''));
    $osobniCislo = trim((string)($data['osobni_cislo'] ?? ''));
    $datumNarozeni = hr_employee_parse_birth_date((string)($data['datum_narozeni'] ?? ''));
    $rodneCislo = trim((string)($data['rodne_cislo'] ?? ''));
    $cisloObcanskehoPrukazu = trim((string)($data['cislo_obcanskeho_prukazu'] ?? ''));
    $mistoNarozeni = trim((string)($data['misto_narozeni'] ?? ''));
    $statniObcanstvi = trim((string)($data['statni_obcanstvi'] ?? ''));
    $zdrPoj = (int)($data['zdr_poj'] ?? 0);
    $pohlavi = trim((string)($data['pohlavi'] ?? ''));
    $telefon = preg_replace('/\D+/', '', (string)($data['telefon'] ?? '')) ?? '';
    $email = trim((string)($data['email'] ?? ''));

    if ($jmeno === '' || $prijmeni === '') {
        throw new RuntimeException('Vyplňte jméno a příjmení.');
    }
    if (!in_array($pohlavi, ['muž', 'žena', 'jiné'], true)) {
        throw new RuntimeException('Vyberte pohlaví.');
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('E-mail nemá platný tvar.');
    }
    if ($telefon !== '' && strlen($telefon) !== 9) {
        throw new RuntimeException('Telefon musí být české číslo s 9 číslicemi.');
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id_person FROM hr_person WHERE id_person = ? AND aktivni = 1 LIMIT 1');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $person = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($person)) {
            throw new RuntimeException('Zaměstnanec nebyl nalezen.');
        }

        if ($osobniCislo !== '') {
            $stmt = $db->prepare('SELECT id_person FROM hr_person WHERE osobni_cislo = ? AND id_person <> ? LIMIT 1');
            $stmt->bind_param('si', $osobniCislo, $idPerson);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($duplicate)) {
                throw new RuntimeException('Osobní číslo již patří jinému zaměstnanci.');
            }
        }

        if ($zdrPoj > 0) {
            $stmt = $db->prepare('SELECT id_pojistovna FROM hr_cis_pojistovny WHERE kod = ? AND aktivni = 1 LIMIT 1');
            $stmt->bind_param('i', $zdrPoj);
            $stmt->execute();
            $healthInsurer = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!is_array($healthInsurer)) {
                throw new RuntimeException('Vyberte platnou zdravotní pojišťovnu.');
            }
        }

        $stmt = $db->prepare('SELECT * FROM hr_osobni_udaje WHERE id_person = ? AND platny = 1 ORDER BY id_osobni_udaje DESC LIMIT 1');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $currentPersonal = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $samePersonal = is_array($currentPersonal)
            && trim((string)$currentPersonal['jmeno']) === $jmeno
            && trim((string)($currentPersonal['druhe_jmeno'] ?? '')) === $druheJmeno
            && trim((string)$currentPersonal['prijmeni']) === $prijmeni
            && (string)($currentPersonal['datum_narozeni'] ?? '') === $datumNarozeni
            && trim((string)($currentPersonal['rodne_cislo'] ?? '')) === $rodneCislo
            && trim((string)($currentPersonal['cislo_obcanskeho_prukazu'] ?? '')) === $cisloObcanskehoPrukazu
            && trim((string)($currentPersonal['misto_narozeni'] ?? '')) === $mistoNarozeni
            && trim((string)($currentPersonal['statni_obcanstvi'] ?? '')) === $statniObcanstvi
            && (int)($currentPersonal['zdr_poj'] ?? 0) === $zdrPoj
            && trim((string)($currentPersonal['pohlavi'] ?? '')) === $pohlavi;

        if (!$samePersonal) {
            if (is_array($currentPersonal)) {
                $idOsobniUdaje = (int)$currentPersonal['id_osobni_udaje'];
                $stmt = $db->prepare('UPDATE hr_osobni_udaje SET platny = 0, zruseno = NOW() WHERE id_osobni_udaje = ?');
                $stmt->bind_param('i', $idOsobniUdaje);
                $stmt->execute();
                $stmt->close();
            }

            $titulPred = is_array($currentPersonal) ? $currentPersonal['titul_pred'] : null;
            $rodnePrijmeni = is_array($currentPersonal) ? $currentPersonal['rodne_prijmeni'] : null;
            $titulZa = is_array($currentPersonal) ? $currentPersonal['titul_za'] : null;
            $foto = is_array($currentPersonal) ? $currentPersonal['foto'] : null;
            $poznamka = is_array($currentPersonal) ? $currentPersonal['poznamka'] : null;
            $datumNarozeniDb = $datumNarozeni !== '' ? $datumNarozeni : null;
            $rodneCisloDb = $rodneCislo !== '' ? $rodneCislo : null;
            $cisloObcanskehoPrukazuDb = $cisloObcanskehoPrukazu !== '' ? $cisloObcanskehoPrukazu : null;
            $mistoNarozeniDb = $mistoNarozeni !== '' ? $mistoNarozeni : null;
            $statniObcanstviDb = $statniObcanstvi !== '' ? $statniObcanstvi : null;
            $zdrPojDb = $zdrPoj > 0 ? $zdrPoj : null;
            $pohlaviDb = $pohlavi !== '' ? $pohlavi : null;
            $stmt = $db->prepare('INSERT INTO hr_osobni_udaje (id_person, titul_pred, jmeno, druhe_jmeno, prijmeni, rodne_prijmeni, titul_za, foto, datum_narozeni, rodne_cislo, cislo_obcanskeho_prukazu, zdr_poj, statni_obcanstvi, misto_narozeni, pohlavi, poznamka, id_person_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)');
            $types = 'i' . str_repeat('s', 10) . 'i' . str_repeat('s', 4) . 'i';
            $stmt->bind_param($types, $idPerson, $titulPred, $jmeno, $druheJmeno, $prijmeni, $rodnePrijmeni, $titulZa, $foto, $datumNarozeniDb, $rodneCisloDb, $cisloObcanskehoPrukazuDb, $zdrPojDb, $statniObcanstviDb, $mistoNarozeniDb, $pohlaviDb, $poznamka, $zadalPerson);
            $stmt->execute();
            $stmt->close();
        }

        $osobniCisloDb = $osobniCislo !== '' ? $osobniCislo : null;
        $stmt = $db->prepare('UPDATE hr_person SET osobni_cislo = ? WHERE id_person = ?');
        $stmt->bind_param('si', $osobniCisloDb, $idPerson);
        $stmt->execute();
        $stmt->close();

        hr_update_employee_main_phone($db, $idPerson, $telefon, $zadalPerson);
        hr_update_employee_main_email($db, $idPerson, $email, $zadalPerson);
        hr_update_employee_address($db, $idPerson, $data, $zadalPerson, 0, 'adresa_');
        hr_update_employee_address($db, $idPerson, $data, $zadalPerson, 1, 'dorucovaci_');
        hr_update_employee_emergency_contact($db, $idPerson, $data, $zadalUser);
        hr_update_employee_bank_account($db, $idPerson, $data, $zadalPerson);

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function hr_update_employee_address(mysqli $db, int $idPerson, array $data, int $zadalPerson, int $typ, string $prefix): void
{
    $address = [
        'ulice' => trim((string)($data[$prefix . 'ulice'] ?? '')),
        'cp' => trim((string)($data[$prefix . 'cp'] ?? '')),
        'mesto' => trim((string)($data[$prefix . 'mesto'] ?? '')),
        'psc' => trim((string)($data[$prefix . 'psc'] ?? '')),
        'stat' => trim((string)($data[$prefix . 'stat'] ?? '')),
    ];
    $stmt = $db->prepare('SELECT id_adresa, ulice, cp, mesto, psc, stat FROM hr_adresa WHERE id_person = ? AND typ = ? AND platny = 1 ORDER BY id_adresa DESC LIMIT 1');
    $stmt->bind_param('ii', $idPerson, $typ); $stmt->execute(); $current = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $same = is_array($current);
    foreach ($address as $key => $value) { $same = $same && trim((string)($current[$key] ?? '')) === $value; }
    if ($same) { return; }
    if (is_array($current)) { $id = (int)$current['id_adresa']; $stmt = $db->prepare('UPDATE hr_adresa SET platny = 0, zruseno = NOW() WHERE id_adresa = ?'); $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    if (!array_filter($address, static fn(string $value): bool => $value !== '')) { return; }
    $stmt = $db->prepare('INSERT INTO hr_adresa (id_person, ulice, cp, mesto, psc, stat, typ, zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)');
    $stmt->bind_param('isssssii', $idPerson, $address['ulice'], $address['cp'], $address['mesto'], $address['psc'], $address['stat'], $typ, $zadalPerson); $stmt->execute(); $stmt->close();
}

function hr_update_employee_emergency_contact(mysqli $db, int $idPerson, array $data, int $zadalUser): void
{
    $contact = ['jmeno' => trim((string)($data['nouzovy_jmeno'] ?? '')), 'vztah' => trim((string)($data['nouzovy_vztah'] ?? '')), 'telefon' => trim((string)($data['nouzovy_telefon'] ?? '')), 'email' => trim((string)($data['nouzovy_email'] ?? ''))];
    if (array_filter($contact, static fn(string $value): bool => $value !== '') && $contact['jmeno'] === '') { throw new RuntimeException('U nouzového kontaktu vyplňte jméno.'); }
    if ($contact['email'] !== '' && filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false) { throw new RuntimeException('E-mail nouzového kontaktu nemá platný tvar.'); }
    $stmt = $db->prepare('SELECT id_nouzovy_kontakt, jmeno, vztah, telefon, email FROM hr_nouzovy_kontakt WHERE id_person = ? AND platny = 1 AND hlavni = 1 ORDER BY id_nouzovy_kontakt DESC LIMIT 1');
    $stmt->bind_param('i', $idPerson); $stmt->execute(); $current = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $same = is_array($current); foreach ($contact as $key => $value) { $same = $same && trim((string)($current[$key] ?? '')) === $value; } if ($same) { return; }
    if (is_array($current)) { $id = (int)$current['id_nouzovy_kontakt']; $stmt = $db->prepare('UPDATE hr_nouzovy_kontakt SET platny = 0 WHERE id_nouzovy_kontakt = ?'); $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    if ($contact['jmeno'] === '') { return; }
    $hlavni = 1; $stmt = $db->prepare('INSERT INTO hr_nouzovy_kontakt (id_person, jmeno, vztah, telefon, email, hlavni, platny, id_user_zadal, vytvoreno) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())');
    $stmt->bind_param('issssii', $idPerson, $contact['jmeno'], $contact['vztah'], $contact['telefon'], $contact['email'], $hlavni, $zadalUser); $stmt->execute(); $stmt->close();
}

function hr_update_employee_bank_account(mysqli $db, int $idPerson, array $data, int $zadalPerson): void
{
    $account = ['cislo_uctu' => trim((string)($data['ucet_cislo'] ?? '')), 'kod_banky' => trim((string)($data['ucet_kod_banky'] ?? '')), 'iban' => trim((string)($data['ucet_iban'] ?? ''))];
    if (array_filter($account, static fn(string $value): bool => $value !== '') && $account['cislo_uctu'] === '') { throw new RuntimeException('U bankovního účtu vyplňte číslo účtu.'); }
    $stmt = $db->prepare('SELECT id_bankovni_ucet, cislo_uctu, kod_banky, iban FROM hr_bankovni_ucet WHERE id_person = ? AND platny = 1 ORDER BY zmena DESC, id_bankovni_ucet DESC LIMIT 1');
    $stmt->bind_param('i', $idPerson); $stmt->execute(); $current = $stmt->get_result()->fetch_assoc(); $stmt->close();
    $same = is_array($current); foreach ($account as $key => $value) { $same = $same && trim((string)($current[$key] ?? '')) === $value; } if ($same) { return; }
    $stmt = $db->prepare('UPDATE hr_bankovni_ucet SET platny = 0, zmena = NOW(), zadal = ? WHERE id_person = ? AND platny = 1'); $stmt->bind_param('ii', $zadalPerson, $idPerson); $stmt->execute(); $stmt->close();
    if ($account['cislo_uctu'] === '') { return; }
    $hlavni = 1; $stmt = $db->prepare('INSERT INTO hr_bankovni_ucet (id_person, cislo_uctu, kod_banky, iban, hlavni, zadal, zadano, zmena, platny) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)');
    $stmt->bind_param('isssii', $idPerson, $account['cislo_uctu'], $account['kod_banky'], $account['iban'], $hlavni, $zadalPerson); $stmt->execute(); $stmt->close();
}

function hr_employee_parse_birth_date(string $value): string
{
    $value = str_replace(',', '.', trim($value));
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!d.m.Y', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('d.m.Y') !== $value || $date > new DateTimeImmutable('today')) {
        throw new RuntimeException('Datum narození zadejte ve formátu DD.MM.RRRR.');
    }

    return $date->format('Y-m-d');
}

function hr_update_employee_main_phone(mysqli $db, int $idPerson, string $telefon, int $zadalPerson): void
{
    $stmt = $db->prepare('SELECT id_telefon, id_telefon_typ, telefon FROM hr_telefon WHERE id_person = ? AND platny = 1 AND hlavni = 1 ORDER BY id_telefon DESC LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (is_array($current) && (string)$current['telefon'] === $telefon) {
        return;
    }
    if (is_array($current)) {
        $idTelefon = (int)$current['id_telefon'];
        $stmt = $db->prepare('UPDATE hr_telefon SET platny = 0, zruseno = NOW() WHERE id_telefon = ?');
        $stmt->bind_param('i', $idTelefon);
        $stmt->execute();
        $stmt->close();
    }
    if ($telefon !== '') {
        $typ = is_array($current) ? (int)$current['id_telefon_typ'] : 1;
        $hlavni = 1;
        $stmt = $db->prepare('INSERT INTO hr_telefon (id_person, id_telefon_typ, telefon, telefon_normalizovany, hlavni, id_person_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)');
        $stmt->bind_param('iissii', $idPerson, $typ, $telefon, $telefon, $hlavni, $zadalPerson);
        $stmt->execute();
        $stmt->close();
    }
}

function hr_update_employee_main_email(mysqli $db, int $idPerson, string $email, int $zadalPerson): void
{
    $stmt = $db->prepare('SELECT id_email, id_email_typ, email FROM hr_email WHERE id_person = ? AND platny = 1 AND hlavni = 1 ORDER BY id_email DESC LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (is_array($current) && strcasecmp((string)$current['email'], $email) === 0) {
        return;
    }
    if (is_array($current)) {
        $idEmail = (int)$current['id_email'];
        $stmt = $db->prepare('UPDATE hr_email SET platny = 0, zruseno = NOW() WHERE id_email = ?');
        $stmt->bind_param('i', $idEmail);
        $stmt->execute();
        $stmt->close();
    }
    if ($email !== '') {
        $typ = is_array($current) ? (int)$current['id_email_typ'] : 1;
        $hlavni = 1;
        $stmt = $db->prepare('INSERT INTO hr_email (id_person, id_email_typ, email, hlavni, id_person_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, NOW(), 1)');
        $stmt->bind_param('iisii', $idPerson, $typ, $email, $hlavni, $zadalPerson);
        $stmt->execute();
        $stmt->close();
    }
}
