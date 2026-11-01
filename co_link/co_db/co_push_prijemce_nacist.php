<?php
/* Co-Link: jednim dotazem nacte splatne prijemce a obsah ulozene zpravy. */
declare(strict_types=1);
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

/** Udaje jmena pochazeji ze spolecneho HR zdroje; pristup overuje volajici az tesne pred odeslanim. */
function co_push_prijemce_nacist(int $message): array
{
    return co_rows('SELECT f.id,f.pokusy,f.zprava,d.id zarizeni,d.uzivatel,d.endpoint,d.klic_public,d.klic_auth,d.pc_hash,
            d.zruseno,d.platnost_do,z.konverzace,z.smazano,z.text,z.vytvoreno,
            COALESCE(' . cb_osoba_jmeno_sql('z.autor') . ',"Uživatel") jmeno
            FROM co_push_fronta f JOIN co_zarizeni d ON d.id=f.zarizeni
            JOIN co_zprava z ON z.id=f.zprava WHERE f.hotovo IS NULL AND f.dalsi_pokus<=NOW() AND f.zprava=? ORDER BY f.id',[$message]);
}
