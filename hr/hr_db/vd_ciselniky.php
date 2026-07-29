<?php
declare(strict_types=1);

/**
 * DB ciselniky pro verejne dotazniky a akce naboru.
 */

/**
 * Nacte aktivni stavy VD pro vyber ve formulari.
 */
function hr_nacti_vd_stavy(mysqli $db): array
{
    return hr_fetch_lookup($db, 'hr_cis_vd_stav', 'id_vd_stav', 'nazev', 'id_vd_stav');
}

/**
 * Nacte aktivni typy akci VD pro vyber ve formulari.
 */
function hr_nacti_vd_akce_typy(mysqli $db): array
{
    return hr_fetch_lookup($db, 'hr_cis_vd_akce_typ', 'id_vd_akce_typ', 'nazev', 'id_vd_akce_typ');
}
