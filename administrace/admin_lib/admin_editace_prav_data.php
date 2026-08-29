<?php
declare(strict_types=1);

/*
 * Účel souboru: Připraví data potřebná pro kostru stránky Editovat práva.
 * Soubor nevykresluje HTML a databázovou práci předává vrstěv admin_db.
 */

require_once __DIR__ . '/../admin_db/admin_editace_prav_db.php';

/** Vrátí seznam modulů pro oba panely stránky. */
function cb_admin_editace_prav_data(): array
{
    return [
        'moduly' => cb_admin_editace_prav_moduly(),
    ];
}
