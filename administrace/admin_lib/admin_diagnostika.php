<?php
/* Cte omezeny konec diagnostiky, sparuje souvisejici pozadavky a pripravi srozumitelne casy, faze a velikosti pro prehled. */
declare(strict_types=1);
require_once __DIR__.'/../../common/lib/diagnostika_vykonu.php';

/** Vrati nejvyse 300 nejnovejsich shod z poslednich 4 MiB zvoleneho dne. */
function cb_admin_diag_rows(string $day, int $user, string $module, float $slow, string $kind='action'): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)) return [];
    $file=cb_diag_dir().'/'.$day.'.jsonl';
    if (!is_file($file)) return [];
    $handle=fopen($file,'rb'); if (!$handle) return [];
    try {
        $size=(int)(fstat($handle)['size']??0);
        if ($size>4*1024*1024) {fseek($handle,-4*1024*1024,SEEK_END);fgets($handle);}
        $rows=[];$servers=[];$actionServers=[];$actionRequests=[];
        while (($line=fgets($handle,16385))!==false) {
            $row=json_decode($line,true);
            if(is_array($row) && ($row['kind']??'')==='request' && !empty($row['action_id'])) {
                $key=(int)($row['user']??0).'|'.($row['module']??'').'|'.$row['action_id'];
                $actionRequests[$key][(string)($row['request_id']??'')]=$row;
            }
            if (is_array($row) && ($row['kind']??'')==='server' && !empty($row['request_id'])) {
                $prefix=(int)($row['user']??0).'|'.($row['module']??'').'|';
                $servers[$prefix.$row['request_id']]=$row;
                if(!empty($row['action_id']))$actionServers[$prefix.$row['action_id']][$row['request_id']]=$row;
            }
            if (!is_array($row) || ($kind!=='' && ($row['kind']??'')!==$kind) || ($user>0 && (int)($row['user']??0)!==$user)
                || ($module!=='' && ($row['module']??'')!==$module)
                || (float)($row['total_ms']??$row['response_ms']??$row['php_ms']??0)<$slow) continue;
            $rows[]=$row;
            if (count($rows)>600) $rows=array_slice($rows,-300);
        }
        $rows=array_reverse(array_slice($rows,-300));
        foreach($rows as &$row) {
            $prefix=(int)($row['user']??0).'|'.($row['module']??'').'|';
            if(($row['kind']??'')==='request' && isset($servers[$prefix.($row['request_id']??'')]))$row['server']=$servers[$prefix.$row['request_id']];
            if(($row['kind']??'')==='action' && isset($actionServers[$prefix.($row['action_id']??'')])) {
                // Souběžné požadavky nesčítáme do falešného času čekání: ukážeme maximum jednoho požadavku/fáze.
                $summary=['response_ms'=>0,'spans'=>[],'request_count'=>0,'aggregation'=>'max_per_request'];
                foreach($actionServers[$prefix.$row['action_id']] as $server) {
                    $summary['request_count']++;$summary['response_ms']=max($summary['response_ms'],(float)($server['response_ms']??$server['php_ms']??0));
                    foreach($server['spans']??[] as $name=>$span)if(($span['ms']??0)>($summary['spans'][$name]['ms']??-1))$summary['spans'][$name]=$span;
                }
                $row['server']=$summary;
            }
            if(($row['kind']??'')==='action') {
                $row['related_requests']=array_values($actionRequests[$prefix.($row['action_id']??'')]??[]);
                foreach($row['related_requests'] as &$request)if(isset($servers[$prefix.($request['request_id']??'')]))$request['server']=$servers[$prefix.$request['request_id']];
                unset($request);
                usort($row['related_requests'],static fn($a,$b)=>($a['started_at']??0)<=>($b['started_at']??0));
            }
        }
        unset($row);return $rows;
    } finally {fclose($handle);}
}

/** Male odpovedi zobrazime v bajtech, aby zaokrouhleni nevytvorilo z nenulovych dat nulu. */
function cb_admin_diag_bytes(?float $bytes): string
{
    if($bytes===null)return '—';
    if($bytes<1024)return number_format($bytes,0,',',' ').' B';
    if($bytes<1048576)return number_format($bytes/1024,1,',',' ').' KiB';
    return number_format($bytes/1048576,2,',',' ').' MiB';
}

/** Prehled popisuje pracovni akce cesky; puvodni technicky nazev zustava v detailu. */
function cb_admin_diag_label(array $row): string
{
    if(($row['action']??'')==='loadlist' && !empty($row['meta']['search_chars']))return 'Hledání zpráv';
    return ['open_email'=>'Otevření E-mailu','open_colink'=>'Otevření Co-Linku','open_chat'=>'Otevření konverzace',
        'loadaccount'=>'Načtení schránky','loadlist'=>'Načtení seznamu zpráv','openmessage'=>'Otevření zprávy',
        'openattachment'=>'Otevření přílohy','state'=>'Stav schránek','folders'=>'Seznam složek','list'=>'Seznam / hledání',
        'message'=>'Obsah zprávy','attachment'=>'Příloha','background'=>'Kontrola na pozadí','stav'=>'Obnova konverzací',
        'zpravy'=>'Načtení zpráv','lide'=>'Adresář','odeslat'=>'Odeslání zprávy','send'=>'Odeslání e-mailu',
        'draft'=>'Uložení konceptu','read'=>'Označení přečtení','precteno'=>'Potvrzení přečtení'][$row['action']??'']??(string)($row['action']??'');
}

/** Nejvetsi merena faze je konkretni voditko; faze se mohou prekryvat a netvori uplny rozpad casu PHP. */
function cb_admin_diag_phase(array $row): string
{
    $spans=$row['server']['spans']??$row['spans']??[];
    if(!$spans)return '—';
    uasort($spans,static fn($a,$b)=>($b['ms']??0)<=>($a['ms']??0));$key=array_key_first($spans);
    return cb_admin_diag_phase_label((string)$key).' · '.cb_admin_diag_ms((float)$spans[$key]['ms']);
}

/** Nazvy fazi jsou spolecne pro souhrn i rozpis detailu. */
function cb_admin_diag_phase_label(string $key): string
{
    $labels=['bootstrap_auth'=>'Příprava a ověření','imap_connect'=>'IMAP připojení','imap_folder'=>'IMAP složka','imap_search'=>'IMAP hledání',
        'imap_headers'=>'IMAP hlavičky','imap_examine'=>'IMAP stav složky','imap_bodystructure'=>'IMAP struktura příloh',
        'imap_message_headers'=>'IMAP hlavička zprávy','imap_message_body'=>'IMAP tělo a přílohy','rows_and_work_sql'=>'Řádky a pracovní stavy SQL',
        'smtp_send'=>'SMTP odeslání','imap_store_sent'=>'Uložení do Odeslaných','sql_prepare_execute'=>'SQL příprava a provedení'];
    return $labels[$key]??$key;
}

/** Cas zobrazi v sekundach jen tam, kde to zlepsi prehlednost; null zustava nezjistene. */
function cb_admin_diag_ms(mixed $ms): string
{
    if(!is_numeric($ms))return '—';
    return (float)$ms>=1000?number_format((float)$ms/1000,2,',',' ').' s':number_format((float)$ms,1,',',' ').' ms';
}

/** Vysledek pracovni akce prelozi bez zamemeny chyby a preruseneho nacitani. */
function cb_admin_diag_status(mixed $status): string
{
    if(is_numeric($status))return 'HTTP '.(int)$status;
    return ['ok'=>'Dokončeno','error'=>'Chyba','abort'=>'Přerušeno','timeout'=>'Vypršel čas','superseded'=>'Nahrazeno novou akcí',
        'incomplete'=>'Neúplné výsledky','headers'=>'Přijaty pouze hlavičky'][(string)$status]??'Nezjištěno';
}

/** Pro serverovy radek i klientsky pozadavek pouzije odpovidajici cas pripravy odpovedi. */
function cb_admin_diag_php(array $row): mixed
{
    return $row['server']['response_ms']??$row['response_ms']??$row['php_ms']??null;
}

/** Delka behu je nezavisla na zvolenych jednotkach merenych HTTP pozadavku. */
function cb_admin_diag_duration(int $seconds): string
{
    $seconds=max(0,$seconds);$hours=intdiv($seconds,3600);$minutes=intdiv($seconds%3600,60);
    return ($hours?$hours.' h ':'').$minutes.' min '.($seconds%60).' s';
}
