<?php
/* Co-Link: přiřadí aktuální fotografie ze společné složky HR přímo podle ID osob. */
declare(strict_types=1);
require_once __DIR__ . '/../../common/lib/osoba_foto.php';

/** Stejný zdroj jako v HR nepotřebuje načítat cesty fotografií z osobních údajů. */
function co_adresar_fotografie(array $ids): array
{
    $photos = [];
    foreach ($ids as $id) {
        $photo = cb_osoba_foto((int)$id);
        if ($photo !== null) $photos[(int)$id] = '../' . $photo;
    }
    return $photos;
}
