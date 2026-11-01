<?php
/* Co-Link: jednim SELECT nacte platne zarizeni vcetne podminky aktivni PC relace. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Prijima pouze hash tokenu; surovy prihlasovaci token se do DB dotazu nepredava. */
function co_zarizeni_podle_tokenu_nacist(string $hash): array
{
    return co_rows('SELECT d.id,d.uzivatel,d.nazev,d.pc_hash FROM co_zarizeni d WHERE d.token_hash=? AND d.zruseno IS NULL AND (d.pc_hash IS NULL OR d.platnost_do>NOW())
        AND (d.pc_hash IS NULL OR EXISTS(SELECT 1 FROM user_pc_session p WHERE HEX(p.pc_hash)=UPPER(d.pc_hash) AND p.id_user=d.uzivatel AND p.zruseno IS NULL))', [$hash]);
}
