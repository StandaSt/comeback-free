<?php
declare(strict_types=1);

function cb_provoz_pages(): array
{
    return [
        'prehled' => [
            'file' => __DIR__ . '/../pages/prehled.php',
            'title' => 'Přehled',
        ],
        'denni_report' => [
            'file' => __DIR__ . '/../pages/denni_report.php',
            'title' => 'Denní report',
        ],
        'objednavky' => [
            'file' => __DIR__ . '/../pages/objednavky.php',
            'title' => 'Objednávky',
        ],
        'prehled_hodin' => [
            'file' => __DIR__ . '/../pages/prehled_hodin.php',
            'title' => 'Přehled hodin',
        ],
        'nastaveni_reportu' => [
            'file' => __DIR__ . '/../pages/nastaveni_reportu.php',
            'title' => 'Nastavení proměnných v reportu',
        ],
        'uprava_profilu' => [
            'file' => __DIR__ . '/../../common/pages/uprava_profilu.php',
            'title' => 'Úprava profilu',
        ],
    ];
}

function cb_provoz_current_page(): array
{
    $pages = cb_provoz_pages();
    $page = trim((string)($_GET['page'] ?? 'prehled'));
    if ($page === '' || $page === 'dashboard') {
        $page = 'prehled';
    }
    if (!isset($pages[$page])) {
        $page = 'prehled';
    }

    return [
        'key' => $page,
        'file' => $pages[$page]['file'],
        'title' => $pages[$page]['title'],
        'exists' => is_file((string)$pages[$page]['file']),
    ];
}
