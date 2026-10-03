<?php
declare(strict_types=1);

/*
 * Ověří a transakčně uloží týden požadavků V2, Kdykoliv nebo volno HPP.
 */

/** Přenese výsledek uložení do dalšího zobrazení formuláře. */
function cb_smeny_pozadavky_flash(string $type, string $text): void
{
    $_SESSION['cb_smeny_pozadavky_flash'] = ['type' => $type, 'text' => $text];
}

/** Převádí noční časy na souvislou časovou osu provozního dne. */
function cb_smeny_pozadavky_cas_overit(string $time, bool $isEnd): int
{
    if (preg_match('~^(\d{2}):(\d{2})$~', $time, $match) !== 1) {
        throw new CbUserVisibleException('Čas musí být zadaný po 15 minutách.');
    }
    $hour = (int)$match[1];
    $minute = (int)$match[2];
    if ($hour > 23 || !in_array($minute, [0, 15, 30, 45], true)) {
        throw new CbUserVisibleException('Čas musí být zadaný po 15 minutách.');
    }
    $value = ($hour * 60) + $minute;
    if ($value < 360 || ($isEnd && $value <= 240)) {
        $value += 1440;
    }
    $max = $isEnd ? 1680 : 1665;
    if ($value < 360 || $value > $max) {
        throw new CbUserVisibleException('Požadavek lze zadat v rozmezí 6:00 až 4:00 následujícího dne.');
    }
    return $value;
}

/** Připraví data bez zápisu; HPP ani Kdykoliv neukládají nadbytečné denní intervaly. */
function cb_smeny_pozadavky_pripravit(array $person, array $week, array $post): array
{
    $allowedDates = [];
    foreach ($week['days'] as $day) {
        $allowedDates[(string)$day['date']] = [
            'name' => (string)$day['name'],
            'closing_key' => (string)$day['closing_key'],
        ];
    }
    $blocks = [];
    $dayOff = '';
    if ((int)$person['je_hpp'] === 1) {
        $dayOff = trim((string)($post['datum_volna'] ?? ''));
        if ($dayOff !== '' && !isset($allowedDates[$dayOff])) {
            throw new CbUserVisibleException('Vybraný den volna nepatří do ukládaného týdne.');
        }
        if ($dayOff !== '' && ((int)$person['id_pob'] <= 0 || (int)$person['id_slot'] <= 0)) {
            throw new CbUserVisibleException('Pro volbu volna musí být v HR nastavena hlavní pobočka i hlavní pracovní slot.');
        }
    } elseif (empty($post['kdykoliv'])) {
        $postedBlocks = $post['blocks'] ?? [];
        if (!is_array($postedBlocks)) {
            throw new CbUserVisibleException('Odeslané požadavky nemají správný formát.');
        }
        foreach ($postedBlocks as $date => $postedBlock) {
            $date = (string)$date;
            if (!isset($allowedDates[$date]) || !is_array($postedBlock)) {
                throw new CbUserVisibleException('Odeslaný den požadavku nepatří do týdne nebo nemá správný formát.');
            }
            $from = trim((string)($postedBlock['od'] ?? ''));
            $to = trim((string)($postedBlock['do'] ?? ''));
            if ($from === '' && $to === '') {
                continue;
            }
            if ($from === '' || $to === '') {
                throw new CbUserVisibleException($allowedDates[$date]['name'] . ': nastavte začátek i konec bloku.');
            }
            $fromValue = cb_smeny_pozadavky_cas_overit($from, false);
            $toValue = cb_smeny_pozadavky_cas_overit($to, true);
            $closing = substr(trim((string)($person[$allowedDates[$date]['closing_key']] ?? '')), 0, 5);
            if ($closing === '') {
                throw new CbUserVisibleException($allowedDates[$date]['name'] . ': pobočka nemá nastavený zavírací čas.');
            }
            $closingValue = cb_smeny_pozadavky_cas_overit($closing, true);
            if ($fromValue < 600 || (($fromValue - 600) % 30) !== 0) {
                throw new CbUserVisibleException($allowedDates[$date]['name'] . ': začátek musí být od 10:00 po 30 minutách.');
            }
            if ($to !== $closing && (($toValue - 600) % 30) !== 0) {
                throw new CbUserVisibleException($allowedDates[$date]['name'] . ': konec musí být po 30 minutách nebo přesně v zavírací čas.');
            }
            if (($toValue - $fromValue) < 180) {
                throw new CbUserVisibleException($allowedDates[$date]['name'] . ': požadavek musí trvat alespoň 3 hodiny.');
            }
            if ($toValue > $closingValue) {
                throw new CbUserVisibleException($allowedDates[$date]['name'] . ': konec nelze nastavit po zavírací době pobočky.');
            }
            $blocks[$date] = ['od' => $from, 'do' => $to];
        }
    }


    return ['rezim' => (int)$person['je_hpp'] === 1 ? 'hpp' : (!empty($post['kdykoliv']) ? 'kdykoliv' : 'intervaly'),
        'volno' => $dayOff === '' ? null : $dayOff, 'blocks' => $blocks];
}

/** Ověří právo, uzávěrku a konkrétní týden před atomickým přepsáním požadavků osoby. */
function cb_smeny_pozadavky_ulozit(mysqli $db, ?array $person, array $weeks): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST'
        || (string)($_POST['action'] ?? '') !== 'smeny_pozadavky_ulozit') {
        return;
    }
    if (!cb_smeny_pozadavky_ma_pravo() || !cb_crf_platny()) {
        throw new CbUserVisibleException('Nemáte právo zadat požadavky nebo vypršela platnost stránky.');
    }
    if ($person === null) {
        throw new CbUserVisibleException('Přihlášený účet není propojený s aktivní osobou v HR.');
    }
    $weekIndex = filter_var($_POST['week'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3]]);
    if ($weekIndex === false || !isset($weeks[$weekIndex])
        || (string)($_POST['tyden_od'] ?? '') !== (string)$weeks[$weekIndex]['start_day']) {
        throw new CbUserVisibleException('Vybraný týden už není aktuální. Obnovte stránku.');
    }
    $week = $weeks[$weekIndex];
    if (empty($week['open'])) {
        throw new CbUserVisibleException('Termín pro zadání požadavků skončil ' . $week['deadline']->format('j. n. Y v H:i') . '.');
    }
    if ((int)$person['id_pob'] <= 0 || ((int)$person['je_hpp'] === 1 && (int)$person['id_slot'] <= 0)) {
        throw new CbUserVisibleException('V HR chybí hlavní pobočka nebo hlavní pozice HPP pracovníka.');
    }
    $data = cb_smeny_pozadavky_pripravit($person, $week, $_POST);
    $idPerson = (int)$person['id_person'];
    $startDay = (string)$week['start_day'];
    $mode = $data['rezim'];
    $dayOff = $data['volno'];
    $idBranch = $dayOff === null ? null : (int)$person['id_pob'];
    $idSlot = $dayOff === null ? null : (int)$person['id_slot'];

    $db->begin_transaction();
    try {
        // Stabilní rodičovský řádek serializuje i první uložení, kdy hlavička ještě neexistuje.
        $stmt = $db->prepare('SELECT id_person FROM hr_person WHERE id_person=? AND aktivni=1 FOR UPDATE');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $active = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($active === null) {
            throw new CbUserVisibleException('Osoba už není v HR aktivní.');
        }
        if (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')) > $week['deadline']) {
            throw new CbUserVisibleException('Během ukládání skončila uzávěrka požadavků.');
        }
        $stmt = $db->prepare('SELECT id_smeny_pozadavek FROM smeny_pozadavek WHERE id_person=? AND tyden_od=? FOR UPDATE');
        $stmt->bind_param('is', $idPerson, $startDay);
        $stmt->execute();
        $idRequest = (int)($stmt->get_result()->fetch_assoc()['id_smeny_pozadavek'] ?? 0);
        $stmt->close();
        // Nepoužít upsert: druhý unikátní klíč rezervuje HPP volno jiného pracovníka.
        if ($idRequest === 0) {
            $stmt = $db->prepare('INSERT INTO smeny_pozadavek
                (id_person,tyden_od,rezim,volno_datum,volno_id_pob,volno_id_slot,ulozil_id_person)
                VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('isssiii', $idPerson, $startDay, $mode, $dayOff, $idBranch, $idSlot, $idPerson);
            $stmt->execute();
            $idRequest = (int)$db->insert_id;
        } else {
            $stmt = $db->prepare('UPDATE smeny_pozadavek SET rezim=?,volno_datum=?,volno_id_pob=?,
                volno_id_slot=?,ulozil_id_person=?,ulozeno=NOW() WHERE id_smeny_pozadavek=?');
            $stmt->bind_param('ssiiii', $mode, $dayOff, $idBranch, $idSlot, $idPerson, $idRequest);
            $stmt->execute();
        }
        $stmt->close();
        $stmt = $db->prepare('DELETE FROM smeny_pozadavek_den WHERE id_smeny_pozadavek=?');
        $stmt->bind_param('i', $idRequest);
        $stmt->execute();
        $stmt->close();
        if ($mode === 'intervaly' && $data['blocks'] !== []) {
            $stmt = $db->prepare('INSERT INTO smeny_pozadavek_den (id_smeny_pozadavek,den_tydne,cas_od,cas_do) VALUES (?,?,?,?)');
            foreach ($data['blocks'] as $date => $block) {
                $day = (int)(new DateTimeImmutable($date))->format('N');
                $from = $block['od'];
                $to = $block['do'];
                $stmt->bind_param('iiss', $idRequest, $day, $from, $to);
                $stmt->execute();
            }
            $stmt->close();
        }
        $db->commit();
    } catch (mysqli_sql_exception $e) {
        $db->rollback();
        if ((int)$e->getCode() === 1062 && $mode === 'hpp') {
            throw new CbUserVisibleException('Tento den si mezitím zvolil jiný HPP pracovník se stejnou pobočkou a pozicí. Vyberte jiný den.');
        }
        throw $e;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    cb_smeny_pozadavky_flash('success', 'Požadavky na celý týden byly uloženy.');
    header('Location: ' . cb_root_url('index.php?m=smeny&page=pozadavky&week=' . $weekIndex));
    exit;
}
