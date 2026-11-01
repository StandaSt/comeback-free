<?php
/* Co-Link: overi pristup k jedne konverzaci a pri zamitnuti vyvola puvodni chybu 403. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/../co_db/co_konverzaci_nacist.php';

/** Kontrola jednoho objektu se pouziva i pro stahovani, vyhledavani a push frontu. */
function co_konverzace(int $user, int $id, bool $lock = false): array
{
    $row = co_konverzaci_nacist($user, $id, $lock)[0] ?? [];
    if (!$row) co_chyba('Do této konverzace nemáte přístup.',403);
    return $row;
}
