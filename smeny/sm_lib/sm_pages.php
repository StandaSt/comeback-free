<?php
declare(strict_types=1);
/* Účel: Definuje ploché menu Směn podle oprávnění; výběr týdne patří na stránku, nikoli do podmenu. */

/** Jedna položka otevře stránku; její vlastní filtry vybírají týden. */
function cb_smeny_pages(): array
{
    $pages = [
        ['page' => 'prehled', 'label' => 'Přehled'],
        ['page' => 'me_smeny', 'label' => 'Mé směny'],
        ['page' => 'historie_smen', 'label' => 'Historie směn'],
    ];

    // Editační právo 407 zpřístupní plánovač i bez samostatného práva číst přehledy ostatních.
    if ((function_exists('cb_smeny_planovani_vidi') && cb_smeny_planovani_vidi()) || (function_exists('cb_smeny_planovani_edituje') && cb_smeny_planovani_edituje())) {
        $pages[] = ['page' => 'planovani_smen', 'label' => 'Plánování směn'];
    }
    if (function_exists('cb_smeny_planovani_vidi') && cb_smeny_planovani_vidi()) {
        $pages[] = ['page' => 'naplanovane_smeny', 'label' => 'Naplánované směny'];
        $pages[] = ['page' => 'zadane_pozadavky', 'label' => 'Zadané požadavky'];
    }

    if (function_exists('cb_smeny_sablony_ma_pravo') && cb_smeny_sablony_ma_pravo()) {
        array_splice($pages, 3, 0, [[
            'page' => 'sablony',
            'label' => 'Šablony',
        ]]);
    }

    if (function_exists('cb_smeny_pozadavky_ma_pravo') && cb_smeny_pozadavky_ma_pravo()) {
        array_splice($pages, 1, 0, [[
            'page' => 'pozadavky',
            'label' => 'Požadavky',
        ]]);
    }

    if (function_exists('cb_smeny_nastaveni_ma_pravo') && cb_smeny_nastaveni_ma_pravo()) {
        $pages[] = ['page' => 'nastaveni', 'label' => 'Nastavení'];
    }

    return $pages;
}

function cb_smeny_current_page(array $pages): array
{
    $page = strtolower(trim((string)($_GET['page'] ?? 'prehled')));
    foreach ($pages as $item) {
        if ((string)$item['page'] === $page) {
            return ['key' => $page, 'title' => (string)$item['label']];
        }
    }
    if ($page === 'uprava_profilu') {
        return ['key' => 'uprava_profilu', 'title' => 'Úprava profilu'];
    }
    if ($page === 'pozadavky') {
        return ['key' => 'bez_prava', 'title' => 'Přístup k požadavkům'];
    }
    if ($page === 'sablony') {
        return ['key' => 'bez_prava', 'title' => 'Přístup k šablonám'];
    }
    if ($page === 'planovani_smen') {
        return ['key' => 'bez_prava', 'title' => 'Přístup k plánování směn'];
    }
    if (in_array($page, ['naplanovane_smeny','zadane_pozadavky'], true)) {
        return ['key' => 'bez_prava', 'title' => 'Přístup k přehledu směn'];
    }

    return ['key' => 'prehled', 'title' => 'Přehled'];
}
