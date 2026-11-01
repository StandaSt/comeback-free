<?php
declare(strict_types=1);
/* Jediny odesilac obecnych informaci: nejprve ulozi vsechny adresaty, pak dorucuje push.
 * Zanikle odbery vyrazuje pres sdilenou ochranu proti soubehu; nedorucitelne informace predava adminovi.
 * Prihlasovaci 2FA neodesila ani nemeni. Volajici predava obsah, cil detailu a volitelny trvaly klic.
 */
require_once __DIR__.'/../lib/osoba_jmeno.php';
require_once __DIR__.'/../lib/push_odber.php';

/** Povinne ulozeni vysledku, bez zamaskovani chyby auditu jako uspesneho odeslani. */
function cb_info_audit(mysqli $db, int $user, ?int $device, string $type, string $state, ?int $status, ?string $error): void
{
    $stmt=$db->prepare('INSERT INTO push_audit (id_user,id_zarizeni,typ,stav,http_status,chyba,vytvoreno) VALUES (?,?,?,?,?,?,NOW())');
    $stmt->bind_param('iissis',$user,$device,$type,$state,$status,$error); $stmt->execute(); $stmt->close();
}

/** Predani ma vlastni obsah i token; administrator neziskava token puvodniho prijemce. */
function cb_info_predat_adminovi(int $id, int $user, string $title, string $content, string $reason): array
{
    $db=db();
    $stmt=$db->prepare('SELECT '.cb_osoba_jmeno_sql('p.id_person').' jmeno FROM hr_person p WHERE p.id_user=?');
    $stmt->bind_param('i',$user); $stmt->execute(); $person=$stmt->get_result()->fetch_assoc(); $stmt->close();
    $name=trim((string)($person['jmeno']??''));
    $label=($name!==''?$name.' ':'').'[ID '.$user.']';
    $text='Původní příjemce: '.$label."\nDůvod předání adminovi: ".$reason."\n\nPůvodní upozornění: ".$title."\n\n".$content;
    return cb_info_odeslat([1],'push_nedoruceno','Nedoručené upozornění', $text,
        ['id_puvodni_informace'=>$id,'id_puvodniho_prijemce'=>$user,'duvod'=>$reason],null,'','',
        'predani:'.$id.':'.$user,false);
}

/** Spolecna cesta zachova i specialni mobilni detail a metadata stavajicich odesilatelu.
 * Prijeti poskytovatelem je uspech transportu, nikoli dukaz zobrazeni na telefonu.
 */
function cb_info_odeslat(array $users, string $type, string $title, string $content, array $metadata = [],
    ?int $actor = null, string $detailUrl = '', string $preview = '', string $key = '', bool $forward = true): array
{
    global $PROSTREDI;
    if (($PROSTREDI??'')!=='SERVER') throw new RuntimeException('Odesílání informace je povoleno pouze na serveru.');
    $users=array_values(array_unique(array_filter(array_map('intval',$users),static fn(int $id): bool=>$id>0)));
    if (!$users || trim($content)==='' || $type==='' || strlen($type)>40) throw new InvalidArgumentException('Neplatná evidovaná notifikace.');
    $db=db(); $lock='';
    if ($key!=='') {
        $lock='cb_info_'.substr(hash('sha256',$type.':'.$key),0,48);
        $stmt=$db->prepare('SELECT GET_LOCK(?,0)'); $stmt->bind_param('s',$lock); $stmt->execute();
        $acquired=(int)$stmt->get_result()->fetch_row()[0]===1; $stmt->close();
        if (!$acquired) throw new RuntimeException('Stejnou notifikaci právě zpracovává jiný běh.');
    }
    try {
        if ($key!=='') {
            $stmt=$db->prepare("SELECT id_admin_info FROM admin_info WHERE typ=? AND JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(pozn),pozn,'{}'),'$.klic'))=? LIMIT 1");
            $stmt->bind_param('ss',$type,$key); $stmt->execute(); $existing=$stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($existing) return ['ok'=>1,'id_admin_info'=>(int)$existing['id_admin_info'],'opakovani'=>true,'odeslano'=>0,'predano_adminovi'=>0,'selhalo'=>0];
            $metadata['klic']=$key;
        }
        $note=json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $db->begin_transaction();
        try {
            // Registrace zarizeni nikdy nerozhoduje o tom, zda ma uzivatel narok na informaci v IS.
            $stmt=$db->prepare('INSERT INTO admin_info (typ,nadpis,obsah,pozn,id_odeslal) VALUES (?,?,?,?,?)');
            $stmt->bind_param('ssssi',$type,$title,$content,$note,$actor); $stmt->execute(); $id=(int)$db->insert_id; $stmt->close();
            $tokens=[];
            foreach ($users as $user) {
                $token=bin2hex(random_bytes(32));
                $stmt=$db->prepare('INSERT INTO admin_info_user (id_admin_info,id_user,token) VALUES (?,?,?)');
                $stmt->bind_param('iis',$id,$user,$token); $stmt->execute(); $stmt->close(); $tokens[$user]=$token;
            }
            $db->commit();
        } catch (Throwable $e) { $db->rollback(); throw $e; }
        $transport=null; $transportError='';
        try {
            if (!defined('CB_VAPID_PUBLIC')||!defined('CB_VAPID_PRIVATE')||!defined('CB_VAPID_SUBJECT')||!cb_push_has_vendor()) throw new RuntimeException('Chybí konfigurace nebo knihovna Web Push.');
            require_once cb_push_vendor_autoload();
            $transport=new Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>(string)CB_VAPID_SUBJECT,'publicKey'=>(string)CB_VAPID_PUBLIC,'privateKey'=>(string)CB_VAPID_PRIVATE]]);
        } catch (Throwable $e) { $transportError=$e->getMessage(); }
        $body=preg_replace('/\s+/u',' ',trim($preview)!==''?$preview:$content)??$content;
        if (mb_strlen($body,'UTF-8')>140) $body=mb_substr($body,0,137,'UTF-8').'...';
        $sent=0; $failed=0; $forwarded=0;
        foreach ($tokens as $user=>$token) {
            $delivered=false; $reasonForAdmin='';
            try {
                $devices=cb_push_load_devices($user);
                if (!$devices) {
                    $reasonForAdmin='Příjemce nemá registrované aktivní zařízení pro push notifikace.';
                    cb_info_audit($db,$user,null,$type,'fail',null,$reasonForAdmin);
                } elseif (!$transport) {
                    cb_info_audit($db,$user,null,$type,'fail',null,$transportError);
                } else {
                    $urlBase=trim($detailUrl)!==''?$detailUrl:cb_module_url('provoz').'mobil/admin_info.php';
                    $url=cb_push_url_abs($urlBase).(str_contains($urlBase,'?')?'&':'?').'t='.rawurlencode($token);
                    $payload=json_encode(['type'=>'ADMIN_INFO','title'=>$title,'body'=>$body,'url'=>$url],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                    foreach ($devices as $device) {
                        $ok=false; $status=null; $error=null;
                        try {
                            $subscription=Minishlink\WebPush\Subscription::create(['endpoint'=>$device['endpoint'],'publicKey'=>$device['klic_public'],'authToken'=>$device['klic_auth']]);
                            $report=$transport->sendOneNotification($subscription,$payload);
                            $ok=$report && $report->isSuccess();
                            $status=$report && $report->getResponse()?$report->getResponse()->getStatusCode():null;
                            $error=$ok?null:($report?$report->getReason():'Push: bez reportu');
                        } catch (Throwable $e) { $error=$e->getMessage(); }
                        cb_info_audit($db,$user,(int)$device['id'],$type,$ok?'ok':'fail',$status,$error);
                        if (!$ok && in_array($status,[404,410],true)) cb_push_odber_vyrad_zanikly($db,$user,(int)$device['id'],(string)$device['endpoint'],$status);
                        $delivered=$delivered||$ok;
                    }
                    // Nacteme odbery znovu: soubezne obnoveny nebo dalsi funkcni telefon brani zbytecnemu predani.
                    if (!$delivered && cb_push_load_devices($user)===[]) $reasonForAdmin='Odběry push příjemce již nejsou platné; služba je odmítla jako zaniklé (HTTP 404/410).';
                }
                if ($delivered) {
                    $stmt=$db->prepare('UPDATE admin_info_user SET odeslano=NOW() WHERE id_admin_info=? AND id_user=?');
                    $stmt->bind_param('ii',$id,$user); $stmt->execute(); $stmt->close(); $sent++;
                } elseif ($forward && $user!==1 && $reasonForAdmin!=='') {
                    // Kopie ma svuj trvaly klic a nikdy se sama nepredava dalsimu adresatovi.
                    $copy=cb_info_predat_adminovi($id,$user,$title,$content,$reasonForAdmin);
                    if ((int)$copy['ok']===1) $forwarded++; else $failed++;
                } else { $failed++; }
            } catch (Throwable $e) {
                // Chyba jednoho prijemce nezastavi ostatni; nevydavame ji za odeslanou zpravu.
                $failed++; cb_info_audit($db,$user,null,$type,'fail',null,$e->getMessage());
            }
        }
        return ['ok'=>$failed===0?1:0,'id_admin_info'=>$id,'opakovani'=>false,'odeslano'=>$sent,'predano_adminovi'=>$forwarded,'selhalo'=>$failed];
    } finally {
        if ($lock!=='') { $stmt=$db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->bind_param('s',$lock); $stmt->execute(); $stmt->close(); }
    }
}
