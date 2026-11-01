<?php
/* Co-Link: vyhodnoti opravneni a jejich zavislosti s puvodni cache pro kazdou osobu. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_roli_overit.php';
require_once __DIR__ . '/../co_db/co_prava_nacist.php';

/** Pouziva stejnou unii roli a prednost individualnich vyjimek jako IS, bez sdileni jeho session. */
function co_prava(int $id, bool $obnovit = false): array
{
    // Cache patri osobe v ramci pozadavku; vynucena obnova zahodi jen jeji zaznam.
    static $cache = [];
    if($obnovit) unset($cache[$id]);
    if (isset($cache[$id])) return $cache[$id];
    $roles = co_roli_overit($id);
    $rows = co_prava_nacist($id);
    $rights = [];
    foreach ($rows as $r) $rights[(int)$r['id_pravo']] = (int)$r['aktivni'] === 0 || ($roles !== [] && (int)($r['vyjimka'] ?? $r['globalni']) === 1);
    foreach ([701,702,703] as $r) $rights[$r] = !empty($rights[700]) && !empty($rights[$r]);
    return $cache[$id] = $rights;
}

/** Pravo spravy nikdy samo neobchazi clenstvi v soukrome konverzaci. */
function co_pravo(int $user, int $right): bool { return !empty(co_prava($user)[$right]); }
