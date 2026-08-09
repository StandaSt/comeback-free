<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$ukMenuItems = $ukMenuItems ?? [
    ['page' => 'prehled', 'label' => 'Přehled'],
];
$ukPage = $ukPage ?? 'prehled';
$ukMenu = [];
foreach ($ukMenuItems as $item) {
    $itemPage = (string)$item['page'];
    $ukMenu[] = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=ukoly&page=' . rawurlencode($itemPage)),
        'active' => $ukPage === $itemPage,
    ];
}

cb_render_blok_menu([
    'title' => 'Úkoly-požadavky',
    'aria_label' => 'Menu úkolů',
    'items' => $ukMenu,
]);
