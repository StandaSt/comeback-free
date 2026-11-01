<?php
/* Co-Link: odesle oznameni jednomu overenemu zarizeni a zpracuje vysledek transportu. */
declare(strict_types=1);
require_once __DIR__ . '/co_push_obsah.php';
require_once __DIR__ . '/co_push_opakovat.php';
require_once __DIR__ . '/../co_db/co_push_odber_zrusit.php';
require_once __DIR__ . '/../co_db/co_push_pokus_dokoncit.php';

/** Volajici uz overil prijemce; chyba transportu zachova puvodni pravidla opakovani. */
function co_push_odeslat(string $baseUrl, array $r): void
{
    $id = (int)$r['id'];
    try {
        $sender=new Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>CB_VAPID_SUBJECT,'publicKey'=>CB_VAPID_PUBLIC,'privateKey'=>CB_VAPID_PRIVATE]],['TTL'=>3600],5,['allow_redirects'=>false,'connect_timeout'=>3]);
        $subscription=Minishlink\WebPush\Subscription::create(['endpoint'=>$r['endpoint'],'publicKey'=>$r['klic_public'],'authToken'=>$r['klic_auth'],'contentEncoding'=>'aes128gcm']);
        // Schvaleny nahled obsahu posilame az po vsech kontrolach pristupu prijemce.
        $report=$sender->sendOneNotification($subscription,json_encode(co_push_obsah($baseUrl,$r),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        if($report->isSubscriptionExpired()) co_push_odber_zrusit((int)$r['zarizeni'], (string)$r['endpoint']);
        if($report->isSuccess() || $report->isSubscriptionExpired()) co_push_pokus_dokoncit($id);
        else { error_log('Co-Link push: fronta '.$id.' HTTP '.($report->getResponse()?->getStatusCode()??0)); co_push_opakovat($id,(int)$r['pokusy']); }
    } catch(Throwable $e) { error_log('Co-Link push: '.get_class($e)); co_push_opakovat($id,(int)$r['pokusy']); }
}
