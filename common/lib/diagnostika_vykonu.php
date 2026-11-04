<?php
/* Diagnostika Co-Linku a E-mailu: casove omezene mereni, evidence zacatku behu a metadata bez obsahu komunikace i zapisu do DB. */
declare(strict_types=1);

/** Provozni zaznamy zustavaji mimo verejny adresar www. */
function cb_diag_dir(): string
{
    return dirname(__DIR__, 3).'/data/diagnostika';
}

/** Vedle umisteni mimo www pridame zakaz HTTP pristupu i pro lokalni Apache s korenem nad projektem. */
function cb_diag_prepare_dir(): bool
{
    try {
        $dir=cb_diag_dir();
        if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) return false;
        $guard=$dir.'/.htaccess';
        return is_file($guard) || @file_put_contents($guard,"# Soukroma diagnostika IS: pristup pouze pres autorizovanou Administraci.\nRequire all denied\n",LOCK_EX)!==false;
    } catch (Throwable) {return false;}
}

/** Chybejici, poskozena nebo expirovana konfigurace znamena vypnuto. */
function cb_diag_config(): array
{
    $off = ['enabled'=>false, 'until'=>0, 'users'=>[], 'started_at'=>null, 'minutes'=>null, 'stopped_at'=>null];
    try {
        $file = cb_diag_dir().'/nastaveni.json';
        if (!is_file($file) || @filesize($file)>8192) return $off;
        $data = json_decode((string)@file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_bool($data['enabled']??null) || !is_int($data['until']??null)
            || !is_array($data['users']??null) || count($data['users'])>20) return $off;
        foreach($data['users'] as $id)if(!is_int($id) || $id<=0)return $off;
        // Vyber osob zachovame i po vypnuti/expiraci, aby dalsi zapnuti omylem nezahrnulo vsechny.
        $started=is_int($data['started_at']??null) && $data['started_at']>0?$data['started_at']:null;
        $stopped=is_int($data['stopped_at']??null) && $data['stopped_at']>0?$data['stopped_at']:null;
        if($data['enabled'] && $data['until']<=time())$stopped=$data['until'];
        return ['enabled'=>$data['enabled'] && $data['until']>time(), 'until'=>$data['until'], 'users'=>array_values($data['users']),
            'started_at'=>$started,'minutes'=>in_array($data['minutes']??null,[15,30,60,120],true)?$data['minutes']:null,'stopped_at'=>$stopped];
    } catch (Throwable) { return $off; }
}

/** Diagnostiku i jeji zaznamy smi spravovat pouze spravce s pristupem k exportu cele DB. */
function cb_diag_admin_allowed(): bool
{
    return !empty($_SESSION['login_ok']) && function_exists('cb_pravo_ma') && cb_pravo_ma(100) && cb_pravo_ma(109);
}

/** Povoleni se vzdy vyhodnocuje pro serverem overenou osobu. */
function cb_diag_enabled(array $config, int $user): bool
{
    return $user>0 && !empty($config['enabled']) && (int)$config['until']>time()
        && (!$config['users'] || in_array($user, $config['users'], true));
}

/** Zapis je neblokujici a omezeny na 16 MiB denne; diagnostika nesmi zdrzet aplikaci. */
function cb_diag_write(array $row): void
{
    try {
        $dir=cb_diag_dir();
        if (!cb_diag_prepare_dir()) return;
        $line=json_encode(['at'=>date(DATE_ATOM)]+$row, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR)."\n";
        if (strlen($line)>16384) return;
        $lock=@fopen($dir.'/zapis.lock','c');
        if (!$lock) return;
        try {
            if (!@flock($lock,LOCK_EX|LOCK_NB)) return;
            $file=$dir.'/'.date('Y-m-d').'.jsonl'; clearstatcache(true,$file);
            if (is_file($file) && @filesize($file)+strlen($line)>16*1024*1024) return;
            @file_put_contents($file,$line,FILE_APPEND);
            // Uklid bezi nejvyse jednou denne a nikdy nesaha mimo diagnosticky adresar.
            $stamp=$dir.'/uklid.txt';
            if (!is_file($stamp) || trim((string)@file_get_contents($stamp))!==date('Y-m-d')) {
                foreach (glob($dir.'/*.jsonl')?:[] as $old) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}\.jsonl$/D',basename($old)) && basename($old)<date('Y-m-d',strtotime('-13 days'))) @unlink($old);
                }
                @file_put_contents($stamp,date('Y-m-d'));
            }
        } finally { @flock($lock,LOCK_UN); fclose($lock); }
    } catch (Throwable) { /* Nedostupny disk nesmi zmenit vysledek pracovni akce. */ }
}

/** Cas behu zacina pri skutecnem zapnuti; prodlouzeni zachova zacatek a staremu nastaveni jej zpetne nevymysli. */
function cb_diag_save(bool $enabled, int $minutes, string $users): void
{
    if (!cb_diag_admin_allowed()) throw new RuntimeException('Nemáte oprávnění spravovat diagnostiku.');
    if (!in_array($minutes,[15,30,60,120],true)) throw new RuntimeException('Neplatná doba měření.');
    if (strlen($users)>250 || !preg_match('/^[0-9,\s]*$/D',$users)) throw new RuntimeException('Zadejte ID uživatelů oddělená čárkami.');
    $ids=array_values(array_unique(array_filter(array_map('intval',preg_split('/[,\s]+/',trim($users))),static fn($id)=>$id>0)));
    if (count($ids)>20) throw new RuntimeException('Lze vybrat nejvýše 20 uživatelů.');
    $dir=cb_diag_dir();
    if (!cb_diag_prepare_dir()) throw new RuntimeException('Nelze připravit chráněný adresář diagnostiky.');
    $tmp=@tempnam($dir,'nastaveni-');
    if ($tmp===false) throw new RuntimeException('Nelze uložit nastavení diagnostiky.');
    try {
        $previous=cb_diag_config();$now=time();
        $started=$enabled?($previous['enabled']?$previous['started_at']:$now):$previous['started_at'];
        $stopped=$enabled?null:($previous['enabled']?$now:$previous['stopped_at']);
        $data=json_encode(['enabled'=>$enabled,'until'=>$enabled?$now+$minutes*60:0,'users'=>$ids,
            'started_at'=>$started,'minutes'=>$minutes,'stopped_at'=>$stopped],JSON_THROW_ON_ERROR);
        if (@file_put_contents($tmp,$data)===false || !@rename($tmp,$dir.'/nastaveni.json')) throw new RuntimeException('Nelze uložit nastavení diagnostiky.');
    } finally { if (is_file($tmp)) @unlink($tmp); }
}

/** Identifikatory koreluji zaznamy, nikdy neslouzi jako autorizace. */
function cb_diag_id(mixed $value): string
{
    return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{8,80}$/D',$value)?$value:'';
}

/** Zamezi ukladani textu hledani, nazvu slozek, URL, tokenu a obsahu zprav. */
function cb_diag_meta(array $data): array
{
    $out=[];
    foreach (['account','uid','conversation','page','search_chars','body','aggregate','aggregate_part','background','count','device'] as $key) {
        if (isset($data[$key]) && is_numeric($data[$key])) $out[$key]=max(0,min(1000000000,(int)$data[$key]));
    }
    return $out;
}

/** Zachyti take cas bootstrapu; bez overene identity se zadny zaznam neulozi. */
function cb_diag_server_start(string $module): void
{
    try {
        header('X-CB-Diag: 0');
        $config=cb_diag_config();
        if (!$config['enabled']) return;
        $id=cb_diag_id($_SERVER['HTTP_X_CB_DIAG_ID']??'') ?: bin2hex(random_bytes(16));
        $action=(string)($_GET[$module==='email'?'action':'akce']??'');
        if (!preg_match('/^[a-z_]{1,40}$/D',$action)) $action='unknown';
        $GLOBALS['cb_diag']=['config'=>$config,'module'=>$module,'request_id'=>$id,'action'=>$action,
            'action_id'=>cb_diag_id($_SERVER['HTTP_X_CB_DIAG_ACTION']??''),'start'=>microtime(true),'user'=>0,'spans'=>[]];
        register_shutdown_function(static function(): void {
            try {
                $d=$GLOBALS['cb_diag']??[];
                if (empty($d['user']) || !cb_diag_enabled(cb_diag_config(),$d['user'])) return;
                $last=error_get_last();
                cb_diag_write(['kind'=>'server','module'=>$d['module'],'user'=>$d['user'],'login'=>$d['login']??0,
                    'request_id'=>$d['request_id'],'action_id'=>$d['action_id'],'action'=>$d['action'],'meta'=>$d['meta']??[],
                    'status'=>http_response_code()?:200,'php_ms'=>round((microtime(true)-(float)($_SERVER['REQUEST_TIME_FLOAT']??$d['start']))*1000,2),
                    'response_ms'=>$d['response_ms']??null,'response_body_bytes'=>$d['response_body_bytes']??null,'spans'=>$d['spans'],
                    'fatal'=>$last && in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)]);
            } catch (Throwable) {}
        });
    } catch (Throwable) {}
}

/** Identitu i cilene uzivatele kontroluje server, nikoli zaslana metadata klienta. */
function cb_diag_bind(int $user, array $meta=[]): void
{
    try {
        if (empty($GLOBALS['cb_diag']) || !cb_diag_enabled($GLOBALS['cb_diag']['config'],$user)) return;
        $d=&$GLOBALS['cb_diag'];
        if (empty($d['user'])) {
            $ms=round((microtime(true)-(float)($_SERVER['REQUEST_TIME_FLOAT']??$d['start']))*1000,3);
            $d['spans']['bootstrap_auth']=['ms'=>$ms,'max_ms'=>$ms,'count'=>1];
        }
        $d['user']=$user; $d['login']=(int)($_SESSION['cb_id_login']??0); $d['meta']=cb_diag_meta($meta);
        header('X-CB-Diag: '.$d['config']['until']);
        header('X-CB-Diag-Id: '.$d['request_id']);
    } catch (Throwable) {}
}

/** Cas merime jen pri zapnute diagnostice a bez dalsich SQL dotazu. */
function cb_diag_clock(): float
{
    return !empty($GLOBALS['cb_diag']['user'])?hrtime(true)/1000000:0.0;
}

/** Drobne opakovane faze seskupime; v logu zustava soucet, maximum a pocet. */
function cb_diag_span(string $name, float $start): void
{
    try {
        if (!$start || empty($GLOBALS['cb_diag']['user'])) return;
        $ms=max(0,hrtime(true)/1000000-$start);
        $s=&$GLOBALS['cb_diag']['spans'][$name];
        if (!$s) $s=['ms'=>0,'max_ms'=>0,'count'=>0];
        $s=['ms'=>round($s['ms']+$ms,3),'max_ms'=>round(max($s['max_ms'],$ms),3),'count'=>$s['count']+1];
    } catch (Throwable) {}
}

/** Serverovy cas se ukonci pred odeslanim odpovedi, oddelene od pozdejsiho push/disconnect. */
function cb_diag_response(?int $bytes=null): void
{
    try {
        if (empty($GLOBALS['cb_diag']['user'])) return;
        $d=&$GLOBALS['cb_diag'];
        $d['response_ms']=round((microtime(true)-(float)($_SERVER['REQUEST_TIME_FLOAT']??$d['start']))*1000,2);
        $d['response_body_bytes']=$bytes;
        if (!headers_sent()) {
            header('Server-Timing: cb_php;dur='.$d['response_ms'].', cb_req;desc="'.$d['request_id'].'"',false);
            if ($bytes!==null) header('X-CB-Diag-Body-Bytes: '.$bytes);
        }
    } catch (Throwable) {}
}

/** Klient prijima pouze priznak a expiraci, nikoli seznam sledovanych osob. */
function cb_diag_boot(int $user): string
{
    $c=cb_diag_config();
    return (string)(cb_diag_enabled($c,$user)?$c['until']:0);
}

/** Klientska data prijimame pouze podle pevneho seznamu; nelze protlacit obsah zpravy ani podvrzenou identitu. */
function cb_diag_client_event(mixed $event, string $module, int $user, int $login, string $userAgent=''): ?array
{
    if (!is_array($event) || !in_array($event['kind']??'',['request','action'],true)
        || !is_string($event['action']??null) || !preg_match('/^[a-z_]{1,50}$/D',$event['action'])) return null;
    $row=['kind'=>$event['kind'],'module'=>$module,'user'=>$user,'login'=>$login,'action'=>$event['action'],
        'request_id'=>cb_diag_id($event['request_id']??''),'action_id'=>cb_diag_id($event['action_id']??''),
        'meta'=>cb_diag_meta(is_array($event['meta']??null)?$event['meta']:[]),
        'status'=>in_array($event['status']??'',['ok','error','abort','timeout','superseded','incomplete','headers'],true)?$event['status']:'error'];
    foreach (['at','started_at','total_ms','wait_ms','download_ms','dns_ms','connect_ms','transfer_bytes','encoded_bytes','decoded_bytes','php_ms','work_ms','render_wait_ms','requests','unknown_sizes','failed_requests','http_status','expected_decoded_bytes','unknown_decoded_sizes'] as $key) {
        $value=$event[$key]??null;
        $row[$key]=is_numeric($value) && is_finite((float)$value) && $value>=0 && $value<=1e15?(float)$value:null;
    }
    $row['client_at']=$row['at'];unset($row['at']);
    foreach (['visible','service_worker','body_complete'] as $key) if (isset($event[$key]) && is_bool($event[$key])) $row[$key]=$event[$key];
    // Pouze typ prohlizece; cely UA ani URL, IP, session ID a zpravy se neukladaji.
    $ua=$userAgent;
    $row['browser']=preg_match('/Edg\//',$ua)?'Edge':(preg_match('/Firefox\//',$ua)?'Firefox':(preg_match('/Chrome\//',$ua)?'Chrome':(str_contains($ua,'Safari')?'Safari':'other')));
    return $row;
}
