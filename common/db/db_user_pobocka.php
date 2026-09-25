<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/firemni_pristup.php';

/*
 * Účel souboru: Jednotné vyhodnocení přístupu osoby k pobočkám podle HR.
 * pristup_vsechny_pobocky=1 znamená všechny aktivní pobočky firmy osoby.
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
        FROM hr_person osoba
        INNER JOIN pobocka cil ON cil.id_pob = ? AND cil.aktivni = 1
        WHERE osoba.id_person = ?
          AND (EXISTS (
              SELECT 1 FROM hr_pracoviste prima
              WHERE prima.id_person = osoba.id_person AND prima.id_pob = cil.id_pob
                AND prima.platny = 1 AND (prima.platnost_od IS NULL OR prima.platnost_od <= CURRENT_DATE())
                AND (prima.platnost_do IS NULL OR prima.platnost_do >= CURRENT_DATE())
          ) OR (osoba.pristup_vsechny_pobocky = 1 AND cil.id_firma = osoba.id_firma))
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
        SELECT p.id_pob, p.nazev, p.oblast, MAX(CASE WHEN prac.hlavni = 1 THEN 1 ELSE 0 END) AS main
        FROM hr_person osoba
        INNER JOIN pobocka p ON p.id_firma = osoba.id_firma AND p.aktivni = 1
        LEFT JOIN hr_pracoviste prac ON prac.id_person = osoba.id_person AND prac.id_pob = p.id_pob
            AND prac.platny = 1 AND (prac.platnost_od IS NULL OR prac.platnost_od <= CURRENT_DATE())
            AND (prac.platnost_do IS NULL OR prac.platnost_do >= CURRENT_DATE())
        WHERE osoba.id_person = ?
          AND (
              prac.id_pob IS NOT NULL
              OR osoba.pristup_vsechny_pobocky = 1
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
