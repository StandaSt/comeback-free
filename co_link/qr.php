<?php
declare(strict_types=1);
/* Co-Link: vydavani a jednorazova vymena QR; uspesne pripojeni zrusi vsechny vydane kody stejneho uzivatele. */

/** Spolecny zamek uzivatele radi vydani i pouziti ruznych QR za sebe bez zmeny uctu. */
function co_qr_zamkni(int $user): void
{
    if (!co_rows('SELECT id_user FROM user WHERE id_user=? FOR UPDATE', [$user])) {
        co_chyba('Uživatel přihlašovacího odkazu není dostupný.', 410);
    }
}

/** Novy kod ma vlastnich pet minut; existujici kody pri jeho vydani zustavaji platne. */
function co_qr_vytvor(int $user): array
{
    $token = bin2hex(random_bytes(32));
    co_transakce(static function () use ($user, $token): void {
        co_qr_zamkni($user);
        co_sql('INSERT INTO co_qr(uzivatel,token_hash,platnost_do) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))', [$user, hash('sha256', $token)])->close();
    });
    return ['odkaz'=>co_cesta().'#pripojit='.$token, 'sekundy'=>300];
}

/** Kontrola pod zamkem zabrani dvema soubeznym pripojenim pres ruzne kody stejne osoby. */
function co_qr_pripoj(string $token, string $name): array
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) co_chyba('Neplatný přihlašovací odkaz.', 410);
    $hash = hash('sha256', $token);
    // Prvni cteni urci jen vlastnika zamku; platnost se rozhoduje az druhym, zamykacim ctenim.
    $owner = co_rows('SELECT uzivatel FROM co_qr WHERE token_hash=?', [$hash])[0] ?? [];
    if (!$owner) co_chyba('Neplatný přihlašovací odkaz. Na PC načtěte nový QR kód.', 410);
    return co_transakce(static function () use ($hash, $name, $owner): array {
        $user = (int)$owner['uzivatel'];
        co_qr_zamkni($user);
        $qr = co_rows('SELECT id,uzivatel,pouzito,(platnost_do>NOW()) AS platny FROM co_qr WHERE token_hash=? AND uzivatel=? FOR UPDATE', [$hash, $user])[0] ?? [];
        if (!$qr) co_chyba('Neplatný přihlašovací odkaz. Na PC načtěte nový QR kód.', 410);
        if ($qr['pouzito'] !== null) co_chyba('Tento QR kód už byl použit nebo zneplatněn po připojení jiného zařízení. Na PC načtěte nový QR kód.', 410);
        if (!(int)$qr['platny']) co_chyba('Platnost QR kódu skončila. Na PC načtěte nový QR kód.', 410);
        $result = co_pripoj($user, $name);
        // Zruseni vsech dosud vydanych kodu i audit patri ke stejne transakci jako nove zarizeni.
        co_sql('UPDATE co_qr SET pouzito=NOW() WHERE uzivatel=? AND pouzito IS NULL', [$user])->close();
        co_audit($user, 'zarizeni_pripojeno', (int)$result['zarizeni']);
        return $result;
    });
}
