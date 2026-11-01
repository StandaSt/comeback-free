<?php
/* Co-Link: jednim SELECT zjisti konverzaci existujici nesmazane zpravy pro zmenu reakce. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Kontrolu pristupu a archivace provadi volajici sluzba pred zahajenim transakce. */
function co_reakce_zpravu_nacist(int $id): array
{
    return co_rows('SELECT konverzace FROM co_zprava WHERE id=? AND smazano IS NULL', [$id]);
}
