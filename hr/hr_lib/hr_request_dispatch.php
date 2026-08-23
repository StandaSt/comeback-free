<?php
/*
 * Ucel souboru: Predava POST akce HR spravnemu modulovemu handleru.
 * Nova akce se vybira podle cb_action; starsi stranky zustavaji beze zmeny
 * do sve samostatne migrace.
 */
declare(strict_types=1);

function cb_hr_request_dispatch(mysqli $db, string $page, array $user, int $roleId): void
{
    $isShellRequest = isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE']);
    $isFormPost = ($_SERVER['REQUEST_METHOD'] === 'POST') && !$isShellRequest;
    if (!$isFormPost) {
        return;
    }

    $action = trim((string)($_POST['cb_action'] ?? ''));
    if ($action === 'hr_zamestnanec_ulozit') {
        hr_post_zamestnanec($db, $roleId);
        return;
    }
    if ($action === 'hr_pozadavek_vytvorit') {
        hr_post_pozadavek_vytvorit($db, $user);
        return;
    }
    if ($action === 'hr_pozadavek_zrusit') {
        hr_post_pozadavek_zrusit($db);
        return;
    }

    if ($action === 'hr_nabor_ulozit_akci' && $page === 'nabor') {
        hr_post_nabor($db);
    }

}
