<?php
/* Co-Link: jediny dotaz pro pocet priloh uzivatele, ktere dosud nejsou pripojene ke zprave. */
declare(strict_types=1);

/** Pocet slouzi puvodni ochrane blokoveho nahravani pred hromadenim rozpracovanych priloh. */
function co_prilohy_rozpracovane_spocitat(int $user): int
{
    return (int)co_rows('SELECT COUNT(*) pocet FROM co_priloha WHERE vlastnik=? AND zprava IS NULL', [$user])[0]['pocet'];
}
