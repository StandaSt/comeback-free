<?php
/* Co-Link: rozhodne o ukonceni neuspesneho pokusu nebo dalsim terminu opakovani. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_push_pokus_dokoncit.php';
require_once __DIR__ . '/../co_db/co_push_pokus_odlozit.php';

/** Zachovava puvodni hranici pokusu a exponencialni prodlevu nejvyse 1800 sekund. */
function co_push_opakovat(int $id, int $attempts): void
{
    if ($attempts >= 5) co_push_pokus_dokoncit($id);
    else co_push_pokus_odlozit($id, min(1800, 30 * (2 ** $attempts)));
}
