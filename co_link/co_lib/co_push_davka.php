<?php
/* Co-Link: zpracuje omezenou davku cekajicich zprav pro volitelny CLI vstup. */
declare(strict_types=1);
require_once __DIR__ . '/co_push_priprava.php';
require_once __DIR__ . '/co_push_zprava.php';
require_once __DIR__ . '/../co_db/co_push_frontu_nacist.php';

/** Puvodni CLI vstup zustava k rucnimu zpracovani cekajicich pokusu; web jej nepotrebuje. */
function co_push_davka(string $baseUrl): int
{
    if (!co_push_priprava()) return 0;
    $count=0;
    foreach(co_push_frontu_nacist() as $row) {
        $count+=co_push_zprava($baseUrl,(int)$row['zprava']);
    }
    return $count;
}
