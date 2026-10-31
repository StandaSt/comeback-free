<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../common/lib/osoba_jmeno.php';
/* Co-Link: okamzity push s casem, odesilatelem a kratkym uryvkem; vlastni odbery a chyby jsou oddelene od 2FA. */
require_once __DIR__.'/../common/lib/format.php';

/** Pripravi kratky prosty text bez metadat editoru; cas vzdy patri ulozene zprave. */
function co_push_obsah(string $baseUrl, array $message): array
{
    $name=trim(preg_replace('/[\s\p{Cc}]+/u',' ',(string)($message['jmeno']??''))??'');
    // Vejdou se vsechny HR casti jmena i titul; puvodni limit 100 by mohl titul odriznout.
    $name=mb_substr($name!==''?$name:'Uživatel',0,260,'UTF-8');
    // Za oddelovacem jsou styly editoru, ktere do systemove notifikace nepatri.
    $text=explode("\n\x1eCO1:",(string)$message['text'],2)[0];
    $text=trim(preg_replace('/[\s\p{Cc}]+/u',' ',$text)??'');
    if(mb_strlen($text,'UTF-8')>120) $text=mb_substr($text,0,119,'UTF-8').'…';
    if($text==='') $text='Poslal(a) přílohu.';
    $time=new DateTimeImmutable((string)$message['vytvoreno'],new DateTimeZone('Europe/Prague'));
    return ['konverzace'=>(int)$message['konverzace'],'url'=>$baseUrl.'?konverzace='.$message['konverzace'],
        'body'=>cb_format('t',$message['vytvoreno']).' · '.$name."\n".$text,'timestamp'=>$time->getTimestamp()*1000];
}

/** Lokalni testovani nikdy neodesila skutecne notifikace; transport se nacita az na serveru. */
function co_push_priprava(): bool
{
    global $PROSTREDI;
    if ($PROSTREDI !== 'SERVER') return false;
    if (!defined('CB_VAPID_PRIVATE') || !defined('CB_VAPID_PUBLIC') || !defined('CB_VAPID_SUBJECT')) {
        throw new RuntimeException('Co-Link Web Push nema nastaveni VAPID.');
    }
    require_once dirname(__DIR__,2).'/vendor/autoload.php';
    return true;
}

/** Volani po commitu neposkodi ulozenou zpravu ani uspesnou odpoved API pri chybe transportu. */
function co_push_ihned(int $message): void
{
    try {
        if (!co_push_priprava()) return;
        co_push_zprava('https://comebacks.cz/co_link/', $message);
    } catch (Throwable $error) {
        error_log('Co-Link push zprava '.$message.': '.get_class($error).' / '.$error->getCode());
    }
}

/** Puvodni CLI vstup zustava k rucnimu zpracovani cekajicich pokusu; web jej nepotrebuje. */
function co_push_davka(string $baseUrl): int
{
    if (!co_push_priprava()) return 0;
    $count=0;
    foreach(co_rows('SELECT DISTINCT zprava FROM co_push_fronta WHERE hotovo IS NULL AND dalsi_pokus<=NOW() ORDER BY zprava LIMIT 100') as $row) {
        $count+=co_push_zprava($baseUrl,(int)$row['zprava']);
    }
    return $count;
}

/** Overi pristup znovu tesne pred upozornenim; odvolany pristup ani ztiseni neposila obsah ven. */
function co_push_zprava(string $baseUrl, int $message): int
{
    if (!co_push_priprava()) return 0;
    $lockName='co-link-push-'.$message;
    $lock=co_rows('SELECT GET_LOCK(?,0) zamceno',[$lockName])[0]['zamceno']??0;
    if((int)$lock!==1) return 0;
    try {
        $rows=co_rows('SELECT f.id,f.pokusy,f.zprava,d.id zarizeni,d.uzivatel,d.endpoint,d.klic_public,d.klic_auth,d.pc_hash,
            d.zruseno,d.platnost_do,z.konverzace,z.smazano,z.text,z.vytvoreno,
            COALESCE(' . cb_osoba_jmeno_sql('z.autor') . ',"Uživatel") jmeno
            FROM co_push_fronta f JOIN co_zarizeni d ON d.id=f.zarizeni
            JOIN co_zprava z ON z.id=f.zprava WHERE f.hotovo IS NULL AND f.dalsi_pokus<=NOW() AND f.zprava=? ORDER BY f.id',[$message]);
        foreach($rows as $r) {
            $user=(int)$r['uzivatel'];$id=(int)$r['id'];
            // Prava overujeme znovu i po ulozeni zpravy; odebrany pristup ihned plati.
            co_prava($user,true);
            $skip=$r['zruseno']!==null || ($r['pc_hash']!==null && strtotime($r['platnost_do'])<=time()) || $r['smazano']!==null || !$r['endpoint']
                || !co_osoba($user) || !co_pravo($user,700)
                || ($r['pc_hash']!==null && co_rows('SELECT 1 FROM user_pc_session WHERE HEX(pc_hash)=UPPER(?) AND id_user=? AND zruseno IS NULL',[$r['pc_hash'],$user])===[])
                || co_rows('SELECT k.id FROM co_konverzace k WHERE k.id=? AND '.co_pristup_sql($user),[(int)$r['konverzace']])===[]
                || co_rows('SELECT 1 FROM co_volba WHERE uzivatel=? AND konverzace=? AND ztiseno=1',[$user,(int)$r['konverzace']])!==[]
                || co_rows('SELECT 1 FROM co_precteni WHERE zprava=? AND uzivatel=?',[(int)$r['zprava'],$user])!==[];
            if($skip) { co_sql('UPDATE co_push_fronta SET hotovo=NOW() WHERE id=?',[$id])->close(); continue; }
            try {
                $sender=new Minishlink\WebPush\WebPush(['VAPID'=>['subject'=>CB_VAPID_SUBJECT,'publicKey'=>CB_VAPID_PUBLIC,'privateKey'=>CB_VAPID_PRIVATE]],['TTL'=>3600],5,['allow_redirects'=>false,'connect_timeout'=>3]);
                $subscription=Minishlink\WebPush\Subscription::create(['endpoint'=>$r['endpoint'],'publicKey'=>$r['klic_public'],'authToken'=>$r['klic_auth'],'contentEncoding'=>'aes128gcm']);
                // Schvaleny nahled obsahu posilame az po vsech kontrolach pristupu prijemce.
                $report=$sender->sendOneNotification($subscription,json_encode(co_push_obsah($baseUrl,$r),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
                if($report->isSubscriptionExpired()) co_sql('UPDATE co_zarizeni SET endpoint=NULL,klic_public=NULL,klic_auth=NULL WHERE id=? AND endpoint=?',[(int)$r['zarizeni'],$r['endpoint']])->close();
                if($report->isSuccess() || $report->isSubscriptionExpired()) co_sql('UPDATE co_push_fronta SET hotovo=NOW(),pokusy=pokusy+1 WHERE id=?',[$id])->close();
                else { error_log('Co-Link push: fronta '.$id.' HTTP '.($report->getResponse()?->getStatusCode()??0)); co_push_opakovat($id,(int)$r['pokusy']); }
            } catch(Throwable $e) { error_log('Co-Link push: '.get_class($e)); co_push_opakovat($id,(int)$r['pokusy']); }
        }
        return count($rows);
    } finally { co_rows('SELECT RELEASE_LOCK(?)',[$lockName]); }
}

/** Neuspesny push ma omezeny pocet opakovani; ulozena zprava zustava vzdy v historii. */
function co_push_opakovat(int $id, int $attempts): void
{
    if($attempts>=5) co_sql('UPDATE co_push_fronta SET hotovo=NOW(),pokusy=pokusy+1 WHERE id=?',[$id])->close();
    else co_sql('UPDATE co_push_fronta SET pokusy=pokusy+1,dalsi_pokus=DATE_ADD(NOW(),INTERVAL ? SECOND) WHERE id=?',[min(1800,30*(2**$attempts)),$id])->close();
}

