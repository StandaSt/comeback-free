<?php
/* E-mail: CLI kontrola posty na pozadi; sdilena pripojeni bere z centralni spravy v DB, osobni rucni rezim vynechava. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/posta.php';
require_once __DIR__.'/../common/config/secrets.php';

/** CRON ma samostatne spojeni bez automatickeho auditu IS a tajne hodnoty nikdy nevypisuje. */
function em_worker_db(): mysqli
{
    global $SECRETS;
    $environment=in_array('--server',$_SERVER['argv']??[],true)?'server':'local';
    $cfg=$SECRETS['db'][$environment]??[];
    if ($environment==='local' && (($cfg['host']??'')!=='127.0.0.1' || (int)($cfg['port']??0)!==3308)) throw new EmailChyba('Neplatne lokalni spojeni.');
    mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
    $db=new mysqli($cfg['host'],$cfg['user'],$cfg['pass'],$cfg['name'],(int)$cfg['port']); $db->set_charset('utf8mb4');
    return $db;
}

try {
    $cfg=em_config(); $db=em_worker_db();
    if (!em_schema($db)) throw new EmailChyba('Nejprve nainstalujte modul.');
    if ((int)$db->query("SELECT GET_LOCK('email:worker',0)")->fetch_row()[0]!==1) exit;
    $accounts=$db->query('SELECT * FROM email_ucet WHERE aktivni=1 AND rezim="is" AND heslo_sifra IS NOT NULL ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    $failed=0;
    foreach ($accounts as $a) {
        $client=null;
        try {
            if ($a['typ']==='osobni') {
                $p=em_osoba($db,(int)$a['id_user']);
                if (!$p['manager'] || (int)$p['id_firma']!==(int)$a['id_firma']) continue;
            } elseif ($a['typ']==='pobocka') {
                // Stejna centralne pripojena schranka slouzi pobocce i souhrnu; stara konfiguracni mapa uz ji neomezuje.
                $s=$db->prepare('SELECT 1 FROM pobocka WHERE id_pob=? AND id_firma=? AND aktivni=1');
                $s->bind_param('ii',$a['id_pob'],$a['id_firma']); $s->execute();
                if (!$s->get_result()->fetch_row()) continue;
            }
            $pw=em_rozsifruj($a['heslo_sifra'],'ucet:'.$a['email']);
            $client=em_imap($a,$pw); $unread=(int)(em_slozka($client,'INBOX')->status()['unseen']??0);
            $s=$db->prepare('INSERT INTO email_cache(ucet,neprecteno,aktualizovano) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE neprecteno=VALUES(neprecteno),aktualizovano=NOW()');
            $s->bind_param('ii',$a['id'],$unread); $s->execute();
            // Externi klient oznaci odpoved pomoci IMAP Answered; nevyvozujeme konkretniho autora za sdilenou adresou.
            if ($a['typ']!=='osobni') {
                $s=$db->prepare('SELECT * FROM email_stav WHERE ucet=? AND stav="resi"'); $s->bind_param('i',$a['id']); $s->execute();
                foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $w) {
                    $m=em_zprava($client,['folder'=>$w['slozka'],'validity'=>$w['uidvalidity'],'uid'=>$w['uid']],false);
                    if ($m->flags->has('answered') && !(int)$w['odpovezeno_predem']) {
                        $q=$db->prepare('UPDATE email_stav SET stav="odpovezeno",resitel=NULL,zamek_do=NULL,verze=verze+1,zmeneno=NOW() WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=? AND verze=?');
                        $q->bind_param('isiii',$a['id'],$w['slozka_hash'],$w['uidvalidity'],$w['uid'],$w['verze']); $q->execute();
                    }
                }
            }
        } catch (Throwable) { $failed++; fwrite(STDERR,'Schranku ID '.(int)$a['id']." se nepodarilo zkontrolovat.\n"); }
        finally { if ($client) { try { $client->disconnect(); } catch (Throwable) {} } }
    }
    $db->query("SELECT RELEASE_LOCK('email:worker')");
    echo 'Kontrola dokoncena, chyb: '.$failed.".\n"; exit($failed?1:0);
} catch (Throwable $e) {
    fwrite(STDERR,($e instanceof EmailChyba?$e->getMessage():'Kontrolu posty se nepodarilo spustit.')."\n"); exit(1);
}
