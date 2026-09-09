<?php
declare(strict_types=1);

/*
 * Účel souboru: Pro připravený účet vytvoří první vstup a odešle pozvánku.
 * Nevytváří user, role, pobočky ani vazbu na person.
 */

require_once __DIR__ . '/prvni_vstup.php';
require_once __DIR__ . '/email_prvni_vstup.php';

function cb_user_spojeni_odeslat(mysqli $db, int $idUser): void
{
    $user = cb_prvni_vstup_user($db, $idUser);
    if (!is_array($user) || (int)$user['aktivni'] !== 1 || (int)$user['schvalen'] !== 1) {
        throw new RuntimeException('Účet není připraven pro první vstup.');
    }
    if (trim((string)$user['heslo_hash']) !== '') {
        throw new RuntimeException('Účet již má nastavené heslo.');
    }

    $token = cb_prvni_vstup_vytvor_token($db, $idUser);
    $name = trim((string)$user['jmeno'] . ' ' . (string)$user['prijmeni']);
    cb_email_prvni_vstup_odeslat(
        (string)$user['email'],
        $name,
        cb_url_abs('?prvni_vstup=' . rawurlencode($token))
    );
}
