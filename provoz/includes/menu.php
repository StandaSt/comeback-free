<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$provozMenuItems = [
    ['page' => 'prehled', 'label' => 'Přehled'],
    ['page' => 'denni_report', 'label' => 'Denní report'],
    ['page' => 'objednavky', 'label' => 'Objednávky'],
    ['page' => 'prehled_hodin', 'label' => 'Přehled hodin'],
];

$provozMenu = [];
foreach ($provozMenuItems as $item) {
    $idPravo = (int)($item['pravo'] ?? 0);
    if ($idPravo > 0 && (!function_exists('cb_pravo_ma') || !cb_pravo_ma($idPravo))) {
        continue;
    }

    $itemPage = (string)$item['page'];
    $provozMenu[] = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=provoz&page=' . rawurlencode($itemPage)),
        'active' => $cbPage === $itemPage,
    ];
}

cb_render_blok_menu([
    'title' => 'Provoz',
    'aria_label' => 'Menu Provozu',
    'items' => $provozMenu,
]);
