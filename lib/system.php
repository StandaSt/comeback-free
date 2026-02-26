<?php
// lib/system.php * Verze: V1 * Aktualizace: 24.2.2026

/*
 * Nastavení systému (JEDINÉ místo pro měnitelné hodnoty)
 *
 * Co tu je:
 * - výchozí stránka pro nepřihlášeného / přihlášeného (pro index.php)
 *
 * Pravidla:
 * - bez logiky a bez DB
 * - jen hodnoty (konfigurace)
 */

declare(strict_types=1);

// Výchozí stránka pro nepřihlášeného uživatele (např. uvod1..uvod5)
const CB_DEFAULT_PAGE_GUEST = 'uvod_demo_rotace';

// Výchozí stránka pro přihlášeného uživatele
const CB_DEFAULT_PAGE_USER  = 'uvod';

// klíče pro notifikace či co :-)
define('CB_VAPID_PUBLIC', 'BGoTpm1YhMYflfQZDPMva5DpjihYquvRqtHXrN061Z5OKOAvhq41GExSZcW_-K8EyTugOl5pBYZf5Nk2FYK_CWI');
define('CB_VAPID_PRIVATE', 'k3_uCR9VHDc1kAUDbvE98fPKr40KffvtwUMN78n7wH0');


/* lib/system.php * Verze: V1 * Aktualizace: 24.2.2026 * Počet řádků: 29 */
// Konec souboru