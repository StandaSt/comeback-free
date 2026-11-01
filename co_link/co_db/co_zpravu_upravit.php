<?php
/* Co-Link: jednim UPDATE ulozi text, cas upravy a zvysi revizi zpravy. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Text musi byt overen pred volanim; pristup a zamceni zajistuje sluzba zmeny. */
function co_zpravu_upravit(int $id, string $text): void
{
    co_sql('UPDATE co_zprava SET text=?,upraveno=NOW(6),revize=revize+1 WHERE id=?', [$text, $id])->close();
}
