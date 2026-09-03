<?php
declare(strict_types=1);

function cb_helpdesk_views(): array
{
    return [
        'all' => 'Přehled tiketů',
        'new-ticket' => 'Nový tiket',
        'mine' => 'Moje tikety',
        'watched' => 'Sledované',
        'closed' => 'Uzavřené',
        'admin' => 'Admin',
        'uprava_profilu' => 'Úprava profilu',
    ];
}

function cb_helpdesk_current_view(bool $isAdmin): array
{
    $views = cb_helpdesk_views();
    $view = strtolower(trim((string)($_GET['hd'] ?? 'all')));
    if (!isset($views[$view])) {
        $view = 'all';
    }
    if ($view === 'admin' && !$isAdmin) {
        $view = 'all';
    }

    return ['key' => $view, 'title' => $views[$view]];
}
