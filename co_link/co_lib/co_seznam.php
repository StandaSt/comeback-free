<?php
/* Co-Link: slozi seznam dostupnych konverzaci a priradi jejich cleny. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_konverzace_seznam_nacist.php';
require_once __DIR__ . '/../co_db/co_konverzace_cleny_nacist.php';

/** Seznam chatu vraci jen dostupne konverzace a pocty zprav bez skutecneho precteni. */
function co_seznam(int $user): array
{
    $list=co_konverzace_seznam_nacist($user);
    $members=[];
    foreach(co_konverzace_cleny_nacist($user) as $m) $members[(int)$m['konverzace']][]=(int)$m['uzivatel'];
    foreach($list as &$channel) $channel['clenove']=$members[(int)$channel['id']]??[];
    unset($channel);
    return $list;
}
