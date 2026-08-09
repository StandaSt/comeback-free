<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$smMenuItems = $smMenuItems ?? [
    ['page' => 'prehled', 'label' => 'Přehled'],
];
$smPage = $smPage ?? 'prehled';
$smMenu = [];
foreach ($smMenuItems as $item) {
    $itemPage = (string)$item['page'];
    $smMenuItem = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=smeny&page=' . rawurlencode($itemPage)),
        'active' => $smPage === $itemPage,
    ];
    if (isset($item['items']) && is_array($item['items'])) {
        $smMenuItem['items'] = $item['items'];
    }
    $smMenu[] = $smMenuItem;
}

cb_render_blok_menu([
    'title' => 'Směny',
    'aria_label' => 'Menu směn',
    'items' => $smMenu,
]);
