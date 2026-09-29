<?php
declare(strict_types=1);

/* Účel souboru: Načte nebo založí konkrétní týdenní rozpis a spravuje jeho otisk vybrané šablony. */

function cb_smeny_planovani_tyden_id(mysqli $db, array $branch, array $week, int $idPerson): int
{
    $idFirma = (int)$branch['id_firma'];
    $start = (string)$week['start_day'];
    $deadline = $week['deadline']->format('Y-m-d H:i:s');
    $stmt = $db->prepare('INSERT INTO smeny_tyden (id_firma, start_day, pozadavky_deadline, stav, vytvoril_id_person) VALUES (?, ?, ?, "uzavreny", ?) ON DUPLICATE KEY UPDATE id_smeny_tyden = LAST_INSERT_ID(id_smeny_tyden)');
    $stmt->bind_param('issi', $idFirma, $start, $deadline, $idPerson);
    $stmt->execute();
    $idWeek = (int)$db->insert_id;
    $stmt->close();
    if ($idWeek <= 0) {
        $stmt = $db->prepare('SELECT id_smeny_tyden FROM smeny_tyden WHERE id_firma = ? AND start_day = ? LIMIT 1');
        $stmt->bind_param('is', $idFirma, $start);
        $stmt->execute();
        $idWeek = (int)($stmt->get_result()->fetch_assoc()['id_smeny_tyden'] ?? 0);
        $stmt->close();
    }
    return $idWeek;
}

/** @return array<string,mixed>|null */
function cb_smeny_planovani_nacist(mysqli $db, int $idBranch, string $startDay): ?array
{
    $stmt = $db->prepare('SELECT r.*, t.start_day, s.nazev AS sablona_nazev FROM smeny_rozpis r INNER JOIN smeny_tyden t ON t.id_smeny_tyden = r.id_smeny_tyden LEFT JOIN smeny_sablona s ON s.id_smeny_sablona = r.id_smeny_sablona WHERE r.id_pob = ? AND t.start_day = ? AND r.stav <> "zruseny" ORDER BY r.verze DESC LIMIT 1');
    $stmt->bind_param('is', $idBranch, $startDay);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    if (!is_array($plan)) return null;
    $plan['blocks'] = [];
    $stmt = $db->prepare('SELECT b.*, o.id_smeny_obsazeni, o.id_person, TRIM(CONCAT_WS(" ", u.jmeno, u.prijmeni)) AS pracovnik FROM smeny_rozpis_blok b LEFT JOIN smeny_obsazeni o ON o.id_smeny_rozpis_blok=b.id_smeny_rozpis_blok AND o.stav="prirazeno" LEFT JOIN hr_osobni_udaje u ON u.id_osobni_udaje=(SELECT MAX(u2.id_osobni_udaje) FROM hr_osobni_udaje u2 WHERE u2.id_person=o.id_person AND u2.platny=1) WHERE b.id_smeny_rozpis=? AND b.stav<>"zruseny" ORDER BY b.datum,b.poradi,b.id_smeny_rozpis_blok');
    $idPlan = (int)$plan['id_smeny_rozpis'];
    $stmt->bind_param('i', $idPlan);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $plan['blocks'][] = $row;
    $stmt->close();
    return $plan;
}

function cb_smeny_planovani_zalozit(mysqli $db, array $branch, array $week, int $idTemplate, int $idPerson): int
{
    $template = cb_smeny_sablona_nacist($db, $idTemplate, [(int)$branch['id_pob'] => $branch]);
    if ($template === null) throw new CbUserVisibleException('Vybraná šablona nepatří k této pobočce.');
    $db->begin_transaction();
    try {
        $idWeek = cb_smeny_planovani_tyden_id($db, $branch, $week, $idPerson);
        $idFirma=(int)$branch['id_firma']; $idBranch=(int)$branch['id_pob'];
        $stmt=$db->prepare('INSERT INTO smeny_rozpis (id_smeny_tyden,id_firma,id_pob,id_smeny_sablona,vytvoril_id_person) VALUES (?,?,?,?,?)');
        $stmt->bind_param('iiiii',$idWeek,$idFirma,$idBranch,$idTemplate,$idPerson); $stmt->execute();
        $idPlan=(int)$db->insert_id; $stmt->close();
        cb_smeny_planovani_vlozit_sablonu($db, $idPlan, $template, $week);
        cb_smeny_audit_zapis($db,$idPerson,'zalozen','rozpis',$idPlan,null,['id_smeny_sablona'=>$idTemplate,'start_day'=>$week['start_day']]);
        $db->commit(); return $idPlan;
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

/** Vloží aktuální obsah šablony jako samostatný otisk do rozpisu. */
function cb_smeny_planovani_vlozit_sablonu(mysqli $db, int $idPlan, array $template, array $week): void
{
    $stmt = $db->prepare('INSERT INTO smeny_rozpis_blok (id_smeny_rozpis,id_smeny_sablona_blok,datum,id_slot,cas_od,cas_do,poradi) VALUES (?,?,?,?,?,?,?)');
    foreach ($template['blocks'] as $day => $blocks) {
        $date = $week['start']->modify('+' . ($day - 1) . ' days')->format('Y-m-d');
        foreach ($blocks as $block) {
            $idSource = (int)$block['id_smeny_sablona_blok'];
            $idSlot = (int)$block['id_slot'];
            $from = (string)$block['cas_od'];
            $to = (string)$block['cas_do'];
            $order = (int)$block['poradi'];
            $stmt->bind_param('iisissi', $idPlan, $idSource, $date, $idSlot, $from, $to, $order);
            $stmt->execute();
        }
    }
    $stmt->close();
}

/** Znovu načte šablonu pouze do rozpisu, ve kterém ještě není žádný pracovník. */
function cb_smeny_planovani_sablonu_obnovit(mysqli $db, int $idPlan, array $branch, array $week, int $idTemplate, int $idPerson): void
{
    $template = cb_smeny_sablona_nacist($db, $idTemplate, [(int)$branch['id_pob'] => $branch]);
    if ($template === null) {
        throw new CbUserVisibleException('Vybraná šablona nepatří k této pobočce.');
    }
    $stmt = $db->prepare('SELECT COUNT(*) AS pocet FROM smeny_obsazeni o INNER JOIN smeny_rozpis_blok b ON b.id_smeny_rozpis_blok=o.id_smeny_rozpis_blok WHERE b.id_smeny_rozpis=? AND o.stav="prirazeno"');
    $stmt->bind_param('i', $idPlan);
    $stmt->execute();
    $assigned = (int)($stmt->get_result()->fetch_assoc()['pocet'] ?? 0);
    $stmt->close();
    if ($assigned > 0) {
        throw new CbUserVisibleException('Šablonu lze změnit jen před přidáním prvního zaměstnance.');
    }
    $stmt = $db->prepare('SELECT stav FROM smeny_rozpis WHERE id_smeny_rozpis=? LIMIT 1');
    $stmt->bind_param('i', $idPlan);
    $stmt->execute();
    $planState = (string)($stmt->get_result()->fetch_assoc()['stav'] ?? '');
    $stmt->close();
    if ($planState !== 'rozpracovany') {
        throw new CbUserVisibleException('Šablonu lze načíst pouze do rozpracovaného týdne.');
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE smeny_rozpis_blok SET stav="zruseny" WHERE id_smeny_rozpis=? AND stav<>"zruseny"');
        $stmt->bind_param('i', $idPlan);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('UPDATE smeny_rozpis SET id_smeny_sablona=? WHERE id_smeny_rozpis=? AND stav="rozpracovany"');
        $stmt->bind_param('ii', $idTemplate, $idPlan);
        $stmt->execute();
        $stmt->close();
        cb_smeny_planovani_vlozit_sablonu($db, $idPlan, $template, $week);
        cb_smeny_audit_zapis($db, $idPerson, 'obnovena_sablona', 'rozpis', $idPlan, null, ['id_smeny_sablona' => $idTemplate]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
