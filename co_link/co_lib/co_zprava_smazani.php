<?php
/* Co-Link: logicky smaze zpravu a uzavre jeji cekajici push pokusy ve volajici transakci. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_zpravu_smazat.php';
require_once __DIR__ . '/../co_db/co_push_zpravu_uzavrit.php';

/** Pristup, autorstvi i revizi overuje spolecna sluzba pred zavolanim teto vetve. */
function co_zprava_smazani(int $id): void
{
    co_zpravu_smazat($id);
    co_push_zpravu_uzavrit($id);
}
