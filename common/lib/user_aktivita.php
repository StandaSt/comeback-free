<?php
declare(strict_types=1);
/* Účel: Přijímá omezené dávky událostí prohlížeče a zapisuje potvrzené serverové akce do společné historie uživatele. */
require_once __DIR__.'/uloz_akci.php';

/** Povolené události se rozšiřují po modulech; klient si nesmí určit libovolný typ nebo výsledek serverové akce. */
function cb_aktivita_katalog(): array
{
    return ['smeny'=>[
        'stranka_zobrazena'=>2,'stranka_opustena'=>18,'menu_klik'=>2,'menu_stejne'=>19,
        'tyden_vybran'=>3,'pobocka_vybrana'=>4,'den_vybran'=>5,'filtr_zmenen'=>5,'filtr_reset'=>6,
        'pole_zmeneno'=>9,'sloupec_pridan'=>10,'slot_pridan'=>10,'slot_odebran'=>11,
        'cas_vybran'=>9,'osoba_vybrana'=>9,'vyber_zrusen'=>18,'editor_otevren'=>17,'editor_zavren'=>18,
        'ovladani_klik'=>9,'formular_odeslan'=>12,'formular_neplatny'=>20,'planovani_ukonceno'=>18,
    ]];
}

/** Kontext má pevná pole, délky a skalární hodnoty; tokeny, hesla a libovolný obsah formulářů se nepřijímají. */
function cb_aktivita_klient_validovat(array $event): array
{
    $module=$event['modul'] ?? null; $code=$event['udalost'] ?? null;
    $types=cb_aktivita_katalog();
    if(!is_string($module) || !is_string($code) || !isset($types[$module][$code])) throw new InvalidArgumentException('Nepovolená událost.');
    foreach(['id','karta'] as $key) if(!is_string($event[$key] ?? null) || preg_match('~^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$~D',$event[$key])!==1) throw new InvalidArgumentException('Neplatné ID události.');
    if(!is_int($event['poradi'] ?? null) || $event['poradi']<1 || $event['poradi']>2147483647 || !is_int($event['cas_ms'] ?? null) || $event['cas_ms']<0) throw new InvalidArgumentException('Neplatné pořadí události.');
    $page=$event['stranka'] ?? '';
    if(!is_string($page) || preg_match('~^[a-z_]{1,50}$~D',$page)!==1) throw new InvalidArgumentException('Neplatná stránka.');
    $context=$event['detail'] ?? [];
    if(!is_array($context) || count($context)>24) throw new InvalidArgumentException('Neplatný kontext.');
    $numbers=['id_pob','week','id_person','id_slot','id_smeny_rozpis','id_smeny_sablona','id_smeny_smena','id_smeny_oznameni','id_smeny_zruseni','den_index'];
    $strings=['datum','tyden_od','cas_od','cas_do','pole','old','new','akce','cil_modul','cil_stranka','duvod','popisek'];
    foreach($context as $key=>$value) {
        if(in_array($key,$numbers,true)) {
            if(!is_int($value) || $value<0 || $value>2147483647) throw new InvalidArgumentException('Neplatné ID kontextu.');
        } elseif(in_array($key,$strings,true)) {
            if(!is_string($value) || mb_strlen($value)>300 || preg_match('~[\x00-\x08\x0b\x0c\x0e-\x1f]~',$value)) throw new InvalidArgumentException('Neplatná hodnota kontextu.');
        } else throw new InvalidArgumentException('Nepovolené pole kontextu.');
    }
    return ['id'=>$event['id'],'typ'=>$types[$module][$code],'modul'=>$module,'detail'=>$context+[
        'udalost'=>$code,'puvod'=>'prohlizec','faze'=>$code==='formular_odeslan'?'odeslano':($code==='stranka_zobrazena'?'zobrazeno':'rozpracovano'),
        'stranka'=>$page,'udalost_id'=>$event['id'],'karta_id'=>$event['karta'],'poradi'=>$event['poradi'],'klient_cas_ms'=>$event['cas_ms'],
    ]];
}

/** Serverový zápis používá skutečný účet a login ze společného loggeru, nikdy identitu přijatou z prohlížeče. */
function cb_aktivita_server_zapis(string $module,string $code,array $context=[],int $type=12,string $object='akce',int $id=0,bool $success=true,?string $old=null,?string $new=null): bool
{
    try {
        return cb_user_akce_zapis(['id_user_akce_typ'=>$type,'modul'=>$module,'objekt'=>$object,'id_objektu'=>$id,
            'zdroj'=>'server','vysledek'=>$success?1:0,'hodnota_old'=>$old,'hodnota_new'=>$new,
            'detail'=>array_merge($context,['udalost'=>$code,'puvod'=>'server','faze'=>$success?'potvrzeno':'odmitnuto'])]);
    } catch(Throwable $error) { error_log('Aktivita: serverový zápis selhal: '.$error->getMessage()); return false; }
}

/** Potvrzuje jen uložená ID. Omezená paměť loginu odfiltruje i opakování dávky po přechodu na novou stránku. */
function cb_aktivita_davka(array $events): array
{
    if(count($events)<1 || count($events)>30 || !array_is_list($events)) throw new InvalidArgumentException('Neplatná velikost dávky.');
    $validated=[];
    foreach($events as $event) {
        if(!is_array($event)) throw new InvalidArgumentException('Neplatná událost.');
        $item=cb_aktivita_klient_validovat($event);
        if(!cb_modul_ma_pristup($item['modul'])) throw new InvalidArgumentException('Modul není dostupný.');
        $validated[]=$item;
    }
    $login=(string)(int)($_SESSION['cb_id_login'] ?? 0).':'.(int)($_SESSION['cb_user']['id_user'] ?? 0);
    $state=&$_SESSION['cb_aktivita'];
    if(!is_array($state) || ($state['login'] ?? '')!==$login) $state=['login'=>$login,'seen'=>[],'minute'=>0,'count'=>0];
    $now=time(); $minute=intdiv($now,60);
    if($state['minute']!==$minute) { $state['minute']=$minute; $state['count']=0; }
    $state['seen']=array_filter($state['seen'],static fn(int $time):bool=>$time>=$now-3600);
    $accepted=[];
    foreach($validated as $item) {
        if(isset($state['seen'][$item['id']])) { $accepted[]=$item['id']; continue; }
        if($state['count']>=600) break;
        $detail=$item['detail'];
        try { $saved=cb_user_akce_zapis(['id_user_akce_typ'=>$item['typ'],'modul'=>$item['modul'],'objekt'=>'ovladani','zdroj'=>'prohlizec','vysledek'=>$item['typ']===20?0:1,
            'pole'=>$detail['pole'] ?? '', 'hodnota_old'=>$detail['old'] ?? null,'hodnota_new'=>$detail['new'] ?? null,'detail'=>$detail]); }
        catch(Throwable $error) { error_log('Aktivita: příjem dávky selhal: '.$error->getMessage()); $saved=false; }
        if(!$saved) break;
        $state['seen'][$item['id']]=$now; $state['count']++; $accepted[]=$item['id'];
    }
    $state['seen']=array_slice($state['seen'],-2000,null,true);
    return ['ok'=>count($accepted)===count($validated),'accepted'=>$accepted];
}

/** Samostatný vstup neotevírá modul ani neprodlužuje jeho plánovací zámek. */
function cb_aktivita_prijem(): void
{
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    if(($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') { http_response_code(405); echo '{"ok":false}'; return; }
    if(empty($_SESSION['login_ok'])) { http_response_code(401); echo '{"ok":false}'; return; }
    if(!cb_crf_platny()) { http_response_code(403); echo '{"ok":false}'; return; }
    try {
        $raw=$_POST['events'] ?? '';
        if(!is_string($raw) || strlen($raw)>48000) throw new InvalidArgumentException('Příliš velká dávka.');
        $events=json_decode($raw,true,12,JSON_THROW_ON_ERROR);
        if(!is_array($events)) throw new InvalidArgumentException('Neplatná dávka.');
        $result=cb_aktivita_davka($events);
        if(!$result['ok']) http_response_code(503);
        echo json_encode($result,JSON_THROW_ON_ERROR);
    } catch(InvalidArgumentException|JsonException $error) { http_response_code(400); echo '{"ok":false}'; }
}
