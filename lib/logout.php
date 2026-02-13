<?php
// lib/logout.php * Verze: V8 * Aktualizace: 12.2.2026
declare(strict_types=1);

/*
 * Odhlášení uživatele
 *
 * Co musí platit:
 * - vždy (když známe id_user) zapíšeme do DB user_login akce=0
 * - po odhlášení nesmí zůstat nic „napůl“ v session
 * - pak redirect na úvod
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/login_diagnostika.php';

/**
 * Zapíše odhlášení do user_login (akce=0)
 * - když id_user není známé, nic nezapisuje
 * - při DB chybě se odhlášení nezastaví (uživatel se musí odhlásit vždy)
 */
function cb_logout_log_to_db(): void
{
    $cbUser = $_SESSION['cb_user'] ?? null;
    if (!is_array($cbUser) || empty($cbUser['id_user'])) {
        return;
    }

    $idUser = (int)$cbUser['id_user'];

    try {
        $sql = 'INSERT INTO user_login (id_user, akce) VALUES (?,0)';
        $stmt = db()->prepare($sql);
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // jen diagnostika – neblokujeme odhlášení
        cb_login_log_line('logout_db_failed', ['id_user' => (string)$idUser], $e);
    }
}

// 1) zapsat odhlášení do DB (pokud máme id_user)
cb_logout_log_to_db();

// 2) vyčistit session proměnné
$_SESSION = [];

// 3) smazat session cookie (pokud existuje)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'] ?? '/',
        $p['domain'] ?? '',
        (bool)($p['secure'] ?? false),
        (bool)($p['httponly'] ?? true)
    );
}

// 4) zrušit session na serveru
session_destroy();

// 5) návrat na úvod
header('Location: ' . cb_url('index.php?page=uvod'));
exit;

/* lib/logout.php * Verze: V8 * Aktualizace: 12.2.2026 * Počet řádků: 71 */
// Konec souboru