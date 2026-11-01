<?php
/* Co-Link: jednim SELECT nacte a zamkne zpravu dane konverzace pro overeni potvrzovane revize. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Zamykani probiha na spojeni transakce volajici sluzby; chybejici radek vraci jako prazdnou mnozinu. */
function co_zpravu_pro_precteni_nacist(int $id, int $cid): array
{
    return co_rows('SELECT id,autor,revize,smazano FROM co_zprava WHERE id=? AND konverzace=? FOR UPDATE', [$id, $cid]);
}
