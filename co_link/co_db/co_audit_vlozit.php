<?php
/* Co-Link: zapisuje audit operací a výslovně povolená metadata bez textu zpráv a přihlašovacích tajemství. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Před ručním spuštěním migrace zachová základní audit; sama databázi nikdy neupravuje. */
function co_audit_ma_detail(): bool
{
    static $available = null;
    if ($available === null) {
        $statement=co_sql("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='co_audit' AND column_name='detail_json'");
        $available=$statement->get_result()->num_rows>0; $statement->close();
    }
    return $available;
}

/** Whitelist brání nechtěnému předání zprávy, souboru či tokenu i při budoucím rozšíření volající služby. */
function co_audit(int $user, string $action, ?int $object = null, array $detail = []): void
{
    $safe=[];
    foreach (['konverzace','typ','cil','zprava','priloha','autor','revize','prostredi','pole','puvodni','nova','emoji','zarizeni'] as $key) {
        if (isset($detail[$key]) && is_scalar($detail[$key])) $safe[$key]=is_string($detail[$key])?mb_substr($detail[$key],0,2000):$detail[$key];
    }
    if (co_audit_ma_detail()) {
        co_sql('INSERT INTO co_audit(uzivatel,akce,objekt,detail_json) VALUES(?,?,?,?)',
            [$user,$action,$object,json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)])->close();
    } else {
        co_sql('INSERT INTO co_audit(uzivatel,akce,objekt) VALUES(?,?,?)',[$user,$action,$object])->close();
    }
}
