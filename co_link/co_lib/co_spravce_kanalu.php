<?php
/* Co-Link: overi pravo spravy kanalu; samo o sobe nepovoluje cteni jeho zprav. */
declare(strict_types=1);
require_once __DIR__ . '/co_prava.php';

/** Spravce meni nastaveni kanalu, ale tato kontrola mu nedava cteni jeho zprav. */
function co_spravce_kanalu(int $user, array $channel): bool
{
    return $channel['typ']==='kanal' && (co_pravo($user,702) || ((int)$channel['autor']===$user && co_pravo($user,701)));
}
