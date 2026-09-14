<?php
/*
 * Ucel souboru: Prevede datovou definici menu HR na stavajici spolecny renderer menu.
 * Neurcuje vlastni polozky menu, layout aplikace ani obsah pracovni plochy.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';
require_once __DIR__ . '/../hr_lib/hr_pages.php';

$hrModuleConfig = cb_hr_module_config();
$hrMenuItems = is_array($hrModuleConfig['menu'] ?? null) ? $hrModuleConfig['menu'] : [];

$hrMenu = [];
foreach ($hrMenuItems as $item) {
    if (!is_array($item)) {
        continue;
    }

    $itemPage = (string)$item['page'];
    if ($itemPage === 'mzdovy_prehled' && !cb_hr_mzdovy_prehled_ma_pravo()) {
        continue;
    }
    if ($itemPage === 'nastaveni' && !cb_hr_nastaveni_ma_pravo()) {
        continue;
    }
    $hrMenu[] = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=hr&page=' . rawurlencode($itemPage)),
        'active' => $page === $itemPage,
    ];
}

cb_render_blok_menu([
    'title' => (string)($hrModuleConfig['title'] ?? 'Personalistika'),
    'aria_label' => 'Menu personalistiky',
    'items' => $hrMenu,
]);
