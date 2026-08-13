<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$provozMenuItems = [
    ['page' => 'prehled', 'label' => 'Přehled'],
    ['page' => 'denni_report', 'label' => 'Denní report'],
    ['page' => 'objednavky', 'label' => 'Objednávky'],
];

$provozMenu = [];
foreach ($provozMenuItems as $item) {
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
