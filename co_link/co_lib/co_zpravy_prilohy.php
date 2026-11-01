<?php
/* Co-Link: seskupi nactene prilohy podle zpravy bez zmeny jejich udaju a poradi. */
declare(strict_types=1);

/** Jen prirazuje radky; dostupnost obsahu smazanych zprav resi slozeni odpovedi. */
function co_zpravy_prilohy(array $rows): array
{
    $files = [];
    foreach ($rows as $file) $files[(int)$file['zprava']][] = $file;
    return $files;
}
