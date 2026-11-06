<?php
declare(strict_types=1);
/* Dokumenty prijima po jednom do chraneneho docasneho prostoru; cely vyber se potvrdi s HR daty a auditem. */

/** Limit jednoho souboru vychazi z beziciho PHP, nikoli z jineho prostredi; cely vyber nema limit souctu MB. */
function hr_dokumenty_limity(): array
{
    $bytes=static function(string $value): int {
        if (!preg_match('/^(\d+)\s*([KMG])?$/i',trim($value),$parts) || (int)$parts[1]===0) return PHP_INT_MAX;
        return (int)$parts[1]*(1024**(['K'=>1,'M'=>2,'G'=>3][strtoupper($parts[2]??'')]??0));
    };
    $post=$bytes((string)ini_get('post_max_size'));
    return ['count'=>10,'file'=>min($bytes((string)ini_get('upload_max_filesize')),max(1,$post-65536))];
}

/** Overi cely vyber pred prvnim zapisem; typ urcuje obsah, nikoli prohlizec nebo samotna pripona. */
function hr_dokumenty_over(array $upload): array
{
    if ($upload === []) return [];
    foreach (['name','tmp_name','error','size'] as $key) {
        if (!isset($upload[$key]) || !is_array($upload[$key])) throw new CbUserVisibleException('Neplatný výběr dokumentů.');
    }
    $limits=hr_dokumenty_limity();
    if (count($upload['name']) > $limits['count']) throw new CbUserVisibleException('Vybrali jste příliš mnoho dokumentů. Limit je '.$limits['count'].'.');
    $files=[];$finfo=new finfo(FILEINFO_MIME_TYPE);
    foreach ($upload['name'] as $i=>$original) {
        $error=$upload['error'][$i]??-1;
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if ($error !== UPLOAD_ERR_OK) throw new CbUserVisibleException('Dokument se nepodařilo nahrát. Zkontrolujte velikost souboru a vyberte jej znovu.');
        $tmp=$upload['tmp_name'][$i]??null;
        if (!is_string($tmp) || !is_uploaded_file($tmp)) throw new CbUserVisibleException('Nahraný dokument není platný.');
        $size=filesize($tmp);
        if ($size === false || $size<1 || $size>$limits['file']) throw new CbUserVisibleException('Dokument překračuje povolenou velikost nebo je prázdný.');
        if (!is_string($original) || !mb_check_encoding($original,'UTF-8')) throw new CbUserVisibleException('Název dokumentu není platný.');
        $name=trim(preg_replace('/[\x00-\x1f\x7f]/u','',basename(str_replace('\\','/',$original))));
        $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));$mime=$finfo->file($tmp);
        $types=['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
        if (!isset($types[$extension]) || $mime!==$types[$extension]) throw new CbUserVisibleException('Přidávejte dokumenty PDF nebo obrázky JPG a PNG.');
        if ($extension==='pdf' && file_get_contents($tmp,false,null,0,5)!=='%PDF-') throw new CbUserVisibleException('PDF dokument není platný.');
        if ($extension!=='pdf' && @getimagesize($tmp)===false) throw new CbUserVisibleException('Obrázek dokumentu není platný.');
        $files[]=['tmp'=>$tmp,'name'=>mb_substr($name,0,200,'UTF-8'),'extension'=>$extension,'mime'=>$mime,'size'=>$size,'sha256'=>hash_file('sha256',$tmp)];
    }
    return $files;
}

/** Temp soubory jsou pod zakazanym common/tmp; metadata patri pouze aktualni prihlasene session. */
function hr_dokumenty_docasny_adresar(): string
{
    $directory=dirname(__DIR__,2).'/common/tmp/hr_dokumenty';
    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Nelze připravit dočasný prostor dokumentů.');
    return $directory;
}

/** Nedokoncene vybery po hodine expirují; uklid se tyka jen nasich pevne pojmenovanych temp souboru. */
function hr_dokumenty_docasne_uklid(): void
{
    $directory=hr_dokumenty_docasny_adresar();$deadline=time()-3600;
    foreach (glob($directory.'/*.tmp')?:[] as $file) {
        if (preg_match('/^[a-f0-9]{64}\.tmp$/D',basename($file)) && filemtime($file)<$deadline) unlink($file);
    }
    foreach ($_SESSION['hr_document_uploads']??[] as $token=>$entry) {
        if (($entry['created']??0)<$deadline || !is_file($entry['file']['tmp']??'')) unset($_SESSION['hr_document_uploads'][$token]);
    }
}

/** Prijme prave jeden soubor, overi pristup a vrati session token; opakovani stejneho prenosu nevytvori druhy soubor. */
function hr_dokumenty_priprav(mysqli $db, int $idPerson, int $idUser, array $upload, string $key, string $purpose, int $type): string
{
    if (!cb_pravo_ma(301) || !cb_pravo_ma(307)) throw new CbUserVisibleException('Nemáte právo přidávat dokumenty zaměstnance.');
    hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
    if (!preg_match('/^[a-f0-9]{32}$/D',$key) || !in_array($purpose,['hr_pracovni_pomer_ukoncit','hr_zamestnanec_dokumenty_pridat'],true)) throw new CbUserVisibleException('Neplatný přenos dokumentu.');
    $files=hr_dokumenty_over($upload);
    if (count($files)!==1) throw new CbUserVisibleException('Dokumenty se přenášejí po jednom.');
    hr_dokumenty_docasne_uklid();
    $_SESSION['hr_document_nonce']??=bin2hex(random_bytes(32));
    $token=hash_hmac('sha256',$idUser.':'.$idPerson.':'.$purpose.':'.$type.':'.$key,$_SESSION['hr_document_nonce']);
    $file=$files[0];$old=$_SESSION['hr_document_uploads'][$token]??null;
    if ($old) {
        if ($old['file']['name']!==$file['name'] || !hash_equals($old['file']['sha256'],$file['sha256'])) throw new CbUserVisibleException('Výběr dokumentu se změnil. Vyberte soubory znovu.');
        return $token;
    }
    if (count($_SESSION['hr_document_uploads']??[])>=100) throw new CbUserVisibleException('Máte příliš mnoho rozpracovaných dokumentů. Dokončete jejich uložení nebo vyčkejte na vypršení přenosu.');
    $path=hr_dokumenty_docasny_adresar().'/'.$token.'.tmp';
    if (!move_uploaded_file($file['tmp'],$path)) throw new RuntimeException('Přenos dokumentu se nepodařilo dokončit.');
    if (!chmod($path,0600)) {unlink($path);throw new RuntimeException('Nelze zabezpečit přenesený dokument.');}
    $file['tmp']=$path;$file['token']=$token;
    $_SESSION['hr_document_uploads'][$token]=['created'=>time(),'person'=>$idPerson,'user'=>$idUser,'purpose'=>$purpose,'type'=>$type,'file'=>$file];
    return $token;
}

/** Docasny prenos nic nezapisuje do HR DB; pouziva autentizaci a CSRF stavajiciho dispatcheru. */
function hr_post_dokument_pripravit(mysqli $db, int $idUser): never
{
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    try {
        $token=hr_dokumenty_priprav($db,(int)($_POST['id_person']??0),$idUser,$_FILES['dokumenty']??[],
            (string)($_POST['upload_key']??''),(string)($_POST['document_purpose']??''),(int)($_POST['id_dokument_typ']??0));
        echo json_encode(['success'=>true,'token'=>$token],JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        http_response_code(422);echo json_encode(['success'=>false,'message'=>cb_hr_chyba_text($e,'Přenos dokumentu')],JSON_THROW_ON_ERROR);
    }
    exit;
}

/** Finalni formular prijima jen tokeny vlastnich prenosu pro stejnou osobu, typ a ucel; nikdy cestu od klienta. */
function hr_dokumenty_vyber(int $idPerson, int $idUser, string $purpose, int $type): array
{
    $tokens=$_POST['dokument_tokeny']??[];
    if (!is_array($tokens) || count($tokens)>hr_dokumenty_limity()['count']) throw new CbUserVisibleException('Neplatný výběr dokumentů.');
    $direct=hr_dokumenty_over($_FILES['dokumenty']??[]);
    if (!$tokens) {
        if (count($direct)>1) throw new CbUserVisibleException('Obnovte stránku. Dokumenty se nyní přenášejí jednotlivě.');
        return $direct;
    }
    if ($direct || count(array_unique($tokens,SORT_REGULAR))!==count($tokens)) throw new CbUserVisibleException('Neplatný výběr dokumentů.');
    $files=[];
    foreach ($tokens as $token) {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) throw new CbUserVisibleException('Neplatný přenos dokumentu.');
        $entry=$_SESSION['hr_document_uploads'][$token]??null;
        if (!$entry || $entry['created']<time()-3600 || $entry['user']!==$idUser || $entry['person']!==$idPerson || $entry['purpose']!==$purpose || $entry['type']!==$type) throw new CbUserVisibleException('Přenos dokumentu vypršel nebo už byl uložen. Obnovte stránku a zkontrolujte přehled dokumentů.');
        $file=$entry['file'];$expected=hr_dokumenty_docasny_adresar().'/'.$token.'.tmp';
        if ($file['tmp']!==$expected || !is_file($expected) || !hash_equals($file['sha256'],(string)hash_file('sha256',$expected))) throw new CbUserVisibleException('Kontrola přeneseného dokumentu selhala. Vyberte jej znovu.');
        $files[]=$file;
    }
    return $files;
}

/** Az po commitu auditu zaniknou docasne kopie; pri chybe zustavaji pro bezpecne opakovani bez noveho uploadu. */
function hr_dokumenty_potvrd(array $files): void
{
    foreach ($files as $file) if (isset($file['token'])) {
        if (is_file($file['tmp']) && !unlink($file['tmp'])) error_log('HR: uklid potvrzeneho docasneho dokumentu selhal.');
        unset($_SESSION['hr_document_uploads'][$file['token']]);
    }
}

/** Odstrani pouze konkretni nove soubory tohoto pozadavku pri selhani DB nebo auditu. */
function hr_dokumenty_vrat_soubory(array $paths): void
{
    foreach ($paths as $path) if (is_file($path) && !unlink($path)) error_log('HR: nelze uklidit nepotvrzeny dokument.');
}

/** Pripoji dokumenty k transakci volajiciho; nikdy nepotvrzuje jen cast pracovniho pomeru. */
function hr_dokumenty_uloz(mysqli $db, int $idPerson, int $idUser, array $files, int $type, array &$paths): void
{
    if (!$files) return;
    if (!cb_pravo_ma(301) || !cb_pravo_ma(307)) throw new CbUserVisibleException('Nemáte právo přidávat dokumenty zaměstnance.');
    hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
    $s=$db->prepare('SELECT p.id_firma,t.id_dokument_typ FROM hr_person p JOIN hr_cis_dokument_typ t ON t.id_dokument_typ=? AND t.aktivni=1 JOIN hr_cis_dokument_stav st ON st.id_dokument_stav=1 AND st.aktivni=1 WHERE p.id_person=?');
    $s->bind_param('ii',$type,$idPerson);$s->execute();$person=$s->get_result()->fetch_assoc();$s->close();
    if (!$person) throw new CbUserVisibleException('Typ dokumentu není dostupný.');
    $root=dirname(__DIR__,3).'/data/hr_dokumenty';
    $relative='../data/hr_dokumenty/nahrane/'.(int)$person['id_firma'].'/'.$idPerson;
    $directory=$root.'/nahrane/'.(int)$person['id_firma'].'/'.$idPerson;
    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Nelze vytvořit úložiště dokumentů.');
    $resolved=realpath($directory);$rootResolved=realpath($root);
    if (!$resolved || !$rootResolved || !str_starts_with($resolved,$rootResolved.DIRECTORY_SEPARATOR)) throw new RuntimeException('Neplatná cesta úložiště dokumentů.');
    foreach ($files as $file) {
        $stored=bin2hex(random_bytes(20)).'.'.$file['extension'];$path=$resolved.DIRECTORY_SEPARATOR.$stored;
        // Copy muze pri plnem disku zanechat i neuplny soubor; uklid musi znat cil jeste pred kopirovanim.
        $paths[]=$path;
        // Temp kopie zustava do potvrzeni DB i auditu, aby chyba neznicila uz preneseny vyber.
        $moved=isset($file['token'])?copy($file['tmp'],$path):move_uploaded_file($file['tmp'],$path);
        if (!$moved) throw new RuntimeException('Uložení dokumentu selhalo.');
        if (!chmod($path,0600)) throw new RuntimeException('Nelze zabezpečit dokument.');$filePath=$relative.'/'.$stored;
        $s=$db->prepare('INSERT INTO hr_dokument(id_firma,id_person,id_dokument_typ,id_dokument_stav,nazev,id_user_zadal) VALUES(?,?,?,1,?,?)');
        $s->bind_param('iiisi',$person['id_firma'],$idPerson,$type,$file['name'],$idUser);$s->execute();$document=(int)$db->insert_id;$s->close();
        // Zobrazovany nazev je puvodni, fyzicky soubor ma nahodne jmeno bez uzivatelske cesty.
        $s=$db->prepare('INSERT INTO hr_dokument_soubor(id_dokument,verze,puvodni_nazev,ulozeny_nazev,relativni_cesta,mime_typ,velikost,sha256,poradi,id_user_zadal) VALUES(?,1,?,?,?,?,?,?,1,?)');
        $s->bind_param('issssisi',$document,$file['name'],$file['name'],$filePath,$file['mime'],$file['size'],$file['sha256'],$idUser);
        $s->execute();$s->close();
    }
}

/** Samostatne pridani pouziva tutez validaci, transakci a audit jako dokumenty u ukonceni. */
function hr_post_zamestnanec_dokumenty(mysqli $db, int $idUser): void
{
    $idPerson=(int)($_POST['id_person']??0);$paths=[];
    $url=cb_root_url('index.php?m=hr&page=zamestnanec&id='.$idPerson.'&sekce=dokumenty');
    try {
        if (!cb_pravo_ma(301) || !cb_pravo_ma(307)) throw new CbUserVisibleException('Nemáte právo přidávat dokumenty zaměstnance.');
        hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
        $files=hr_dokumenty_vyber($idPerson,$idUser,'hr_zamestnanec_dokumenty_pridat',(int)($_POST['id_dokument_typ']??0));
        if (!$files) throw new CbUserVisibleException('Vyberte alespoň jeden dokument.');
        hr_audit_transaction_begin($db);
        hr_dokumenty_uloz($db,$idPerson,$idUser,$files,(int)($_POST['id_dokument_typ']??0),$paths);
        hr_audit_transaction_commit($db);
        hr_audit_dokoncit($db,true,'Dokumenty zaměstnance byly přidány.');
        hr_dokumenty_potvrd($files);
    } catch (Throwable $e) {
        try {hr_audit_transaction_rollback($db);} catch (Throwable $ignored) {}
        hr_dokumenty_vrat_soubory($paths);
        hr_audit_form_finish($url,false,cb_hr_chyba_text($e,'Přidání dokumentů zaměstnance'));
    }
    hr_audit_form_finish($url,true,'Dokumenty zaměstnance byly přidány.');
}
