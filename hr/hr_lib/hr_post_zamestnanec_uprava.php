<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zpracuje ulozeni zakladnich udaju a pracovniho pomeru zamestnance.
 * Soubor nema HTML vystup a po zpracovani vraci uzivatele zpet na kartu zamestnance.
 */

/**
 * Ulozi vsechny editovane zakladni udaje jedne karty zamestnance.
 */
function hr_post_zamestnanec_uprava(mysqli $db, int $roleId, int $zadalUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        try {
            $zadalPerson = hr_current_person_id($db);
        } catch (RuntimeException $e) {
            if ($roleId !== 1) {
                throw $e;
            }
            $zadalPerson = 1;
        }
        hr_update_employee_basic_data($db, $idPerson, $_POST, $zadalPerson, $zadalUser);
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson) . '&upravit=1'),
            true,
            'Karta zaměstnance byla uložena.'
        );
    } catch (Throwable $e) {
        $_SESSION['hr_edit_input'] = $_POST;
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson) . '&upravit=1'),
            false,
            $e->getMessage(),
            $_POST
        );
    }
}

/**
 * Ulozi zmeny pracovniho pomeru, uvazku, mzdy a benefitu v jedne transakci.
 */
function hr_post_pracovni_pomer_uprava(mysqli $db): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $zadalPerson = hr_current_person_id($db);
        $typ = (int)($_POST['id_pracovni_vztah_typ'] ?? 0);
        $nastupInput = str_replace(',', '.', trim((string)($_POST['datum_nastupu'] ?? '')));
        $nastupDate = DateTimeImmutable::createFromFormat('!d.m.Y', $nastupInput);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!in_array($typ, [1, 2, 3, 5], true) || $nastupDate === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $nastupDate->format('d.m.Y') !== $nastupInput || $nastupDate > new DateTimeImmutable('today')) {
            throw new RuntimeException('Vyplňte platný typ vztahu a datum nástupu ve formátu DD.MM.RRRR.');
        }
        [$uvazek, $hodin] = hr_pracovni_pomer_uvazek_z_postu($typ, $_POST);
        [$mzdaTyp, $mzdaCastka] = hr_pracovni_pomer_mzda_z_postu($_POST);
        $benefity = hr_pracovni_pomer_benefity_z_postu($db, $_POST);
        $nastup = $nastupDate->format('Y-m-d');
        $platnostOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od'] ?? ''), 'datum platnosti');
        if ($platnostOd < $nastup) {
            throw new RuntimeException('Datum platnosti nesmí být před datem nástupu.');
        }
        $db->begin_transaction();
        $current = hr_fetch_employee_work_relation($db, $idPerson);
        if (!is_array($current)) {
            throw new RuntimeException('Aktuální pracovní poměr nebyl nalezen.');
        }
        if ((int)$current['id_pracovni_vztah_typ'] !== $typ || (string)$current['datum_nastupu'] !== $nastup) {
            $oldRelationId = (int)$current['id_pracovni_vztah'];
            $stmt = $db->prepare('UPDATE hr_pracovni_vztah SET platny = 0, zruseno = NOW() WHERE id_pracovni_vztah = ?');
            $stmt->bind_param('i', $oldRelationId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO hr_pracovni_vztah (id_person, id_pracovni_vztah_typ, datum_nastupu, id_person_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, NOW(), 1)');
            $stmt->bind_param('iisi', $idPerson, $typ, $nastup, $zadalPerson);
            $stmt->execute();
            $relationId = (int)$db->insert_id;
            $stmt->close();
        } else {
            $relationId = (int)$current['id_pracovni_vztah'];
        }

        $currentWorkloadCode = $current['uvazek'] === null ? null : (int)$current['uvazek'];
        $currentHours = $current['hodin_tydne'] === null ? null : (float)$current['hodin_tydne'];
        $workloadChanged = $currentWorkloadCode !== $uvazek
            || $currentHours !== $hodin
            || $relationId !== (int)$current['id_pracovni_vztah'];

        if ($workloadChanged) {
            $stmt = $db->prepare('UPDATE hr_pracovni_uvazek SET platny = 0, platnost_do = ?, zruseno = NOW(), id_person_zrusil = ? WHERE id_pracovni_vztah = ? AND platny = 1');
            $stmt->bind_param('sii', $platnostOd, $zadalPerson, $relationId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO hr_pracovni_uvazek (id_pracovni_vztah, uvazek, hodin_tydne, platnost_od, zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, NOW(), 1)');
            $stmt->bind_param('iddsi', $relationId, $uvazek, $hodin, $platnostOd, $zadalPerson);
            $stmt->execute();
            $stmt->close();
        }

        $currentSalaryType = $current['id_mzda_typ'] === null ? null : (int)$current['id_mzda_typ'];
        $currentSalaryAmount = $current['mzda_castka'] === null ? null : (int)$current['mzda_castka'];
        $salaryChanged = $currentSalaryType !== $mzdaTyp
            || $currentSalaryAmount !== $mzdaCastka
            || $relationId !== (int)$current['id_pracovni_vztah'];
        if ($salaryChanged) {
            $stmt = $db->prepare('UPDATE hr_mzda SET platny = 0, platnost_do = ?, zruseno = NOW(), id_person_zrusil = ? WHERE id_pracovni_vztah = ? AND platny = 1');
            $stmt->bind_param('sii', $platnostOd, $zadalPerson, $relationId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO hr_mzda (id_pracovni_vztah, id_mzda_typ, castka, platnost_od, zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, NOW(), 1)');
            $stmt->bind_param('iiisi', $relationId, $mzdaTyp, $mzdaCastka, $platnostOd, $zadalPerson);
            $stmt->execute();
            $stmt->close();
        }

        $currentBenefits = hr_fetch_employee_work_benefit_ids($db, $relationId);
        sort($currentBenefits);
        if ($currentBenefits !== $benefity) {
            $odebrane = array_values(array_diff($currentBenefits, $benefity));
            $pridane = array_values(array_diff($benefity, $currentBenefits));
            if ($odebrane !== []) {
                $stmt = $db->prepare('UPDATE hr_benefit SET platny = 0, platnost_do = ?, zruseno = NOW(), id_person_zrusil = ? WHERE id_pracovni_vztah = ? AND id_cis_benefit = ? AND platny = 1');
                foreach ($odebrane as $idBenefit) {
                    $stmt->bind_param('siii', $platnostOd, $zadalPerson, $relationId, $idBenefit);
                    $stmt->execute();
                }
                $stmt->close();
            }
            if ($pridane !== []) {
                $stmt = $db->prepare('INSERT INTO hr_benefit (id_pracovni_vztah, id_cis_benefit, platnost_od, zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, NOW(), 1)');
                foreach ($pridane as $idBenefit) {
                    $stmt->bind_param('iisi', $relationId, $idBenefit, $platnostOd, $zadalPerson);
                    $stmt->execute();
                }
                $stmt->close();
            }
        }
        $db->commit();
        cb_form_finish(cb_root_url('index.php?m=hr&page=zamestnanec&id='.rawurlencode((string)$idPerson).'&sekce=pracovni_pomer'), true, 'Pracovní poměr byl uložen.');
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        cb_form_finish(cb_root_url('index.php?m=hr&page=zamestnanec&id='.rawurlencode((string)$idPerson).'&sekce=pracovni_pomer'), false, $e->getMessage(), $_POST);
    }
}

/**
 * Pripravi kod uvazku a pocet hodin z formulare podle typu pracovniho vztahu.
 */
function hr_pracovni_pomer_uvazek_z_postu(int $typ, array $input): array
{
    if ($typ === 2) {
        return [null, null];
    }
    if ($typ === 3) {
        return [0, hr_pracovni_pomer_hodiny_z_postu($input, 60.0)];
    }

    $uvazek = (int)($input['uvazek'] ?? -1);
    $pevneHodiny = [1 => 40.0, 2 => 20.0, 4 => 10.0];
    if (isset($pevneHodiny[$uvazek])) {
        return [$uvazek, $pevneHodiny[$uvazek]];
    }
    if ($uvazek !== 0) {
        throw new RuntimeException('Vyberte platný úvazek.');
    }
    return [0, hr_pracovni_pomer_hodiny_z_postu($input, 99.5)];
}

/**
 * Overi pocet hodin: jen cele hodiny nebo pulhodiny v povolenem rozsahu.
 */
function hr_pracovni_pomer_hodiny_z_postu(array $input, float $maximum): float
{
    $hodin = str_replace(',', '.', trim((string)($input['hodin_tydne'] ?? '')));
    if (!preg_match('/^\d+(?:\.[05])?$/', $hodin)) {
        throw new RuntimeException('Počet hodin týdně musí být celý nebo půlhodinový.');
    }
    $value = (float)$hodin;
    if ($value <= 0 || $value > $maximum) {
        throw new RuntimeException('Počet hodin týdně musí být vyšší než 0 a nejvýše ' . str_replace('.', ',', (string)$maximum) . '.');
    }
    return $value;
}

/**
 * Overi typ a celou korunovou castku mzdy z formulare.
 */
function hr_pracovni_pomer_mzda_z_postu(array $input): array
{
    $typ = (int)($input['id_mzda_typ'] ?? 0);
    $castka = trim((string)($input['mzda_castka'] ?? ''));
    if (!in_array($typ, [1, 2], true) || !ctype_digit($castka) || $castka === '0' || strlen($castka) > 10 || (strlen($castka) === 10 && $castka > '4294967295')) {
        throw new RuntimeException('Vyplňte platný typ mzdy a částku v celých Kč.');
    }
    return [$typ, (int)$castka];
}

/**
 * Overi vybrane benefity proti aktualne aktivnimu ciselniku.
 */
function hr_pracovni_pomer_benefity_z_postu(mysqli $db, array $input): array
{
    $benefity = $input['benefity'] ?? [];
    if (!is_array($benefity)) {
        throw new RuntimeException('Výběr benefitů není platný.');
    }

    $selected = [];
    foreach ($benefity as $benefit) {
        if (!is_scalar($benefit) || !ctype_digit((string)$benefit) || (int)$benefit <= 0) {
            throw new RuntimeException('Výběr benefitů není platný.');
        }
        $selected[(int)$benefit] = true;
    }
    $ids = array_keys($selected);
    sort($ids);

    $allowed = [];
    foreach (hr_fetch_active_benefits($db) as $benefit) {
        $allowed[(int)$benefit['id']] = true;
    }
    foreach ($ids as $idBenefit) {
        if (!isset($allowed[$idBenefit])) {
            throw new RuntimeException('Vybraný benefit již není aktivní.');
        }
    }

    return $ids;
}

/** Ulozi nove preruseni aktualniho pracovniho pomeru. */
function hr_post_pracovni_preruseni_ulozit(mysqli $db, int $zadalUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $idVztah = hr_pracovni_vztah_z_postu($db, $idPerson);
        $idTyp = (int)($_POST['id_pracovni_preruseni_typ'] ?? 0);
        $datumOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['datum_od'] ?? ''), 'datum začátku přerušení');
        $datumDoInput = trim((string)($_POST['datum_do'] ?? ''));
        $datumDo = $datumDoInput === '' ? null : hr_pracovni_pomer_datum_z_postu($datumDoInput, 'datum konce přerušení');
        $poznamka = trim((string)($_POST['poznamka'] ?? ''));
        if ($datumDo !== null && $datumDo < $datumOd) {
            throw new RuntimeException('Datum konce přerušení nesmí být před datem začátku.');
        }
        $stmt = $db->prepare('SELECT id_pracovni_preruseni_typ FROM hr_cis_pracovni_preruseni_typ WHERE id_pracovni_preruseni_typ = ? AND aktivni = 1 LIMIT 1');
        $stmt->bind_param('i', $idTyp);
        $stmt->execute();
        $typ = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($typ)) {
            throw new RuntimeException('Vyberte platný typ přerušení.');
        }
        $stmt = $db->prepare('SELECT id_pracovni_preruseni FROM hr_pracovni_preruseni WHERE id_pracovni_vztah = ? AND (datum_do IS NULL OR datum_do >= ?) AND (? IS NULL OR datum_od <= ?) LIMIT 1');
        $stmt->bind_param('isss', $idVztah, $datumOd, $datumDo, $datumDo);
        $stmt->execute();
        $overlap = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($overlap)) {
            throw new RuntimeException('Přerušení se nesmí překrývat s již evidovaným přerušením.');
        }
        $stmt = $db->prepare('INSERT INTO hr_pracovni_preruseni (id_pracovni_vztah, id_pracovni_preruseni_typ, datum_od, datum_do, poznamka, id_user_zadal, vytvoreno) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('iisssi', $idVztah, $idTyp, $datumOd, $datumDo, $poznamka, $zadalUser);
        $stmt->execute();
        $stmt->close();
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Přerušení pracovního poměru bylo uloženo.');
    } catch (Throwable $e) {
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, $e->getMessage(), $_POST);
    }
}

/** Uzavre dosud otevrene preruseni pracovniho pomeru. */
function hr_post_pracovni_preruseni_uzavrit(mysqli $db, int $zadalUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $idPreruseni = (int)($_POST['id_pracovni_preruseni'] ?? 0);
        $datumDo = hr_pracovni_pomer_datum_z_postu((string)($_POST['datum_do'] ?? ''), 'datum konce přerušení');
        $stmt = $db->prepare('SELECT pp.datum_od FROM hr_pracovni_preruseni pp INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = pp.id_pracovni_vztah WHERE pp.id_pracovni_preruseni = ? AND pv.id_person = ? AND pp.datum_do IS NULL LIMIT 1');
        $stmt->bind_param('ii', $idPreruseni, $idPerson);
        $stmt->execute();
        $preruseni = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($preruseni) || $datumDo < (string)$preruseni['datum_od']) {
            throw new RuntimeException('Datum konce přerušení není platný.');
        }
        $stmt = $db->prepare('UPDATE hr_pracovni_preruseni SET datum_do = ?, id_user_zadal = ? WHERE id_pracovni_preruseni = ?');
        $stmt->bind_param('sii', $datumDo, $zadalUser, $idPreruseni);
        $stmt->execute();
        $stmt->close();
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Přerušení pracovního poměru bylo uzavřeno.');
    } catch (Throwable $e) {
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, $e->getMessage(), $_POST);
    }
}

/** Eviduje ukonceni aktualniho pracovniho pomeru. */
function hr_post_pracovni_pomer_ukoncit(mysqli $db, int $zadalUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $idVztah = hr_pracovni_vztah_z_postu($db, $idPerson);
        $idTyp = (int)($_POST['id_pracovni_ukonceni_typ'] ?? 0);
        $datumOznameniInput = trim((string)($_POST['datum_oznameni'] ?? ''));
        $datumOznameni = $datumOznameniInput === '' ? null : hr_pracovni_pomer_datum_z_postu($datumOznameniInput, 'datum oznámení');
        $datumUkonceni = hr_pracovni_pomer_datum_z_postu((string)($_POST['datum_ukonceni'] ?? ''), 'datum ukončení');
        $poznamka = trim((string)($_POST['poznamka'] ?? ''));
        $stmt = $db->prepare('SELECT datum_nastupu, datum_ukonceni FROM hr_pracovni_vztah WHERE id_pracovni_vztah = ? AND id_person = ? AND platny = 1 LIMIT 1');
        $stmt->bind_param('ii', $idVztah, $idPerson);
        $stmt->execute();
        $vztah = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($vztah) || $datumUkonceni < (string)$vztah['datum_nastupu']) {
            throw new RuntimeException('Datum ukončení musí být v den nástupu nebo později.');
        }
        if ($datumOznameni !== null && $datumOznameni > $datumUkonceni) {
            throw new RuntimeException('Datum oznámení nesmí být po datu ukončení.');
        }
        $stmt = $db->prepare('SELECT id_pracovni_ukonceni_typ FROM hr_cis_pracovni_ukonceni_typ WHERE id_pracovni_ukonceni_typ = ? AND aktivni = 1 LIMIT 1');
        $stmt->bind_param('i', $idTyp);
        $stmt->execute();
        $typ = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($typ)) {
            throw new RuntimeException('Vyberte platný důvod ukončení.');
        }
        $db->begin_transaction();
        $stmt = $db->prepare('INSERT INTO hr_pracovni_ukonceni (id_pracovni_vztah, id_pracovni_ukonceni_typ, datum_oznameni, datum_ukonceni, poznamka, id_user_zadal, vytvoreno) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('iisssi', $idVztah, $idTyp, $datumOznameni, $datumUkonceni, $poznamka, $zadalUser);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('UPDATE hr_pracovni_vztah SET datum_ukonceni = ? WHERE id_pracovni_vztah = ?');
        $stmt->bind_param('si', $datumUkonceni, $idVztah);
        $stmt->execute();
        $stmt->close();
        $db->commit();
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Ukončení pracovního poměru bylo uloženo.');
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, $e->getMessage(), $_POST);
    }
}

function hr_pracovni_vztah_z_postu(mysqli $db, int $idPerson): int
{
    $idVztah = (int)($_POST['id_pracovni_vztah'] ?? 0);
    $stmt = $db->prepare('SELECT id_pracovni_vztah FROM hr_pracovni_vztah WHERE id_pracovni_vztah = ? AND id_person = ? AND platny = 1 LIMIT 1');
    $stmt->bind_param('ii', $idVztah, $idPerson);
    $stmt->execute();
    $vztah = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($vztah)) {
        throw new RuntimeException('Pracovní poměr nebyl nalezen.');
    }
    return $idVztah;
}

function hr_pracovni_pomer_datum_z_postu(string $value, string $label): string
{
    $value = str_replace(',', '.', trim($value));
    $date = DateTimeImmutable::createFromFormat('!d.m.Y', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('d.m.Y') !== $value) {
        throw new RuntimeException('Vyplňte platné ' . $label . ' ve formátu DD.MM.RRRR.');
    }
    return $date->format('Y-m-d');
}

function hr_pracovni_pomer_url(int $idPerson): string
{
    return cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson) . '&sekce=pracovni_pomer');
}
