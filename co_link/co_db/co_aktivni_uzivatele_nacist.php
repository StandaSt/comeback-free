<?php
/* Co-Link: jednim ctecim dotazem zjisti nedavnou aktivitu vybranych kolegu v IS. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Vraci pouze ID kolegu s akci mladsi nez deset minut; hranici urcuje cas databaze. */
function co_aktivni_uzivatele_nacist(array $users): array
{
    $users = array_values(array_unique(array_filter(array_map('intval', $users), static fn(int $id): bool => $id > 0)));
    if (!$users) return [];
    return co_rows('SELECT DISTINCT id_user FROM user_akce_new WHERE id_user IN ('
        . implode(',', array_fill(0, count($users), '?'))
        . ') AND cas > NOW() - INTERVAL 10 MINUTE AND cas <= NOW()', $users);
}
