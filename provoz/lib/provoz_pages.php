<?php
// Mapa stran Provozu a prava k trasam; pravo 220 chrani pouze samostatnou stranku Objednavky, nikoli Prehled.
declare(strict_types=1);

require_once __DIR__ . '/denni_report_prava.php';
require_once __DIR__ . '/nastaveni_prava.php';
require_once __DIR__ . '/kontrola_trzeb_pristup.php';
require_once __DIR__ . '/objednavky_prava.php';

// Vrati povolene klice stran, jejich soubory, titulky a pripadna prava.
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
            // Stejne pravo jako v menu plati i pri zadani prime adresy.
            'pravo' => 202,
        ],
        'porovnani_reportu' => [
            'file' => __DIR__ . '/../pages/porovnani_reportu.php',
            'title' => 'Porovnání reportů',
            // Porovnani je detail archivu a vyzaduje stejne pravo.
            'pravo' => 202,
        ],
        'kontrola_trzeb' => [
            'file' => __DIR__ . '/../pages/kontrola_trzeb.php',
            'title' => 'Kontrola tržeb',
            'pravo' => CB_KT_ZOBRAZIT,
        ],
        'archiv_kontrol_trzeb' => [
            'file' => __DIR__ . '/../pages/archiv_kontrol_trzeb.php',
            'title' => 'Archiv kontrol tržeb',
            'pravo' => CB_KT_ZOBRAZIT,
        ],
        'objednavky' => [
            'file' => __DIR__ . '/../pages/objednavky.php',
            'title' => 'Objednávky',
            'pravo' => CB_OBJEDNAVKY_ZOBRAZIT_PRAVO,
        ],
        'prehled_hodin' => [
            'file' => __DIR__ . '/../pages/prehled_hodin.php',
            'title' => 'Přehled hodin',
            // Stejne pravo jako v menu plati i pri zadani prime adresy.
            'pravo' => 209,
        ],
        'ai_analytik' => [
            'file' => __DIR__ . '/../pages/ai_analytik.php',
            'title' => 'Chytrý Franta',
        ],
        'nastaveni_reportu' => [
            'file' => __DIR__ . '/../pages/nastaveni_reportu.php',
            'title' => 'Nastavení proměnných v reportu',
        ],
        'nastaveni' => [
            'file' => __DIR__ . '/../pages/nastaveni.php',
            'title' => 'Nastavení',
            'pravo' => CB_PROVOZ_NASTAVENI_PRAVO,
        ],
        'nastaveni_pobocky' => [
            'file' => __DIR__ . '/../pages/nastaveni_pobocky.php',
            'title' => 'Nastavení poboček',
            'pravo' => CB_PROVOZ_NASTAVENI_PRAVO,
        ],
        'uprava_profilu' => [
            'file' => __DIR__ . '/../../common/pages/uprava_profilu.php',
            'title' => 'Úprava profilu',
        ],
    ];
}

// Vybere platnou dostupnou stranku, jinak bezpecne vrati prehled Provozu.
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
    // Nove pravo pred obnovou ciselniku relace zustava bezpecne nepristupne.
    if ($idPravo > 0 && !($idPravo === CB_OBJEDNAVKY_ZOBRAZIT_PRAVO ? cb_objednavky_ma_pravo()
        : ($idPravo === CB_KT_ZOBRAZIT ? cb_kt_ma_pravo() : cb_denni_report_ma_pravo($idPravo)))) {
        $page = 'prehled';
    }

    return [
        'key' => $page,
        'file' => $pages[$page]['file'],
        'title' => $pages[$page]['title'],
        'exists' => is_file((string)$pages[$page]['file']),
    ];
}
