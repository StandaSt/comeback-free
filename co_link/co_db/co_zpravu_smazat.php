<?php
/* Co-Link: jednim UPDATE vyprazdni text, oznaci zpravu jako smazanou a zvysi revizi. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Zachovava puvodni logicke smazani bez odstraneni zaznamu zpravy. */
function co_zpravu_smazat(int $id): void
{
    co_sql('UPDATE co_zprava SET text="",smazano=NOW(6),revize=revize+1 WHERE id=?', [$id])->close();
}
