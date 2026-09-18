<?php
declare(strict_types=1);

require_once __DIR__ . '/denni_report_prava.php';

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
            'pravo' => CB_DENNI_REPORT_ZOBRAZIT_PRAVO,
        ],
        'kontrola_reportu' => [
            'file' => __DIR__ . '/../pages/kontrola_reportu.php',
            'title' => 'Kontrola reportů',
        ],
        'archiv_reportu' => [
            'file' => __DIR__ . '/../pages/archiv_reportu.php',
            'title' => 'Archiv reportů',
        ],
        'porovnani_reportu' => [
            'file' => __DIR__ . '/../pages/porovnani_reportu.php',
            'title' => 'Porovnání reportů',
        ],
        'objednavky' => [
            'file' => __DIR__ . '/../pages/objednavky.php',
            'title' => 'Objednávky',
        ],
        'prehled_hodin' => [
            'file' => __DIR__ . '/../pages/prehled_hodin.php',
            'title' => 'Přehled hodin',
        ],
        'ai_analytik' => [
            'file' => __DIR__ . '/../pages/ai_analytik.php',
            'title' => 'Chytrý Franta',
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
    $idPravo = (int)($pages[$page]['pravo'] ?? 0);
    if ($idPravo > 0 && !cb_denni_report_ma_pravo($idPravo)) {
        $page = 'prehled';
    }

    return [
        'key' => $page,
        'file' => $pages[$page]['file'],
        'title' => $pages[$page]['title'],
        'exists' => is_file((string)$pages[$page]['file']),
    ];
}
