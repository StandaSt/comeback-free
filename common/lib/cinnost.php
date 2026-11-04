<?php
declare(strict_types=1);
/* Účel: Jediné serverové rozhraní nové evidence činnosti pro všech osm modulů; převezme modul požadavku, doplní autora a čas, ověří číselníky a předá jednotný zápis.
 * Samotný include nic nečte ani nezapisuje. Volá pouze důvěryhodný PHP kód po ověření přístupu k cíli.
 * Pole ani objekt identity se nikdy nepřebírají hromadně z POST/JSON. Prohlížeč bude mít samostatný ověřující vstup.
 * Chyby se hlásí výjimkou, nikoli tichým vynecháním záznamu. Transakci řídí volající, funkce neprovádí commit ani rollback.
 */
require_once __DIR__.'/cinnost_pravidla.php';
require_once __DIR__.'/cinnost_identita.php';
require_once __DIR__.'/../db/db_cinnost.php';

/** Kontext patří konkrétnímu serverovému požadavku, nikoli session sdílené kartami; samostatné API jej nastaví jednou při vstupu. */
function cb_cinnost_modul(): string
{
    $module = $GLOBALS['CURRENT_MODULE'] ?? '';
    if ($module === '' && defined('CB_EMBEDDED_MODULE')) $module = constant('CB_EMBEDDED_MODULE');
    if (!is_string($module) || $module === '') throw new RuntimeException('Požadavek nemá nastavený modul činnosti.');
    cb_cinnost_tabulka($module);
    return $module;
}

/** Jedno volání předává jen ID akce a parametry; modul, autorství ani texty popisů se nepřebírají od klienta.
 * @return array{id_cinnost:string,opakovani:bool,udalost_uuid:string}
 */
function cb_cinnost_zapsat(int $idAkce, array $parametry = []): array
{
    $modul = cb_cinnost_modul();
    cb_cinnost_parametry_overit($parametry);
    if ($idAkce < 1) throw new InvalidArgumentException('Neplatná akce činnosti.');
    $identity = $parametry['identita'] ?? CbCinnostIdentita::zIs();
    if (!$identity instanceof CbCinnostIdentita) throw new InvalidArgumentException('Neplatná identita činnosti.');
    $uuid = $parametry['udalost_uuid'] ?? cb_cinnost_uuid();
    if (!is_string($uuid) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $uuid)) throw new InvalidArgumentException('Neplatné UUID činnosti.');
    $time = $parametry['cas_udalosti'] ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if (!$time instanceof DateTimeImmutable) throw new InvalidArgumentException('Čas činnosti musí být serverový datumový objekt.');
    $db = db();
    $catalog = cb_cinnost_db_katalog($db, $modul, $idAkce, $parametry);
    $checked = cb_cinnost_pravidla_overit($modul, $parametry, $catalog['akce'], $catalog['pole']);
    $row = [
        'udalost_uuid'=>hex2bin(str_replace('-', '', strtolower($uuid))),
        'cas_udalosti'=>$time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
        'id_user'=>$identity->idUser, 'id_login'=>$identity->idLogin, 'id_user_prava'=>$identity->idUserPrava,
        'id_firma'=>cb_cinnost_db_firma($db, $identity->idUser, $parametry['id_firma'] ?? null),
        'id_akce'=>$idAkce, 'id_stranka'=>$parametry['id_stranka'] ?? null,
        'id_puvod'=>$checked['id_puvod'], 'id_stav'=>$checked['id_stav'],
        'id_cil_typ'=>$checked['id_cil_typ'], 'cil_id'=>$parametry['cil_id'] ?? null,
        'id_person'=>$parametry['id_person'] ?? null, 'id_pob'=>$parametry['id_pob'] ?? null,
        'id_pole'=>$parametry['id_pole'] ?? null, 'parametry_json'=>$checked['parametry_json'],
    ];
    return cb_cinnost_db_vlozit($db, $modul, $row) + ['udalost_uuid'=>strtolower($uuid)];
}

/** UUID vytváří server pro jednotlivé volání; opakované odeslání musí dodat stejné UUID původní události. */
function cb_cinnost_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
    $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
    $hex = bin2hex($bytes);
    return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
}
