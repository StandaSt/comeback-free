<?php
declare(strict_types=1);

/* Účel souboru: Načítá kandidáty pro plánování a bezpečně ukládá jednotlivá obsazení směn. */

/** @return array<int,array<string,mixed>> */
function cb_smeny_planovani_kandidati(mysqli $db, array $plan, string $date, int $idSlot): array
{
    $slotTable = cb_hr_schema_table($db, 'slot');
    $idFirma = (int)$plan['id_firma'];
    $idBranch = (int)$plan['id_pob'];
    $idWeek = (int)$plan['id_smeny_tyden'];
    $startDay = (string)$plan['start_day'];
    $endDay = (new DateTimeImmutable($startDay))->modify('+6 days')->format('Y-m-d');
    $sql = '
        SELECT hp.id_person,
               TRIM(CONCAT_WS(" ", ou.jmeno, ou.prijmeni)) AS jmeno,
               COALESCE(main_branch.id_pob, 0) AS id_hlavni_pob,
               COALESCE(pob.nazev, "Bez hlavní pobočky") AS hlavni_pobocka,
               CASE WHEN main_branch.id_pob = ? THEN 1 ELSE 0 END AS je_hlavni_pobocka,
               CASE WHEN EXISTS (
                   SELECT 1 FROM hr_pracovni_vztah pv
                   WHERE pv.id_person=hp.id_person AND pv.id_pracovni_vztah_typ=1 AND pv.platny=1
                     AND (pv.datum_nastupu IS NULL OR pv.datum_nastupu<=?)
                     AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni>=?)
               ) THEN 1 ELSE 0 END AS je_hpp,
               MAX(TIME_FORMAT(pb.cas_od, "%H:%i")) AS pozadavek_od,
               MAX(TIME_FORMAT(pb.cas_do, "%H:%i")) AS pozadavek_do,
               CASE WHEN MAX(hv.id_smeny_hpp_volno) IS NULL THEN 0 ELSE 1 END AS ma_volno,
               COALESCE((
                   SELECT ROUND(SUM(TIMESTAMPDIFF(MINUTE,
                       TIMESTAMP(rb2.datum, rb2.cas_od),
                       TIMESTAMP(DATE_ADD(rb2.datum, INTERVAL (rb2.cas_do<=rb2.cas_od) DAY), rb2.cas_do)
                   )) / 60, 1)
                   FROM smeny_obsazeni o2
                   INNER JOIN smeny_rozpis_blok rb2 ON rb2.id_smeny_rozpis_blok=o2.id_smeny_rozpis_blok
                   INNER JOIN smeny_rozpis r2 ON r2.id_smeny_rozpis=rb2.id_smeny_rozpis
                   WHERE o2.id_person=hp.id_person AND o2.stav="prirazeno" AND rb2.stav="obsazeny"
                     AND rb2.datum BETWEEN ? AND ? AND r2.stav<>"zruseny"
               ), 0) AS hodin_tyden
        FROM hr_person hp
        INNER JOIN ' . $slotTable . ' z ON z.id_person=hp.id_person AND z.id_slot=? AND z.platny=1
          AND (z.platnost_od IS NULL OR z.platnost_od<=?) AND (z.platnost_do IS NULL OR z.platnost_do>=?)
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(
            SELECT MAX(ou2.id_osobni_udaje) FROM hr_osobni_udaje ou2 WHERE ou2.id_person=hp.id_person AND ou2.platny=1
        )
        LEFT JOIN hr_pracoviste main_branch ON main_branch.id_pracoviste=(
            SELECT MAX(pr.id_pracoviste) FROM hr_pracoviste pr
            WHERE pr.id_person=hp.id_person AND pr.hlavni=1 AND pr.platny=1
              AND (pr.platnost_od IS NULL OR pr.platnost_od<=?) AND (pr.platnost_do IS NULL OR pr.platnost_do>=?)
        )
        LEFT JOIN pobocka pob ON pob.id_pob=main_branch.id_pob
        LEFT JOIN smeny_pozadavek sp ON sp.id_smeny_tyden=? AND sp.id_person=hp.id_person AND sp.stav<>"rozpracovany"
        LEFT JOIN smeny_pozadavek_blok pb ON pb.id_smeny_pozadavek=sp.id_smeny_pozadavek AND pb.datum=?
        LEFT JOIN smeny_hpp_volno hv ON hv.id_smeny_pozadavek=sp.id_smeny_pozadavek AND hv.datum=?
        WHERE hp.id_firma=? AND hp.aktivni=1
        GROUP BY hp.id_person, ou.jmeno, ou.prijmeni, main_branch.id_pob, pob.nazev
        ORDER BY je_hlavni_pobocka DESC, (MAX(pb.id_smeny_pozadavek_blok) IS NOT NULL) DESC, jmeno, hp.id_person';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('issssissssissi', $idBranch, $date, $date, $startDay, $endDay, $idSlot, $date, $date, $date, $date, $idWeek, $date, $date, $idFirma);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        foreach (['id_person', 'id_hlavni_pob', 'je_hlavni_pobocka', 'je_hpp', 'ma_volno'] as $key) {
            $row[$key] = (int)($row[$key] ?? 0);
        }
        $row['hodin_tyden'] = (float)($row['hodin_tyden'] ?? 0);
        $row['podle_pozadavku'] = ($row['je_hpp'] === 1 && $row['ma_volno'] === 0) || (string)($row['pozadavek_od'] ?? '') !== '';
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

/** Ověří čas a uloží nový pracovní blok odděleně od barevné pomůcky šablony. */
function cb_smeny_planovani_priradit(mysqli $db, array $plan, array $branch, array $week, array $post, int $plannerId): void
{
    if ((string)$plan['stav'] !== 'rozpracovany') {
        throw new CbUserVisibleException('Směny lze přidávat pouze do rozpracovaného týdne.');
    }
    $date = trim((string)($post['datum'] ?? ''));
    $idSlot = (int)($post['id_slot'] ?? 0);
    $idPerson = (int)($post['id_person'] ?? 0);
    $from = trim((string)($post['cas_od'] ?? ''));
    $to = trim((string)($post['cas_do'] ?? ''));
    $ignore = !empty($post['bez_ohledu']);
    $allowedDates = array_column($week['days'], 'date');
    if (!in_array($date, $allowedDates, true) || !isset(cb_smeny_sablony_pozice($branch)[$idSlot]) || $idPerson <= 0) {
        throw new CbUserVisibleException('Vybraný den, pracovní slot nebo zaměstnanec nejsou platné.');
    }
    $closing = cb_smeny_sablony_zaviraci_cas($branch, (int)(array_search($date, $allowedDates, true) + 1));
    $fromMinutes = cb_smeny_sablony_cas_overit($from, false, $closing);
    $toMinutes = cb_smeny_sablony_cas_overit($to, true, $closing);
    if ($toMinutes <= $fromMinutes) {
        throw new CbUserVisibleException('Konec směny musí být později než začátek.');
    }
    $candidate = null;
    foreach (cb_smeny_planovani_kandidati($db, $plan, $date, $idSlot) as $row) {
        if ((int)$row['id_person'] === $idPerson) {
            $candidate = $row;
            break;
        }
    }
    if ($candidate === null) {
        throw new CbUserVisibleException('Zaměstnanec už není pro tuto pozici dostupný.');
    }
    if (!$ignore) {
        if (empty($candidate['podle_pozadavku'])) {
            throw new CbUserVisibleException('Zaměstnanec nemá pro tento den požadavek. Použijte volbu Bez ohledu na požadavky.');
        }
        if ((int)$candidate['je_hpp'] !== 1) {
            $requestFrom = cb_smeny_sablony_cas_minuty((string)$candidate['pozadavek_od'], false);
            $requestTo = cb_smeny_sablony_cas_minuty((string)$candidate['pozadavek_do'], true);
            if (max($fromMinutes, $requestFrom) >= min($toMinutes, $requestTo)) {
                throw new CbUserVisibleException('Směna se ani částečně nepřekrývá s požadavkem zaměstnance.');
            }
        }
    }

    // Jeden pracovník nesmí mít ve stejný den dvě časově překryté směny.
    $stmt = $db->prepare('SELECT rb.cas_od, rb.cas_do FROM smeny_obsazeni o INNER JOIN smeny_rozpis_blok rb ON rb.id_smeny_rozpis_blok=o.id_smeny_rozpis_blok WHERE o.id_person=? AND o.stav="prirazeno" AND rb.datum=? AND rb.stav="obsazeny"');
    $stmt->bind_param('is', $idPerson, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($existing = $result->fetch_assoc()) {
        $existingFrom = cb_smeny_sablony_cas_minuty(substr((string)$existing['cas_od'], 0, 5), false);
        $existingTo = cb_smeny_sablony_cas_minuty(substr((string)$existing['cas_do'], 0, 5), true);
        if (max($fromMinutes, $existingFrom) < min($toMinutes, $existingTo)) {
            $stmt->close();
            throw new CbUserVisibleException('Zaměstnanec už má v tomto čase naplánovanou jinou směnu.');
        }
    }
    $stmt->close();

    $idPlan = (int)$plan['id_smeny_rozpis'];
    $mode = $ignore ? 'bez_ohledu_na_pozadavky' : 'podle_pozadavku';
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('INSERT INTO smeny_rozpis_blok (id_smeny_rozpis,id_smeny_sablona_blok,datum,id_slot,cas_od,cas_do,poradi,stav) VALUES (?,NULL,?,?,?,?,0,"obsazeny")');
        $stmt->bind_param('isiss', $idPlan, $date, $idSlot, $from, $to);
        $stmt->execute();
        $idBlock = (int)$db->insert_id;
        $stmt->close();
        $stmt = $db->prepare('INSERT INTO smeny_obsazeni (id_smeny_rozpis_blok,id_person,zpusob_vyberu,priradil_id_person) VALUES (?,?,?,?)');
        $stmt->bind_param('iisi', $idBlock, $idPerson, $mode, $plannerId);
        $stmt->execute();
        $stmt->close();
        cb_smeny_audit_zapis($db, $plannerId, 'prirazen_pracovnik', 'obsazeni', $idBlock, null, ['id_person' => $idPerson, 'datum' => $date, 'cas_od' => $from, 'cas_do' => $to]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
