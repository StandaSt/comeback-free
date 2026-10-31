<?php
declare(strict_types=1);
/* Co-Link: overeni priloh a ulozeni mimo verejny web; stazeni vzdy kontroluje clenstvi. */

/** Kratke bloky dovoli velke prilohy bez navysovani PHP limitu pro jeden HTTP pozadavek. */
function co_nahrat(int $user): array
{
    $file=$_FILES['soubor']??[];
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($error!==UPLOAD_ERR_OK) co_chyba('Soubor se nepodařilo nahrát. Zkontrolujte velikost a připojení.',413);
    $tmp=(string)($file['tmp_name']??'');
    if(!is_uploaded_file($tmp)) co_chyba('Chybí nahraný soubor.');
    $limit=(int)co_rows('SELECT priloha_mb FROM co_nastaveni WHERE id=1')[0]['priloha_mb']*1048576;
    if(isset($_POST['upload_id'])) return co_nahrat_blok($user,$tmp,$limit);
    return co_dokoncit_prilohu($user,$tmp,(string)($file['name']??'soubor'),$limit,true);
}

/** Do slozky docasnych dat se zapisuje jen nahodny identifikator svazany s vlastnikem. */
function co_nahrat_blok(int $user, string $tmp, int $limit): array
{
    $id=(string)($_POST['upload_id']??'');
    $total=filter_var($_POST['celkem']??null,FILTER_VALIDATE_INT);
    $offset=filter_var($_POST['pozice']??null,FILTER_VALIDATE_INT);
    $chunk=filesize($tmp);
    if(!preg_match('/^[a-f0-9]{32}$/D',$id) || $total===false || $offset===false || $total<1 || $total>$limit || $offset<0 || $chunk===false || $chunk<1 || $chunk>1048576 || $offset+$chunk>$total) co_chyba('Neplatná velikost nebo pořadí části přílohy.',413);
    if((int)co_rows('SELECT COUNT(*) pocet FROM co_priloha WHERE vlastnik=? AND zprava IS NULL',[$user])[0]['pocet']>=100) co_chyba('Máte příliš mnoho rozpracovaných příloh. Zkuste to po jejich automatickém úklidu.');
    $dir=dirname(__DIR__,2).'/data/tmp/co_link';
    if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Docasne uloziste neni dostupne.');
    $path=$dir.'/'.$user.'-'.$id;
    $handle=fopen($path,'c+b');
    if($handle===false) throw new RuntimeException('Nelze otevrit cast prilohy.');
    $completed=false;
    try {
        if(!flock($handle,LOCK_EX)) throw new RuntimeException('Nelze zamknout prenos.');
        $actual=fstat($handle)['size'];
        if($actual!==$offset) co_chyba('Přenos přílohy byl přerušen. Vyberte soubor znovu.',409);
        fseek($handle,0,SEEK_END);
        $input=fopen($tmp,'rb');
        if($input===false) throw new RuntimeException('Nelze precist cast prilohy.');
        try { $written=stream_copy_to_stream($input,$handle); } finally { fclose($input); }
        if($written!==$chunk) throw new RuntimeException('Neuplny zapis prilohy.');
        fflush($handle);
        $completed=$offset+$chunk===$total;
        if($completed) {
            $result=co_dokoncit_prilohu($user,$path,(string)($_POST['nazev']??'soubor'),$limit,false);
            return $result+['dokonceno'=>true];
        }
        return ['dokonceno'=>false,'pozice'=>$offset+$chunk];
    } finally { flock($handle,LOCK_UN); fclose($handle); if($completed && is_file($path)) unlink($path); }
}

/** Finalni MIME kontrola probiha az nad celym souborem, nikoli jen nad jeho prvnim blokem. */
function co_dokoncit_prilohu(int $user, string $tmp, string $original, int $limit, bool $httpUpload): array
{
    $size=filesize($tmp);
    if($size===false || $size<1 || $size>$limit) co_chyba('Soubor překračuje nastavený limit přílohy.',413);
    $name=mb_substr(basename(str_replace('\\','/',$original)),0,240);
    $name=preg_replace('/[\x00-\x1F\x7F]/u','',$name) ?: 'soubor';
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
    $allowed=[
        'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'webp'=>['image/webp'],'gif'=>['image/gif'],
        'heic'=>['image/heic','image/heif','application/octet-stream'],'heif'=>['image/heic','image/heif','application/octet-stream'],
        'pdf'=>['application/pdf'],'txt'=>['text/plain'],'csv'=>['text/plain','text/csv','application/csv'],
        'doc'=>['application/msword','application/x-ole-storage','application/CDFV2'],
        'xls'=>['application/vnd.ms-excel','application/x-ole-storage','application/CDFV2'],
        'docx'=>['application/zip','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx'=>['application/zip','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx'=>['application/zip','application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip'=>['application/zip','application/x-zip-compressed'],
        'mp4'=>['video/mp4'],'mov'=>['video/quicktime'],
    ];
    if(!isset($allowed[$ext]) || !in_array($mime,$allowed[$ext],true)) co_chyba('Tento typ přílohy není podporovaný.');
    $dir=co_uloziste();
    if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Uloziste neni dostupne.');
    $stored=bin2hex(random_bytes(32));
    $moved=$httpUpload?move_uploaded_file($tmp,$dir.'/'.$stored):copy($tmp,$dir.'/'.$stored);
    if(!$moved) throw new RuntimeException('Ulozeni prilohy selhalo.');
    try {
        co_sql('INSERT INTO co_priloha(vlastnik,nazev,soubor,mime,velikost) VALUES(?,?,?,?,?)',[$user,$name,$stored,$mime,$size])->close();
        return ['priloha'=>['id'=>(int)db()->insert_id,'nazev'=>$name,'velikost'=>$size]];
    } catch(Throwable $e) { unlink($dir.'/'.$stored); throw $e; }
}
