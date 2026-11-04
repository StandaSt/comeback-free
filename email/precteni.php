<?php
/* E-mail: osobni precteni spolecne posty, vychozi snimek IMAP a pocty podle uzivatele. */
declare(strict_types=1);

/** Bez migrace se nesmi tise vratit spolecne precteni a ovlivnit ostatni uzivatele. */
function em_precteni_schema(mysqli $db): bool
{
    static $ready=null;
    if ($ready!==null) return $ready;
    foreach (['email_precteni_slozka','email_precteni','email_precteni_cache'] as $table) {
        if ($db->query("SHOW TABLES LIKE '".str_replace('_','\\_',$table)."'")->num_rows!==1) return $ready=false;
    }
    return $ready=true;
}

/** Prvni synchronizace zachyti vychozi Seen; dalsi cteni ve webmailu osobni stav IS nemeni. */
function em_precteni_zaklad(mysqli $db, array $a, Webklex\PHPIMAP\Folder $folder, int $validity): array
{
    if (!em_precteni_schema($db)) throw new EmailChyba('Nejprve spusťte SQL pro osobní evidenci přečtení e-mailů.');
    $hash=hash('sha256',$folder->path);
    $s=$db->prepare('SELECT prectene FROM email_precteni_slozka WHERE ucet=? AND slozka_hash=? AND uidvalidity=?');
    $s->bind_param('isi',$a['id'],$hash,$validity); $s->execute(); $row=$s->get_result()->fetch_assoc();
    if (!$row) {
        $seen=array_map('intval',$folder->query()->leaveUnread()->fetchBody(false)->all()->whereUndeleted()->whereSeen()->search()->all());
        $json=json_encode(array_values($seen),JSON_THROW_ON_ERROR); $path=$folder->path;
        $q=$db->prepare('INSERT IGNORE INTO email_precteni_slozka(ucet,slozka_hash,slozka,uidvalidity,prectene) VALUES(?,?,?,?,?)');
        $q->bind_param('issis',$a['id'],$hash,$path,$validity,$json); $q->execute();
        // Soubězne pozadavky pouziji stejny vitezny snimek.
        $s->execute(); $row=$s->get_result()->fetch_assoc();
    }
    return array_fill_keys(json_decode($row['prectene'],true,512,JSON_THROW_ON_ERROR),true);
}

/** Vychozi stav prekryji pouze volby prihlaseneho uzivatele. */
function em_precteni_mapa(mysqli $db, array $a, Webklex\PHPIMAP\Folder $folder, int $validity): array
{
    $map=em_precteni_zaklad($db,$a,$folder,$validity); $hash=hash('sha256',$folder->path); $user=(int)$_SESSION['cb_user']['id_user'];
    $s=$db->prepare('SELECT uid,precteno FROM email_precteni WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND id_user=?');
    $s->bind_param('isii',$a['id'],$hash,$validity,$user); $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $map[(int)$row['uid']]=(bool)$row['precteno'];
    return $map;
}

/** Navrat na neprectene zachova posledni cas precteni, ale zmeni stav jen danemu cloveku. */
function em_precteni_uloz(mysqli $db, array $a, array $ref, bool $seen): void
{
    [$id,$hash,$v,$uid]=em_ref($a,$ref); $user=(int)$_SESSION['cb_user']['id_user']; $value=(int)$seen; $path=$ref['folder'];
    $s=$db->prepare('INSERT INTO email_precteni(ucet,slozka_hash,slozka,uidvalidity,uid,id_user,precteno,precteno_at) VALUES(?,?,?,?,?,?,?,IF(?=1,NOW(),NULL)) ON DUPLICATE KEY UPDATE precteno=VALUES(precteno),precteno_at=IF(VALUES(precteno)=1,NOW(),precteno_at)');
    $s->bind_param('issiiiii',$id,$hash,$path,$v,$uid,$user,$value,$value); $s->execute();
}

/** Pocty pracuji pouze s UID; osobni schranka dal pouziva mailserver. */
function em_precteni_pocet(mysqli $db, array $a, Webklex\PHPIMAP\Folder $folder, ?int $validity=null): int
{
    if ($a['typ']==='osobni') return (int)($folder->status()['unseen']??0);
    $validity??=(int)$folder->examine()['uidvalidity'];
    if ($validity<1) throw new EmailChyba('Server neposkytl identitu složky.');
    $map=em_precteni_mapa($db,$a,$folder,$validity);
    $uids=$folder->query()->leaveUnread()->fetchBody(false)->all()->whereUndeleted()->search()->all();
    $count=0; foreach ($uids as $uid) if (empty($map[(int)$uid])) $count++;
    if (strcasecmp($folder->path,'INBOX')===0) {
        $user=(int)$_SESSION['cb_user']['id_user'];
        $s=$db->prepare('INSERT INTO email_precteni_cache(ucet,id_user,neprecteno,aktualizovano) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE neprecteno=VALUES(neprecteno),aktualizovano=NOW()');
        $s->bind_param('iii',$a['id'],$user,$count); $s->execute();
    }
    return $count;
}

/** Chybejici osobni pocet zustane nezjisteny; nesmi se nahradit globalnim IMAP Seen. */
function em_precteni_pocty(mysqli $db, array $accounts): array
{
    $ids=array_map('intval',array_column($accounts,'id')); if (!$ids) return [];
    $out=[]; $ready=em_precteni_schema($db); $user=(int)$_SESSION['cb_user']['id_user'];
    $personal=[]; if ($ready) foreach ($db->query('SELECT ucet,neprecteno FROM email_precteni_cache WHERE id_user='.$user.' AND ucet IN('.implode(',',$ids).')')->fetch_all(MYSQLI_ASSOC) as $r) $personal[(int)$r['ucet']]=(int)$r['neprecteno'];
    $common=[]; foreach ($db->query('SELECT ucet,neprecteno FROM email_cache WHERE ucet IN('.implode(',',$ids).')')->fetch_all(MYSQLI_ASSOC) as $r) $common[(int)$r['ucet']]=(int)$r['neprecteno'];
    foreach ($accounts as $a) $out[(int)$a['id']]=$a['typ']==='osobni' || !$ready?($common[(int)$a['id']]??null):($personal[(int)$a['id']]??null);
    return $out;
}

/** Presun prenese osobni volby vsech ctenaru i vychozi stav na nove UID. */
function em_precteni_presun(mysqli $db, array $a, array $old, array $next, bool $baseline): void
{
    [$id,$hash,$v,$uid]=em_ref($a,$old); $newHash=hash('sha256',$next['folder']); $path=$next['folder']; $nv=(int)$next['validity']; $nu=(int)$next['uid'];
    $db->begin_transaction();
    try {
        $s=$db->prepare('SELECT prectene FROM email_precteni_slozka WHERE ucet=? AND slozka_hash=? AND uidvalidity=? FOR UPDATE');
        $s->bind_param('isi',$id,$newHash,$nv); $s->execute(); $row=$s->get_result()->fetch_assoc();
        if (!$row) throw new EmailChyba('Chybí výchozí stav cílové složky.');
        $values=array_fill_keys(json_decode($row['prectene'],true,512,JSON_THROW_ON_ERROR),true);
        if ($baseline) $values[$nu]=true; else unset($values[$nu]);
        $json=json_encode(array_map('intval',array_keys($values)),JSON_THROW_ON_ERROR);
        $q=$db->prepare('UPDATE email_precteni_slozka SET prectene=? WHERE ucet=? AND slozka_hash=? AND uidvalidity=?'); $q->bind_param('sisi',$json,$id,$newHash,$nv); $q->execute();
        $q=$db->prepare('UPDATE email_precteni SET slozka_hash=?,slozka=?,uidvalidity=?,uid=? WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
        $q->bind_param('ssiiisii',$newHash,$path,$nv,$nu,$id,$hash,$v,$uid); $q->execute();
        $q=$db->prepare('DELETE FROM email_precteni_cache WHERE ucet=?'); $q->bind_param('i',$id); $q->execute(); $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

/** Prejmenovani slozky nebo jejiho predka nesmi ztratit vychozi ani osobni precteni. */
function em_precteni_prejmenovat(mysqli $db, int $account, string $old, string $next, string $delimiter): void
{
    if (!em_precteni_schema($db)) return;
    foreach (['email_precteni_slozka','email_precteni'] as $table) {
        $s=$db->prepare('SELECT DISTINCT slozka,slozka_hash FROM '.$table.' WHERE ucet=?'); $s->bind_param('i',$account); $s->execute();
        foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            if ($row['slozka']!==$old && !str_starts_with($row['slozka'],$old.$delimiter)) continue;
            $path=$next.substr($row['slozka'],strlen($old)); $hash=hash('sha256',$path);
            $q=$db->prepare('UPDATE '.$table.' SET slozka=?,slozka_hash=? WHERE ucet=? AND slozka_hash=?');
            $q->bind_param('ssis',$path,$hash,$account,$row['slozka_hash']); $q->execute();
        }
    }
}

