<?php
/* Co-Link: vyvola ocekavanou chybu se zpravou vhodnou pro uzivatele. */
declare(strict_types=1);

/** Ocekavana chyba obsahuje jen zpravu vhodnou uzivateli. */
function co_chyba(string $text, int $status = 400): never
{
    throw new DomainException($text, $status);
}
