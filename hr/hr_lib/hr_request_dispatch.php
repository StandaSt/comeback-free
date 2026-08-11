<?php
declare(strict_types=1);

function cb_hr_request_dispatch(mysqli $db, string $page, array $user, int $roleId): void
{
    $isShellRequest = isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE']);
    $isFormPost = ($_SERVER['REQUEST_METHOD'] === 'POST') && !$isShellRequest;
    if (!$isFormPost) {
        return;
    }

    if ($page === 'nabor') {
        hr_post_nabor($db);
    }

    if ($page === 'pozadavky' && in_array($roleId, [1, 5], true)) {
        hr_post_pozadavky($db, $user, $roleId);
    }

    if ($page === 'novy_zamestnanec') {
        hr_post_zamestnanec($db, $roleId);
    }
}
