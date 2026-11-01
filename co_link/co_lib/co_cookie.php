<?php
/* Co-Link: urci nazev prihlasovaci cookie a nastavi jeji puvodni bezpecnostni vlastnosti. */
declare(strict_types=1);
require_once __DIR__ . '/co_https.php';
require_once __DIR__ . '/co_cesta.php';

/** Cookie zustava omezena na Co-Link; prihlaseni do ostatnich agend tim nevznika. */
function co_cookie_jmeno(): string { return co_https() ? '__Secure-co_link' : 'co_link'; }

/** Telefon ma trvalou cookie, PC pouze cookie do konce relace prohlizece. */
function co_cookie(string $token, int $expiry): void
{
    setcookie(co_cookie_jmeno(), $token, ['expires'=>$expiry,'path'=>co_cesta(),'secure'=>co_https(),'httponly'=>true,'samesite'=>'Strict']);
}
