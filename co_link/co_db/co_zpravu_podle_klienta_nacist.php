<?php
/* Co-Link: jednim SELECT vyhleda drive ulozenou zpravu autora podle klientem vytvoreneho ID. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Umoznuje vraceni puvodniho ID po opakovanem odeslani bez druheho ulozeni. */
function co_zpravu_podle_klienta_nacist(int $user, string $client): array
{
    return co_rows('SELECT id,konverzace FROM co_zprava WHERE autor=? AND klient_id=?', [$user, $client]);
}
