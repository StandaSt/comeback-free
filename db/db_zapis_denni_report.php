<?php
// db/db_zapis_denni_report.php * K10 finalni zapis denniho reportu
declare(strict_types=1);

function cb_db_zapis_denni_report(mysqli $conn, int $idPob, string $datumReportu, int $idUser, array $workdayRange, ?float $rozdilForm, ?float $colPomerForm): int
{
    if ($idPob <= 0 || $datumReportu === '' || $idUser <= 0) {
        throw new RuntimeException('Neplatny denni report pro ulozeni.');
    }

    $draftRow = cb_db_dr_pracovni_find($conn, $idPob, $datumReportu);
    if (!is_array($draftRow)) {
        throw new RuntimeException('Pracovni report neexistuje.');
    }

    $idDr = (int)($draftRow['id_dr'] ?? 0);
    $rawPersonRows = cb_db_dr_pracovni_osoby_list($conn, $idDr);
    $restiaSummary = cb_denni_report_restia_summary($conn, $idPob, $workdayRange);
    $personRows = cb_denni_report_person_rows($rawPersonRows);
    $kuryrData = cb_denni_report_kuryr_delivery_data($conn, $idPob, $workdayRange, $personRows['kuryr']);
    $kuryrRowsByUser = [];
    foreach ($kuryrData['kuryr_rows'] as $row) {
        $kuryrRowsByUser[(int)($row['id_user'] ?? 0)] = $row;
    }

    $oteviral = isset($draftRow['oteviral']) ? (int)$draftRow['oteviral'] : null;
    $zaviral = isset($draftRow['zaviral']) ? (int)$draftRow['zaviral'] : null;
    $oteviralText = cb_denni_report_user_full_name_by_id($conn, $oteviral);
    $zaviralText = cb_denni_report_user_full_name_by_id($conn, $zaviral);
    $poznamka = trim((string)($draftRow['poznamka'] ?? ''));
    $zdroj = 2;
    $stav = 1;
    $platny = 1;

    $conn->begin_transaction();
    try {
        $stmtReport = $conn->prepare('
            INSERT INTO reporty_is
                (datum_reportu, id_pob, oteviral, zaviral, oteviral_text, zaviral_text, poznamka, zdroj, stav, zadal, platny)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_reportu = LAST_INSERT_ID(id_reportu),
                oteviral = VALUES(oteviral),
                zaviral = VALUES(zaviral),
                oteviral_text = VALUES(oteviral_text),
                zaviral_text = VALUES(zaviral_text),
                poznamka = VALUES(poznamka),
                zdroj = VALUES(zdroj),
                stav = VALUES(stav),
                zadal = VALUES(zadal),
                platny = VALUES(platny)
        ');
        if ($stmtReport === false) {
            throw new RuntimeException('Nelze pripravit ulozeni reporty_is.');
        }
        $stmtReport->bind_param('siiisssiiii', $datumReportu, $idPob, $oteviral, $zaviral, $oteviralText, $zaviralText, $poznamka, $zdroj, $stav, $idUser, $platny);
        $stmtReport->execute();
        $idReportu = (int)$conn->insert_id;
        $stmtReport->close();
        if ($idReportu <= 0) {
            throw new RuntimeException('Nelze zjistit ID ulozeneho reportu.');
        }

        $stmtPokladna = $conn->prepare('
            INSERT INTO reporty_is_pokladna
                (id_reportu, hotovost, terminal, stravenky, rozdil, vydaje_benzin, vydaje_auta, vydaje_suroviny, vydaje_ostatni, vydaje_phm_soukrome, vydaje_doklady_ks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                hotovost = VALUES(hotovost),
                terminal = VALUES(terminal),
                stravenky = VALUES(stravenky),
                rozdil = VALUES(rozdil),
                vydaje_benzin = VALUES(vydaje_benzin),
                vydaje_auta = VALUES(vydaje_auta),
                vydaje_suroviny = VALUES(vydaje_suroviny),
                vydaje_ostatni = VALUES(vydaje_ostatni),
                vydaje_phm_soukrome = VALUES(vydaje_phm_soukrome),
                vydaje_doklady_ks = VALUES(vydaje_doklady_ks)
        ');
        if ($stmtPokladna === false) {
            throw new RuntimeException('Nelze pripravit ulozeni pokladny.');
        }
        $hotovost = (float)($draftRow['hotovost'] ?? 0);
        $terminal = (float)($draftRow['terminal'] ?? 0);
        $stravenky = (float)($draftRow['stravenky'] ?? 0);
        $rozdil = $rozdilForm === null ? 0.0 : (float)$rozdilForm;
        $vydajeBenzin = (float)($draftRow['vydaje_benzin'] ?? 0);
        $vydajeAuta = (float)($draftRow['vydaje_auta'] ?? 0);
        $vydajeSuroviny = (float)($draftRow['vydaje_suroviny'] ?? 0);
        $vydajeOstatni = (float)($draftRow['vydaje_ostatni'] ?? 0);
        $vydajePhmSoukrome = (float)($draftRow['vydaje_phm_soukrome'] ?? 0);
        $vydajeDokladyKs = cb_denni_report_docs_count_from_person_rows($rawPersonRows);
        $stmtPokladna->bind_param('idddddddddi', $idReportu, $hotovost, $terminal, $stravenky, $rozdil, $vydajeBenzin, $vydajeAuta, $vydajeSuroviny, $vydajeOstatni, $vydajePhmSoukrome, $vydajeDokladyKs);
        $stmtPokladna->execute();
        $stmtPokladna->close();

        $stmtRestia = $conn->prepare('
            INSERT INTO reporty_is_restia
                (id_reportu, trzba, wolt, bolt, damejidlo, web, wolt_cash, dj_cash, col_pomer, zrusene_obj_ks, zrusene_obj_kc, zpozdene_rozvozy_5_min, make_time_prumer_sec, objednavky_nezrusene_ks, nase_rozvozy_ks, woltdrive_ks, woltdrive_pozde_5_min, woltdrive_pozde_nase_vina, nase_rozvozy_pozde_pomer, woltdrive_zpozdene_ks, doruceno_vcas_pomer, woltdrive_zpozdene_pomer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                trzba = VALUES(trzba), wolt = VALUES(wolt), bolt = VALUES(bolt), damejidlo = VALUES(damejidlo), web = VALUES(web),
                wolt_cash = VALUES(wolt_cash), dj_cash = VALUES(dj_cash), col_pomer = VALUES(col_pomer),
                zrusene_obj_ks = VALUES(zrusene_obj_ks), zrusene_obj_kc = VALUES(zrusene_obj_kc),
                zpozdene_rozvozy_5_min = VALUES(zpozdene_rozvozy_5_min), make_time_prumer_sec = VALUES(make_time_prumer_sec),
                objednavky_nezrusene_ks = VALUES(objednavky_nezrusene_ks), nase_rozvozy_ks = VALUES(nase_rozvozy_ks),
                woltdrive_ks = VALUES(woltdrive_ks), woltdrive_pozde_5_min = VALUES(woltdrive_pozde_5_min),
                woltdrive_pozde_nase_vina = VALUES(woltdrive_pozde_nase_vina), nase_rozvozy_pozde_pomer = VALUES(nase_rozvozy_pozde_pomer),
                woltdrive_zpozdene_ks = VALUES(woltdrive_zpozdene_ks), doruceno_vcas_pomer = VALUES(doruceno_vcas_pomer),
                woltdrive_zpozdene_pomer = VALUES(woltdrive_zpozdene_pomer)
        ');
        if ($stmtRestia === false) {
            throw new RuntimeException('Nelze pripravit ulozeni Restie.');
        }
        $trzba = (float)$restiaSummary['trzba'];
        $wolt = (float)$restiaSummary['wolt'];
        $bolt = (float)$restiaSummary['bolt'];
        $damejidlo = (float)$restiaSummary['dj'];
        $web = (float)$restiaSummary['web'];
        $woltCash = (float)$restiaSummary['wolt_cash'];
        $djCash = (float)$restiaSummary['dj_cash'];
        $colPomer = $colPomerForm;
        $zruseneKs = (int)$restiaSummary['cancel_count'];
        $zruseneKc = (float)$restiaSummary['cancel_value'];
        $zpozdeneRozvozy = (int)$restiaSummary['delay_count'];
        $makeTime = $restiaSummary['make_time_avg_sec'] === null ? null : (int)$restiaSummary['make_time_avg_sec'];
        $objednavkyNezrusene = (int)$restiaSummary['orders_total'];
        $naseRozvozy = (int)$restiaSummary['own_deliveries'];
        $woltdriveKs = 0;
        $woltdrivePozde = (int)$restiaSummary['woltdrive_late'];
        $woltdriveNaseVina = 0;
        $naseRozvozyPozdePomer = null;
        $woltdriveZpozdeneKs = $woltdrivePozde;
        $dorucenoVcasPomer = null;
        $woltdriveZpozdenePomer = null;
        $stmtRestia->bind_param('iddddddddidiiiiiiididd', $idReportu, $trzba, $wolt, $bolt, $damejidlo, $web, $woltCash, $djCash, $colPomer, $zruseneKs, $zruseneKc, $zpozdeneRozvozy, $makeTime, $objednavkyNezrusene, $naseRozvozy, $woltdriveKs, $woltdrivePozde, $woltdriveNaseVina, $naseRozvozyPozdePomer, $woltdriveZpozdeneKs, $dorucenoVcasPomer, $woltdriveZpozdenePomer);
        $stmtRestia->execute();
        $stmtRestia->close();

        $stmtDeletePeople = $conn->prepare('DELETE FROM reporty_is_osoby WHERE id_reportu = ?');
        if ($stmtDeletePeople === false) {
            throw new RuntimeException('Nelze pripravit mazani osob reportu.');
        }
        $stmtDeletePeople->bind_param('i', $idReportu);
        $stmtDeletePeople->execute();
        $stmtDeletePeople->close();

        $stmtPerson = $conn->prepare('
            INSERT INTO reporty_is_osoby
                (id_reportu, id_user, jmeno, prijmeni, slot, smena_od, smena_do, pauza, odpracovano, rozvozu_restia, rozvozu_manual, rozvozu_celkem, vlastni_vuz, vyplatit_phm)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmtPerson === false) {
            throw new RuntimeException('Nelze pripravit ulozeni osob reportu.');
        }
        foreach ($rawPersonRows as $row) {
            $idOsobyUser = (int)($row['id_user'] ?? 0);
            $slot = (int)($row['id_slot'] ?? 0);
            $fullName = cb_denni_report_person_full_name($row['jmeno'] ?? '', $row['prijmeni'] ?? '');
            $kuryrRow = $slot === 2 ? ($kuryrRowsByUser[$idOsobyUser] ?? []) : [];
            $jmeno = trim((string)($row['jmeno'] ?? ''));
            $prijmeni = trim((string)($row['prijmeni'] ?? ''));
            $smenaOd = $row['smena_od'] === null ? null : (string)$row['smena_od'];
            $smenaDo = $row['smena_do'] === null ? null : (string)$row['smena_do'];
            $pauza = (float)($row['pauza'] ?? 0);
            $odpracovano = (float)($row['odpracovano'] ?? 0);
            $rozvozuRestia = (int)($kuryrRow['delivery_restia'] ?? 0);
            $rozvozuManual = (int)($row['rozvozu_manual'] ?? 0);
            $rozvozuCelkem = $slot === 2 ? ($rozvozuRestia + $rozvozuManual) : 0;
            $vlastniVuz = (int)($row['vlastni_vuz'] ?? 0);
            $vyplatitPhm = (float)($row['vyplatit_phm'] ?? 0);
            if ($fullName === '') {
                continue;
            }
            $stmtPerson->bind_param('iississddiiiid', $idReportu, $idOsobyUser, $jmeno, $prijmeni, $slot, $smenaOd, $smenaDo, $pauza, $odpracovano, $rozvozuRestia, $rozvozuManual, $rozvozuCelkem, $vlastniVuz, $vyplatitPhm);
            $stmtPerson->execute();
        }
        $stmtPerson->close();

        $conn->commit();
        return $idReportu;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function cb_db_zapis_denni_report_people_from_post(array $post, string $type): array
{
    $prefix = ($type === 'kuryr') ? 'kuryr' : 'instor';
    $slot = ($type === 'kuryr') ? 2 : 1;
    $ids = $post[$prefix . '_id_user'] ?? [];
    $starts = $post[$prefix . '_zacatek'] ?? [];
    $ends = $post[$prefix . '_konec'] ?? [];
    $breaks = $post[$prefix . '_pauza_hod'] ?? [];
    $hours = $post[$prefix . '_hodiny'] ?? [];
    $restias = $post[$prefix . '_pocet_rozvozu_restia'] ?? [];
    $manuals = $post[$prefix . '_pocet_rozvozu_manual'] ?? [];
    $cars = $post[$prefix . '_vlastni_vuz'] ?? [];
    $phms = $post[$prefix . '_vyplatit_phm'] ?? [];

    if (!is_array($ids)) {
        return [];
    }

    $rows = [];
    foreach ($ids as $index => $idUserRaw) {
        $idUser = (int)$idUserRaw;
        if ($idUser <= 0) {
            continue;
        }
        $rows[] = [
            'id_user' => $idUser,
            'slot' => $slot,
            'smena_od' => trim((string)($starts[$index] ?? '')),
            'smena_do' => trim((string)($ends[$index] ?? '')),
            'pauza' => cb_vcr_float($breaks[$index] ?? 0),
            'odpracovano' => cb_vcr_float($hours[$index] ?? 0),
            'rozvozu_restia' => ($type === 'kuryr') ? max(0, (int)($restias[$index] ?? 0)) : 0,
            'rozvozu_manual' => ($type === 'kuryr') ? max(0, (int)($manuals[$index] ?? 0)) : 0,
            'vlastni_vuz' => ($type === 'kuryr' && (int)($cars[$index] ?? 0) === 1) ? 1 : 0,
            'vyplatit_phm' => ($type === 'kuryr') ? cb_vcr_float($phms[$index] ?? 0) : 0.0,
        ];
    }

    return $rows;
}

function cb_db_zapis_denni_report_from_form(mysqli $conn, int $idPob, string $datumReportu, int $idUser, array $restiaSummary, array $post, ?float $rozdilForm, ?float $colPomerForm): int
{
    if ($idPob <= 0 || $datumReportu === '' || $idUser <= 0) {
        throw new RuntimeException('Neplatny denni report pro ulozeni formulare.');
    }

    $oteviral = (int)($post['oteviral'] ?? 0);
    $zaviral = (int)($post['zaviral'] ?? 0);
    $oteviral = $oteviral > 0 ? $oteviral : null;
    $zaviral = $zaviral > 0 ? $zaviral : null;
    $oteviralText = cb_denni_report_user_full_name_by_id($conn, $oteviral);
    $zaviralText = cb_denni_report_user_full_name_by_id($conn, $zaviral);
    $poznamka = trim((string)($post['poznamka'] ?? ''));
    $zdroj = 2;
    $stav = 1;
    $platny = 1;
    $cash = cb_vcr_cash_from_post($post);
    $rawPersonRows = array_merge(
        cb_db_zapis_denni_report_people_from_post($post, 'instor'),
        cb_db_zapis_denni_report_people_from_post($post, 'kuryr')
    );

    $conn->begin_transaction();
    try {
        $stmtReport = $conn->prepare('
            INSERT INTO reporty_is
                (datum_reportu, id_pob, oteviral, zaviral, oteviral_text, zaviral_text, poznamka, zdroj, stav, zadal, platny)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                id_reportu = LAST_INSERT_ID(id_reportu),
                oteviral = VALUES(oteviral),
                zaviral = VALUES(zaviral),
                oteviral_text = VALUES(oteviral_text),
                zaviral_text = VALUES(zaviral_text),
                poznamka = VALUES(poznamka),
                zdroj = VALUES(zdroj),
                stav = VALUES(stav),
                zadal = VALUES(zadal),
                platny = VALUES(platny)
        ');
        if ($stmtReport === false) {
            throw new RuntimeException('Nelze pripravit ulozeni historickeho reportu.');
        }
        $stmtReport->bind_param('siiisssiiii', $datumReportu, $idPob, $oteviral, $zaviral, $oteviralText, $zaviralText, $poznamka, $zdroj, $stav, $idUser, $platny);
        $stmtReport->execute();
        $idReportu = (int)$conn->insert_id;
        $stmtReport->close();
        if ($idReportu <= 0) {
            throw new RuntimeException('Nelze zjistit ID historickeho reportu.');
        }

        $stmtPokladna = $conn->prepare('
            INSERT INTO reporty_is_pokladna
                (id_reportu, hotovost, terminal, stravenky, rozdil, vydaje_benzin, vydaje_auta, vydaje_suroviny, vydaje_ostatni, vydaje_phm_soukrome, vydaje_doklady_ks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                hotovost = VALUES(hotovost),
                terminal = VALUES(terminal),
                stravenky = VALUES(stravenky),
                rozdil = VALUES(rozdil),
                vydaje_benzin = VALUES(vydaje_benzin),
                vydaje_auta = VALUES(vydaje_auta),
                vydaje_suroviny = VALUES(vydaje_suroviny),
                vydaje_ostatni = VALUES(vydaje_ostatni),
                vydaje_phm_soukrome = VALUES(vydaje_phm_soukrome),
                vydaje_doklady_ks = VALUES(vydaje_doklady_ks)
        ');
        if ($stmtPokladna === false) {
            throw new RuntimeException('Nelze pripravit ulozeni historicke pokladny.');
        }
        $hotovost = (float)($cash['hotovost'] ?? 0);
        $terminal = (float)($cash['terminal'] ?? 0);
        $stravenky = (float)($cash['stravenky'] ?? 0);
        $rozdil = $rozdilForm === null ? 0.0 : (float)$rozdilForm;
        $vydajeBenzin = (float)($cash['vydaje_benzin'] ?? 0);
        $vydajeAuta = (float)($cash['vydaje_auta'] ?? 0);
        $vydajeSuroviny = (float)($cash['vydaje_suroviny'] ?? 0);
        $vydajeOstatni = (float)($cash['vydaje_ostatni'] ?? 0);
        $vydajePhmSoukrome = (float)($cash['vydaje_phm_soukrome'] ?? 0);
        $vydajeDokladyKs = 0;
        foreach ($rawPersonRows as $row) {
            if ((int)($row['slot'] ?? 0) === 2 && (float)($row['vyplatit_phm'] ?? 0) > 0) {
                $vydajeDokladyKs++;
            }
        }
        $stmtPokladna->bind_param('idddddddddi', $idReportu, $hotovost, $terminal, $stravenky, $rozdil, $vydajeBenzin, $vydajeAuta, $vydajeSuroviny, $vydajeOstatni, $vydajePhmSoukrome, $vydajeDokladyKs);
        $stmtPokladna->execute();
        $stmtPokladna->close();

        $stmtRestia = $conn->prepare('
            INSERT INTO reporty_is_restia
                (id_reportu, trzba, wolt, bolt, damejidlo, web, wolt_cash, dj_cash, col_pomer, zrusene_obj_ks, zrusene_obj_kc, zpozdene_rozvozy_5_min, make_time_prumer_sec, objednavky_nezrusene_ks, nase_rozvozy_ks, woltdrive_ks, woltdrive_pozde_5_min, woltdrive_pozde_nase_vina, nase_rozvozy_pozde_pomer, woltdrive_zpozdene_ks, doruceno_vcas_pomer, woltdrive_zpozdene_pomer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                trzba = VALUES(trzba), wolt = VALUES(wolt), bolt = VALUES(bolt), damejidlo = VALUES(damejidlo), web = VALUES(web),
                wolt_cash = VALUES(wolt_cash), dj_cash = VALUES(dj_cash), col_pomer = VALUES(col_pomer),
                zrusene_obj_ks = VALUES(zrusene_obj_ks), zrusene_obj_kc = VALUES(zrusene_obj_kc),
                zpozdene_rozvozy_5_min = VALUES(zpozdene_rozvozy_5_min), make_time_prumer_sec = VALUES(make_time_prumer_sec),
                objednavky_nezrusene_ks = VALUES(objednavky_nezrusene_ks), nase_rozvozy_ks = VALUES(nase_rozvozy_ks),
                woltdrive_ks = VALUES(woltdrive_ks), woltdrive_pozde_5_min = VALUES(woltdrive_pozde_5_min),
                woltdrive_pozde_nase_vina = VALUES(woltdrive_pozde_nase_vina), nase_rozvozy_pozde_pomer = VALUES(nase_rozvozy_pozde_pomer),
                woltdrive_zpozdene_ks = VALUES(woltdrive_zpozdene_ks), doruceno_vcas_pomer = VALUES(doruceno_vcas_pomer),
                woltdrive_zpozdene_pomer = VALUES(woltdrive_zpozdene_pomer)
        ');
        if ($stmtRestia === false) {
            throw new RuntimeException('Nelze pripravit ulozeni historicke Restie.');
        }
        $trzba = (float)($restiaSummary['trzba'] ?? 0);
        $wolt = (float)($restiaSummary['wolt'] ?? 0);
        $bolt = (float)($restiaSummary['bolt'] ?? 0);
        $damejidlo = (float)($restiaSummary['dj'] ?? 0);
        $web = (float)($restiaSummary['web'] ?? 0);
        $woltCash = (float)($restiaSummary['wolt_cash'] ?? 0);
        $djCash = (float)($restiaSummary['dj_cash'] ?? 0);
        $colPomer = $colPomerForm;
        $zruseneKs = (int)($restiaSummary['cancel_count'] ?? 0);
        $zruseneKc = (float)($restiaSummary['cancel_value'] ?? 0);
        $zpozdeneRozvozy = (int)($restiaSummary['delay_count'] ?? 0);
        $makeTime = isset($restiaSummary['make_time_avg_sec']) ? (int)$restiaSummary['make_time_avg_sec'] : null;
        $objednavkyNezrusene = (int)($restiaSummary['orders_total'] ?? 0);
        $naseRozvozy = (int)($restiaSummary['own_deliveries'] ?? 0);
        $woltdriveKs = 0;
        $woltdrivePozde = (int)($restiaSummary['woltdrive_late'] ?? 0);
        $woltdriveNaseVina = 0;
        $naseRozvozyPozdePomer = null;
        $woltdriveZpozdeneKs = $woltdrivePozde;
        $dorucenoVcasPomer = null;
        $woltdriveZpozdenePomer = null;
        $stmtRestia->bind_param('iddddddddidiiiiiiididd', $idReportu, $trzba, $wolt, $bolt, $damejidlo, $web, $woltCash, $djCash, $colPomer, $zruseneKs, $zruseneKc, $zpozdeneRozvozy, $makeTime, $objednavkyNezrusene, $naseRozvozy, $woltdriveKs, $woltdrivePozde, $woltdriveNaseVina, $naseRozvozyPozdePomer, $woltdriveZpozdeneKs, $dorucenoVcasPomer, $woltdriveZpozdenePomer);
        $stmtRestia->execute();
        $stmtRestia->close();

        $stmtDeletePeople = $conn->prepare('DELETE FROM reporty_is_osoby WHERE id_reportu = ?');
        if ($stmtDeletePeople === false) {
            throw new RuntimeException('Nelze pripravit mazani historickych osob reportu.');
        }
        $stmtDeletePeople->bind_param('i', $idReportu);
        $stmtDeletePeople->execute();
        $stmtDeletePeople->close();

        $stmtPerson = $conn->prepare('
            INSERT INTO reporty_is_osoby
                (id_reportu, id_user, jmeno, prijmeni, slot, smena_od, smena_do, pauza, odpracovano, rozvozu_restia, rozvozu_manual, rozvozu_celkem, vlastni_vuz, vyplatit_phm)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmtPerson === false) {
            throw new RuntimeException('Nelze pripravit ulozeni historickych osob reportu.');
        }
        foreach ($rawPersonRows as $row) {
            $idOsobyUser = (int)($row['id_user'] ?? 0);
            if ($idOsobyUser <= 0) {
                continue;
            }
            $nameParts = cb_denni_report_user_name_parts_by_id($conn, $idOsobyUser);
            $jmeno = trim((string)($nameParts['jmeno'] ?? ''));
            $prijmeni = trim((string)($nameParts['prijmeni'] ?? ''));
            if ($jmeno === '' && $prijmeni === '') {
                continue;
            }
            $slot = (int)($row['slot'] ?? 0);
            $smenaOd = trim((string)($row['smena_od'] ?? ''));
            $smenaDo = trim((string)($row['smena_do'] ?? ''));
            $smenaOd = $smenaOd !== '' ? $smenaOd : null;
            $smenaDo = $smenaDo !== '' ? $smenaDo : null;
            $pauza = (float)($row['pauza'] ?? 0);
            $odpracovano = (float)($row['odpracovano'] ?? 0);
            $rozvozuRestia = ($slot === 2) ? max(0, (int)($row['rozvozu_restia'] ?? 0)) : 0;
            $rozvozuManual = (int)($row['rozvozu_manual'] ?? 0);
            $rozvozuCelkem = $slot === 2 ? ($rozvozuRestia + $rozvozuManual) : 0;
            $vlastniVuz = (int)($row['vlastni_vuz'] ?? 0);
            $vyplatitPhm = (float)($row['vyplatit_phm'] ?? 0);
            $stmtPerson->bind_param('iississddiiiid', $idReportu, $idOsobyUser, $jmeno, $prijmeni, $slot, $smenaOd, $smenaDo, $pauza, $odpracovano, $rozvozuRestia, $rozvozuManual, $rozvozuCelkem, $vlastniVuz, $vyplatitPhm);
            $stmtPerson->execute();
        }
        $stmtPerson->close();

        $conn->commit();
        return $idReportu;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

// db/db_zapis_denni_report.php * Konec souboru
