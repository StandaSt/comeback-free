<?php
/* Co-Link: overi vstup, vytvori token a pripoji telefon nebo PC s puvodni platnosti. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_prava.php';
require_once __DIR__ . '/co_cookie.php';
require_once __DIR__ . '/../co_db/co_osobu_nacist.php';
require_once __DIR__ . '/../co_db/co_zarizeni_vlozit.php';

/** Samostatny nahodny token ma v DB pouze hash; ztracene zarizeni lze odvolat. */
function co_pripoj(int $user, string $name, ?string $pcHash = null): array
{
    if (!co_osoba($user) || !co_pravo($user,700)) co_chyba('Nemáte povolen vstup do Co-Linku.',403);
    $token = bin2hex(random_bytes(32));
    // Existujici NOT NULL sloupec zachovavame bez migrace; u telefonu se datum nepouziva pri overovani.
    $expiry=$pcHash===null?'9999-12-31 23:59:59':date('Y-m-d H:i:s',time()+12*3600);
    co_zarizeni_vlozit($user, hash('sha256',$token), $pcHash, mb_substr(trim($name) ?: 'Moje zařízení',0,100), $expiry);
    $device = (int)db()->insert_id;
    co_cookie($token,$pcHash===null?time()+365*86400:0);
    return ['zarizeni'=>$device,'csrf'=>hash_hmac('sha256','co-link-csrf',$token)];
}
