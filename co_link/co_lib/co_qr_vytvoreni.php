<?php
/* Co-Link: vytvori pripojovaci QR odkaz; ridi token, transakci a poradi dvou samostatnych DB operaci. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_qr_uzivatele_zamknout.php';
require_once __DIR__ . '/../co_db/co_qr_vlozit.php';

/** Novy odkaz plati pet minut; vydani nerusi drive vydane platne odkazy stejneho uzivatele. */
function co_qr_vytvor(int $user): array
{
    // Tajny token dostane pouze klient; do DB predavame jeho jednosmerny hash.
    $token = bin2hex(random_bytes(32));
    co_transakce(static function () use ($user, $token): void {
        // Stejny prvni zamek pouziva i pripojeni, aby se vydani a pouziti kodu nepredbihaly.
        if (!co_qr_uzivatele_zamknout($user)) {
            co_chyba('Uživatel přihlašovacího odkazu není dostupný.', 410);
        }
        co_qr_vlozit($user, hash('sha256', $token));
    });
    return ['odkaz' => co_cesta() . '#pripojit=' . $token, 'sekundy' => 300];
}
