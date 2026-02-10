<?php
// lib/logout.php * Verze: V4 * Aktualizace: 10.2.2026
declare(strict_types=1);

/*
 * Odhlášení uživatele
 * - cíleně zruší přihlašovací session proměnné
 * - zruší session a vrátí na úvod
 */

session_start();

unset($_SESSION['login_ok']);
unset($_SESSION['cb_user']);
unset($_SESSION['cb_token']);
unset($_SESSION['cb_flash']);

session_destroy();

header('Location: /comeback/index.php');
exit;

// lib/logout.php * Verze: V4 * Aktualizace: 10.2.2026