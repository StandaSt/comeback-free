<?php
// lib/db_user.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 112
declare(strict_types=1);

/*
 * DB: user + user_login
 *
 * Účel:
 * - malé, jednoučelové funkce pro práci s tabulkou user a user_login
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Převod hodnoty na DATETIME string pro MySQL, nebo null.
 */
function cb_db_dt_or_null(mixed $v): ?string
{
    if ($v === null) return null;
    $s = trim((string)$v);
    if ($s === '') return null;

    $ts = strtotime($s);
    if (!$ts) return null;

    return date('Y-m-d H:i:s', $ts);
}

/**
 * Upsert do tabulky user podle id_user (Směny).
 * - INSERT pokud neexistuje
 * - jinak UPDATE na aktuální hodnoty ze Směn
 *
 * @param array $p  plný profil ze Směn (userGetLogged)
 */
function cb_db_upsert_user(mysqli $conn, array $p): void
{
    $idUser = (int)$p['id'];

    $jmeno = (string)($p['name'] ?? '');
    $prijmeni = (string)($p['surname'] ?? '');
    $email = (string)($p['email'] ?? '');
    $telefon = (string)($p['phoneNumber'] ?? '');
    $aktivni = !empty($p['active']) ? 1 : 0;
    $schvalen = !empty($p['approved']) ? 1 : 0;

    $vytvoren = cb_db_dt_or_null($p['createTime'] ?? null);
    $visit = cb_db_dt_or_null($p['lastLoginTime'] ?? null);

    // existuje?
    $stmt = $conn->prepare('SELECT id_user FROM user WHERE id_user=? LIMIT 1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->store_result();
    $exists = ($stmt->num_rows > 0);
    $stmt->close();

    if (!$exists) {
        $stmt = $conn->prepare(
            'INSERT INTO user (id_user,jmeno,prijmeni,email,telefon,aktivni,schvalen,vytvoren_smeny,visit_smeny)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'issssiiss',
            $idUser,
            $jmeno,
            $prijmeni,
            $email,
            $telefon,
            $aktivni,
            $schvalen,
            $vytvoren,
            $visit
        );
        $stmt->execute();
        $stmt->close();
        return;
    }

    $stmt = $conn->prepare(
        'UPDATE user
         SET jmeno=?, prijmeni=?, email=?, telefon=?, aktivni=?, schvalen=?, vytvoren_smeny=?, visit_smeny=?
         WHERE id_user=?'
    );
    $stmt->bind_param(
        'ssssiissi',
        $jmeno,
        $prijmeni,
        $email,
        $telefon,
        $aktivni,
        $schvalen,
        $vytvoren,
        $visit,
        $idUser
    );
    $stmt->execute();
    $stmt->close();
}

/**
 * Zápis do user_login (akce = 1 přihlášení / 0 odhlášení)
 */
function cb_db_insert_login_event(mysqli $conn, int $idUser, int $akce): void
{
    $stmt = $conn->prepare('INSERT INTO user_login (id_user, akce) VALUES (?,?)');
    $stmt->bind_param('ii', $idUser, $akce);
    $stmt->execute();
    $stmt->close();
}

// lib/db_user.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 112