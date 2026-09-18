<?php
declare(strict_types=1);

/** @return array{firmy:array<int,array{id_firma:int,nazev:string}>,pobocky:array<int,array<string,mixed>>} */
function hr_nastaveni_pobocky(mysqli $db, int $idUser): array
{
    $ids = cb_firemni_pristup_firmy($db, $idUser);
    if ($ids === []) {
        return ['firmy' => [], 'pobocky' => []];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $db->prepare('SELECT id_firma, obchodni_jmeno FROM firma WHERE id_firma IN (' . $marks . ') ORDER BY id_firma');
    $refs = [&$types];
    foreach ($ids as $i => $id) { $refs[] = &$ids[$i]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $firmy = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $firmy[] = ['id_firma' => (int)$row['id_firma'], 'nazev' => (string)$row['obchodni_jmeno']];
    }
    $stmt->close();

    $stmt = $db->prepare('SELECT p.id_pob, p.id_firma, p.kod, p.nazev, p.ulice, p.mesto, p.oblast, p.psc, p.aktivni, f.obchodni_jmeno AS firma FROM pobocka p JOIN firma f ON f.id_firma = p.id_firma WHERE p.id_firma IN (' . $marks . ') ORDER BY p.id_firma, p.id_pob');
    $refs = [&$types];
    foreach ($ids as $i => $id) { $refs[] = &$ids[$i]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $pobocky = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id_pob'] = (int)$row['id_pob'];
        $row['id_firma'] = (int)$row['id_firma'];
        $row['aktivni'] = (int)$row['aktivni'];
        $pobocky[] = $row;
    }
    $stmt->close();
    return ['firmy' => $firmy, 'pobocky' => $pobocky];
}

function hr_nastaveni_pobocka_pridat(mysqli $db, array $data, int $idUser): void
{
    $idFirma = (int)($data['id_firma'] ?? 0);
    if (!cb_firemni_pristup_ma_firmu($db, $idUser, $idFirma)) {
        throw new CbUserVisibleException('Nemáte oprávnění přidat pobočku této firmě.');
    }
    $kod = trim((string)($data['kod'] ?? ''));
    $nazev = trim((string)($data['nazev'] ?? ''));
    $ulice = trim((string)($data['ulice'] ?? ''));
    $mesto = trim((string)($data['mesto'] ?? ''));
    $oblast = trim((string)($data['oblast'] ?? ''));
    $pscText = preg_replace('/\s+/', '', (string)($data['psc'] ?? '')) ?? '';
    if ($kod === '' || $nazev === '' || $mesto === '' || !preg_match('/^\d{5}$/', $pscText)) {
        throw new CbUserVisibleException('Vyplňte kód, název, město a pětimístné PSČ.');
    }
    if (mb_strlen($kod, 'UTF-8') > 20 || mb_strlen($nazev, 'UTF-8') > 100 || mb_strlen($ulice, 'UTF-8') > 150 || mb_strlen($mesto, 'UTF-8') > 100 || mb_strlen($oblast, 'UTF-8') > 50) {
        throw new CbUserVisibleException('Některý údaj pobočky je příliš dlouhý.');
    }
    $psc = (int)$pscText;
    $stmt = $db->prepare("INSERT INTO pobocka (id_firma, kod, nazev, ulice, mesto, oblast, psc, zadal, aktivni, pob_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, '#808080')");
    $stmt->bind_param('isssssii', $idFirma, $kod, $nazev, $ulice, $mesto, $oblast, $psc, $idUser);
    $stmt->execute();
    $stmt->close();
}

function hr_nastaveni_pobocka_zmenit_stav(mysqli $db, int $idPob, bool $aktivni, int $idUser): void
{
    $stmt = $db->prepare('SELECT id_firma FROM pobocka WHERE id_pob = ? LIMIT 1');
    $stmt->bind_param('i', $idPob);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row) || !cb_firemni_pristup_ma_firmu($db, $idUser, (int)$row['id_firma'])) {
        throw new CbUserVisibleException('Nemáte oprávnění změnit tuto pobočku.');
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
    $stmt = $db->prepare('UPDATE pobocka SET aktivni = ?, zadal = ?, zadano = NOW() WHERE id_pob = ?');
    $stmt->bind_param('iii', $stav, $idUser, $idPob);
    $stmt->execute();
    if ($stmt->affected_rows < 1) {
        $stmt->close();
        throw new RuntimeException('Pobočku se nepodařilo změnit.');
    }
    $stmt->close();
}
