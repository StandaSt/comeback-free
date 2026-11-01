<?php
/* Co-Link: seznam a odvolani vlastnich zarizeni, odhlaseni aktualniho a validace jeho push odberu. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_cookie.php';
require_once __DIR__ . '/../co_db/co_audit_vlozit.php';
require_once __DIR__ . '/../co_db/co_sprava_zarizeni_db.php';

/** Odpoved obsahuje stejne udaje i oznaceni aktualniho zarizeni jako dosavadni API. */
function co_zarizeni_seznam(int $user, int $current): array
{
    return ['zarizeni' => co_sprava_zarizeni_nacist($user), 'aktualni' => $current];
}

/** Audit zustava za UPDATE i pri odvolani neexistujiciho nebo ciziho ID. */
function co_zarizeni_odvolat(int $user, int $id): void
{
    co_sprava_zarizeni_odvolat($id, $user);
    co_audit($user, 'zarizeni_odvolano', $id);
}

/** Po zneplatneni zarizeni odstrani jeho prihlasovaci cookie stejnym zpusobem. */
function co_zarizeni_odhlasit(int $id): void
{
    co_sprava_zarizeni_odhlasit($id);
    co_cookie('', time() - 3600);
}

/** Zachova puvodni whitelist poskytovatelu; server nesmi prijmout libovolny cil push. */
function co_zarizeni_push_ulozit(int $id, array $d): void
{
    $endpoint = (string)($d['endpoint'] ?? '');
    $keys = (array)($d['keys'] ?? []);
    $host = strtolower((string)parse_url($endpoint, PHP_URL_HOST));
    // Odmitnuti ciziho endpointu zabrani serverovym HTTP pozadavkum do vnitrni site.
    $allowed = $host === 'fcm.googleapis.com' || $host === 'updates.push.services.mozilla.com' || str_ends_with($host, '.push.services.mozilla.com') || str_ends_with($host, '.push.apple.com') || $host === 'web.push.apple.com' || str_ends_with($host, '.notify.windows.com');
    if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https' || !$allowed || strlen($endpoint) > 2048 || !preg_match('/^[A-Za-z0-9_-]{60,200}$/D', (string)($keys['p256dh'] ?? '')) || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', (string)($keys['auth'] ?? ''))) co_chyba('Nepodporovaný odběr upozornění.');
    co_sprava_zarizeni_push_ulozit($id, $endpoint, $keys);
}
