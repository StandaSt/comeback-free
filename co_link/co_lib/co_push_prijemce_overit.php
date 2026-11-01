<?php
/* Co-Link: pred odeslanim znovu overi platnost zarizeni, prava, pristup, ztiseni a precteni. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_push_pc_overit.php';
require_once __DIR__ . '/../co_db/co_push_pristup_overit.php';
require_once __DIR__ . '/../co_db/co_push_ztiseni_overit.php';
require_once __DIR__ . '/../co_db/co_push_precteni_overit.php';

/** Poradi a kratke vyhodnocovani kontrol zachovava puvodni ochranu obsahu zpravy. */
function co_push_prijemce_overit(array $r): bool
{
    $user = (int)$r['uzivatel'];
    // Zmena prav po ulozeni zpravy musi platit uz pro toto oznameni.
    co_prava($user, true);
    return !($r['zruseno']!==null || ($r['pc_hash']!==null && strtotime($r['platnost_do'])<=time()) || $r['smazano']!==null || !$r['endpoint']
        || !co_osoba($user) || !co_pravo($user,700)
        || ($r['pc_hash']!==null && !co_push_pc_overit((string)$r['pc_hash'], $user))
        || !co_push_pristup_overit($user, (int)$r['konverzace'])
        || co_push_ztiseni_overit($user, (int)$r['konverzace'])
        || co_push_precteni_overit((int)$r['zprava'], $user));
}
