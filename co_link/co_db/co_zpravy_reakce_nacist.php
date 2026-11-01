<?php
/* Co-Link: jednim SELECT nacte souhrny reakci a priznak reakce dane osoby. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Prazdna stranka nic nenacita; reakce se vybiraji pouze pro zobrazovana ID. */
function co_zpravy_reakce_nacist(int $user, array $ids): array
{
    if ($ids === []) return [];
    $in = implode(',', array_map('intval', $ids));
    return co_rows('SELECT zprava,emoji,COUNT(*) pocet,MAX(uzivatel=?) moje FROM co_reakce WHERE zprava IN ('.$in.') GROUP BY zprava,emoji',[$user]);
}
