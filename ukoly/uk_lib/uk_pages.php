<?php
declare(strict_types=1);

function cb_ukoly_pages(): array
{
    return [
        ['page' => 'prehled', 'label' => 'Přehled'],
        ['page' => 'nove_zadani', 'label' => 'Nové zadání'],
        ['page' => 'prehled_ukolu', 'label' => 'Přehled úkolů'],
        ['page' => 'ukoly_pro_me', 'label' => 'Úkoly pro mě'],
        ['page' => 'me_pozadavky', 'label' => 'Mé požadavky'],
    ];
}

function cb_ukoly_current_page(array $pages): array
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
