<?php
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/prava.php';

function cb_helpdesk_views(): array
{
    return [
        'all' => 'Přehled tiketů',
        'new-ticket' => 'Nový tiket',
        'mine' => 'Moje tikety',
        'watched' => 'Sledované',
        'closed' => 'Uzavřené',
        'uprava_profilu' => 'Úprava profilu',
    ];
}

function cb_helpdesk_view_allowed(string $view): bool
{
    if (!isset(cb_helpdesk_views()[$view]) || !cb_pravo_ma(600)) {
        return false;
    }

    return in_array($view, ['all', 'uprava_profilu'], true) || cb_pravo_ma(601);
}

function cb_helpdesk_current_view(): array
{
    $views = cb_helpdesk_views();
    $view = strtolower(trim((string)($_GET['hd'] ?? 'all')));
    if (!isset($views[$view])) {
        $view = 'all';
    }

    return ['key' => $view, 'title' => $views[$view]];
}
