<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/includes/blok_menu.php';

$hlMenuItems = [
    [
        'view' => 'all',
        'label' => 'Přehled tiketů',
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

$hlMenu = [];
foreach ($hlMenuItems as $item) {
    $itemView = (string)$item['view'];
    if (!cb_helpdesk_view_allowed($itemView)) {
        continue;
    }
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
