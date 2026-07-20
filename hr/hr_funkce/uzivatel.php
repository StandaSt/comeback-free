<?php
declare(strict_types=1);

/**
 * Vrati ID aktualne prihlaseneho uzivatele v HR modulu.
 */
function hr_current_user_id(): int
{
    $user = $_SESSION['cb_user'] ?? null;
    return is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
}
