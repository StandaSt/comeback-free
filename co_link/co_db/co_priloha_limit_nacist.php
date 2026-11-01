<?php
/* Co-Link: jediny dotaz pro nacteni nastaveneho limitu velikosti prilohy v MB. */
declare(strict_types=1);

/** Prevod na bajty a uplatneni limitu zajistuje volajici nahravaci sluzba. */
function co_priloha_limit_nacist(): int
{
    return (int)co_rows('SELECT priloha_mb FROM co_nastaveni WHERE id=1')[0]['priloha_mb'];
}
