<?php
// lib/denni_report_vyroba.php * Denní report pobočky Výroba bez Restie.
declare(strict_types=1);

require_once __DIR__ . '/denni_report_data.php';
require_once __DIR__ . '/../db/db_zapis_denni_report.php';

const CB_VYROBA_ID_POB = 7;
const CB_VYROBA_REPORT_SLOT = 3; // Stejná technická hodnota jako u importované Google historie výroby.

function cb_vyroba_user_has_branch(mysqli $conn, int $idPerson): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM hr_person hp WHERE hp.id_person = ? AND hp.aktivni = 1 AND (hp.pristup_vsechny_pobocky = 1 OR EXISTS (SELECT 1 FROM hr_pracoviste prac WHERE prac.id_person = hp.id_person AND prac.id_pob = ? AND prac.platny = 1)) LIMIT 1');
    $branch = CB_VYROBA_ID_POB;
    $stmt->bind_param('ii', $idPerson, $branch);
    $stmt->execute();
    $result = $stmt->get_result();
    $allowed = $result instanceof mysqli_result && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();
    return $allowed;
}

function cb_vyroba_user_main_branch(mysqli $conn, int $idPerson): bool
{
    return cb_denni_report_user_main_branch_id($conn, $idPerson) === CB_VYROBA_ID_POB;
}

function cb_vyroba_should_render(mysqli $conn, int $idPerson): bool
{
    $requested = (int)($_POST['zr_id_pob'] ?? $_GET['zr_id_pob'] ?? 0);
    return $requested === CB_VYROBA_ID_POB
        || ($requested <= 0 && cb_vyroba_user_main_branch($conn, $idPerson));
}

/** @return array<int,string> */
function cb_vyroba_allowed_branches(mysqli $conn, int $idPerson): array
{
    $stmt = $conn->prepare('SELECT p.id_pob, p.nazev FROM pobocka p INNER JOIN hr_person hp ON hp.id_person = ? AND hp.aktivni = 1 WHERE p.aktivni = 1 AND (hp.pristup_vsechny_pobocky = 1 OR EXISTS (SELECT 1 FROM hr_pracoviste prac WHERE prac.id_person = hp.id_person AND prac.id_pob = p.id_pob AND prac.platny = 1)) ORDER BY p.id_pob');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $result = $stmt->get_result();
    $branches = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $branches[(int)$row['id_pob']] = (string)$row['nazev'];
        }
        $result->free();
    }
    $stmt->close();
    return $branches;
}

/** @return array<int,array{id_person:int,name:string}> */
function cb_vyroba_people_options(mysqli $conn): array
{
    $branch = CB_VYROBA_ID_POB;
    $stmt = $conn->prepare('SELECT DISTINCT hp.id_person, TRIM(CONCAT_WS(" ", ou.prijmeni, ou.jmeno)) AS name FROM hr_person hp INNER JOIN `user` u ON u.id_user = hp.id_person INNER JOIN hr_osobni_udaje ou ON ou.id_person = hp.id_person AND ou.platny = 1 INNER JOIN hr_pracoviste prac ON prac.id_person = hp.id_person AND prac.id_pob = ? AND prac.platny = 1 WHERE hp.aktivni = 1 ORDER BY name, hp.id_person');
    $stmt->bind_param('i', $branch);
    $stmt->execute();
    $result = $stmt->get_result();
    $options = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') {
                $options[] = ['id_person' => (int)$row['id_person'], 'name' => $name];
            }
        }
        $result->free();
    }
    $stmt->close();
    return $options;
}

function cb_vyroba_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function cb_vyroba_money(mixed $value, string $label): float
{
    if (!is_scalar($value) && $value !== null) {
        throw new CbUserVisibleException($label . ': neplatná částka.');
    }
    $text = str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], trim((string)$value));
    if ($text === '') {
        return 0.0;
    }
    if (preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $text) !== 1) {
        throw new CbUserVisibleException($label . ': zadej nezápornou částku nejvýše na dvě desetinná místa.');
    }
    return (float)$text;
}

/** @return array{hours:float,reason:string} */
function cb_vyroba_shift_hours(string $start, string $end, float $pause): array
{
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) !== 1
        || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) !== 1) {
        return ['hours' => 0.0, 'reason' => 'Čas musí být ve formátu HH:MM.'];
    }
    $startParts = array_map('intval', explode(':', $start));
    $endParts = array_map('intval', explode(':', $end));
    $startMinutes = $startParts[0] * 60 + $startParts[1];
    $endMinutes = $endParts[0] * 60 + $endParts[1];
    if ($endMinutes === $startMinutes) {
        return ['hours' => 0.0, 'reason' => 'Konec směny musí být jiný než začátek.'];
    }
    if ($endMinutes < $startMinutes) {
        $endMinutes += 1440;
    }
    $length = ($endMinutes - $startMinutes) / 60;
    if ($length > 24 || $pause < 0 || $pause >= $length) {
        return ['hours' => 0.0, 'reason' => 'Pauza musí být kratší než směna a směna nesmí přesáhnout 24 hodin.'];
    }
    return ['hours' => round($length - $pause, 2), 'reason' => ''];
}

/** @return array<int,array{id_person:int,start:string,end:string,pause:float,hours:float}> */
function cb_vyroba_people_from_post(mysqli $conn, array $post, ?array $existing): array
{
    $ids = $post['vyroba_id_person'] ?? [];
    $starts = $post['vyroba_zacatek'] ?? [];
    $ends = $post['vyroba_konec'] ?? [];
    $pauses = $post['vyroba_pauza'] ?? [];
    if (!is_array($ids) || !is_array($starts) || !is_array($ends) || !is_array($pauses) || count($ids) > 50) {
        throw new CbUserVisibleException('Neplatný seznam pracovníků výroby.');
    }
    $available = array_column(cb_vyroba_people_options($conn), 'name', 'id_person');
    $existingIds = [];
    foreach ((array)($existing['people_rows'] ?? []) as $row) {
        $id = (int)($row['id_user'] ?? 0);
        if ($id > 0) {
            $existingIds[$id] = true;
        }
    }
    $rows = [];
    $seen = [];
    foreach ($ids as $index => $rawId) {
        $id = (int)$rawId;
        if ($id <= 0) {
            continue;
        }
        if (!isset($available[$id]) && !isset($existingIds[$id])) {
            throw new CbUserVisibleException('Pracovník ID ' . $id . ' není přiřazen k Výrobě v HR.');
        }
        $personLabel = trim((string)($available[$id] ?? cb_denni_report_user_full_name_by_id($conn, $id)));
        if ($personLabel === '') {
            $personLabel = 'Pracovník ID ' . $id;
        }
        if (isset($seen[$id])) {
            throw new CbUserVisibleException($personLabel . ' je v reportu uveden dvakrát.');
        }
        $seen[$id] = true;
        $start = trim((string)($starts[$index] ?? ''));
        $end = trim((string)($ends[$index] ?? ''));
        $pauseText = str_replace(',', '.', trim((string)($pauses[$index] ?? '')));
        if ($pauseText === '') {
            $pauseText = '0';
        }
        if (preg_match('/^\d{1,2}(?:\.\d{1,2})?$/', $pauseText) !== 1) {
            throw new CbUserVisibleException($personLabel . ': neplatná pauza.');
        }
        $pause = (float)$pauseText;
        $worked = cb_vyroba_shift_hours($start, $end, $pause);
        if ($worked['reason'] !== '') {
            throw new CbUserVisibleException($personLabel . ', směna ' . $start . '–' . $end . ': ' . $worked['reason']);
        }
        $rows[] = ['id_person' => $id, 'start' => $start, 'end' => $end, 'pause' => $pause, 'hours' => $worked['hours']];
    }
    if ($rows === []) {
        throw new CbUserVisibleException('Vyber alespoň jednoho pracovníka výroby.');
    }
    return $rows;
}

function cb_vyroba_save(mysqli $conn, int $actorId, array $post): void
{
    if ($actorId <= 0 || !cb_vyroba_user_has_branch($conn, $actorId)) {
        throw new CbUserVisibleException('Nemáš právo uložit report výroby.');
    }
    if ((int)($post['id_pob'] ?? 0) !== CB_VYROBA_ID_POB) {
        throw new CbUserVisibleException('Neplatná pobočka reportu.');
    }
    $date = trim((string)($post['datum_reportu'] ?? ''));
    if (!cb_vyroba_valid_date($date) || $date > date('Y-m-d')) {
        throw new CbUserVisibleException('Vyber dnešní nebo dřívější platné datum reportu.');
    }
    $existing = cb_denni_report_history_load($conn, CB_VYROBA_ID_POB, $date);
    $existingReport = (array)($existing['report'] ?? []);
    $existingId = (int)($existingReport['id_reportu'] ?? 0);
    if ((int)($post['vyroba_expected_report_id'] ?? -1) !== $existingId) {
        throw new CbUserVisibleException('Report se mezitím změnil. Obnov stránku a zkus to znovu.');
    }
    if ($existingId === 0 && !cb_denni_report_ma_pravo(CB_DENNI_REPORT_UZAVRIT_PRAVO)) {
        throw new CbUserVisibleException('Nemáš právo založit report výroby.');
    }
    if ($existingId > 0 && (int)($existingReport['zdroj'] ?? 0) === 1) {
        throw new CbUserVisibleException('Historický report z Google je pouze ke čtení.');
    }
    if ($existingId > 0 && (!cb_denni_report_ma_pravo(CB_DENNI_REPORT_EDITOVAT_PRAVO) || !cb_vyroba_user_main_branch($conn, $actorId))) {
        throw new CbUserVisibleException('Nemáš právo opravit již uložený report výroby.');
    }
    foreach ((array)($existing['people_rows'] ?? []) as $existingPerson) {
        if ($existingId > 0 && (int)($existingPerson['id_user'] ?? 0) <= 0) {
            throw new CbUserVisibleException('Report obsahuje historickou osobu bez ID. Úprava je zablokována, aby se neztratila.');
        }
    }
    $people = cb_vyroba_people_from_post($conn, $post, $existing);
    $fuel = cb_vyroba_money($post['vyroba_palivo'] ?? '', 'Benzín / nafta');
    $ingredients = cb_vyroba_money($post['vyroba_suroviny'] ?? '', 'Suroviny');
    $other = cb_vyroba_money($post['vyroba_ostatni'] ?? '', 'Ostatní');
    if (!is_scalar($post['vyroba_vzkaz'] ?? '')) {
        throw new CbUserVisibleException('Vzkaz má neplatný formát.');
    }
    $note = trim((string)($post['vyroba_vzkaz'] ?? ''));
    if (mb_strlen($note, 'UTF-8') > 10000) {
        throw new CbUserVisibleException('Vzkaz může mít nejvýše 10 000 znaků.');
    }
    $branch = CB_VYROBA_ID_POB;
    $slot = CB_VYROBA_REPORT_SLOT;
    $lock = cb_db_reporty_is_acquire_lock($conn, $branch, $date);
    try {
        $conn->begin_transaction();
        $currentId = cb_db_reporty_is_find_active_id($conn, $branch, $date);
        if ($currentId !== $existingId) {
            throw new CbUserVisibleException('Report se mezitím změnil. Obnov stránku a zkus to znovu.');
        }
        if ($currentId > 0) {
            cb_db_reporty_is_mark_invalid($conn, $currentId);
        }
        $stmt = $conn->prepare('INSERT INTO reporty_is (datum_reportu, id_pob, poznamka, zdroj, zadal, platny) VALUES (?, ?, ?, 2, ?, 1)');
        $stmt->bind_param('sisi', $date, $branch, $note, $actorId);
        $stmt->execute();
        $reportId = (int)$conn->insert_id;
        $stmt->close();
        $stmt = $conn->prepare('INSERT INTO reporty_is_pokladna (id_reportu, vydaje_auta, vydaje_suroviny, vydaje_ostatni) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iddd', $reportId, $fuel, $ingredients, $other);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('INSERT INTO reporty_is_osoby (id_reportu, id_user, slot, smena_od, smena_do, pauza, odpracovano) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($people as $person) {
            $personId = $person['id_person'];
            $start = $person['start'];
            $end = $person['end'];
            $pause = $person['pause'];
            $hours = $person['hours'];
            $stmt->bind_param('iiissdd', $reportId, $personId, $slot, $start, $end, $pause, $hours);
            $stmt->execute();
        }
        $stmt->close();
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    } finally {
        cb_db_reporty_is_release_lock($conn, $lock);
    }
}
