<?php
/* Co-Link: slozi dostupne konverzace, jejich cleny a nedavnou aktivitu soukromych protejsku v IS. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_konverzace_seznam_nacist.php';
require_once __DIR__ . '/../co_db/co_konverzace_cleny_nacist.php';
require_once __DIR__ . '/../co_db/co_aktivni_uzivatele_nacist.php';

/** Dostupne chaty doplnuje o pocty neprectenych zprav a nedavnou aktivitu soukromych protejsku. */
function co_seznam(int $user): array
{
    $list=co_konverzace_seznam_nacist($user);
    $members=[];
    foreach(co_konverzace_cleny_nacist($user) as $m) $members[(int)$m['konverzace']][]=(int)$m['uzivatel'];
    // Aktivitu cteme pouze pro protejsky dostupnych soukromych konverzaci, nikoli pro cely adresar.
    $peers=[];
    foreach($list as $channel) {
        if ($channel['typ']==='soukroma') foreach($members[(int)$channel['id']]??[] as $id) if ($id!==$user) $peers[]=$id;
    }
    $active=array_fill_keys(array_map(static fn(array $row): int => (int)$row['id_user'], co_aktivni_uzivatele_nacist($peers)), true);
    foreach($list as &$channel) {
        $channel['clenove']=$members[(int)$channel['id']]??[];
        $channel['online']=false;
        if ($channel['typ']==='soukroma') foreach($channel['clenove'] as $id) if ($id!==$user && isset($active[$id])) $channel['online']=true;
    }
    unset($channel);
    return $list;
}
