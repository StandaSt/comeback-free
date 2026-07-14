<?php
// db/db_zapis_denni_report.php * K10 finalni zapis denniho reportu
declare(strict_types=1);

function cb_db_zapis_denni_report_already_saved_message(): string
{
    return 'Report je již uložen. Opakovaný pokus o uložení byl zablokován.';
}

function cb_db_zapis_denni_report_is_duplicate_error(Throwable $e, string $indexName = ''): bool
{
    if ((int)$e->getCode() !== 1062) {
        return false;
    }

    $message = (string)$e->getMessage();
    if (stripos($message, 'Duplicate entry') === false) {
        return false;
    }

    if ($indexName === '') {
        return true;
    }

    return stripos($message, $indexName) !== false;
}

function cb_db_reporty_is_find_active_id(mysqli $conn, int $idPob, string $datumReportu): int
{
    $stmt = $conn->prepare('
        SELECT id_reportu
        FROM reporty_is
        WHERE id_pob = ?
          AND datum_reportu = ?
          AND platny = 1
        ORDER BY id_reportu DESC
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze overit existenci finalniho reportu.');
    }

    $stmt->bind_param('is', $idPob, $datumReportu);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? (int)($row['id_reportu'] ?? 0) : 0;
}

function cb_db_reporty_is_mark_invalid(mysqli $conn, int $idReportu): void
{
    if ($idReportu <= 0) {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE reporty_is
        SET platny = 0
        WHERE id_reportu = ?
          AND platny = 1
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze oznacit puvodni report jako neplatny.');
    }

    $stmt->bind_param('i', $idReportu);
    $stmt->execute();
    $stmt->close();
}

function cb_db_reporty_is_lock_name(int $idPob, string $datumReportu): string
{
    return 'reporty_is_final_' . $idPob . '_' . $datumReportu;
}

function cb_db_reporty_is_acquire_lock(mysqli $conn, int $idPob, string $datumReportu, int $timeoutSeconds = 10): string
{
    $lockName = cb_db_reporty_is_lock_name($idPob, $datumReportu);
    $stmt = $conn->prepare('SELECT GET_LOCK(?, ?) AS lock_ok');
    if ($stmt === false) {
        throw new RuntimeException('Nelze pripravit zamek finalniho reportu.');
    }

    $stmt->bind_param('si', $lockName, $timeoutSeconds);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if ((int)($row['lock_ok'] ?? 0) !== 1) {
        throw new RuntimeException('Ulozeni tohoto reportu uz prave probiha. Zkus to prosim za chvili znovu.');
    }

    return $lockName;
}

function cb_db_reporty_is_release_lock(mysqli $conn, string $lockName): void
{
    if ($lockName === '') {
        return;
    }

    $stmt = $conn->prepare('SELECT RELEASE_LOCK(?)');
    if ($stmt === false) {
        return;
    }

    $stmt->bind_param('s', $lockName);
    $stmt->execute();
    $stmt->close();
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

function cb_db_zapis_denni_report_valid_time(string $value): bool
{
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $value)) {
        return false;
    }

    return true;
}

function cb_db_zapis_denni_report_user_allowed(mysqli $conn, int $idPob, int $idUser, int $slot): bool
{
    $stmt = $conn->prepare('
        SELECT 1
        FROM user_pobocka up
        INNER JOIN user_slot us ON us.id_user = up.id_user
        WHERE up.id_pob = ?
          AND up.id_user = ?
          AND us.id_slot = ?
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze overit pracovnika reportu.');
    }

    $stmt->bind_param('iii', $idPob, $idUser, $slot);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = $result instanceof mysqli_result && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return $allowed;
}

function cb_db_zapis_denni_report_user_in_active_report(mysqli $conn, int $idPob, string $datumReportu, int $idUser, int $slot, string $usage): bool
{
    if ($usage === 'oteviral' || $usage === 'zaviral') {
        $column = $usage;
        $stmt = $conn->prepare('
            SELECT 1
            FROM reporty_is
            WHERE id_pob = ?
              AND datum_reportu = ?
              AND platny = 1
              AND `' . $column . '` = ?
            LIMIT 1
        ');
    } else {
        $stmt = $conn->prepare('
            SELECT 1
            FROM reporty_is r
            INNER JOIN reporty_is_osoby o ON o.id_reportu = r.id_reportu
            WHERE r.id_pob = ?
              AND r.datum_reportu = ?
              AND r.platny = 1
              AND o.id_user = ?
              AND o.slot = ?
            LIMIT 1
        ');
    }
    if ($stmt === false) {
        throw new RuntimeException('Nelze overit puvodniho pracovnika reportu.');
    }

    if ($usage === 'oteviral' || $usage === 'zaviral') {
        $stmt->bind_param('isi', $idPob, $datumReportu, $idUser);
    } else {
        $stmt->bind_param('isii', $idPob, $datumReportu, $idUser, $slot);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return $exists;
}

function cb_db_zapis_denni_report_validate(mysqli $conn, int $idPob, string $datumReportu, array $post, array $personRows, bool $allowExistingReportUsers): void
{
    $oteviral = (int)($post['oteviral'] ?? 0);
    $zaviral = (int)($post['zaviral'] ?? 0);
    if ($oteviral <= 0 || $zaviral <= 0) {
        throw new RuntimeException('Vyberte, kdo oteviral a zaviral pobocku.');
    }
    $oteviralAllowed = cb_db_zapis_denni_report_user_allowed($conn, $idPob, $oteviral, 1)
        || ($allowExistingReportUsers && cb_db_zapis_denni_report_user_in_active_report($conn, $idPob, $datumReportu, $oteviral, 1, 'oteviral'));
    $zaviralAllowed = cb_db_zapis_denni_report_user_allowed($conn, $idPob, $zaviral, 1)
        || ($allowExistingReportUsers && cb_db_zapis_denni_report_user_in_active_report($conn, $idPob, $datumReportu, $zaviral, 1, 'zaviral'));
    if (!$oteviralAllowed || !$zaviralAllowed) {
        throw new RuntimeException('Otevirajici nebo zavirajici nepatri mezi pracovniky pobocky.');
    }

    foreach (['pokladna_hotovost', 'pokladna_terminal', 'pokladna_stravenky'] as $field) {
        if (trim((string)($post[$field] ?? '')) === '') {
            throw new RuntimeException('Vyplnte vsechny povinne hodnoty pokladny.');
        }
    }

    $seen = [];
    foreach ($personRows as $row) {
        $idUser = (int)($row['id_user'] ?? 0);
        $slot = (int)($row['slot'] ?? 0);
        $start = trim((string)($row['smena_od'] ?? ''));
        $end = trim((string)($row['smena_do'] ?? ''));
        if ($idUser <= 0 || !in_array($slot, [1, 2], true)) {
            throw new RuntimeException('Report obsahuje neplatneho pracovnika.');
        }
        if (!cb_db_zapis_denni_report_valid_time($start) || !cb_db_zapis_denni_report_valid_time($end)) {
            throw new RuntimeException('U kazdeho pracovnika vyplnte platny zacatek a konec smeny.');
        }
        $personAllowed = cb_db_zapis_denni_report_user_allowed($conn, $idPob, $idUser, $slot)
            || ($allowExistingReportUsers && cb_db_zapis_denni_report_user_in_active_report($conn, $idPob, $datumReportu, $idUser, $slot, 'person'));
        if (!$personAllowed) {
            throw new RuntimeException('Pracovnik nepatri do vybrane pobocky nebo typu smeny.');
        }
        $personKey = $slot . ':' . $idUser;
        if (isset($seen[$personKey])) {
            throw new RuntimeException('Stejny pracovnik je v reportu uveden vicekrat.');
        }
        $seen[$personKey] = true;
    }
}

function cb_db_zapis_denni_report_from_form(mysqli $conn, int $idPob, string $datumReportu, int $idUser, array $restiaSummary, array $post, ?float $rozdilForm, ?float $colPomerForm, bool $invalidateExistingActive = false): int
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
    $platny = 1;
    $cash = cb_vcr_cash_from_post($post);
    $rawPersonRows = array_merge(
        cb_db_zapis_denni_report_people_from_post($post, 'instor'),
        cb_db_zapis_denni_report_people_from_post($post, 'kuryr')
    );
    cb_db_zapis_denni_report_validate($conn, $idPob, $datumReportu, $post, $rawPersonRows, $invalidateExistingActive);
    $lockName = cb_db_reporty_is_acquire_lock($conn, $idPob, $datumReportu);

    try {
        $conn->begin_transaction();
        $activeReportId = cb_db_reporty_is_find_active_id($conn, $idPob, $datumReportu);
        if ($activeReportId > 0 && !$invalidateExistingActive) {
            throw new RuntimeException(cb_db_zapis_denni_report_already_saved_message());
        }
        if ($activeReportId > 0 && $invalidateExistingActive) {
            cb_db_reporty_is_mark_invalid($conn, $activeReportId);
        }

        $stmtReport = $conn->prepare('
            INSERT INTO reporty_is
                (datum_reportu, id_pob, oteviral, zaviral, oteviral_text, zaviral_text, poznamka, zdroj, zadal, platny)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmtReport === false) {
            throw new RuntimeException('Nelze pripravit ulozeni reportu.');
        }
        $stmtReport->bind_param('siiisssiii', $datumReportu, $idPob, $oteviral, $zaviral, $oteviralText, $zaviralText, $poznamka, $zdroj, $idUser, $platny);
        try {
            $stmtReport->execute();
        } catch (Throwable $e) {
            $stmtReport->close();
            if (!$invalidateExistingActive && cb_db_zapis_denni_report_is_duplicate_error($e, 'uq_reporty_is_pob_datum')) {
                throw new RuntimeException(cb_db_zapis_denni_report_already_saved_message(), 0, $e);
            }
            throw $e;
        }
        $idReportu = (int)$conn->insert_id;
        $stmtReport->close();
        if ($idReportu <= 0) {
            throw new RuntimeException('Nelze zjistit ID ulozeneho reportu.');
        }

        $stmtPokladna = $conn->prepare('
            INSERT INTO reporty_is_pokladna
                (id_reportu, hotovost, terminal, stravenky, rozdil, vydaje_benzin, vydaje_auta, vydaje_suroviny, vydaje_ostatni, vydaje_phm_soukrome, vydaje_doklady_ks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmtPokladna === false) {
            throw new RuntimeException('Nelze pripravit ulozeni pokladny.');
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
                (id_reportu, trzba, wolt, wolt_obj, bolt, bolt_obj, damejidlo, damejidlo_obj, web, web_obj, wolt_cash, wolt_cash_obj, dj_cash, dj_cash_obj, col_pomer, zrusene_obj_ks, zrusene_obj_kc, zpozdene_rozvozy_5_min, make_time_prumer_sec, objednavky_nezrusene_ks, nase_rozvozy_ks, woltdrive_ks, woltdrive_pozde_5_min, woltdrive_pozde_nase_vina, nase_rozvozy_pozde_pomer, woltdrive_zpozdene_ks, doruceno_vcas_pomer, woltdrive_zpozdene_pomer)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        if ($stmtRestia === false) {
            throw new RuntimeException('Nelze pripravit ulozeni Restie.');
        }
        $trzba = (float)($restiaSummary['trzba'] ?? 0);
        $wolt = (float)($restiaSummary['wolt'] ?? 0);
        $woltObj = (int)($restiaSummary['wolt_count'] ?? 0);
        $bolt = (float)($restiaSummary['bolt'] ?? 0);
        $boltObj = (int)($restiaSummary['bolt_count'] ?? 0);
        $damejidlo = (float)($restiaSummary['dj'] ?? 0);
        $damejidloObj = (int)($restiaSummary['dj_count'] ?? 0);
        $web = (float)($restiaSummary['web'] ?? 0);
        $webObj = (int)($restiaSummary['web_count'] ?? 0);
        $woltCash = (float)($restiaSummary['wolt_cash'] ?? 0);
        $woltCashObj = (int)($restiaSummary['wolt_cash_count'] ?? 0);
        $djCash = (float)($restiaSummary['dj_cash'] ?? 0);
        $djCashObj = (int)($restiaSummary['dj_cash_count'] ?? 0);
        $colPomer = $colPomerForm;
        $zruseneKs = (int)($restiaSummary['cancel_count'] ?? 0);
        $zruseneKc = (float)($restiaSummary['cancel_value'] ?? 0);
        $zpozdeneRozvozy = (int)($restiaSummary['delay_count'] ?? 0);
        $makeTime = isset($restiaSummary['make_time_avg_sec']) ? (int)$restiaSummary['make_time_avg_sec'] : null;
        $objednavkyNezrusene = (int)($restiaSummary['orders_total'] ?? 0);
        $naseRozvozy = (int)($restiaSummary['own_deliveries'] ?? 0);
        $woltdriveKs = (int)($restiaSummary['woltdrive_count'] ?? 0);
        $woltdrivePozde = (int)($restiaSummary['woltdrive_late'] ?? 0);
        $woltdriveNaseVina = (int)($restiaSummary['woltdrive_our_fault'] ?? 0);
        $naseRozvozyPozdePomer = isset($restiaSummary['own_delivery_late_ratio']) ? (float)$restiaSummary['own_delivery_late_ratio'] : null;
        $woltdriveZpozdeneKs = (int)($restiaSummary['woltdrive_late_count'] ?? $woltdrivePozde);
        $dorucenoVcasPomer = isset($restiaSummary['delivered_on_time_ratio']) ? (float)$restiaSummary['delivered_on_time_ratio'] : null;
        $woltdriveZpozdenePomer = isset($restiaSummary['woltdrive_late_ratio']) ? (float)$restiaSummary['woltdrive_late_ratio'] : null;
        $stmtRestia->bind_param('iddidididididididiiiiiiididd', $idReportu, $trzba, $wolt, $woltObj, $bolt, $boltObj, $damejidlo, $damejidloObj, $web, $webObj, $woltCash, $woltCashObj, $djCash, $djCashObj, $colPomer, $zruseneKs, $zruseneKc, $zpozdeneRozvozy, $makeTime, $objednavkyNezrusene, $naseRozvozy, $woltdriveKs, $woltdrivePozde, $woltdriveNaseVina, $naseRozvozyPozdePomer, $woltdriveZpozdeneKs, $dorucenoVcasPomer, $woltdriveZpozdenePomer);
        $stmtRestia->execute();
        $stmtRestia->close();

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
    } finally {
        cb_db_reporty_is_release_lock($conn, $lockName);
    }
}

// db/db_zapis_denni_report.php * Konec souboru
