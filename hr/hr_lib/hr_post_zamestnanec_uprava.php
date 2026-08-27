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
        $_SESSION['hr_flash'] = ['type' => 'hr_success', 'text' => 'Karta zaměstnance byla uložena.'];
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = ['type' => 'hr_error', 'text' => $e->getMessage()];
        $_SESSION['hr_edit_input'] = $_POST;
    }

    header('Location: ' . cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson) . '&upravit=1'), true, 303);
    exit;
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
        if ($typ < 1 || $typ > 3 || $nastupDate === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $nastupDate->format('d.m.Y') !== $nastupInput || $nastupDate > new DateTimeImmutable('today')) {
            throw new RuntimeException('Vyplňte platný typ vztahu a datum nástupu ve formátu DD.MM.RRRR.');
        }
        [$uvazek, $hodin] = hr_pracovni_pomer_uvazek_z_postu($typ, $_POST);
        [$mzdaTyp, $mzdaCastka] = hr_pracovni_pomer_mzda_z_postu($_POST);
        $benefity = hr_pracovni_pomer_benefity_z_postu($db, $_POST);
        $nastup = $nastupDate->format('Y-m-d');
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
            $stmt = $db->prepare('UPDATE hr_pracovni_uvazek SET platny = 0 WHERE id_pracovni_vztah = ? AND platny = 1');
            $stmt->bind_param('i', $relationId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO hr_pracovni_uvazek (id_pracovni_vztah, uvazek, hodin_tydne, zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, NOW(), 1)');
            $stmt->bind_param('iddi', $relationId, $uvazek, $hodin, $zadalPerson);
            $stmt->execute();
            $stmt->close();
        }

        $currentSalaryType = $current['id_mzda_typ'] === null ? null : (int)$current['id_mzda_typ'];
        $currentSalaryAmount = $current['mzda_castka'] === null ? null : (int)$current['mzda_castka'];
        $salaryChanged = $currentSalaryType !== $mzdaTyp
            || $currentSalaryAmount !== $mzdaCastka
            || $relationId !== (int)$current['id_pracovni_vztah'];
        if ($salaryChanged) {
            $stmt = $db->prepare('UPDATE hr_mzda SET platny = 0 WHERE id_pracovni_vztah = ? AND platny = 1');
            $stmt->bind_param('i', $relationId);
            $stmt->execute();
            $stmt->close();

            $stmt = $db->prepare('INSERT INTO hr_mzda (id_pracovni_vztah, id_mzda_typ, castka, zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, NOW(), 1)');
            $stmt->bind_param('iiii', $relationId, $mzdaTyp, $mzdaCastka, $zadalPerson);
            $stmt->execute();
            $stmt->close();
        }

        $currentBenefits = hr_fetch_employee_work_benefit_ids($db, $relationId);
        sort($currentBenefits);
        if ($currentBenefits !== $benefity) {
            $stmt = $db->prepare('UPDATE hr_benefit SET platny = 0 WHERE id_pracovni_vztah = ? AND platny = 1');
            $stmt->bind_param('i', $relationId);
            $stmt->execute();
            $stmt->close();

            if ($benefity !== []) {
                $stmt = $db->prepare('INSERT INTO hr_benefit (id_pracovni_vztah, id_cis_benefit, zadal, vytvoreno, platny) VALUES (?, ?, ?, NOW(), 1)');
                foreach ($benefity as $idBenefit) {
                    $stmt->bind_param('iii', $relationId, $idBenefit, $zadalPerson);
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
