<?php
declare(strict_types=1);
/* Jednoucelovy CLI vstup pro denni uplatneni naplanovanych HR zmen po pulnoci v Europe/Prague.
 * Bez parametru provede serverovy prepocet pro hosting s pouhou cestou k PHP souboru.
 * --dry-run pouze cte, --local vyslovne voli lokal. HTTP pristup je zakazan.
 * Spolecna konfigurace se nacita v globalnim rozsahu pred prvnim volanim DB.
 */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once __DIR__.'/../../common/lib/app.php';
// Konfigurace musi byt globalni; pri prvnim nacteni uvnitr db() by ji db_connect() nevidela.
require_once __DIR__.'/../../common/lib/system.php';
require_once __DIR__.'/../hr_lib/hr_casove_zmeny.php';
$args=array_slice($argv,1);
if (array_diff($args,['--apply','--dry-run','--server','--local']) || (in_array('--apply',$args,true) && in_array('--dry-run',$args,true)) || (in_array('--local',$args,true) && in_array('--server',$args,true))) {
    fwrite(STDERR,"Použití: php casove_zmeny.php [--server|--local] [--dry-run|--apply]\n"); exit(2);
}
$apply=$args===[] || in_array('--apply',$args,true);
// Jediny hostingovy vstup bez parametru pouziva server; diagnostika muze vyslovne zvolit lokal.
$PROSTREDI=in_array('--local',$args,true)?'LOCAL':'SERVER';
$db=null; $errors=0; $changed=0; $locked=false;
try {
    $db=db();
    // SQL i PHP musi pouzit stejny cesky den take na serveru s UTC systemovym casem.
    $zone=(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->format('P');
    $stmt=$db->prepare('SET SESSION time_zone=?'); $stmt->bind_param('s',$zone); $stmt->execute(); $stmt->close();
    if ($apply) {
        hr_casove_schema_vyzaduj($db);
        $locked=(int)$db->query("SELECT GET_LOCK('comeback_hr_casove_zmeny',0)")->fetch_row()[0]===1;
        if (!$locked) throw new RuntimeException('Jiný přepočet HR již běží.');
    }
    // Porovnani aktivnich a planovanych osob dohoni vynechany den; rucne vypnute ucty zustanou vypnute.
    $maAktivaci=$db->query("SHOW COLUMNS FROM hr_person LIKE 'casova_aktivace_od'")->num_rows===1;
    $ids=$db->query('SELECT id_person FROM hr_person WHERE testovaci=0 AND (aktivni=1'.($maAktivaci?' OR casova_aktivace_od IS NOT NULL':'').') ORDER BY id_person')->fetch_all(MYSQLI_ASSOC);
    foreach ($ids as $row) {
        $id=(int)$row['id_person'];
        try {
            if ($apply) { $db->begin_transaction(); cb_hr_osobu_zamkni($db,$id); }
            $before=hr_casovy_stav_snimnout($db,$id);
            if ($apply) {
                hr_casovy_stav_obnovit($db,$id,null);
                $after=hr_casovy_stav_snimnout($db,$id);
                if ($before!==$after) { hr_casovy_audit($db,$id,$before,$after); $changed++; }
                $db->commit();
            } else {
                // Nahled pouze cte: pred migraci nenacita jeste neexistujici priznak nastupu.
                $function=cb_hr_funkce_osoby($db,$id);
                echo json_encode(['id_person'=>$id,'stav'=>$before,'funkce_role'=>$function['id_role']??null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),PHP_EOL;
            }
        } catch (Throwable $e) {
            if ($apply) $db->rollback();
            $errors++; fwrite(STDERR,"Osoba $id: ".$e->getMessage().PHP_EOL);
        }
    }
    echo json_encode(['datum'=>cb_hr_den(),'rezim'=>$apply?'zapis':'pouze_cteni','osob'=>count($ids),'zmeneno'=>$changed,'chyby'=>$errors],JSON_UNESCAPED_UNICODE),PHP_EOL;
} catch (Throwable $e) { $errors++; fwrite(STDERR,$e->getMessage().PHP_EOL); }
finally { if ($locked) $db->query("SELECT RELEASE_LOCK('comeback_hr_casove_zmeny')"); }
exit($errors ? 1 : 0);
