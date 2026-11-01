<?php
/* Co-Link: overi dostupnost prilohy, pristup do konverzace a soubor v neverejnem ulozisti; nevytvari HTTP vystup. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_prilohu_nacist.php';

/** Vrati metadata a cestu az po vsech kontrolach; obsah ani hlavičky zde neposilame. */
function co_priloha_pripravit(int $user, int $id): array
{
    $file = co_prilohu_nacist($id);
    // Nepripojena priloha ani priloha smazane zpravy nesmi byt dostupna pres ulozeny odkaz.
    if (!$file || $file['smazano'] !== null) co_chyba('Příloha není dostupná.', 404);
    co_konverzace($user, (int)$file['konverzace']);
    // Cestu smi tvorit jen interni nahodny identifikator; puvodni nazev slouzi pouze stazeni.
    if (!preg_match('/^[a-f0-9]{64}$/D', (string)$file['soubor'])) co_chyba('Příloha není dostupná.', 404);
    $path = co_uloziste() . '/' . $file['soubor'];
    if (!is_file($path)) co_chyba('Příloha není dostupná.', 404);
    return ['priloha' => $file, 'cesta' => $path];
}
