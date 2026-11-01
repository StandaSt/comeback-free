<?php
/* Co-Link: jednim INSERT zapise auditni akci bez obsahu soukrome komunikace. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Pro audit se uklada akce a objekt, nikoli text soukrome komunikace nebo prihlasovaci tajemstvi. */
function co_audit(int $user, string $action, ?int $object = null): void
{
    co_sql('INSERT INTO co_audit(uzivatel,akce,objekt) VALUES(?,?,?)',[$user,$action,$object])->close();
}
