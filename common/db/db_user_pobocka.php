<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/firemni_pristup.php';

/*
 * Účel souboru: Jednotné vyhodnocení přístupu usera k pobočkám.
 * pob_all=1 na jedné vazbě znamená všechny aktivní pobočky stejné firmy.
 */

function cb_db_user_ma_pobocku(mysqli $db, int $idUser, int $idPob): bool
{
    if ($idUser <= 0 || $idPob < 0) {
        return false;
    }
    $firemniUzivatel = cb_firemni_pristup_uzivatel($db, $idUser);
    if ($firemniUzivatel['admin'] || $firemniUzivatel['top_management']) {
        $allowedFirmy = cb_firemni_pristup_firmy($db, $idUser);
        if ($allowedFirmy === []) {
            return false;
        }
        $allowedFirmySql = implode(',', array_map('intval', $allowedFirmy));
        $stmt = $db->prepare('SELECT 1 FROM pobocka WHERE id_pob = ? AND aktivni = 1 AND id_firma IN (' . $allowedFirmySql . ') LIMIT 1');
        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $allowed = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        return $allowed;
    }
    $stmt = $db->prepare('
        SELECT 1
        FROM user u
        INNER JOIN pobocka cil ON cil.id_pob = ? AND cil.aktivni = 1
        LEFT JOIN user_pobocka prima ON prima.id_user = u.id_user AND prima.id_pob = cil.id_pob
        LEFT JOIN user_pobocka vse ON vse.id_user = u.id_user AND vse.pob_all = 1
        WHERE u.id_user = ?
          AND (prima.id_pob IS NOT NULL OR (vse.id_user IS NOT NULL AND cil.id_firma = u.id_firma))
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze ověřit přístup uživatele k pobočce.');
    }
    $stmt->bind_param('ii', $idPob, $idUser);
    $stmt->execute();
    $allowed = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $allowed;
}

/** @return array<int,array{id_pob:int,nazev:string,oblast:string,main:int}> */
function cb_db_user_pobocky(mysqli $db, int $idUser): array
{
    if ($idUser <= 0) {
        return [];
    }
    $firemniUzivatel = cb_firemni_pristup_uzivatel($db, $idUser);
    if ($firemniUzivatel['admin'] || $firemniUzivatel['top_management']) {
        $allowedFirmy = cb_firemni_pristup_firmy($db, $idUser);
        if ($allowedFirmy === []) {
            return [];
        }
        $allowedFirmySql = implode(',', array_map('intval', $allowedFirmy));
        $result = $db->query('SELECT id_pob, nazev, oblast, 0 AS main FROM pobocka WHERE aktivni = 1 AND id_firma IN (' . $allowedFirmySql . ') ORDER BY nazev ASC');
        $out = [];
        while ($row = $result->fetch_assoc()) {
            $out[] = [
                'id_pob' => (int)$row['id_pob'],
                'nazev' => (string)$row['nazev'],
                'oblast' => (string)($row['oblast'] ?? ''),
                'main' => 0,
            ];
        }
        $result->free();
        return $out;
    }
    $stmt = $db->prepare('
        SELECT p.id_pob, p.nazev, p.oblast, MAX(CASE WHEN up_main.main = 1 THEN 1 ELSE 0 END) AS main
        FROM user u
        INNER JOIN pobocka p ON p.id_firma = u.id_firma AND p.aktivni = 1
        LEFT JOIN user_pobocka up_main ON up_main.id_user = u.id_user AND up_main.id_pob = p.id_pob
        WHERE u.id_user = ?
          AND (
              up_main.id_pob IS NOT NULL
              OR EXISTS (SELECT 1 FROM user_pobocka up_all WHERE up_all.id_user = u.id_user AND up_all.pob_all = 1)
          )
        GROUP BY p.id_pob, p.nazev, p.oblast
        ORDER BY p.nazev ASC
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze načíst pobočky uživatele.');
    }
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $out = [];
    while ($row = $result->fetch_assoc()) {
        $out[] = [
            'id_pob' => (int)$row['id_pob'],
            'nazev' => (string)$row['nazev'],
            'oblast' => (string)($row['oblast'] ?? ''),
            'main' => (int)$row['main'],
        ];
    }
    $stmt->close();
    return $out;
}
