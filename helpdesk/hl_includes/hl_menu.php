<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$hlMenuItems = [
    [
        'view' => 'all',
        'label' => 'Přehled',
    ],
    [
        'view' => 'new-ticket',
        'label' => 'Nový tiket',
    ],
    [
        'view' => 'mine',
        'label' => 'Moje tikety',
    ],
    [
        'view' => 'watched',
        'label' => 'Sledované',
    ],
    [
        'view' => 'closed',
        'label' => 'Uzavřené',
    ],
];

if ($isAdmin) {
    $hlMenuItems[] = [
        'view' => 'admin',
        'label' => 'Admin',
    ];
}

$hlMenu = [];
foreach ($hlMenuItems as $item) {
    $itemView = (string)$item['view'];
    $hlMenu[] = [
        'label' => (string)$item['label'],
        'url' => $helpdeskMenuUrl($itemView),
        'active' => $helpdeskView === $itemView,
    ];
}

cb_render_blok_menu([
    'title' => 'HelpDesk',
    'aria_label' => 'HelpDesk menu',
    'items' => $hlMenu,
]);
