<?php
// db/db_pobocka.php * Verze: V1 * Aktualizace: 12.2.2026
declare(strict_types=1);

/*
 * DB: pobocka
 *
 * ĂšÄŤel:
 * - najĂ­t poboÄŤku podle kĂłdu (pobocka.kod)
 * - kdyĹľ neexistuje, vytvoĹ™it placeholder (doÄŤasnĂ© hodnoty)
 */

/**
 * Najde id_pob podle pobocka.kod.
 */
function cb_db_find_pob_id_by_kod(mysqli $conn, string $kod): ?int
{
    $stmt = $conn->prepare('SELECT id_pob FROM pobocka WHERE kod=? LIMIT 1');
    $stmt->bind_param('s', $kod);
    $stmt->execute();
    $stmt->bind_result($idPob);
    $row = $stmt->fetch();
    $stmt->close();

    if ($row) {
        return (int)$idPob;
    }
    return null;
}

/**
 * VloĹľĂ­ placeholder poboÄŤku, protoĹľe poboÄŤka pĹ™iĹˇla ze SmÄ›n, ale neexistuje v naĹˇĂ­ DB.
 *
 * Pozn.:
 * - tabulka pobocka mĂˇ NOT NULL sloupce, proto vyplĹujeme doÄŤasnĂ© hodnoty
 * - id_pob je AUTO_INCREMENT
 */
function cb_db_insert_pob_placeholder(mysqli $conn, string $kod): void
{
    $nazev = 'ÄŤekĂˇ na doplnÄ›nĂ­';
    $ulice = '---';
    $mesto = '---';
    $psc = 0;
    $zadal = 0;

    $stmt = $conn->prepare(
        'INSERT INTO pobocka (kod,nazev,ulice,mesto,psc,zadal,aktivni) VALUES (?,?,?,?,?,?,1)'
    );
    $stmt->bind_param('ssssii', $kod, $nazev, $ulice, $mesto, $psc, $zadal);
    $stmt->execute();
    $stmt->close();

}

/**
 * Projde kĂłdy poboÄŤek, zajistĂ­ pobocka.kod a vrĂˇtĂ­ seznam id_pob.
 *
 * @param string[] $codes
 * @return int[]  id_pob pro poĹľadovanĂ© poboÄŤky
 */
function cb_db_ensure_branches_get_ids(mysqli $conn, array $codes): array
{
    $ids = [];

    foreach ($codes as $kod) {
        $kod = trim((string)$kod);
        if ($kod === '') {
            continue;
        }

        $idPob = cb_db_find_pob_id_by_kod($conn, $kod);
        if ($idPob === null) {
            cb_db_insert_pob_placeholder($conn, $kod);
            $idPob = cb_db_find_pob_id_by_kod($conn, $kod);
        }

        if ($idPob !== null) {
            $ids[] = $idPob;
        }
    }

    $ids = array_values(array_unique($ids));
    sort($ids);

    return $ids;
}

/* db/db_pobocka.php * Verze: V1 * Aktualizace: 12.2.2026 * PoÄŤet Ĺ™ĂˇdkĹŻ: 93 */
// Konec souboru
