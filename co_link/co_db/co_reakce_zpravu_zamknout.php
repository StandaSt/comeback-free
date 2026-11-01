<?php
/* Co-Link: jednim SELECT FOR UPDATE zamkne zpravu na spojeni transakce zmeny reakce. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Zachovava puvodni zamykaci dotaz bez nacitani a dalsiho vyhodnocovani jeho vysledku. */
function co_reakce_zpravu_zamknout(int $id): void
{
    co_sql('SELECT id FROM co_zprava WHERE id=? FOR UPDATE', [$id])->close();
}
