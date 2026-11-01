<?php
/* Co-Link: overi token zarizeni, aktivni osobu a pravo vstupu; telefonu obnovi cookie. */
declare(strict_types=1);
require_once __DIR__ . '/co_cookie.php';
require_once __DIR__ . '/co_prava.php';
require_once __DIR__ . '/../co_db/co_osobu_nacist.php';
require_once __DIR__ . '/../co_db/co_zarizeni_podle_tokenu_nacist.php';

/** Telefon nema casovy limit; PC nadale vyzaduje platnou relaci IS a vlastni platnost. */
function co_identita(): array
{
    $token = (string)($_COOKIE[co_cookie_jmeno()] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return [];
    $d = co_zarizeni_podle_tokenu_nacist(hash('sha256',$token))[0] ?? [];
    if (!$d || !($p=co_osoba((int)$d['uzivatel'])) || !co_pravo((int)$d['uzivatel'],700)) return [];
    // Prohlizec potrebuje konec platnosti cookie; pri pouzivani telefonu ji prubezne posouvame bez noveho QR.
    if($d['pc_hash']===null) co_cookie($token,time()+365*86400);
    return $p + ['zarizeni'=>(int)$d['id'],'csrf'=>hash_hmac('sha256','co-link-csrf',$token)];
}
