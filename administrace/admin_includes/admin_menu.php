<?php
declare(strict_types=1);

/*
 * Účel souboru: Vykreslí levé menu modulu Administrace a označí aktivní stránku.
 * Položky musí odpovídat klientské definici v common/js/moduly_navigace.js.
 */

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$adminMenuItems = [
    ['page' => 'prava_roli', 'label' => 'Globální práva'],
    ['page' => 'editace_prav', 'label' => 'Editovat práva'],
    ['page' => 'individualni_prava', 'label' => 'Individuální práva uživatele'],
    ['page' => 'spousteni_scriptu', 'label' => 'Spouštění scriptů'],
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
