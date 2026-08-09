<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$hrMenuItems = [
    ['page' => 'prehled', 'label' => 'Přehled'],
    ['page' => 'nabor', 'label' => 'Nábor'],
    ['page' => 'zamestnanci', 'label' => 'Zaměstnanci'],
    ['page' => 'pozadavky', 'label' => 'Požadavky'],
    ['page' => 'pracovni_pomery', 'label' => 'Pracovní poměry'],
    ['page' => 'dokumenty', 'label' => 'Dokumenty'],
    ['page' => 'skoleni', 'label' => 'Školení'],
    ['page' => 'prohlidky', 'label' => 'Lékařské prohlídky'],
    ['page' => 'dovolene', 'label' => 'Dovolené'],
    ['page' => 'reporty', 'label' => 'Reporty'],
    ['page' => 'nastaveni', 'label' => 'Nastavení'],
];

$hrMenu = [];
foreach ($hrMenuItems as $item) {
    $itemPage = (string)$item['page'];
    $hrMenu[] = [
        'label' => (string)$item['label'],
        'url' => cb_root_url('index.php?m=hr&page=' . rawurlencode($itemPage)),
        'active' => $page === $itemPage,
    ];
}

cb_render_blok_menu([
    'title' => 'Personalistika',
    'aria_label' => 'Menu personalistiky',
    'items' => $hrMenu,
]);
