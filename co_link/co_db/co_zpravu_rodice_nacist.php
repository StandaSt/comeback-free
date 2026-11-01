<?php
/* Co-Link: jednim SELECT nacte rodicovskou zpravu v dane konverzaci pro overeni vlakna. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Vysledek obsahuje rodic pro kontrolu, ze odpoved miri na hlavni zpravu. */
function co_zpravu_rodice_nacist(int $parent, int $cid): array
{
    return co_rows('SELECT id,rodic FROM co_zprava WHERE id=? AND konverzace=?', [$parent, $cid]);
}
