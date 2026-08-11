<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$adminMenuItems = [
    ['page' => 'prava_roli', 'label' => 'Práva rolí'],
    ['page' => 'individualni_prava', 'label' => 'Individuální práva uživatele'],
];

$adminMenu = [];
foreach ($adminMenuItems as $item) {
    $itemPage = (string)$item['page'];
    $adminMenu[] = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=administrace&page=' . rawurlencode($itemPage)),
        'active' => $adminPage === $itemPage,
    ];
}

cb_render_blok_menu([
    'title' => 'Administrace',
    'aria_label' => 'Menu administrace',
    'items' => $adminMenu,
]);
