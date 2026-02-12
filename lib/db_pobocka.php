<?php
// lib/db_pobocka.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 90
declare(strict_types=1);

/*
 * DB: pobocka
 *
 * Účel:
 * - najít pobočku podle kódu (pobocka.kod)
 * - když neexistuje, vytvořit placeholder (dočasné hodnoty)
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_diagnostika.php';

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
 * Vloží placeholder pobočku, protože pobočka přišla ze Směn, ale neexistuje v naší DB.
 *
 * Pozn.:
 * - tabulka pobocka má NOT NULL sloupce, proto vyplňujeme dočasné hodnoty
 * - id_pob je AUTO_INCREMENT
 */
function cb_db_insert_pob_placeholder(mysqli $conn, string $kod): void
{
    $nazev = 'čeká na doplnění';
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

    cb_login_log_line('db_new_branch_placeholder', ['kod' => $kod]);
}

/**
 * Projde kódy poboček, zajistí pobocka.kod a vrátí seznam id_pob.
 *
 * @param string[] $codes
 * @return int[]  id_pob pro požadované pobočky
 */
function cb_db_ensure_branches_get_ids(mysqli $conn, array $codes): array
{
    $ids = [];

    foreach ($codes as $kod) {
        $kod = trim((string)$kod);
        if ($kod === '') continue;

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

// lib/db_pobocka.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 90