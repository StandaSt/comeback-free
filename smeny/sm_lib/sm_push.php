<?php
declare(strict_types=1);
/* Účel: Doručí uložená oznámení směn přes společný Web Push. Selhání nezruší zveřejněný rozpis. */

/** Odešle jednu zprávu na zařízení zaměstnance a vrátí stav doručení; nic automaticky neopakuje. */
function cb_smeny_push_odeslat(array $notice): string
{
    global $PROSTREDI;
    $user=(int)($notice['id_user']??0);
    if($user<=0)return 'bez_zarizeni';
    $devices=cb_push_load_devices($user);
    if($devices===[])return 'bez_zarizeni';
    if(!cb_push_has_vendor())throw new RuntimeException('Chybí knihovna Web Push.');
    require_once cb_push_vendor_autoload();
    $push=new Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>(string)CB_VAPID_SUBJECT,'publicKey'=>(string)CB_VAPID_PUBLIC,'privateKey'=>(string)CB_VAPID_PRIVATE]],[],10);
    $content=json_decode($notice['obsah'],true,512,JSON_THROW_ON_ERROR);
    // Absolutní odkaz vede na konkrétní oznámení; modál ověří přihlášeného příjemce.
    $root=$PROSTREDI==='SERVER'?cb_login_url():'http://localhost/comeback/';
    $payload=json_encode(['type'=>'SMENY','title'=>'Comeback – směny','body'=>($notice['typ']==='tyden'?'Směny na další týden byly zveřejněny.':cb_smeny_oznameni_text($notice['typ'],$content)),
        'tag'=>'cb-smeny-'.(int)$notice['id_smeny_oznameni'],'url'=>rtrim($root,'/').'/index.php?m=smeny&page=me_smeny&oznameni='.(int)$notice['id_smeny_oznameni']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $failed=false;$delivered=false;
    foreach($devices as $device) {
        try {
            $sub=Minishlink\WebPush\Subscription::create(['endpoint'=>$device['endpoint'],'publicKey'=>$device['klic_public'],'authToken'=>$device['klic_auth']]);
            $report=$push->sendOneNotification($sub,$payload,['TTL'=>86400]);
            $ok=$report->isSuccess();$code=$report->getResponse()?->getStatusCode();
            cb_push_audit_try_insert($user,(int)$device['id'],'smeny',$ok?'ok':'fail',$code,$ok?null:$report->getReason());
            $delivered=$delivered||$ok;
            // Zaniklé odběry nemají zařízení; ostatní chyby zachováme jako nedoručené.
            if(!$ok && !in_array($code,[404,410],true))$failed=true;
        } catch(Throwable $e) {
            $failed=true;cb_push_audit_try_insert($user,(int)$device['id'],'smeny','fail',null,$e->getMessage());
        }
    }
    return $failed?'ceka':($delivered?'odeslano':'bez_zarizeni');
}
