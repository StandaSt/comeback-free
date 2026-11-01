<?php
/* Co-Link: jednim SELECT nacte dostupnou konverzaci s volitelnym zamkem FOR UPDATE. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';
require_once __DIR__ . '/../co_lib/co_pristup_sql.php';

/** Zamykaci varianta zustava na stejnem spojeni a v transakci ridici sluzby. */
function co_konverzaci_nacist(int $user, int $id, bool $lock = false): array
{
    return co_rows('SELECT k.* FROM co_konverzace k WHERE k.id=? AND '.co_pristup_sql($user).($lock?' FOR UPDATE':''),[$id]);
}
