<?php
/* Co-Link: jednim SELECT zamkne a nacte zpravu pro kontrolu autorstvi, precteni a revize. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Pouziva stejny zamek zpravy jako potvrzeni precteni na spojeni volajici transakce. */
function co_zpravu_pro_zmenu_nacist(int $id): array
{
    return co_rows('SELECT * FROM co_zprava WHERE id=? FOR UPDATE', [$id]);
}
