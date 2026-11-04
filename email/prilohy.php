<?php
/* E-mail: docasne sifrovane kopie jednotlivych priloh pro otevreni v lokalnim Office; odkaz neobsahuje heslo ani pristup k dalsi poste. */
declare(strict_types=1);
require_once __DIR__.'/posta.php';

/** Office vybirame podle pripony i skutecneho obsahu; otevreni vzdy vyzaduje vedome kliknuti uzivatele. */
function em_priloha_office(string $content, string $name): ?array
{
    $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
    $types=['doc'=>['word','Word','application/msword'],'docx'=>['word','Word','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'=>['excel','Excel','application/vnd.ms-excel'],'xlsx'=>['excel','Excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt'=>['powerpoint','PowerPoint','application/vnd.ms-powerpoint'],'pptx'=>['powerpoint','PowerPoint','application/vnd.openxmlformats-officedocument.presentationml.presentation']];
    if (!isset($types[$ext])) return null;
    if (in_array($ext,['doc','xls','ppt'],true)) {
        if (!str_starts_with($content,hex2bin('d0cf11e0a1b11ae1'))) return null;
    } else {
        if (!class_exists('ZipArchive') || !str_starts_with($content,'PK')) return null;
        // Kontrola struktury ZIP nic nerozbaluje do verejneho adresare.
        $dir=em_priloha_adresar(); $file=tempnam($dir,'check_');
        if ($file===false) throw new EmailChyba('Přílohu se nepodařilo připravit.');
        try {
            if (file_put_contents($file,$content,LOCK_EX)!==strlen($content)) throw new EmailChyba('Přílohu se nepodařilo připravit.');
            $zip=new ZipArchive(); if ($zip->open($file)!==true) return null;
            try {
                $required=match($ext){'docx'=>'word/document.xml','xlsx'=>'xl/workbook.xml','pptx'=>'ppt/presentation.xml'};
                if ($zip->locateName('[Content_Types].xml')===false || $zip->locateName($required)===false) return null;
                for($i=0;$i<$zip->numFiles;$i++) if (str_ends_with(strtolower((string)$zip->getNameIndex($i)),'vbaproject.bin')) return null;
            } finally {$zip->close();}
        } finally {unlink($file);}
    }
    return ['scheme'=>'ms-'.$types[$ext][0],'label'=>$types[$ext][1],'type'=>$types[$ext][2]];
}

/** Docasna provozni data zustavaji zakazana pro prime HTTP cteni i bez nadrizeneho pravidla serveru. */
function em_priloha_adresar(): string
{
    $dir=__DIR__.'/../common/tmp/email_prilohy';
    if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) throw new EmailChyba('Přílohu se nepodařilo připravit.');
    if (!is_file($dir.'/.htaccess') && file_put_contents($dir.'/.htaccess',"# Docasne sifrovane prilohy nejsou verejne soubory.\nRequire all denied\n",LOCK_EX)===false) throw new EmailChyba('Přílohu se nepodařilo zabezpečit.');
    return $dir;
}

/** Opravneny API pozadavek vytvori odkaz na jednu nemennou kopii na deset minut; Office nepotrebuje session ani heslo uzivatele. */
function em_priloha_odkaz(string $content, string $name, string $type): string
{
    $dir=em_priloha_adresar(); $token=bin2hex(random_bytes(32)); $expires=time()+600;
    $name=em_nazev_prilohy($name);
    $row=['expires'=>$expires,'name'=>$name,'type'=>$type,'cipher'=>em_zasifruj($content,'office:'.$token.':'.$expires.':'.$name.':'.$type)];
    $json=json_encode($row,JSON_THROW_ON_ERROR);
    $file=$dir.'/'.hash('sha256',$token).'.json';
    if (file_put_contents($file,$json,LOCK_EX)!==strlen($json)) throw new EmailChyba('Přílohu se nepodařilo připravit pro aplikaci.');
    chmod($file,0600);
    // Provoz nenechava stare kopie rust bez omezeni; uklizeji se pouze vlastni docasne soubory.
    $checked=0;
    foreach (new DirectoryIterator($dir) as $one) {
        if (++$checked>200) break;
        if ($one->isFile() && preg_match('/^[a-f0-9]{64}\.json$/',$one->getFilename()) && $one->getMTime()<time()-600) unlink($one->getPathname());
    }
    return $token;
}

/** Drzitel nahodneho odkazu smi jen precist jednu kopii do vyprseni; uprava metadat zneplatni overeni sifry. */
function em_priloha_docasna(string $token): array
{
    if (!preg_match('/^[a-f0-9]{64}$/',$token)) throw new EmailChyba('Odkaz na přílohu není platný.');
    $file=__DIR__.'/../common/tmp/email_prilohy/'.hash('sha256',$token).'.json';
    if (!is_file($file)) throw new EmailChyba('Odkaz na přílohu vypršel. Otevřete přílohu znovu v IS.');
    $row=json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
    if ((int)($row['expires']??0)<=time()) {unlink($file);throw new EmailChyba('Odkaz na přílohu vypršel. Otevřete přílohu znovu v IS.');}
    $row['content']=em_rozsifruj((string)$row['cipher'],'office:'.$token.':'.$row['expires'].':'.$row['name'].':'.$row['type']);
    unset($row['cipher']); return $row;
}
