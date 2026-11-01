<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
/* Co-Link: HTTP kontroly, smerovani akci a JSON odpovedi; datove operace provadeji sluzby, puvodni URL api.php zustava funkcni. */
require_once __DIR__ . '/../co_lib/co_data.php';
// QR sluzby nacitaji jednotlive DB operace; pri samotnem include zadny QR dotaz neprobehne.
require_once __DIR__ . '/../co_lib/co_qr_vytvoreni.php';
require_once __DIR__ . '/../co_lib/co_qr_pripojeni.php';
// Souvisejici sluzby nacitaji definice; pri samotnem include se zadna operace ani dotaz neprovadi.
require_once __DIR__ . '/../co_lib/co_sprava_kanalu.php';
require_once __DIR__ . '/../co_lib/co_sprava_zarizeni.php';
require_once __DIR__ . '/../co_lib/co_nastaveni.php';
require_once __DIR__ . '/../co_lib/co_podrobnosti_konverzace.php';
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
            require_once __DIR__.'/../../common/lib/session_boot.php';
            require_once __DIR__.'/../../common/lib/ochrana_crf.php';
            require_once __DIR__.'/../../common/lib/prebrani_prav.php';
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
            // Ciselniky maji vlastni DB operace; jejich pole a poradi zustavaji zachovany.
            require_once __DIR__ . '/../co_db/co_sloty_nacist.php';
            require_once __DIR__ . '/../co_db/co_funkce_nacist.php';
            $out=co_adresar()+['sloty'=>co_sloty_nacist(),
                'funkce'=>co_funkce_nacist()]; break;
        case 'zpravy':
            $out=co_zpravy($user,(int)($_GET['konverzace']??0),(int)($_GET['pred']??0),trim((string)($_GET['hledat']??'')),(int)($_GET['okolo']??0),(int)($_GET['vlakno']??0)); break;
        case 'clenove':
            $out=co_konverzace_clenove($user,(int)($_GET['konverzace']??0)); break;
        case 'odeslat': $out=co_odeslat($user,$d); break;
        case 'upravit': case 'smazat': $out=co_men_zpravu($user,array_replace($d,['akce'=>$action])); break;
        case 'precteno': co_precteno($user,(int)($d['konverzace']??0),(array)($d['zpravy']??[])); break;
        case 'precteni':
            $out=co_zprava_precteni($user,(int)($_GET['zprava']??0)); break;
        case 'reakce': co_reakce($user,(int)($d['zprava']??0),(string)($d['emoji']??'')); break;
        case 'soukroma': $out=co_soukroma($user,$d); break;
        case 'kanal_ulozit': $out=co_kanal_uloz($user,$d); break;
        case 'kanaly':
            $out=co_kanaly_sprava($user); break;
        case 'ztisit':
            co_konverzace_ztisit($user,$d); break;
        case 'qr':
            $out=co_qr_vytvor($user); break;
        case 'zarizeni':
            $out=co_zarizeni_seznam($user,$identity['zarizeni']); break;
        case 'odvolat':
            co_zarizeni_odvolat($user,(int)($d['id']??0)); break;
        case 'odhlasit':
            co_zarizeni_odhlasit($identity['zarizeni']); break;
        case 'push':
            co_zarizeni_push_ulozit($identity['zarizeni'],$d); break;
        case 'nastaveni':
            $out=co_nastaveni_nacist(); break;
        case 'nastaveni_ulozit':
            co_nastaveni_ulozit($user,$d); break;
        case 'nahrat':
            // Nahravaci sluzba sklada samostatne overeni, ulozeni a jednotlive DB operace.
            require_once __DIR__.'/../co_lib/co_priloha_nahrat.php';
            $out=co_nahrat($user); break;
        default: co_chyba('Neznámá akce.',404);
    }
    if($method==='POST') co_zarizeni_aktivitu_obnovit($identity['zarizeni']);
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
        // Okamzity push pouziva samostatne sluzby a jednotlive DB operace.
        require_once __DIR__.'/../co_lib/co_push_ihned.php';
        co_push_ihned($pushMessage);
    } catch(Throwable $error) {
        // Ani chyba nacitani odesilace nesmi pridat chybovy JSON k jiz potvrzene zprave.
        error_log('Co-Link push inicializace: '.get_class($error).' / '.$error->getCode());
    }
}
