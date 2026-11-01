<?php
/* Co-Link: seskupi nactene souhrny reakci podle jednotlivych zprav. */
declare(strict_types=1);

/** Zachovava pocty i priznak moje v puvodnim tvaru dodanem dotazem. */
function co_zpravy_reakce(array $rows): array
{
    $reactions = [];
    foreach ($rows as $reaction) $reactions[(int)$reaction['zprava']][] = $reaction;
    return $reactions;
}
