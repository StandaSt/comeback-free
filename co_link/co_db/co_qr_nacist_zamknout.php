<?php
/* Co-Link: jediny dotaz pro nacteni a zamknuti konkretniho QR vcetne platnosti podle databazoveho casu. */
declare(strict_types=1);

/** Volajici nejdrive zamkne uzivatele; zde se vraci rozhodujici aktualni stav jeho QR. */
function co_qr_nacist_zamknout(string $hash, int $user): array
{
    return co_rows('SELECT id,uzivatel,pouzito,(platnost_do>NOW()) AS platny FROM co_qr WHERE token_hash=? AND uzivatel=? FOR UPDATE', [$hash, $user])[0] ?? [];
}
