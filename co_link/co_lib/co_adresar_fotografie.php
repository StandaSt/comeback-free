<?php
/* Co-Link: overi cesty HR fotografii a priradi je osobam bez dalsich osobnich udaju. */
declare(strict_types=1);

/** Povolena je jen puvodni cesta HR a nahodny nazev s podporovanou priponou. */
function co_adresar_fotografie(array $rows): array
{
    $photos = [];
    foreach ($rows as $r) {
        $photo = (string)($r['foto'] ?? '');
        if (preg_match('~^hr/img/zamestnanci/[a-f0-9]{32}\.(jpg|png|webp)$~D', $photo)) $photos[(int)$r['id_person']] = '../' . $photo;
    }
    return $photos;
}
