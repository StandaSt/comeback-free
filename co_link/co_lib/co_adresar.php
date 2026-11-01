<?php
/* Co-Link: slozi verejny adresar z osob a samostatne pripravenych HR udaju. */
declare(strict_types=1);
require_once __DIR__ . '/co_lide.php';
require_once __DIR__ . '/co_adresar_fotografie.php';
require_once __DIR__ . '/co_adresar_role.php';
require_once __DIR__ . '/co_adresar_sloty.php';
require_once __DIR__ . '/co_adresar_funkce.php';
require_once __DIR__ . '/co_adresar_pobocky.php';
require_once __DIR__ . '/../co_db/co_adresar_fotografie_nacist.php';
require_once __DIR__ . '/../co_db/co_adresar_role_nacist.php';
require_once __DIR__ . '/../co_db/co_adresar_sloty_nacist.php';
require_once __DIR__ . '/../co_db/co_adresar_funkce_nacist.php';
require_once __DIR__ . '/../co_db/co_adresar_main_nacist.php';
require_once __DIR__ . '/../co_db/co_pobocky_nacist.php';

/** Zachovava vice MAIN i slotu; nazvy roli jsou zaloha pouze pro osoby bez slotu a funkci. */
function co_adresar(): array
{
    $people = co_lide();
    $ids = array_map('intval', array_column($people, 'id'));
    $groups = []; $branches = []; $labels = []; $roleLabels = []; $photos = [];
    if ($ids !== []) {
        // Poradi dotazu i pripojovani nazvu zustava stejne jako v puvodnim adresari.
        $photos = co_adresar_fotografie(co_adresar_fotografie_nacist($ids));
        $roles = co_adresar_role(co_adresar_role_nacist($ids));
        $groups = $roles['skupiny']; $roleLabels = $roles['nazvy'];
        $slots = co_adresar_sloty(co_adresar_sloty_nacist($ids));
        foreach ($slots['skupiny'] as $person => $slotGroups) foreach ($slotGroups as $name => $enabled) $groups[$person][$name] = $enabled;
        $labels = $slots['nazvy'];
        foreach (co_adresar_funkce(co_adresar_funkce_nacist($ids)) as $person => $names) foreach ($names as $name) $labels[$person][] = $name;
        $branches = co_adresar_pobocky(co_adresar_main_nacist($ids));
    }
    foreach ($people as &$person) {
        $person['skupiny'] = array_keys($groups[$person['id']] ?? []);
        $person['pobocky'] = array_map('intval', array_keys($branches[$person['id']] ?? []));
        $person['zarazeni'] = array_values(array_unique($labels[$person['id']] ?? $roleLabels[$person['id']] ?? []));
        $person['foto'] = $photos[$person['id']] ?? null;
    }
    unset($person);
    return ['lide' => $people, 'pobocky' => co_pobocky_nacist()];
}
