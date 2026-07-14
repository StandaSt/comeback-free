<?php
// lib/system.php * Verze: V3 * Aktualizace: 26.2.2026

/*
 * Nastavení systému (JEDINÉ místo pro měnitelné hodnoty)
 *
 * Co tu je:
 * - výchozí stránka pro nepřihlášeného / přihlášeného (pro index.php)
 * - VAPID klíče pro Web Push
 * - 2FA (schvalování přihlášení) – časové limity
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

// Web Push (VAPID) klíče + subject (kontakt)
define('CB_VAPID_SUBJECT', 'mailto:admin@pizzacomeback.cz');
define('CB_VAPID_PUBLIC', 'BGoTpm1YhMYflfQZDPMva5DpjihYquvRqtHXrN061Z5OKOAvhq41GExSZcW_-K8EyTugOl5pBYZf5Nk2FYK_CWI');
define('CB_VAPID_PRIVATE', 'k3_uCR9VHDc1kAUDbvE98fPKr40KffvtwUMN78n7wH0');

// 2FA – schválení přihlášení na mobilu
// Limit na rozhodnutí (v sekundách): 5 minut
const CB_2FA_LIMIT_SEC = 300;

// Interval kontroly stavu z PC (v milisekundách): 2000 ms
const CB_2FA_POLL_MS = 2000;

/* lib/system.php * Verze: V3 * Aktualizace: 26.2.2026 * Počet řádků: 38 */
// Konec souboru