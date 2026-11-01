<?php
/* Co-Link: jediny dotaz pro ulozeni hashe noveho QR tokenu s petiminutovou platnosti. */
declare(strict_types=1);

/** Uklada jen hash; ostatni drive vydane kody ponechava beze zmeny. */
function co_qr_vlozit(int $user, string $hash): void
{
    co_sql('INSERT INTO co_qr(uzivatel,token_hash,platnost_do) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))', [$user, $hash])->close();
}
