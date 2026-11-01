<?php
/* Co-Link: povoli transport pouze na serveru a nacte Composer po kontrole VAPID. */
declare(strict_types=1);

/** Lokalni testovani nikdy neodesila skutecne notifikace; transport se nacita az na serveru. */
function co_push_priprava(): bool
{
    global $PROSTREDI;
    if ($PROSTREDI !== 'SERVER') return false;
    if (!defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_SUBJECT')) {
        throw new RuntimeException('Co-Link Web Push nema nastaveni VAPID.');
    }
    require_once dirname(__DIR__,3).'/vendor/autoload.php';
    return true;
}
