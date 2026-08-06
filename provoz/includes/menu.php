<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/module_menu.php';

$provozMenuItems = [
    ['page' => 'prehled', 'label' => 'Přehled'],
    ['page' => 'stranka_1', 'label' => 'Stránka 1'],
    ['page' => 'stranka_2', 'label' => 'Stránka 2'],
    ['page' => 'stranka_3', 'label' => 'Stránka 3'],
];

$provozMenu = [];
foreach ($provozMenuItems as $item) {
    $itemPage = (string)$item['page'];
    $provozMenu[] = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=provoz&page=' . rawurlencode($itemPage)),
        'active' => $cbPage === $itemPage || ($itemPage === 'prehled' && $cbPage === 'dashboard'),
    ];
}

cb_render_module_menu([
    'title' => 'Provoz',
    'aria_label' => 'Menu Provozu',
    'items' => $provozMenu,
]);
