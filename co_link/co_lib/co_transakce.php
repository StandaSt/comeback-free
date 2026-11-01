<?php
/* Co-Link: spusti operaci v transakci a pri vyjimce provede rollback. */
declare(strict_types=1);

/** Transakce spojuje kontrolu a zmenu, zejmena pri soubehu cteni a editace. */
function co_transakce(callable $fn): mixed
{
    db()->begin_transaction();
    try { $result = $fn(); db()->commit(); return $result; }
    catch (Throwable $e) { db()->rollback(); throw $e; }
}
