<?php
/* Co-Link: jediny dotaz pro zneplatneni vsech dosud nepouzitych QR konkretniho uzivatele. */
declare(strict_types=1);

/** Po uspesnem pripojeni zrusi i soubezne vydane kody; cizi uzivatele ani drive pouzite zaznamy nemeni. */
function co_qr_zneplatnit(int $user): void
{
    co_sql('UPDATE co_qr SET pouzito=NOW() WHERE uzivatel=? AND pouzito IS NULL', [$user])->close();
}
