<?php
declare(strict_types=1);

/*
 * Účel souboru: Jednotně určuje firemní rozsah uživatele podle jeho domovské
 * firmy, role a stromu firem. Neřeší HTML ani změny pracovních poměrů.
 */

/** @return array{id_firma:int,admin:bool,top_management:bool} */
function cb_firemni_pristup_uzivatel(mysqli $db, int $idUser): array
{
    if ($idUser <= 0) {
        return ['id_firma' => 0, 'admin' => false, 'top_management' => false];
    }
    $stmt = $db->prepare('SELECT u.id_firma, MAX(CASE WHEN ur.id_role = 1 THEN 1 ELSE 0 END) AS admin, MAX(CASE WHEN ur.id_role = 2 THEN 1 ELSE 0 END) AS top_management FROM user u LEFT JOIN user_role ur ON ur.id_user = u.id_user WHERE u.id_user = ? GROUP BY u.id_user, u.id_firma LIMIT 1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        return ['id_firma' => 0, 'admin' => false, 'top_management' => false];
    }
    return [
        'id_firma' => (int)$row['id_firma'],
        'admin' => (int)$row['admin'] === 1,
        'top_management' => (int)$row['top_management'] === 1,
    ];
}

/** @return int[] */
function cb_firemni_pristup_firmy(mysqli $db, int $idUser): array
{
    $user = cb_firemni_pristup_uzivatel($db, $idUser);
    $result = $db->query('SELECT id_firma, id_firma_nadrazena FROM firma WHERE aktivni = 1 AND platnost_do IS NULL ORDER BY id_firma');
    $parents = [];
    while ($row = $result->fetch_assoc()) {
        $parents[(int)$row['id_firma']] = $row['id_firma_nadrazena'] !== null ? (int)$row['id_firma_nadrazena'] : 0;
    }
    $result->free();

    if ($user['admin']) {
        return array_keys($parents);
    }
    $idFirma = $user['id_firma'];
    if ($idFirma <= 0 || !array_key_exists($idFirma, $parents)) {
        return [];
    }
    if (!$user['top_management']) {
        return [$idFirma];
    }

    $root = $idFirma;
    $visited = [];
    while (($parents[$root] ?? 0) > 0 && !isset($visited[$root])) {
        $visited[$root] = true;
        $root = $parents[$root];
    }
    $allowed = [$root => true];
    do {
        $changed = false;
        foreach ($parents as $firma => $parent) {
            if (!isset($allowed[$firma]) && isset($allowed[$parent])) {
                $allowed[$firma] = true;
                $changed = true;
            }
        }
    } while ($changed);

    $ids = array_map('intval', array_keys($allowed));
    sort($ids);
    return $ids;
}

function cb_firemni_pristup_je_admin(mysqli $db, int $idUser): bool
{
    return cb_firemni_pristup_uzivatel($db, $idUser)['admin'];
}

function cb_firemni_pristup_ma_firmu(mysqli $db, int $idUser, int $idFirma): bool
{
    return $idFirma > 0 && in_array($idFirma, cb_firemni_pristup_firmy($db, $idUser), true);
}

function cb_firemni_pristup_muze_osobu(mysqli $db, int $idUser, int $idPerson): bool
{
    if ($idPerson <= 0) {
        return false;
    }
    $stmt = $db->prepare('SELECT id_firma FROM hr_person WHERE id_person = ? LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        return false;
    }
    return cb_firemni_pristup_ma_firmu($db, $idUser, (int)$row['id_firma']);
}

function cb_firemni_pristup_vyzaduj_osobu(mysqli $db, int $idUser, int $idPerson): void
{
    if (!cb_firemni_pristup_muze_osobu($db, $idUser, $idPerson)) {
        throw new CbUserVisibleException('Nemáte oprávnění pracovat s touto osobou.');
    }
}
