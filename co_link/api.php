<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../common/lib/osoba_jmeno.php';
/* Co-Link: autorizovane API, obnova identity z cookie a QR pripojeni a okamzity push po uspesnem ulozeni zpravy. */
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/qr.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$pushMessage=0;

try {
    if(!co_pripraveno()) co_chyba('Co-Link zatím není připravený k používání.',503);
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if(!in_array($method,['GET','POST'],true)) co_chyba('Nepodporovaná metoda.',405);
    $action=(string)($_GET['akce']??'stav');
    $d=[];
    if($method==='POST') {
        co_puvod();
        if(str_contains($_SERVER['CONTENT_TYPE']??'','application/json')) {
            $raw=file_get_contents('php://input',false,null,0,1048577);
            if($raw===false || strlen($raw)>1048576) co_chyba('Požadavek je příliš velký.',413);
            $d=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($d)) co_chyba('Neplatná data.');
        } else $d=$_POST;
    }
    if(in_array($action,['is_pripojit','qr_pripojit'],true)) {
        if($method!=='POST') co_chyba('Připojení vyžaduje potvrzení.',405);
        if($action==='is_pripojit') {
            require_once __DIR__.'/../common/lib/session_boot.php';
            require_once __DIR__.'/../common/lib/ochrana_crf.php';
            require_once __DIR__.'/../common/lib/prebrani_prav.php';
            if(empty($_SESSION['login_ok']) || !cb_session_validate_after_login() || cb_prebrani_prav_profil()!==null) co_chyba('Přihlaste se do IS pod vlastním účtem.',401);
            cb_crf_vyzaduj();
            $pcToken=cb_pc_cookie_token(false);
            if($pcToken==='') co_chyba('Nelze ověřit zařízení IS.',401);
            $out=co_pripoj((int)$_SESSION['cb_user']['id_user'],(string)($d['nazev']??'Počítač'),hash('sha256',$pcToken));
        } else {
            $out=co_qr_pripoj((string)($d['token']??''),(string)($d['nazev']??'Telefon'));
        }
        echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE); exit;
    }
    $identity=co_identita();
    if(!$identity) co_chyba('Připojení vypršelo nebo bylo odvoláno. Připojte zařízení znovu přes IS.',401);
    // Stejne udaje jako v HTML bootu. GET pouze overi existujici cookie; ostatni akce dale vyzaduji CSRF.
    if($action==='identita'&&$method==='GET') {
        echo json_encode(['ok'=>true,'identita'=>['id'=>(int)$identity['id_user'],'jmeno'=>$identity['jmeno'],'csrf'=>$identity['csrf']]],JSON_UNESCAPED_UNICODE);
        exit;
    }
    $user=(int)$identity['id_user'];
    // Vazba plati i pro cteni: stare okno po zmene uctu nesmi nacist konverzace nove identity.
    co_csrf($identity);
    $readActions=['stav','zpravy','lide','precteni','nastaveni','kanaly','zarizeni','clenove'];
    if($method==='GET' && !in_array($action,$readActions,true)) co_chyba('Tato akce vyžaduje POST.',405);
    $out=[];
    switch($action) {
        case 'stav':
            $out=['ja'=>['id'=>$user,'jmeno'=>$identity['jmeno']],'prava'=>co_prava($user),'konverzace'=>co_seznam($user)]; break;
        case 'lide':
            $out=co_adresar()+['sloty'=>co_rows('SELECT id_slot id,slot nazev FROM cis_slot WHERE aktivni=1 ORDER BY slot'),
                'funkce'=>co_rows('SELECT id_funkce id,nazev,zarazeni FROM cis_funkce WHERE aktivni=1 ORDER BY zarazeni,nazev')]; break;
        case 'zpravy':
            $out=co_zpravy($user,(int)($_GET['konverzace']??0),(int)($_GET['pred']??0),trim((string)($_GET['hledat']??'')),(int)($_GET['okolo']??0),(int)($_GET['vlakno']??0)); break;
        case 'clenove':
            $cid=(int)($_GET['konverzace']??0);
            co_konverzace($user,$cid); $members=[];
            foreach(co_lide() as $person) if(co_rows('SELECT k.id FROM co_konverzace k WHERE k.id=? AND '.co_pristup_sql((int)$person['id']),[$cid])) $members[]=$person;
            $out=['lide'=>$members]; break;
        case 'odeslat': $out=co_odeslat($user,$d); break;
        case 'upravit': case 'smazat': $out=co_men_zpravu($user,array_replace($d,['akce'=>$action])); break;
        case 'precteno': co_precteno($user,(int)($d['konverzace']??0),(array)($d['zpravy']??[])); break;
        case 'precteni':
            $m=co_rows('SELECT konverzace FROM co_zprava WHERE id=?',[(int)($_GET['zprava']??0)])[0]??[];
            if(!$m) co_chyba('Zpráva není dostupná.',404);
            co_konverzace($user,(int)$m['konverzace']);
            $out=['lide'=>co_rows('SELECT p.uzivatel,p.kdy,COALESCE(' . cb_osoba_jmeno_sql('p.uzivatel') . ',"Uživatel") jmeno FROM co_precteni p WHERE p.zprava=? ORDER BY p.kdy',[(int)$_GET['zprava']])]; break;
        case 'reakce': co_reakce($user,(int)($d['zprava']??0),(string)($d['emoji']??'')); break;
        case 'soukroma': $out=co_soukroma($user,$d); break;
        case 'kanal_ulozit': $out=co_kanal_uloz($user,$d); break;
        case 'kanaly':
            $channels=co_rows('SELECT * FROM co_konverzace WHERE typ="kanal" ORDER BY archiv,nazev');
            $out=['kanaly'=>[]];
            foreach($channels as $c) {
                if(!co_spravce_kanalu($user,$c)) continue;
                $c['lide']=array_map('intval',array_column(co_rows('SELECT uzivatel FROM co_clen WHERE konverzace=?',[(int)$c['id']]),'uzivatel'));
                $c['pravidla']=co_rows('SELECT druh,hodnota FROM co_pristup WHERE konverzace=?',[(int)$c['id']]);
                $out['kanaly'][]=$c;
            } break;
        case 'ztisit':
            co_konverzace($user,(int)($d['konverzace']??0));
            co_sql('INSERT INTO co_volba(uzivatel,konverzace,ztiseno) VALUES(?,?,?) ON DUPLICATE KEY UPDATE ztiseno=VALUES(ztiseno)',[$user,(int)$d['konverzace'],!empty($d['ztiseno'])?1:0])->close(); break;
        case 'qr':
            $out=co_qr_vytvor($user); break;
        case 'zarizeni':
            $out=['zarizeni'=>co_rows('SELECT id,nazev,vytvoreno,naposledy,CASE WHEN pc_hash IS NULL THEN NULL ELSE platnost_do END platnost_do FROM co_zarizeni WHERE uzivatel=? AND zruseno IS NULL AND (pc_hash IS NULL OR platnost_do>NOW()) ORDER BY id DESC',[$user]),'aktualni'=>$identity['zarizeni']]; break;
        case 'odvolat':
            co_sql('UPDATE co_zarizeni SET zruseno=NOW(),endpoint=NULL WHERE id=? AND uzivatel=?',[(int)($d['id']??0),$user])->close();
            co_audit($user,'zarizeni_odvolano',(int)($d['id']??0)); break;
        case 'odhlasit':
            co_sql('UPDATE co_zarizeni SET zruseno=NOW(),endpoint=NULL WHERE id=?',[$identity['zarizeni']])->close();
            co_cookie('',time()-3600); break;
        case 'push':
            $endpoint=(string)($d['endpoint']??''); $keys=(array)($d['keys']??[]);
            $host=strtolower((string)parse_url($endpoint,PHP_URL_HOST));
            // Odmitnuti ciziho endpointu zabrani serverovym HTTP pozadavkum do vnitrni site.
            $allowed=$host==='fcm.googleapis.com' || $host==='updates.push.services.mozilla.com' || str_ends_with($host,'.push.services.mozilla.com') || str_ends_with($host,'.push.apple.com') || $host==='web.push.apple.com' || str_ends_with($host,'.notify.windows.com');
            if(parse_url($endpoint,PHP_URL_SCHEME)!=='https' || !$allowed || strlen($endpoint)>2048 || !preg_match('/^[A-Za-z0-9_-]{60,200}$/D',(string)($keys['p256dh']??'')) || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D',(string)($keys['auth']??''))) co_chyba('Nepodporovaný odběr upozornění.');
            co_sql('UPDATE co_zarizeni SET endpoint=?,klic_public=?,klic_auth=? WHERE id=?',[$endpoint,$keys['p256dh'],$keys['auth'],$identity['zarizeni']])->close(); break;
        case 'nastaveni':
            $out=['nastaveni'=>co_rows('SELECT priloha_mb FROM co_nastaveni WHERE id=1')[0]]; break;
        case 'nastaveni_ulozit':
            if(!co_pravo($user,703)) co_chyba('Nemáte právo spravovat Co-Link.',403);
            $mb=filter_var($d['priloha_mb']??null,FILTER_VALIDATE_INT);
            if($mb===false || $mb<1 || $mb>2048) co_chyba('Příloha může mít 1–2048 MB.');
            co_transakce(static function() use($user,$mb): void {
                co_sql('UPDATE co_nastaveni SET priloha_mb=? WHERE id=1',[$mb])->close();
                co_audit($user,'nastaveni_zmeneno');
            }); break;
        case 'nahrat':
            require_once __DIR__.'/upload.php';
            $out=co_nahrat($user); break;
        default: co_chyba('Neznámá akce.',404);
    }
    if($method==='POST') co_sql('UPDATE co_zarizeni SET naposledy=NOW() WHERE id=?',[$identity['zarizeni']])->close();
    $response=json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    // co_odeslat uz dokoncilo transakci; push se nesmi spustit pro neulozenou zpravu.
    if($action==='odeslat') $pushMessage=(int)$out['id'];
    echo $response;
} catch(DomainException $e) {
    http_response_code(in_array($e->getCode(),[400,401,403,404,405,409,410,413,503],true)?$e->getCode():400);
    echo json_encode(['ok'=>false,'err'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    error_log('Co-Link API: '.get_class($e).' / '.$e->getCode());
    http_response_code(500);
    echo json_encode(['ok'=>false,'err'=>'Požadavek se nepodařilo dokončit. Zkuste ho znovu.'],JSON_UNESCAPED_UNICODE);
}

// PHP-FPM vrati potvrzeni pred sitovym cekanim. Zadny CRON ani dalsi pozadavek neni potreba.
if($pushMessage>0) {
    if(session_status()===PHP_SESSION_ACTIVE) session_write_close();
    if(function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    try {
        require_once __DIR__.'/push_dispatch.php';
        co_push_ihned($pushMessage);
    } catch(Throwable $error) {
        // Ani chyba nacitani odesilace nesmi pridat chybovy JSON k jiz potvrzene zprave.
        error_log('Co-Link push inicializace: '.get_class($error).' / '.$error->getCode());
    }
}
