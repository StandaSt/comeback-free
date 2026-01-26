<?php
// lib/logout.php V3 – počet řádků: 18 – aktuální čas v ČR: 19.1.2026 15:05
declare(strict_types=1);

/*
 * Odhlášení uživatele
 * - technická akce (není výstup)
 * - zruší session a vrátí na úvod
 */

require_once __DIR__ . '/bootstrap.php';

session_destroy();

header('Location: ' . cb_url('index.php'));
exit;

/* lib/logout.php V3 – počet řádků: 18 – aktuální čas v ČR: 19.1.2026 15:05 */
