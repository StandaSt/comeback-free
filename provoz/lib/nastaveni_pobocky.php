<?php
/*
 * Sprava pobocek v modulu Provoz: nacita dostupne firmy a pobocky,
 * kontroluje vstupy a uklada zakladni udaje, kontakty a zaviraci dobu.
 */
declare(strict_types=1);

require_once __DIR__ . '/nastaveni_prava.php';
require_once __DIR__ . '/pobocka_provoz.php';
require_once __DIR__ . '/../../common/lib/firemni_pristup.php';
require_once __DIR__ . '/../../common/lib/form_post.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';

// Vrati ID prihlaseneho uzivatele pouzite pro rozsah firem a audit zmen.
function cb_provoz_nastaveni_user_id(): int
{
    $user = $_SESSION['cb_user'] ?? [];
    return is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
}

// Vyzada pravo 217 pred zobrazenim nebo ulozenim spravy pobocek.
function cb_provoz_nastaveni_vyzaduj_pravo(): void
{
    if (!cb_provoz_nastaveni_ma_pravo()) {
        throw new CbUserVisibleException('Nemáte právo spravovat nastavení poboček.');
    }
}

// Vrati povolena ID firem jako bezpecny seznam pro SQL dotazy.
function cb_provoz_nastaveni_firmy_ids(mysqli $db, int $idUser): array
{
    return array_values(array_filter(
        array_map('intval', cb_firemni_pristup_firmy($db, $idUser)),
        static fn(int $idFirma): bool => $idFirma > 0
    ));
}

/** @return array{firmy:array<int,array{id_firma:int,nazev:string}>,pobocky:array<int,array<string,mixed>>} */
function cb_provoz_nastaveni_pobocky_data(mysqli $db, int $idUser): array
{
    $ids = cb_provoz_nastaveni_firmy_ids($db, $idUser);
    if ($ids === []) {
        return ['firmy' => [], 'pobocky' => []];
    }
    $idList = implode(',', $ids);

    $firmy = [];
    $result = $db->query('SELECT id_firma, obchodni_jmeno FROM firma WHERE id_firma IN (' . $idList . ') ORDER BY obchodni_jmeno');
    while ($row = $result->fetch_assoc()) {
        $firmy[] = ['id_firma' => (int)$row['id_firma'], 'nazev' => (string)$row['obchodni_jmeno']];
    }
    $result->free();

    $pobocky = [];
    $result = $db->query(
        'SELECT p.id_pob, p.id_firma, p.kod, p.nazev, p.ulice, p.mesto, p.oblast, p.psc, p.aktivni, '
        . 'p.end_po, p.end_ut, p.end_st, p.end_ct, p.end_pa, p.end_so, p.end_ne, f.obchodni_jmeno AS firma '
        . 'FROM pobocka p INNER JOIN firma f ON f.id_firma = p.id_firma '
        . 'WHERE p.id_firma IN (' . $idList . ') ORDER BY p.aktivni DESC, f.obchodni_jmeno, p.nazev'
    );
    while ($row = $result->fetch_assoc()) {
        $row['id_pob'] = (int)$row['id_pob'];
        $row['id_firma'] = (int)$row['id_firma'];
        $row['aktivni'] = (int)$row['aktivni'];
        foreach (cb_pobocka_provoz_weekdays() as $day) {
            $key = (string)$day['key'];
            $row[$key] = substr((string)$row[$key], 0, 5);
        }
        $row['email'] = [];
        $row['telefon'] = [];
        $pobocky[(int)$row['id_pob']] = $row;
    }
    $result->free();

    if ($pobocky !== []) {
        $pobIds = implode(',', array_keys($pobocky));
        $result = $db->query('SELECT id_pob, email FROM pob_email WHERE aktivni = 1 AND id_pob IN (' . $pobIds . ') ORDER BY email');
        while ($row = $result->fetch_assoc()) {
            $pobocky[(int)$row['id_pob']]['email'][] = (string)$row['email'];
        }
        $result->free();

        $result = $db->query('SELECT id_pob, telefon FROM pob_tel WHERE aktivni = 1 AND id_pob IN (' . $pobIds . ') ORDER BY telefon');
        while ($row = $result->fetch_assoc()) {
            $pobocky[(int)$row['id_pob']]['telefon'][] = (string)$row['telefon'];
        }
        $result->free();
    }

    return ['firmy' => $firmy, 'pobocky' => array_values($pobocky)];
}

// Nacte pobocku jen tehdy, kdy patri do firemniho rozsahu uzivatele.
function cb_provoz_nastaveni_pobocka(mysqli $db, int $idPob, int $idUser): ?array
{
    if ($idPob <= 0) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM pobocka WHERE id_pob = ? LIMIT 1');
    $stmt->bind_param('i', $idPob);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row) || !cb_firemni_pristup_ma_firmu($db, $idUser, (int)$row['id_firma'])) {
        return null;
    }
    return $row;
}

// Sjednoti kontaktni stitky z formulare, odstrani duplicity a prazdne hodnoty.
function cb_provoz_nastaveni_kontakty(mixed $value, string $type): array
{
    $values = is_array($value) ? $value : [$value];
    $parts = [];
    foreach ($values as $item) {
        if (!is_scalar($item)) {
            continue;
        }
        array_push($parts, ...(preg_split('/[\r\n,;]+/u', (string)$item) ?: []));
    }
    $contacts = [];
    foreach ($parts as $part) {
        $contact = trim($part);
        if ($contact === '') {
            continue;
        }
        if ($type === 'email') {
            if (mb_strlen($contact, 'UTF-8') > 150 || filter_var($contact, FILTER_VALIDATE_EMAIL) === false) {
                throw new CbUserVisibleException('E-mail „' . $contact . '“ není platný.');
            }
            $key = mb_strtolower($contact, 'UTF-8');
        } else {
            if (mb_strlen($contact, 'UTF-8') > 30) {
                throw new CbUserVisibleException('Telefon „' . $contact . '“ je příliš dlouhý.');
            }
            $key = $contact;
        }
        $contacts[$key] = $contact;
    }
    return array_values($contacts);
}

// Zkontroluje udaje pobocky a pripravi je ve formatu vhodnem pro DB.
function cb_provoz_nastaveni_validace(array $data): array
{
    $kod = trim((string)($data['kod'] ?? ''));
    $nazev = trim((string)($data['nazev'] ?? ''));
    $ulice = trim((string)($data['ulice'] ?? ''));
    $mesto = trim((string)($data['mesto'] ?? ''));
    $oblast = trim((string)($data['oblast'] ?? ''));
    $pscText = preg_replace('/\s+/', '', (string)($data['psc'] ?? '')) ?? '';
    if ($kod === '' || $nazev === '' || $mesto === '' || preg_match('/^\d{5}$/', $pscText) !== 1) {
        throw new CbUserVisibleException('Vyplňte kód, název, město a pětimístné PSČ.');
    }
    if (mb_strlen($kod, 'UTF-8') > 20 || mb_strlen($nazev, 'UTF-8') > 100 || mb_strlen($ulice, 'UTF-8') > 150 || mb_strlen($mesto, 'UTF-8') > 100 || mb_strlen($oblast, 'UTF-8') > 50) {
        throw new CbUserVisibleException('Některý údaj pobočky je příliš dlouhý.');
    }

    $times = [];
    foreach (cb_pobocka_provoz_weekdays() as $day) {
        $key = (string)$day['key'];
        $times[$key] = cb_pobocka_provoz_valid_time((string)($data[$key] ?? ''), (string)$day['label']);
    }
    return [
        'kod' => $kod,
        'nazev' => $nazev,
        'ulice' => $ulice,
        'mesto' => $mesto,
        'oblast' => $oblast,
        'psc' => (int)$pscText,
        'email' => cb_provoz_nastaveni_kontakty($data['email'] ?? '', 'email'),
        'telefon' => cb_provoz_nastaveni_kontakty($data['telefon'] ?? '', 'telefon'),
        'times' => $times,
    ];
}

// Nahradi aktivni kontakty novym seznamem a historicke zaznamy ponecha neaktivni.
function cb_provoz_nastaveni_uloz_kontakty(mysqli $db, int $idPob, int $idUser, array $emails, array $phones): void
{
    $stmt = $db->prepare('UPDATE pob_email SET aktivni = 0 WHERE id_pob = ? AND aktivni = 1');
    $stmt->bind_param('i', $idPob);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare('INSERT INTO pob_email (id_pob, email, zadal, zadano, aktivni) VALUES (?, ?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE zadal = VALUES(zadal), zadano = NOW(), aktivni = 1');
    foreach ($emails as $email) {
        $stmt->bind_param('isi', $idPob, $email, $idUser);
        $stmt->execute();
    }
    $stmt->close();

    $stmt = $db->prepare('UPDATE pob_tel SET aktivni = 0 WHERE id_pob = ? AND aktivni = 1');
    $stmt->bind_param('i', $idPob);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare('INSERT INTO pob_tel (id_pob, telefon, zadal, zadano, aktivni) VALUES (?, ?, ?, NOW(), 1) ON DUPLICATE KEY UPDATE zadal = VALUES(zadal), zadano = NOW(), aktivni = 1');
    foreach ($phones as $phone) {
        $stmt->bind_param('isi', $idPob, $phone, $idUser);
        $stmt->execute();
    }
    $stmt->close();
}

// Zapise auditni stopu zmeny pobocek do spolecneho protokolu akci.
function cb_provoz_nastaveni_log(int $idPob, string $action): void
{
    if (!cb_user_akce_zapis([
        'id_user_akce_typ' => 14,
        'modul' => 'provoz',
        'zdroj' => 'nastaveni_pobocky',
        'objekt' => 'pobocka',
        'id_objektu' => $idPob,
        'pole' => $action,
    ])) {
        throw new RuntimeException('Změnu se nepodařilo zapsat do protokolu akcí.');
    }
}

// Prida novou pobocku vcetne kontaktu a zaviraci doby.
function cb_provoz_nastaveni_pridat(mysqli $db, array $data, int $idUser): string
{
    $idFirma = (int)($data['id_firma'] ?? 0);
    if (!cb_firemni_pristup_ma_firmu($db, $idUser, $idFirma)) {
        throw new CbUserVisibleException('Nemáte oprávnění přidat pobočku této firmě.');
    }
    $v = cb_provoz_nastaveni_validace($data);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("INSERT INTO pobocka (id_firma, kod, nazev, ulice, mesto, oblast, psc, zadal, aktivni, pob_color, end_po, end_ut, end_st, end_ct, end_pa, end_so, end_ne) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, '#808080', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssssiisssssss', $idFirma, $v['kod'], $v['nazev'], $v['ulice'], $v['mesto'], $v['oblast'], $v['psc'], $idUser, $v['times']['end_po'], $v['times']['end_ut'], $v['times']['end_st'], $v['times']['end_ct'], $v['times']['end_pa'], $v['times']['end_so'], $v['times']['end_ne']);
        $stmt->execute();
        $idPob = (int)$db->insert_id;
        $stmt->close();
        cb_provoz_nastaveni_uloz_kontakty($db, $idPob, $idUser, $v['email'], $v['telefon']);
        cb_provoz_nastaveni_log($idPob, 'pridani');
        $db->commit();
    } catch (mysqli_sql_exception $e) {
        $db->rollback();
        if ((int)$e->getCode() === 1062) {
            throw new CbUserVisibleException('Pobočku nelze přidat, protože zadaný kód už existuje.');
        }
        throw $e;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return 'Pobočka byla přidána.';
}

// Ulozi upravitelne udaje existujici pobocky.
function cb_provoz_nastaveni_ulozit(mysqli $db, array $data, int $idUser): string
{
    $idPob = (int)($data['id_pob'] ?? 0);
    if (!is_array(cb_provoz_nastaveni_pobocka($db, $idPob, $idUser))) {
        throw new CbUserVisibleException('Vybraná pobočka není dostupná.');
    }
    $v = cb_provoz_nastaveni_validace($data);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE pobocka SET kod = ?, nazev = ?, ulice = ?, mesto = ?, oblast = ?, psc = ?, zadal = ?, zadano = NOW(), end_po = ?, end_ut = ?, end_st = ?, end_ct = ?, end_pa = ?, end_so = ?, end_ne = ? WHERE id_pob = ?');
        $stmt->bind_param('sssssiisssssssi', $v['kod'], $v['nazev'], $v['ulice'], $v['mesto'], $v['oblast'], $v['psc'], $idUser, $v['times']['end_po'], $v['times']['end_ut'], $v['times']['end_st'], $v['times']['end_ct'], $v['times']['end_pa'], $v['times']['end_so'], $v['times']['end_ne'], $idPob);
        $stmt->execute();
        $stmt->close();
        cb_provoz_nastaveni_uloz_kontakty($db, $idPob, $idUser, $v['email'], $v['telefon']);
        cb_provoz_nastaveni_log($idPob, 'uprava');
        $db->commit();
    } catch (mysqli_sql_exception $e) {
        $db->rollback();
        if ((int)$e->getCode() === 1062) {
            throw new CbUserVisibleException('Změnu nelze uložit, protože zadaný kód už používá jiná pobočka.');
        }
        throw $e;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return 'Pobočka byla uložena.';
}

// Aktivuje nebo deaktivuje pobocku; aktivni pracoviste brani deaktivaci.
function cb_provoz_nastaveni_zmenit_stav(mysqli $db, array $data, int $idUser): string
{
    $idPob = (int)($data['id_pob'] ?? 0);
    $aktivni = (int)($data['aktivni'] ?? 0) === 1;
    if (!is_array(cb_provoz_nastaveni_pobocka($db, $idPob, $idUser))) {
        throw new CbUserVisibleException('Vybraná pobočka není dostupná.');
    }
    if (!$aktivni) {
        $stmt = $db->prepare('SELECT COUNT(*) AS pocet FROM hr_pracoviste WHERE id_pob = ? AND platny = 1 AND (platnost_do IS NULL OR platnost_do >= CURDATE())');
        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $pocet = (int)($stmt->get_result()->fetch_assoc()['pocet'] ?? 0);
        $stmt->close();
        if ($pocet > 0) {
            throw new CbUserVisibleException('Pobočku nelze deaktivovat, dokud je přiřazena zaměstnancům. Nejprve jim nastavte jinou pobočku s platností od požadovaného data.');
        }
    }
    $stav = $aktivni ? 1 : 0;
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE pobocka SET aktivni = ?, zadal = ?, zadano = NOW() WHERE id_pob = ?');
        $stmt->bind_param('iii', $stav, $idUser, $idPob);
        $stmt->execute();
        $stmt->close();
        cb_provoz_nastaveni_log($idPob, $aktivni ? 'aktivace' : 'deaktivace');
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return $aktivni ? 'Pobočka byla aktivována.' : 'Pobočka byla deaktivována.';
}

// Zpracuje standardni POST formular, ulozi vysledek do session a provede PRG.
function cb_provoz_nastaveni_pobocky_handle_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE'])) {
        return;
    }
    $fallback = cb_root_url('index.php?m=provoz&page=nastaveni_pobocky');
    try {
        cb_provoz_nastaveni_vyzaduj_pravo();
        if (!hash_equals(cb_pobocka_provoz_token(), (string)($_POST['token'] ?? ''))) {
            throw new CbUserVisibleException('Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.');
        }
        $idUser = cb_provoz_nastaveni_user_id();
        $action = trim((string)($_POST['cb_action'] ?? ''));
        $message = match ($action) {
            'provoz_pobocka_pridat' => cb_provoz_nastaveni_pridat(db(), $_POST, $idUser),
            'provoz_pobocka_ulozit' => cb_provoz_nastaveni_ulozit(db(), $_POST, $idUser),
            'provoz_pobocka_zmenit_stav' => cb_provoz_nastaveni_zmenit_stav(db(), $_POST, $idUser),
            default => throw new CbUserVisibleException('Neplatná akce nastavení poboček.'),
        };
        cb_form_finish($fallback, true, $message);
    } catch (Throwable $e) {
        $message = $e instanceof CbUserVisibleException ? $e->getMessage() : cb_chyba_uzivatel($e, [
            'module' => 'PROVOZ',
            'action' => 'Uložení nastavení pobočky',
        ]);
        cb_form_finish($fallback, false, $message, $_POST);
    }
}
