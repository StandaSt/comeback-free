<?php
/*
 * Ucel souboru: Resi seznam a vyber aktualni stranky modulu HR.
 * Cte stabilni nazvy stranek z hr_module.php a zachovava soucasne soubory
 * pro vykresleni obsahu. Neobsahuje HTML, DB logiku ani zpracovani formulare.
 */
declare(strict_types=1);

/*
 * Nacte a zakladne overi deklaraci HR modulu.
 * Funkce vraci pouze data; rozhodovani o layoutu zustava mimo tento soubor.
 */
function cb_hr_module_config(): array
{
    $config = require __DIR__ . '/../hr_module.php';

    if (!is_array($config) || (string)($config['key'] ?? '') !== 'hr') {
        throw new RuntimeException('Neplatna deklarace modulu HR.');
    }

    return $config;
}

function cb_hr_pages(): array
{
    $moduleConfig = cb_hr_module_config();
    $modulePages = is_array($moduleConfig['pages'] ?? null) ? $moduleConfig['pages'] : [];
    $pageTitle = static function (string $key) use ($modulePages): string {
        $pageConfig = is_array($modulePages[$key] ?? null) ? $modulePages[$key] : [];
        return (string)($pageConfig['title'] ?? '');
    };

    return [
        'prehled' => ['file' => __DIR__ . '/../hr_pages/hr_prehled.php', 'title' => $pageTitle('prehled')],
        'nabor' => ['file' => __DIR__ . '/../hr_pages/nabor.php', 'title' => $pageTitle('nabor')],
        'zamestnanci' => ['file' => __DIR__ . '/../hr_pages/zamestnanci.php', 'title' => $pageTitle('zamestnanci')],
        'zamestnanec' => ['file' => __DIR__ . '/../hr_pages/zamestnanec.php', 'title' => $pageTitle('zamestnanec')],
        'novy_zamestnanec' => ['file' => __DIR__ . '/../hr_pages/novy_zamestnanec.php', 'title' => $pageTitle('novy_zamestnanec')],
        'pozadavky' => ['file' => __DIR__ . '/../hr_pages/pozadavky.php', 'title' => $pageTitle('pozadavky')],
        'pracovni_pomery' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => $pageTitle('pracovni_pomery')],
        'dokumenty' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => $pageTitle('dokumenty')],
        'skoleni' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => $pageTitle('skoleni')],
        'prohlidky' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => $pageTitle('prohlidky')],
        'dovolene' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => $pageTitle('dovolene')],
        'reporty' => ['file' => __DIR__ . '/../hr_pages/placeholder.php', 'title' => $pageTitle('reporty')],
        'uprava_profilu' => ['file' => __DIR__ . '/../../common/pages/uprava_profilu.php', 'title' => $pageTitle('uprava_profilu')],
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
