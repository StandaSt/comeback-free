<?php
/* Co-Link: jediny dotaz pro zjisteni vlastnika QR podle hashe pred zamknutim jeho uzivatelskeho zaznamu. */
declare(strict_types=1);

/** Predbezne cteni urci pouze uzivatele; platnost a pouziti QR se rozhoduji az pod zamkem. */
function co_qr_vlastnika_nacist(string $hash): array
{
    return co_rows('SELECT uzivatel FROM co_qr WHERE token_hash=?', [$hash])[0] ?? [];
}
