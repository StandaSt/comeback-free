<?php
declare(strict_types=1);

function cb_hr_pages(): array
{
    return [
        'prehled' => ['file' => __DIR__ . '/../hr_pages/prehled.php', 'title' => 'Přehled'],
        'nabor' => ['file' => __DIR__ . '/../hr_pages/nabor.php', 'title' => 'Nábor'],
        'zamestnanci' => ['file' => __DIR__ . '/../hr_pages/zamestnanci.php', 'title' => 'Zaměstnanci'],
        'zamestnanec' => ['file' => __DIR__ . '/../hr_pages/zamestnanec.php', 'title' => 'Karta zaměstnance'],
        'novy_zamestnanec' => ['file' => __DIR__ . '/../hr_pages/novy_zamestnanec.php', 'title' => 'Nový zaměstnanec'],
        'pozadavky' => ['file' => __DIR__ . '/../hr_pages/pozadavky.php', 'title' => 'Požadavky'],
        'pracovni_pomery' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => 'Pracovní poměry'],
        'dokumenty' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => 'Dokumenty'],
        'skoleni' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => 'Školení'],
        'prohlidky' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => 'Lékařské prohlídky'],
        'dovolene' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => 'Dovolené'],
        'reporty' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => 'Reporty'],
        'uprava_profilu' => ['file' => __DIR__ . '/../../common/pages/uprava_profilu.php', 'title' => 'Úprava profilu'],
    ];
}

function cb_hr_current_page(): array
{
    $pages = cb_hr_pages();
    $page = strtolower(trim((string)($_GET['page'] ?? 'prehled')));
    if ($page === 'dashboard') {
        $page = 'prehled';
    }
    if (!isset($pages[$page])) {
        $page = 'prehled';
    }

    return [
        'key' => $page,
        'file' => $pages[$page]['file'],
        'title' => $pages[$page]['title'],
    ];
}
