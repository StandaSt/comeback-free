<?php
/* Co-Link: sestavuje stabilní označení cíle operace pro audit, nikdy nepřebírá text zprávy. */
declare(strict_types=1);
require_once __DIR__.'/../co_db/co_audit_vlozit.php';
require_once __DIR__.'/../co_db/co_audit_konverzaci_nacist.php';

/** Název cíle zůstane srozumitelný i po přejmenování kanálu nebo změně údajů příjemce. */
function co_audit_kontext(int $user, array $channel): array
{
    $cid=(int)$channel['id']; $type=(string)$channel['typ'];
    $target=$type==='soukroma'
        ? 'Pro '.implode(', ',array_column(co_audit_prijemce_nacist($cid,$user),'jmeno'))
        : ($type==='kanal'?'Kanál: ':'Skupina: ').(string)$channel['nazev'];
    return ['konverzace'=>$cid,'typ'=>$type,'cil'=>$target];
}
