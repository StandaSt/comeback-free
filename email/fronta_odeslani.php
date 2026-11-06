<?php
/* E-mail: docasny sifrovany MIME a stav pokusu v chranenych souborech, bez databazove kopie zpravy. */
declare(strict_types=1);
require_once __DIR__.'/posta.php';

/** Provozni soubory patri do zakazaneho common/tmp; dalsi pravidlo chrani i vlastni podadresar. */
function em_fronta_adresar(int $account=0): string
{
    $base=__DIR__.'/../common/tmp/email_odeslani';
    if (!is_dir($base) && !mkdir($base,0700,true) && !is_dir($base)) throw new EmailChyba('Dočasné úložiště odesílání není dostupné.');
    if (!is_file($base.'/.htaccess') && file_put_contents($base.'/.htaccess',"# Soukrome docasne kopie posty.\nRequire all denied\n",LOCK_EX)===false) throw new EmailChyba('Dočasné úložiště se nepodařilo zabezpečit.');
    if ($account===0) return $base;
    if ($account<1) throw new EmailChyba('Neplatná schránka odesílání.');
    $dir=$base.'/ucet-'.$account;
    if (!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir)) throw new EmailChyba('Dočasné úložiště schránky není dostupné.');
    return $dir;
}

/** Jeden souborovy zamek na schranku chrani stav, soubezne dotazy i uklid bez DB zaznamu. */
function em_fronta_zamek(array $a)
{
    $file=em_fronta_adresar().'/ucet-'.(int)$a['id'].'.lock';
    if (is_link($file)) throw new EmailChyba('Dočasné úložiště není bezpečné.');
    $lock=fopen($file,'c');
    if ($lock===false) throw new EmailChyba('Stav odesílání není dostupný.');
    chmod($file,0600); $blocked=0;
    if (!flock($lock,LOCK_EX|LOCK_NB,$blocked)) {fclose($lock);if($blocked)return null;throw new EmailChyba('Stav odesílání se nepodařilo uzamknout.');}
    return $lock;
}

/** Nahodny token je navic vazany na schranku, osobu i konkretni prihlaseni. */
function em_fronta_cesta(array $a, string $token): string
{
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$token)) throw new EmailChyba('Neplatný identifikátor odesílání.');
    return em_fronta_adresar((int)$a['id']).'/'.hash('sha256',em_identita().':'.$a['id'].':'.$token);
}

/** Atomicka vymena nedovoli precist pulku stavu ani po preruseni PHP. */
function em_fronta_zapis_soubor(string $file, string $content): void
{
    $temp=tempnam(dirname($file),'zapis_');
    if ($temp===false) throw new EmailChyba('Dočasnou kopii se nepodařilo připravit.');
    try {
        chmod($temp,0600);
        if (file_put_contents($temp,$content,LOCK_EX)!==strlen($content) || !rename($temp,$file)) throw new EmailChyba('Dočasnou kopii se nepodařilo bezpečně uložit.');
        chmod($file,0600);
    } finally {if(is_file($temp))unlink($temp);}
}

/** Metadata neobsahuji telo; sifra overuje jejich neporusenost a vazbu na aktualni login. */
function em_fronta_zapis(array $a, string $token, array $row): void
{
    em_fronta_zapis_soubor(em_fronta_cesta($a,$token).'.state',em_zasifruj(json_encode($row,JSON_THROW_ON_ERROR),'stav:'.em_identita().':'.$a['id'].':'.$token));
}

/** Stav cte jen stejna opravnena osoba; prava k uctu predem overuje API. */
function em_fronta_cti(array $a, string $token): ?array
{
    $file=em_fronta_cesta($a,$token).'.state';
    if (!is_file($file)) return null;
    if (is_link($file) || filesize($file)>1048576) throw new EmailChyba('Stav odesílání je poškozený.');
    $row=json_decode(em_rozsifruj((string)file_get_contents($file),'stav:'.em_identita().':'.$a['id'].':'.$token),true,32,JSON_THROW_ON_ERROR);
    if (!is_array($row) || ($row['owner']??'')!==em_identita() || (int)($row['account']??0)!==(int)$a['id']) throw new EmailChyba('K tomuto pokusu o odeslání nemáte přístup.');
    return $row;
}

/** Velka zprava opusti pamet jen do sifrovaneho souboru, nikdy do SQL parametru. */
function em_fronta_uloz_mime(array $a, string $token, string $mime): void
{
    em_fronta_zapis_soubor(em_fronta_cesta($a,$token).'.mime',em_zasifruj($mime,'mime:'.em_identita().':'.$a['id'].':'.$token));
}

/** Puvodni nemenny MIME lze pouzit pouze k doplneni Odeslanych. */
function em_fronta_mime(array $a, string $token, array $row): string
{
    $file=em_fronta_cesta($a,$token).'.mime';
    if ((int)$row['created']<time()-86400 || !is_file($file) || is_link($file)) throw new EmailChyba('Dočasná kopie zprávy už není dostupná.');
    return em_rozsifruj((string)file_get_contents($file),'mime:'.em_identita().':'.$a['id'].':'.$token);
}

/** Po dokonceni kopie ihned zmizi; maly stav zustane jako ochrana proti opakovanemu SMTP. */
function em_fronta_smaz_mime(array $a, string $token): void
{
    $file=em_fronta_cesta($a,$token).'.mime';if(is_file($file))unlink($file);
}

/** Pod zamkem uklizime jen vlastni format souboru: MIME po24h, stav po7dnech a nedopsane zapisy po24h. */
function em_fronta_uklid(array $a): void
{
    $now=time();
    foreach(new DirectoryIterator(em_fronta_adresar((int)$a['id'])) as $file){
        if(!$file->isFile() || $file->isLink())continue;
        $name=$file->getFilename();$ttl=preg_match('/^[a-f0-9]{64}\.state$/D',$name)?604800:(preg_match('/^[a-f0-9]{64}\.mime$/D',$name) || str_starts_with($name,'zapis_')?86400:0);
        if($ttl && $file->getMTime()<$now-$ttl)unlink($file->getPathname());
    }
}

/** Verejny vysledek neobsahuje obsah zpravy ani vnitrni odkazy na soubory. */
function em_fronta_vysledek(array $row): array
{
    $state=(string)$row['state'];
    return ['send_state'=>$state,'sent'=>in_array($state,['sent','complete','expired'],true),'stored'=>$state==='complete','work_saved'=>$row['work_saved']??true];
}

/** Pred SMTP trvale zapiseme nejisty bod; zadna vyjimka potom nesmi povolit automaticke opakovani. */
function em_fronta_predat(array $a, string $token, array $row, callable $smtp): array
{
    $row['state']='sending';em_fronta_zapis($a,$token,$row);
    try {if(!$smtp())throw new RuntimeException('SMTP nepotvrdilo dokonceni.');}
    catch(Throwable){$row['state']='uncertain';em_fronta_zapis($a,$token,$row);return em_fronta_vysledek($row);}
    $row['state']='sent';$row['sent_at']=time();em_fronta_zapis($a,$token,$row);
    return em_fronta_vysledek($row);
}

/** Overeni po ztrate HTTP odpovedi zrusi jen pokus, ktery jeste nemohl vstoupit do SMTP DATA. */
function em_fronta_stav(array $a, string $token, bool $cancelSafe): array
{
    $lock=em_fronta_zamek($a);if($lock===null)return ['send_state'=>'busy','sent'=>false,'stored'=>false];
    try {
        em_fronta_uklid($a);$row=em_fronta_cti($a,$token);
        if($row===null || in_array($row['state'],['preparing','prepared'],true)){
            if(!$cancelSafe)return ['send_state'=>'missing','sent'=>false,'stored'=>false];
            // Tombstone zastavi i puvodni pozadavek, ktery teprve dokoncuje upload nebo pripojeni k IMAP.
            $row=['owner'=>em_identita(),'account'=>(int)$a['id'],'created'=>time(),'state'=>'cancelled'];
            em_fronta_zapis($a,$token,$row);em_fronta_smaz_mime($a,$token);
        }elseif($row['state']==='sending'){$row['state']='uncertain';em_fronta_zapis($a,$token,$row);}
        return em_fronta_vysledek($row);
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
