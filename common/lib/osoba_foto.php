<?php
declare(strict_types=1);
/* Účel: Vrací aktuální fotografii osoby podle ID ze společné složky HR, bez dotazu do databáze. */

/** Změna obsahu obnoví obrázek v prohlížeči i při zachování stejného názvu souboru. */
function cb_osoba_foto(int $idPerson): ?string
{
    if ($idPerson <= 0) return null;
    $path = 'hr/hr_img/zamestnanci/' . $idPerson . '.jpg';
    $file = dirname(__DIR__, 2) . '/' . $path;
    clearstatcache(true, $file);
    if (!is_file($file)) return null;
    $hash = @hash_file('sha256', $file);
    return $hash === false ? null : $path . '?v=' . substr($hash, 0, 12);
}
