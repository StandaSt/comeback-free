<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/module_menu.php';

cb_render_module_menu([
    'title' => 'Směny',
    'aria_label' => 'Menu směn',
    'items' => [
        [
            'label' => 'Přehled',
            'active' => true,
        ],
        [
            'label' => 'Požadavky',
        ],
        [
            'label' => 'Hodnocení',
        ],
        [
            'label' => 'Mé směny',
            'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2'],
        ],
        [
            'label' => 'Plánování směn',
            'items' => ['Aktuální týden', 'Týden + 1'],
        ],
        [
            'label' => 'Šablony',
        ],
        [
            'label' => 'Naplánované směny',
            'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2'],
        ],
        [
            'label' => 'Zadané požadavky',
            'items' => ['Aktuální týden', 'Týden + 1', 'Týden + 2', 'Historie'],
        ],
        [
            'label' => 'Administrace',
        ],
    ],
]);
