<?php
/* Co-Link: pripravi push frontu pouze pro aktivni osoby s pravem a pristupem ke konverzaci. */
declare(strict_types=1);
require_once __DIR__ . '/co_prava.php';
require_once __DIR__ . '/co_pristup_sql.php';
require_once __DIR__ . '/../co_db/co_osobu_nacist.php';
require_once __DIR__ . '/../co_db/co_push_zarizeni_pro_zpravu_nacist.php';
require_once __DIR__ . '/../co_db/co_push_pristup_overit.php';
require_once __DIR__ . '/../co_db/co_push_pokus_vlozit.php';

/** Fronta vznika ve stejne transakci jako zprava; sitovy transport zajistuje az API po commitu. */
function co_zprava_push_pripravit(int $user, int $cid, int $id): void
{
    foreach (co_push_zarizeni_pro_zpravu_nacist($user, $cid) as $device) {
        $recipient = (int)$device['uzivatel'];
        if (!co_osoba($recipient) || !co_pravo($recipient, 700)) continue;
        if (!co_push_pristup_overit($recipient, $cid)) continue;
        co_push_pokus_vlozit($id, (int)$device['id']);
    }
}
