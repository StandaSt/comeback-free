<?php
/* Co-Link: opravneny prehled clenu a precteni zpravy a ztiseni otevrene konverzace. */
declare(strict_types=1);
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_lide.php';
require_once __DIR__ . '/../co_db/co_podrobnosti_konverzace_db.php';

/** Nejprve overi volajiciho a pote zachova puvodni vyber aktivnich osob s pristupem. */
function co_konverzace_clenove(int $user, int $id): array
{
    co_konverzace($user, $id);
    $members = [];
    foreach (co_lide() as $person) {
        if (co_podrobnosti_clen_pristup($id, (int)$person['id'])) $members[] = $person;
    }
    return ['lide' => $members];
}

/** Chybejici zprava a nepristupna konverzace zachovaji puvodni poradi chyb 404/403. */
function co_zprava_precteni(int $user, int $id): array
{
    $m = co_podrobnosti_zprava_konverzace($id)[0] ?? [];
    if (!$m) co_chyba('Zpráva není dostupná.', 404);
    co_konverzace($user, (int)$m['konverzace']);
    return ['lide' => co_podrobnosti_precteni_nacist($id)];
}

/** Samotnou zmenu ztiseni povoli az po overeni pristupu do konverzace. */
function co_konverzace_ztisit(int $user, array $d): void
{
    co_konverzace($user, (int)($d['konverzace'] ?? 0));
    co_podrobnosti_ztiseni_ulozit($user, (int)$d['konverzace'], !empty($d['ztiseno']) ? 1 : 0);
}
