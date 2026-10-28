<?php
declare(strict_types=1);
/* Účel: Uloží fotografii jako novou verzi osobních údajů, ostatní hodnoty převezme beze změny.
 * Původní fotografie zůstává součástí historie; tato akce staré soubory nemaže.
 */
require_once __DIR__ . '/hr_zamestnanci_pristup.php';
require_once __DIR__ . '/../hr_lib/hr_audit.php';

/** Server přijme pouze čitelný čtverec; celé obdélníkové fotografie se na kartu neukládají. */
function hr_foto_over_vyrez(array $upload): void
{
    if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new CbUserVisibleException('Vyberte fotografii a její čtvercový výřez.');
    $temporary=(string)($upload['tmp_name'] ?? '');
    if ($temporary==='' || !is_file($temporary)) throw new CbUserVisibleException('Fotografii se nepodařilo přijmout. Vyberte ji znovu.');
    $size = @getimagesize($temporary);
    if ($size === false || $size[0] < 1 || $size[0] !== $size[1] || $size[0] > 1200) {
        throw new CbUserVisibleException('Vyberte čtvercový výřez fotografie o velikosti nejvýše 1200 × 1200 px.');
    }
}

/** Převezme všechny sloupce aktuálního schématu včetně údajů, které běžný formulář zatím neupravuje. */
function hr_foto_verze_sql(mysqli $db): string
{
    $columns=[];$values=[];
    foreach ($db->query('SHOW COLUMNS FROM hr_osobni_udaje')->fetch_all(MYSQLI_ASSOC) as $column) {
        $name=(string)$column['Field'];
        if ($name==='id_osobni_udaje' || preg_match('/(?:VIRTUAL|STORED|PERSISTENT) GENERATED/i',(string)$column['Extra'])) continue;
        if (!preg_match('/^[a-zA-Z0-9_]+$/D',$name)) throw new LogicException('Neplatný sloupec osobních údajů.');
        $columns[]='`'.$name.'`';
        $values[]=match($name) {'foto','id_user_zadal'=>'?','vytvoreno'=>'NOW()','platny'=>'1','zruseno'=>'NULL',default=>'t.`'.$name.'`'};
    }
    return 'INSERT INTO hr_osobni_udaje ('.implode(',',$columns).') SELECT '.implode(',',$values)
        .' FROM hr_osobni_udaje t WHERE t.id_osobni_udaje=? AND t.id_person=? AND t.platny=1';
}

/** Zámek osoby a původní verze chrání souběžnou změnu fotografie a ostatních osobních údajů. */
function hr_zamestnanec_foto_ulozit(mysqli $db, int $idPerson, int $idUser, string $path): void
{
    hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
    if (!preg_match('~^hr/img/zamestnanci/[a-f0-9]{32}\.(jpg|png|webp)$~D',$path)) throw new LogicException('Neplatná cesta uložené fotografie.');
    hr_audit_transaction_begin($db);
    try {
        cb_hr_osobu_zamkni($db,$idPerson);
        $stmt=$db->prepare('SELECT id_osobni_udaje FROM hr_osobni_udaje WHERE id_person=? AND platny=1 ORDER BY id_osobni_udaje DESC LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i',$idPerson);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();$stmt->close();
        if (!$row) throw new CbUserVisibleException('Nejprve je třeba doplnit osobní údaje zaměstnance.');
        $id=(int)$row['id_osobni_udaje'];
        // Kopie uchová i nepovinné údaje; fotografie nesmí přepsat současně jinou část karty.
        $stmt=$db->prepare(hr_foto_verze_sql($db));
        $stmt->bind_param('siii',$path,$idUser,$id,$idPerson);$stmt->execute();
        if ($stmt->affected_rows!==1) throw new RuntimeException('Fotografii se nepodařilo připojit ke kartě.');
        $stmt->close();
        $stmt=$db->prepare('UPDATE hr_osobni_udaje SET platny=0,zruseno=NOW() WHERE id_osobni_udaje=? AND id_person=?');
        $stmt->bind_param('ii',$id,$idPerson);$stmt->execute();$stmt->close();
        hr_audit_transaction_commit($db);
    } catch (Throwable $e) { hr_audit_transaction_rollback($db);throw $e; }
}
