<?php
declare(strict_types=1);

/*
 * Ucel souboru: Obsahuje jediny seznam prav, ktera jsou skutecne zapojena v aplikaci.
 * Administrace tento seznam pouze cte; stav se nikde rucne neuklada.
 */

if (!function_exists('cb_prava_funkcni_ids')) {
    function cb_prava_funkcni_ids(): array
    {
        return [
            100, 101, 102, 105, 106, 107, 108, 109,
            200, 201, 202, 203, 204, 208, 209, 210, 211, 212, 213, 214, 215, 216,
            300, 305, 306, 307, 311, 312, 313, 314, 316,
            400,
            500,
            600, 601, 602, 604, 605,
        ];
    }
}

if (!function_exists('cb_pravo_je_funkcni')) {
    function cb_pravo_je_funkcni(int $idPravo): bool
    {
        return in_array($idPravo, cb_prava_funkcni_ids(), true);
    }
}
