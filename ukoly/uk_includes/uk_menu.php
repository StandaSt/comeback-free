<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/module_menu.php';

cb_render_module_menu([
    'title' => 'Úkoly-požadavky',
    'aria_label' => 'Menu úkolů',
    'items' => [
        [
            'label' => 'Nové zadání',
            'active' => true,
        ],
        [
            'label' => 'Přehled úkolů',
        ],
        [
            'label' => 'Úkoly pro mě',
        ],
        [
            'label' => 'Mé požadavky',
        ],
    ],
]);
