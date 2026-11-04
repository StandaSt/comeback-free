<?php
/* E-mail: adresar prijemcu oddeleny podle schranek; cte pouze hlavicky a nikdy kontakty HR ani tela zprav. */
declare(strict_types=1);

/** Volitelny adresar nesmi pred spustenim migrace zablokovat stavajici postu. */
function em_kontakty_pripravene(mysqli $db): bool
{
    return $db->query("SHOW TABLES LIKE 'email\\_kontakt'")->num_rows === 1;
}

/** Text pro hledani sjednoti diakritiku i velikost pismen bez zavislosti na locale serveru. */
function em_kontakt_hledany(string $text): string
{
    $text=mb_strtolower($text,'UTF-8');
    return strtr($text,array_combine(
        preg_split('//u','áäčďéěëíĺľňóöôřŕšťúůüýž',-1,PREG_SPLIT_NO_EMPTY),
        str_split('aacdeeeillnooorrstuuuyz')));
}

/** Jedna adresa ma v kazde schrance jeden zaznam; prazdne jmeno neprepise jiz zname jmeno. */
function em_kontakty_uloz(mysqli $db, int $account, array $addresses): void
{
    $s=$db->prepare('INSERT INTO email_kontakt(ucet,email,jmeno,hledani) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE jmeno=IF(VALUES(jmeno)="",jmeno,VALUES(jmeno)),hledani=IF(VALUES(jmeno)="",hledani,VALUES(hledani)),aktualizovano=CURRENT_TIMESTAMP');
    $unique=[];
    foreach($addresses as $address) {
        $email=strtolower(trim((string)($address['email']??'')));
        if(strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) continue;
        $name=mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',(string)($address['name']??''))),0,200);
        if(isset($unique[$email]) && ($unique[$email]!=='' || $name==='')) continue;
        $unique[$email]=$name;
    }
    foreach($unique as $email=>$name) {
        $search=em_kontakt_hledany($name.' '.$email);
        $s->bind_param('isss',$account,$email,$name,$search); $s->execute();
    }
}

/** Obnova je omezena na poslednich 200 hlavicek v Prichozi a Odeslanych, starsi kontakty v adresari zustavaji. */
function em_kontakty_obnov(mysqli $db, array $a, string $password): void
{
    $client=null;
    try {
        $client=em_imap($a,$password); $paths=array_unique(['INBOX',(string)(em_config()['folders']['sent']??'')]);
        foreach($paths as $path) {
            if($path==='') continue;
            // Neexistujici Odeslane nesmi zrusit kontakty uspesne nactene z Prichozi.
            try {
                $messages=em_slozka($client,$path)->query()->leaveUnread()->fetchBody(false)->setFetchOrderDesc()->all()->whereUndeleted()->limit(200,1)->get();
                $addresses=[];
                foreach($messages as $m) {
                    foreach(['from','to','cc','reply_to'] as $field) $addresses=array_merge($addresses,em_adresy($m->$field));
                    // Bcc zname pouze ze sve kopie v Odeslanych; mezi schrankami se nikdy nesdili.
                    if($path!=='INBOX') $addresses=array_merge($addresses,em_adresy($m->bcc));
                }
                em_kontakty_uloz($db,(int)$a['id'],$addresses);
            } catch(Throwable $e) {error_log('EMAIL_CONTACT_SYNC account='.(int)$a['id'].' class='.get_class($e));}
        }
    } finally {if($client) $client->disconnect();}
}

/** Vyhledani pracuje jen s predem autorizovanym uctem a literalnim textem, nikoli SQL sablonou uzivatele. */
function em_kontakty_hledej(mysqli $db, int $account, string $query): array
{
    $query=em_kontakt_hledany(mb_substr(trim($query),0,100));
    if(mb_strlen($query)<2) return [];
    $like='%'.strtr($query,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
    $s=$db->prepare("SELECT email,jmeno name FROM email_kontakt WHERE ucet=? AND hledani LIKE ? ESCAPE '!' ORDER BY aktualizovano DESC,email LIMIT 12");
    $s->bind_param('is',$account,$like); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
