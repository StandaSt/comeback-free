<?php
/* Co-Link: jednim UPDATE nastavi cas zmeny konverzace po ulozeni nove zpravy. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Cas se aktualizuje v teze transakci jako zprava, prilohy a push fronta. */
function co_konverzace_cas_obnovit(int $cid): void
{
    co_sql('UPDATE co_konverzace SET zmeneno=NOW(6) WHERE id=?', [$cid])->close();
}
