<?php
/* Co-Link: jediny dotaz pro zamknuti uzivatele pri vydani nebo pouziti pripojovaciho QR. */
declare(strict_types=1);

/** Spolecny zamek uzivatele serializuje i operace nad ruznymi QR; transakci ridi volajici. */
function co_qr_uzivatele_zamknout(int $user): bool
{
    return co_rows('SELECT id_user FROM user WHERE id_user=? FOR UPDATE', [$user]) !== [];
}
