<?php
// db/db_user.php * Nastavení lokálního přihlašovacího účtu.
declare(strict_types=1);

/*
 * DB: user_set + user_login
 *
 * Účel:
 * - malé, jednoučelové funkce pro nastavení účtu a jeho přihlášení
 */
/**
 * Zajisti vychozi zaznam v user_set pro uzivatele po prvnim loginu.
 */
function cb_db_ensure_user_set(mysqli $conn, int $idUser): void
{
    if ($idUser <= 0) {
        throw new RuntimeException('Neplatne id_user pro user_set.');
    }

    $now = new DateTimeImmutable('now');
    $currentWorkdayDate = $now;
    if ((int)$now->format('G') < 6) {
        $currentWorkdayDate = $currentWorkdayDate->modify('-1 day');
    }
    $today = $currentWorkdayDate->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $yesterday = $currentWorkdayDate->modify('-1 day')->setTime(6, 0, 0)->format('Y-m-d H:i:s');

    $prodleva = 3000;
    $pismo = 2;
    $dark = 0;

    $aktivniModul = 'provoz';

    $stmt = $conn->prepare(
        'INSERT INTO user_set (id_user, prodleva, pismo, dark, obdobi_od, obdobi_do, obdobi_mode, aktivni_modul)
         SELECT ?, ?, ?, ?, ?, ?, ?, ?
         FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1 FROM user_set WHERE id_user = ?
         )'
    );
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit insert user_set.');
    }

    $obdobiMode = 'vcera';
    $stmt->bind_param('iiiissssi', $idUser, $prodleva, $pismo, $dark, $yesterday, $today, $obdobiMode, $aktivniModul, $idUser);
    $stmt->execute();
    $stmt->close();
}

/**
 * Zápis do user_login (akce = 1 přihlášení / 0 odhlášení, duvod = 0 auto / 1 ručně)
 */
function cb_db_insert_login_event(mysqli $conn, int $idUser, int $akce, int $duvod = 0): void
{
    $stmt = $conn->prepare('INSERT INTO user_login (id_user, akce, duvod) VALUES (?,?,?)');
    $stmt->bind_param('iii', $idUser, $akce, $duvod);
    $stmt->execute();
    $stmt->close();
}

/**
 * Zrusi online priznak konkretniho loginu, nebo vsech loginu uzivatele.
 */
function cb_db_clear_online_login_flags(mysqli $conn, int $idUser, int $idLogin = 0): void
{
    if ($idLogin > 0) {
        $stmt = $conn->prepare('UPDATE user_login SET duvod = 0 WHERE id_user = ? AND id_login = ? AND akce = 1 AND duvod = 2');
        $stmt->bind_param('ii', $idUser, $idLogin);
    } else {
        $stmt = $conn->prepare('UPDATE user_login SET duvod = 0 WHERE id_user = ? AND akce = 1 AND duvod = 2');
        $stmt->bind_param('i', $idUser);
    }
    $stmt->execute();
    $stmt->close();
}

// db/db_user.php * Verze: V4 * Aktualizace: 02.04.2026 * Počet řádků: 161
// Konec souboru
