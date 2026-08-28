<?php
declare(strict_types=1);

function cb_admin_pages(): array
{
    return [
        'prava_roli' => [
            'file' => __DIR__ . '/../admin_pages/admin_prava_roli_page.php',
            'title' => 'Práva rolí',
        ],
        'individualni_prava' => [
            'file' => __DIR__ . '/../admin_pages/admin_individualni_prava_page.php',
            'title' => 'Individuální práva uživatele',
        ],
        'spousteni_scriptu' => [
            'file' => __DIR__ . '/../admin_pages/admin_spousteni_scriptu_page.php',
            'title' => 'Ruční spouštění scriptů',
        ],
        'uprava_profilu' => [
            'file' => __DIR__ . '/../../common/pages/uprava_profilu.php',
            'title' => 'Úprava profilu',
        ],
    ];
}

function cb_admin_current_page(): array
{
    $pages = cb_admin_pages();
    $page = strtolower(trim((string)($_GET['page'] ?? 'prava_roli')));
    if (!isset($pages[$page])) {
        $page = 'prava_roli';
    }

    return [
        'key' => $page,
        'file' => $pages[$page]['file'],
        'title' => $pages[$page]['title'],
    ];
}
