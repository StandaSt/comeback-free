<?php
declare(strict_types=1);

/* Opakovatelný převod platných Google reportů do společné historie reporty_is.
 * Náhled pouze čte; zápis provede výhradně potvrzená akce Admina.
 * Dvě koncová data platí včetně uvedeného dne a zůstávají stejná pro náhled i převod.
 */

const CB_ADMIN_GOOGLE_HISTORIE_VYROBA_ID_POB = 7;
const CB_ADMIN_GOOGLE_HISTORIE_RESTAURACE_VYCHOZI_DO = '2026-08-31';

function cb_admin_google_historie_vychozi_data(mysqli $db): array
{
    $stmt = $db->prepare('SELECT MAX(datum_reportu) FROM reporty WHERE id_pob = ? AND platny = 1 AND zdroj = 1');
    $idPob = CB_ADMIN_GOOGLE_HISTORIE_VYROBA_ID_POB;
    $stmt->bind_param('i', $idPob);
    $stmt->execute();
    $vyrobaDo = $stmt->get_result()->fetch_row()[0] ?? null;
    $stmt->close();

    return [
        'restaurace' => CB_ADMIN_GOOGLE_HISTORIE_RESTAURACE_VYCHOZI_DO,
        'vyroba' => is_string($vyrobaDo) ? $vyrobaDo : CB_ADMIN_GOOGLE_HISTORIE_RESTAURACE_VYCHOZI_DO,
    ];
}

function cb_admin_google_historie_data_z_formulare(array $post): array
{
    $labels = ['restaurace' => 'restaurace', 'vyroba' => 'Výrobu'];
    $data = [];
    foreach ($labels as $key => $label) {
        $value = trim((string)($post['google_historie_do_' . $key] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw new CbUserVisibleException('Zadejte platné koncové datum pro ' . $label . '.');
        }
        $data[$key] = $value;
    }

    return $data;
}

function cb_admin_google_historie_nahled(mysqli $db, array $data): array
{
    $stmt = $db->prepare('
        SELECT r.id_pob, p.nazev, COUNT(*) AS pocet,
               MIN(r.datum_reportu) AS od, MAX(r.datum_reportu) AS do_dne
        FROM reporty r
        INNER JOIN pobocka p ON p.id_pob = r.id_pob
        LEFT JOIN reporty_is cil ON cil.id_pob = r.id_pob
            AND cil.datum_reportu = r.datum_reportu AND cil.platny = 1
        WHERE r.platny = 1 AND r.zdroj = 1
          AND r.datum_reportu <= CASE WHEN r.id_pob = ? THEN ? ELSE ? END
          AND cil.id_reportu IS NULL
        GROUP BY r.id_pob, p.nazev
        ORDER BY r.id_pob
    ');
    if ($stmt === false) {
        throw new RuntimeException('Náhled historických reportů se nepodařilo připravit.');
    }
    $vyrobaId = CB_ADMIN_GOOGLE_HISTORIE_VYROBA_ID_POB;
    $stmt->bind_param('iss', $vyrobaId, $data['vyroba'], $data['restaurace']);
    $stmt->execute();
    $result = $stmt->get_result();
    $branches = [];
    $total = 0;
    while ($row = $result->fetch_assoc()) {
        $count = (int)$row['pocet'];
        $branches[] = [
            'id_pob' => (int)$row['id_pob'],
            'nazev' => (string)$row['nazev'],
            'pocet' => $count,
            'od' => (string)$row['od'],
            'do' => (string)$row['do_dne'],
        ];
        $total += $count;
    }
    $stmt->close();

    return ['branches' => $branches, 'total' => $total];
}

function cb_admin_google_historie_dotaz(mysqli $db, string $sql): int
{
    $db->query($sql);
    return $db->affected_rows;
}

function cb_admin_google_historie_proved(mysqli $db, int $idPob, array $data): array
{
    set_time_limit(0);
    $branchStmt = $db->prepare('SELECT id_pob FROM pobocka WHERE id_pob = ? LIMIT 1');
    $branchStmt->bind_param('i', $idPob);
    $branchStmt->execute();
    $branchExists = $branchStmt->get_result()->fetch_assoc() !== null;
    $branchStmt->close();
    if (!$branchExists) {
        throw new CbUserVisibleException('Pobočka pro převod neexistuje.');
    }

    $lock = $db->query("SELECT GET_LOCK('cb_google_historie_prevod', 0)")->fetch_row();
    if ((int)($lock[0] ?? 0) !== 1) {
        throw new CbUserVisibleException('Převod historických reportů už probíhá.');
    }

    $transaction = false;
    try {
        $db->query('DROP TEMPORARY TABLE IF EXISTS cb_google_historie_zdroj');
        $db->query('CREATE TEMPORARY TABLE cb_google_historie_zdroj (
            id_reportu BIGINT UNSIGNED NOT NULL PRIMARY KEY
        ) ENGINE=MEMORY');
        $endDate = $idPob === CB_ADMIN_GOOGLE_HISTORIE_VYROBA_ID_POB ? $data['vyroba'] : $data['restaurace'];
        $endDate = $db->real_escape_string($endDate);
        cb_admin_google_historie_dotaz($db, "
            INSERT INTO cb_google_historie_zdroj (id_reportu)
            SELECT r.id_reportu
            FROM reporty r
            INNER JOIN pobocka p ON p.id_pob = r.id_pob
            LEFT JOIN reporty_is cil ON cil.id_pob = r.id_pob
                AND cil.datum_reportu = r.datum_reportu AND cil.platny = 1
            WHERE r.platny = 1 AND r.zdroj = 1
              AND r.datum_reportu <= '$endDate' AND cil.id_reportu IS NULL
              AND r.id_pob = $idPob
        ");
        $sourceCount = (int)$db->query('SELECT COUNT(*) FROM cb_google_historie_zdroj')->fetch_row()[0];
        if ($sourceCount === 0) {
            return ['reporty' => 0, 'osoby' => 0, 'pokladny' => 0, 'restia' => 0];
        }
        $sourcePeopleCount = (int)$db->query('
            SELECT COUNT(*)
            FROM cb_google_historie_zdroj vyber
            INNER JOIN reporty_osoby osoba ON osoba.id_reportu = vyber.id_reportu
        ')->fetch_row()[0];

        $db->begin_transaction();
        $transaction = true;
        $reports = cb_admin_google_historie_dotaz($db, '
            INSERT INTO reporty_is
                (id_firma, datum_reportu, id_pob, oteviral, zaviral,
                 oteviral_text, zaviral_text, poznamka, zdroj, stav,
                 zadal, zadano, editovano, platny)
            SELECT p.id_firma, r.datum_reportu, r.id_pob,
                   CASE WHEN r.oteviral = 167 THEN 19 ELSE r.oteviral END,
                   CASE WHEN r.zaviral = 167 THEN 19 ELSE r.zaviral END,
                   CASE WHEN r.oteviral IS NULL THEN r.oteviral_text ELSE NULL END,
                   CASE WHEN r.zaviral IS NULL THEN r.zaviral_text ELSE NULL END,
                   r.poznamka, 1, r.stav,
                   CASE WHEN r.zadal = 167 THEN 19 ELSE r.zadal END,
                   r.zadano, r.editovano, 1
            FROM cb_google_historie_zdroj vyber
            INNER JOIN reporty r ON r.id_reportu = vyber.id_reportu
            INNER JOIN pobocka p ON p.id_pob = r.id_pob
        ');
        if ($reports !== $sourceCount) {
            throw new RuntimeException('Počet vložených hlaviček nesouhlasí s náhledem převodu.');
        }

        $mappedPerson = "CASE WHEN osoba.id_user = 167
            OR (osoba.id_user IS NULL AND (
                (osoba.jmeno = 'Jan' AND osoba.prijmeni = 'Pelikán')
                OR (osoba.jmeno = 'Pelikán' AND osoba.prijmeni = 'Jan')
            )) THEN 19 ELSE osoba.id_user END";
        $people = cb_admin_google_historie_dotaz($db, "
            INSERT INTO reporty_is_osoby
                (id_reportu, id_user, jmeno, prijmeni, slot, smena_od, smena_do,
                 pauza, odpracovano, rozvozu_restia, rozvozu_manual, sazba,
                 rozvozu_celkem, vlastni_vuz, vyplatit_phm)
            SELECT cil.id_reportu, $mappedPerson,
                   CASE WHEN $mappedPerson IS NULL THEN osoba.jmeno ELSE NULL END,
                   CASE WHEN $mappedPerson IS NULL THEN osoba.prijmeni ELSE NULL END,
                   osoba.slot, osoba.smena_od, osoba.smena_do, osoba.pauza,
                   osoba.odpracovano, osoba.rozvozu_restia, osoba.rozvozu_manual,
                   0.00, osoba.rozvozu_celkem, osoba.vlastni_vuz, osoba.vyplatit_phm
            FROM cb_google_historie_zdroj vyber
            INNER JOIN reporty zdroj ON zdroj.id_reportu = vyber.id_reportu
            INNER JOIN reporty_is cil ON cil.id_pob = zdroj.id_pob
                AND cil.datum_reportu = zdroj.datum_reportu AND cil.platny = 1 AND cil.zdroj = 1
            INNER JOIN reporty_osoby osoba ON osoba.id_reportu = zdroj.id_reportu
        ");
        if ($people !== $sourcePeopleCount) {
            throw new RuntimeException('Počet vložených směn osob nesouhlasí se zdrojem. Převod se vrací zpět.');
        }

        $cash = cb_admin_google_historie_dotaz($db, '
            INSERT INTO reporty_is_pokladna
                (id_reportu, hotovost, terminal, stravenky, rozdil,
                 vydaje_benzin, vydaje_auta, vydaje_suroviny, vydaje_ostatni,
                 vydaje_phm_soukrome, vydaje_doklady_ks)
            SELECT cil.id_reportu, p.hotovost, p.terminal, p.stravenky, p.rozdil,
                   p.vydaje_benzin, p.vydaje_auta, p.vydaje_suroviny, p.vydaje_ostatni,
                   p.vydaje_phm_soukrome, p.vydaje_doklady_ks
            FROM cb_google_historie_zdroj vyber
            INNER JOIN reporty zdroj ON zdroj.id_reportu = vyber.id_reportu
            INNER JOIN reporty_is cil ON cil.id_pob = zdroj.id_pob
                AND cil.datum_reportu = zdroj.datum_reportu AND cil.platny = 1 AND cil.zdroj = 1
            INNER JOIN reporty_pokladna p ON p.id_reportu = zdroj.id_reportu
        ');
        $restia = cb_admin_google_historie_dotaz($db, '
            INSERT INTO reporty_is_restia
                (id_reportu, trzba, wolt, bolt, damejidlo, web, wolt_cash, dj_cash,
                 col_pomer, zrusene_obj_ks, zrusene_obj_kc, zpozdene_rozvozy_5_min,
                 make_time_prumer_sec, objednavky_nezrusene_ks, nase_rozvozy_ks,
                 woltdrive_ks, woltdrive_pozde_5_min, woltdrive_pozde_nase_vina,
                 nase_rozvozy_pozde_pomer, woltdrive_zpozdene_ks,
                 doruceno_vcas_pomer, woltdrive_zpozdene_pomer)
            SELECT cil.id_reportu, ri.trzba, ri.wolt, ri.bolt, ri.damejidlo, ri.web,
                   ri.wolt_cash, ri.dj_cash, ri.col_pomer, ri.zrusene_obj_ks,
                   ri.zrusene_obj_kc, ri.zpozdene_rozvozy_5_min, ri.make_time_prumer_sec,
                   ri.objednavky_nezrusene_ks, ri.nase_rozvozy_ks, ri.woltdrive_ks,
                   ri.woltdrive_pozde_5_min, ri.woltdrive_pozde_nase_vina,
                   ri.nase_rozvozy_pozde_pomer, ri.woltdrive_zpozdene_ks,
                   ri.doruceno_vcas_pomer, ri.woltdrive_zpozdene_pomer
            FROM cb_google_historie_zdroj vyber
            INNER JOIN reporty zdroj ON zdroj.id_reportu = vyber.id_reportu
            INNER JOIN reporty_is cil ON cil.id_pob = zdroj.id_pob
                AND cil.datum_reportu = zdroj.datum_reportu AND cil.platny = 1 AND cil.zdroj = 1
            INNER JOIN reporty_restia ri ON ri.id_reportu = zdroj.id_reportu
        ');
        if ($cash !== $sourceCount || $restia !== $sourceCount) {
            throw new RuntimeException('Počet pokladen nebo údajů Restia nesouhlasí s počtem reportů. Převod se vrací zpět.');
        }

        cb_admin_google_historie_dopln_pocty($db);
        $db->commit();
        $transaction = false;
        return ['reporty' => $reports, 'osoby' => $people, 'pokladny' => $cash, 'restia' => $restia];
    } catch (Throwable $e) {
        if ($transaction) {
            $db->rollback();
        }
        throw $e;
    } finally {
        try {
            $db->query('DROP TEMPORARY TABLE IF EXISTS cb_google_historie_zdroj');
        } finally {
            $db->query("SELECT RELEASE_LOCK('cb_google_historie_prevod')");
        }
    }
}

function cb_admin_google_historie_dopln_pocty(mysqli $db): void
{
    $notCanceled = "COALESCE(stav.nazev, '') NOT IN ('canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted')";
    $woltCash = "EXISTS (SELECT 1 FROM obj_kuryr kuryr
        WHERE kuryr.id_obj = obj.id_obj AND TRIM(COALESCE(kuryr.jmeno, '')) = 'Wolt Kurýr')";
    $db->query("
        UPDATE reporty_is_restia cil_restia
        INNER JOIN reporty_is cil ON cil.id_reportu = cil_restia.id_reportu
        INNER JOIN (
            SELECT obj.id_pob, cas.report,
                SUM(CASE WHEN $notCanceled AND platforma.kod = 'wolt'
                    AND COALESCE(platba.nazev, '') <> 'cash' THEN 1 ELSE 0 END) AS wolt_obj,
                SUM(CASE WHEN $notCanceled AND platforma.kod = 'bolt'
                    THEN 1 ELSE 0 END) AS bolt_obj,
                SUM(CASE WHEN $notCanceled AND platforma.kod IN ('foodora', 'damejidlo')
                    AND COALESCE(platba.nazev, '') <> 'cash' THEN 1 ELSE 0 END) AS damejidlo_obj,
                SUM(CASE WHEN $notCanceled AND platforma.kod = 'generic'
                    AND COALESCE(platba.nazev, '') = 'online' THEN 1 ELSE 0 END) AS web_obj,
                SUM(CASE WHEN $notCanceled AND platforma.kod = 'generic'
                    AND COALESCE(doruceni.nazev, '') = 'delivery'
                    AND COALESCE(platba.nazev, '') = 'cash' AND $woltCash
                    THEN 1 ELSE 0 END) AS wolt_cash_obj,
                SUM(CASE WHEN $notCanceled AND platforma.kod IN ('foodora', 'damejidlo')
                    AND COALESCE(platba.nazev, '') = 'cash' THEN 1 ELSE 0 END) AS dj_cash_obj
            FROM objednavky_restia obj
            INNER JOIN obj_casy cas ON cas.id_obj = obj.id_obj
            INNER JOIN pobocka pob ON pob.id_pob = obj.id_pob
            INNER JOIN reporty zdroj ON zdroj.id_pob = obj.id_pob
                AND zdroj.datum_reportu = cas.report
            INNER JOIN cb_google_historie_zdroj vyber ON vyber.id_reportu = zdroj.id_reportu
            LEFT JOIN cis_obj_platforma platforma ON platforma.id_platforma = obj.id_platforma
            LEFT JOIN cis_obj_platby platba ON platba.id_platba = obj.id_platba
            LEFT JOIN cis_obj_stav stav ON stav.id_stav = obj.id_stav
            LEFT JOIN cis_doruceni doruceni ON doruceni.id_doruceni = obj.id_doruceni
            WHERE cas.report >= pob.prvni_obj
            GROUP BY obj.id_pob, cas.report
        ) pocty ON pocty.id_pob = cil.id_pob AND pocty.report = cil.datum_reportu
        SET cil_restia.wolt_obj = pocty.wolt_obj,
            cil_restia.bolt_obj = pocty.bolt_obj,
            cil_restia.damejidlo_obj = pocty.damejidlo_obj,
            cil_restia.web_obj = pocty.web_obj,
            cil_restia.wolt_cash_obj = pocty.wolt_cash_obj,
            cil_restia.dj_cash_obj = pocty.dj_cash_obj
        WHERE cil.zdroj = 1 AND cil.platny = 1
    ");
}

function cb_admin_google_historie_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cb_admin_google_historie_handle(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $action = (string)($_POST['cb_action'] ?? '');
    $allowed = [
        'admin_google_historie_preview',
        'admin_google_historie_import',
        'admin_google_historie_pobocky',
        'admin_google_historie_pobocka',
    ];
    if (!in_array($action, $allowed, true)) {
        return;
    }

    $isAjax = in_array($action, ['admin_google_historie_pobocky', 'admin_google_historie_pobocka'], true);
    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');
    try {
        if (!cb_pravo_ma(100)) {
            throw new CbUserVisibleException('Nemáte právo spustit převod reportů.');
        }
        if ($action === 'admin_google_historie_preview') {
            $data = cb_admin_google_historie_data_z_formulare($_POST);
            $preview = cb_admin_google_historie_nahled(db(), $data);
            $_SESSION['cb_admin_google_historie_data'] = $data;
            $_SESSION['cb_admin_script_result'] = [
                'script' => 'google_historie', 'success' => true,
                'message' => 'Náhled pouze čte databázi. K převodu: ' . $preview['total'] . ' reportů.',
                'preview' => $preview, 'data' => $data,
            ];
        } elseif ($action === 'admin_google_historie_import') {
            throw new CbUserVisibleException('Převod vyžaduje aktuální stránku s průběhem po pobočkách. Obnovte stránku a spusťte jej znovu.');
        } else {
            if ((string)($_POST['admin_google_historie_confirm'] ?? '') !== '1') {
                throw new CbUserVisibleException('Potvrďte převod historických reportů.');
            }
            $data = cb_admin_google_historie_data_z_formulare($_POST);
            if (($_SESSION['cb_admin_google_historie_data'] ?? null) !== $data) {
                throw new CbUserVisibleException('Zvolená data se liší od náhledu. Znovu zobrazte reporty k převodu.');
            }
            if ($action === 'admin_google_historie_pobocky') {
                $preview = cb_admin_google_historie_nahled(db(), $data);
                $_SESSION['cb_admin_script_result'] = [
                    'script' => 'google_historie', 'success' => true,
                    'message' => 'K převodu zbývá ' . $preview['total'] . ' reportů.',
                    'preview' => $preview, 'data' => $data,
                ];
                cb_admin_google_historie_json(['ok' => true, 'pobocky' => $preview['branches'], 'celkem' => $preview['total']]);
            }

            $idPob = filter_var($_POST['id_pob'] ?? null, FILTER_VALIDATE_INT);
            if ($idPob === false) {
                throw new CbUserVisibleException('Není určena pobočka pro převod.');
            }
            $result = cb_admin_google_historie_proved(db(), (int)$idPob, $data);
            $preview = cb_admin_google_historie_nahled(db(), $data);
            $_SESSION['cb_admin_script_result'] = [
                'script' => 'google_historie', 'success' => true,
                'message' => 'K převodu zbývá ' . $preview['total'] . ' reportů.',
                'preview' => $preview, 'data' => $data,
            ];
            cb_admin_chyba_audit(static function () use ($result, $idPob): void {
                cb_user_akce_zapis([
                    'id_user_akce_typ' => 14, 'modul' => 'administrace',
                    'objekt' => 'google_historie_reporty_is', 'id_objektu' => (int)$idPob,
                    'pole' => 'prevod_pobocky',
                    'hodnota_new' => 'reporty=' . $result['reporty'] . ';osoby=' . $result['osoby'],
                    'vysledek' => 1, 'zdroj' => 'administrace',
                ]);
            });
            cb_admin_google_historie_json([
                'ok' => true,
                'reporty' => $result['reporty'],
                'osoby' => $result['osoby'],
                'pokladny' => $result['pokladny'],
                'restia' => $result['restia'],
                'zbyva' => $preview['total'],
            ]);
        }
    } catch (Throwable $e) {
        $message = cb_admin_chyba_text($e, 'Převod historických reportů');
        cb_admin_chyba_audit(static function () use ($e): void {
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14, 'modul' => 'administrace',
                'objekt' => 'google_historie_reporty_is', 'pole' => 'prevod',
                'vysledek' => 0, 'err_msg' => $e->getMessage(),
                'zdroj' => 'administrace',
            ]);
        });
        if ($isAjax) {
            cb_admin_google_historie_json(['ok' => false, 'chyba' => $message], cb_admin_chyba_status($e));
        }
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'google_historie', 'success' => false,
            'message' => $message,
        ];
    }
    header('Location: ' . $returnUrl, true, 303);
    exit;
}
