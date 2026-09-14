<?php
// common/lib/objednavka_cislo.php * Jednotné zobrazení čísla objednávky
declare(strict_types=1);

/**
 * Připraví číslo objednávky pro zobrazení v tabulce: celé do 30 znaků,
 * delší zkrátí na 26 znaků a tooltip ponechá jen u zkrácené hodnoty.
 * Složení samotného identifikátoru patří do cb_format('o').
 *
 * @return array{cele:string,zkracene:string,tooltip:string}
 */
function cb_objednavka_cislo(array $objednavka): array
{
    $cele = cb_format('o', $objednavka);

    $jeZkracene = mb_strlen($cele, 'UTF-8') > 30;
    $zkracene = $jeZkracene
        ? mb_substr($cele, 0, 26, 'UTF-8') . '...'
        : $cele;

    return [
        'cele' => $cele,
        'zkracene' => $zkracene,
        'tooltip' => $jeZkracene ? $cele : '',
    ];
}
