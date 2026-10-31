<?php
declare(strict_types=1);
/* Co-Link: CLI uklid pouze vlastnich docasnych a nepripojenych priloh, nikdy historie konverzaci. */
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/lib.php';
$options=getopt('',['prostredi:']);
$PROSTREDI=($options['prostredi']??'server')==='local'?'LOCAL':'SERVER';
if(!co_pripraveno()) exit(2);
// Nepripojene soubory drzi dva dny rezervu; odeslane prilohy a zpravy se nemazou.
foreach(co_rows('SELECT id,soubor FROM co_priloha WHERE zprava IS NULL AND vytvoreno<DATE_SUB(NOW(),INTERVAL 2 DAY)') as $f) {
    co_transakce(static function() use($f): void {
        $row=co_rows('SELECT id,soubor FROM co_priloha WHERE id=? AND zprava IS NULL FOR UPDATE',[(int)$f['id']])[0]??[];
        if(!$row) return;
        if(preg_match('/^[a-f0-9]{64}$/D',$row['soubor'])) {
            $path=co_uloziste().'/'.$row['soubor'];
            if(is_file($path) && !unlink($path)) throw new RuntimeException('Uklid souboru selhal.');
        }
        co_sql('DELETE FROM co_priloha WHERE id=?',[(int)$row['id']])->close();
    });
}
$tmp=dirname(__DIR__,2).'/data/tmp/co_link';
foreach(is_dir($tmp)?new DirectoryIterator($tmp):[] as $file) {
    if($file->isDot() || $file->isLink() || !$file->isFile() || !preg_match('/^[0-9]+-[a-f0-9]{32}$/D',$file->getFilename()) || $file->getMTime()>time()-2*86400) continue;
    $handle=fopen($file->getPathname(),'r+b');
    if($handle!==false) { if(flock($handle,LOCK_EX|LOCK_NB)) { unlink($file->getPathname()); flock($handle,LOCK_UN); } fclose($handle); }
}
co_sql('DELETE FROM co_qr WHERE platnost_do<DATE_SUB(NOW(),INTERVAL 2 DAY)')->close();
