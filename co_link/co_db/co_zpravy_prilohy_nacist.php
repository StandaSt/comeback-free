<?php
/* Co-Link: jednim SELECT nacte prilohy pouze pro skutecne zobrazovanou stranku zprav. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Prazdna stranka nic nenacita a seznam IN obsahuje jen celociselna ID. */
function co_zpravy_prilohy_nacist(array $ids): array
{
    if ($ids === []) return [];
    $in = implode(',', array_map('intval', $ids));
    return co_rows('SELECT id,zprava,nazev,mime,velikost FROM co_priloha WHERE zprava IN ('.$in.')');
}
