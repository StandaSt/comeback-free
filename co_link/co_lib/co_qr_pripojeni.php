<?php
/* Co-Link: jednorazove vymeni QR token za pripojeni zarizeni; ridi overeni, zamky a spolecnou transakci. */
declare(strict_types=1);
require_once __DIR__ . '/../co_db/co_qr_uzivatele_zamknout.php';
require_once __DIR__ . '/../co_db/co_qr_vlastnika_nacist.php';
require_once __DIR__ . '/../co_db/co_qr_nacist_zamknout.php';
require_once __DIR__ . '/../co_db/co_qr_zneplatnit.php';

/** Kontrola pod zamkem nepovoli opakovane pouziti ani pripojeni dalsim kodem po uspesne vymene. */
function co_qr_pripoj(string $token, string $name): array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) co_chyba('Neplatný přihlašovací odkaz.', 410);
    $hash = hash('sha256', $token);
    // Prvni cteni urci jen vlastnika; stav QR se znovu nacte az uvnitr transakce pod zamkem.
    $owner = co_qr_vlastnika_nacist($hash);
    if (!$owner) co_chyba('Neplatný přihlašovací odkaz. Na PC načtěte nový QR kód.', 410);
    return co_transakce(static function () use ($hash, $name, $owner): array {
        $user = (int)$owner['uzivatel'];
        // Zachovavame poradi uzivatel -> QR shodne s vydavanim, vcetne overeni existence uzivatele.
        if (!co_qr_uzivatele_zamknout($user)) {
            co_chyba('Uživatel přihlašovacího odkazu není dostupný.', 410);
        }
        $qr = co_qr_nacist_zamknout($hash, $user);
        if (!$qr) co_chyba('Neplatný přihlašovací odkaz. Na PC načtěte nový QR kód.', 410);
        if ($qr['pouzito'] !== null) co_chyba('Tento QR kód už byl použit nebo zneplatněn po připojení jiného zařízení. Na PC načtěte nový QR kód.', 410);
        if (!(int)$qr['platny']) co_chyba('Platnost QR kódu skončila. Na PC načtěte nový QR kód.', 410);
        $result = co_pripoj($user, $name);
        // Zarizeni, zruseni vsech nepouzitych kodu a audit musi uspet nebo selhat spolecne.
        co_qr_zneplatnit($user);
        co_audit($user, 'zarizeni_pripojeno', (int)$result['zarizeni']);
        return $result;
    });
}
