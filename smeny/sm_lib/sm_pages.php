<?php
declare(strict_types=1);

function cb_smeny_pages(): array
{
    $pages = [
        ['page' => 'prehled', 'label' => 'Přehled'],
        ['page' => 'pozadavky', 'label' => 'Požadavky'],
        ['page' => 'me_smeny', 'label' => 'Mé směny', 'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2']],
        ['page' => 'planovani_smen', 'label' => 'Plánování směn', 'items' => ['Aktuální týden', 'Týden + 1']],
        ['page' => 'sablony', 'label' => 'Šablony'],
        ['page' => 'naplanovane_smeny', 'label' => 'Naplánované směny', 'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2']],
        ['page' => 'zadane_pozadavky', 'label' => 'Zadané požadavky', 'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2', 'Historie']],
    ];

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

    return ['key' => 'prehled', 'title' => 'Přehled'];
}
