<?php
declare(strict_types=1);

/**
 * DB udrzba expirace nepotvrzenych verejnych dotazniku.
 */

/**
 * Nastavi expirovane nepotvrzene verejne dotazniky na stav Nepotvrdil VD.
 */
function hr_expiruj_nepotvrzene_vd(mysqli $db): void
{
    $db->query("
        UPDATE hr_vd vd
        INNER JOIN hr_vd_token t
            ON t.id_vd = vd.id_vd
        SET vd.id_vd_stav = " . HR_VD_STAV_VD_NEPOTVRZENO . ",
            vd.upraveno = NOW()
        WHERE vd.id_vd_stav = " . HR_VD_STAV_NEPOTVRZENO . "
          AND vd.aktivni = 1
          AND t.aktivni = 1
          AND t.pouzito IS NULL
          AND t.platnost_do IS NOT NULL
          AND t.platnost_do < NOW()
    ");
}
