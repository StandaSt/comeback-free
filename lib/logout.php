<?php
// lib/logout.php * Verze: V9 * Aktualizace: 16.2.2026
declare(strict_types=1);

/*
 * Odhlášení uživatele
 *
 * Princip:
 * - zapíše akci=0 do user_login (pokud známe id_user)
 * - kompletně zruší PHP session
 * - přesměruje na úvod
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_diagnostika.php';

/**
 * Zapíše odhlášení do user_login (akce=0)
 */
function cb_logout_log_to_db(): void
{
    $cbUser = $_SESSION['cb_user'] ?? null;

    if (!is_array($cbUser) || empty($cbUser['id_user'])) {
        return;
    }

    $idUser = (int)$cbUser['id_user'];

    try {
        $stmt = db()->prepare('INSERT INTO user_login (id_user, akce) VALUES (?,0)');
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        cb_login_log_line('logout_db_failed', ['id_user' => (string)$idUser], $e);
    }
}

// 1) log do DB
cb_logout_log_to_db();

// 2) zrušit všechny session proměnné
$_SESSION = [];

// 3) zrušit session na serveru
session_destroy();

// 4) návrat na úvod
header('Location: ' . cb_url('/'));
exit;

/* lib/logout.php * Verze: V9 * Aktualizace: 16.2.2026 * Počet řádků: 54 */
// Konec souboru