<?php
declare(strict_types=1);

/*
 * Uloží celý týden požadavků jedné osoby jako jedinou transakci.
 */

function cb_smeny_pozadavky_flash(string $type, string $text): void
{
    $_SESSION['cb_smeny_pozadavky_flash'] = ['type' => $type, 'text' => $text];
}

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

/** @param array<int,array<string,mixed>> $weeks */
function cb_smeny_pozadavky_ulozit(mysqli $db, array $person, array $weeks): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST'
        || (string)($_POST['action'] ?? '') !== 'smeny_pozadavky_ulozit') {
        return;
    }
    if (!cb_crf_platny()) {
        throw new CbUserVisibleException('Platnost stránky vypršela. Obnovte ji a požadavky uložte znovu.');
    }
    if ($person === null) {
        throw new CbUserVisibleException('Přihlášený účet není propojený s aktivní osobou v HR.');
    }

    $weekIndex = filter_var($_POST['week'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3]]);
    if ($weekIndex === false || !isset($weeks[$weekIndex])) {
        throw new CbUserVisibleException('Vybraný týden už není možné uložit. Obnovte stránku.');
    }
    $week = $weeks[$weekIndex];
    if (empty($week['open'])) {
        throw new CbUserVisibleException('Termín pro zadání požadavků skončil ve středu ve 20:00.');
    }
    if ((int)$person['id_firma'] <= 0) {
        throw new CbUserVisibleException('Osoba nemá v HR přiřazenou firmu.');
    }
    if ((int)$person['id_pob'] <= 0) {
        throw new CbUserVisibleException('Osoba nemá v HR nastavenou hlavní pobočku.');
    }
    if ((int)$person['je_hpp'] === 1 && (int)$person['id_slot'] <= 0) {
        throw new CbUserVisibleException('HPP pracovník nemá v HR nastavenou hlavní pozici.');
    }

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
        $dayOff = trim((string)($_POST['datum_volna'] ?? ''));
        if ($dayOff !== '' && !isset($allowedDates[$dayOff])) {
            throw new CbUserVisibleException('Vybraný den volna nepatří do ukládaného týdne.');
        }
        if ($dayOff !== '' && ((int)$person['id_pob'] <= 0 || (int)$person['id_slot'] <= 0)) {
            throw new CbUserVisibleException('Pro volbu volna musí být v HR nastavena hlavní pobočka i hlavní pozice.');
        }
    } else {
        $postedBlocks = $_POST['blocks'] ?? [];
        if (!is_array($postedBlocks)) {
            throw new CbUserVisibleException('Odeslané požadavky nemají správný formát.');
        }
        foreach ($postedBlocks as $date => $postedBlock) {
            $date = (string)$date;
            if (!isset($allowedDates[$date]) || !is_array($postedBlock)) {
                continue;
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

    $idPerson = (int)$person['id_person'];
    $idFirma = (int)$person['id_firma'];
    $startDay = (string)$week['start_day'];
    $deadline = $week['deadline']->format('Y-m-d H:i:s');

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id_smeny_tyden FROM smeny_tyden WHERE id_firma = ? AND start_day = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('is', $idFirma, $startDay);
        $stmt->execute();
        $idWeek = (int)($stmt->get_result()->fetch_assoc()['id_smeny_tyden'] ?? 0);
        $stmt->close();
        if ($idWeek <= 0) {
            $state = 'otevreny';
            $stmt = $db->prepare('INSERT INTO smeny_tyden (id_firma, start_day, pozadavky_od, pozadavky_deadline, stav, vytvoril_id_person) VALUES (?, ?, NOW(), ?, ?, ?)');
            $stmt->bind_param('isssi', $idFirma, $startDay, $deadline, $state, $idPerson);
            $stmt->execute();
            $idWeek = (int)$db->insert_id;
            $stmt->close();
        }

        $stmt = $db->prepare('SELECT id_smeny_pozadavek FROM smeny_pozadavek WHERE id_smeny_tyden = ? AND id_person = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('ii', $idWeek, $idPerson);
        $stmt->execute();
        $idRequest = (int)($stmt->get_result()->fetch_assoc()['id_smeny_pozadavek'] ?? 0);
        $stmt->close();
        if ($idRequest <= 0) {
            $state = 'odeslany';
            $stmt = $db->prepare('INSERT INTO smeny_pozadavek (id_smeny_tyden, id_person, stav, odeslano) VALUES (?, ?, ?, NOW())');
            $stmt->bind_param('iis', $idWeek, $idPerson, $state);
            $stmt->execute();
            $idRequest = (int)$db->insert_id;
            $stmt->close();
        } else {
            $stmt = $db->prepare('UPDATE smeny_pozadavek SET stav = "odeslany", odeslano = NOW(), uzavreno = NULL WHERE id_smeny_pozadavek = ?');
            $stmt->bind_param('i', $idRequest);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare('DELETE FROM smeny_pozadavek_blok WHERE id_smeny_pozadavek = ?');
        $stmt->bind_param('i', $idRequest);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('DELETE FROM smeny_hpp_volno WHERE id_smeny_pozadavek = ?');
        $stmt->bind_param('i', $idRequest);
        $stmt->execute();
        $stmt->close();

        if ((int)$person['je_hpp'] === 1 && $dayOff !== '') {
            $idPob = (int)$person['id_pob'];
            $idSlot = (int)$person['id_slot'];
            $stmt = $db->prepare('INSERT INTO smeny_hpp_volno (id_smeny_pozadavek, id_person, id_pob, id_slot, datum) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiiis', $idRequest, $idPerson, $idPob, $idSlot, $dayOff);
            $stmt->execute();
            $stmt->close();
        } elseif ((int)$person['je_hpp'] !== 1 && $blocks !== []) {
            $stmt = $db->prepare('INSERT INTO smeny_pozadavek_blok (id_smeny_pozadavek, datum, cas_od, cas_do) VALUES (?, ?, ?, ?)');
            foreach ($blocks as $date => $block) {
                $from = $block['od'];
                $to = $block['do'];
                $stmt->bind_param('isss', $idRequest, $date, $from, $to);
                $stmt->execute();
            }
            $stmt->close();
        }

        $db->commit();
    } catch (mysqli_sql_exception $e) {
        $db->rollback();
        if ((int)$e->getCode() === 1062 && (int)$person['je_hpp'] === 1) {
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
