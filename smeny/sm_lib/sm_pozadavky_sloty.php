<?php
/* Pozadavky osoby: casove MAIN a rovnocenne sloty pro rezervace HPP volna na vsech MAIN. */
declare(strict_types=1);

/** MAIN cteme vcetne budoucich zmen; ucinnost se vyhodnoti pro den volna, nikoli dnes. */
function cb_smeny_pozadavky_main(mysqli $db, int $person): array
{
    $stmt=$db->prepare('SELECT DISTINCT m.id_pob,m.platnost_od,m.platnost_do FROM hr_pracoviste_main m
        JOIN hr_person hp ON hp.id_person=m.id_person
        JOIN pobocka p ON p.id_pob=m.id_pob AND p.id_firma=hp.id_firma AND p.aktivni=1
        WHERE m.id_person=? AND m.platny=1 AND m.zruseno IS NULL ORDER BY m.id_pob');
    $stmt->bind_param('i',$person); $stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return $rows;
}

/** Jediny den volna plati pro osobu; kolize se kontroluje ve vsech jejích tehdy platnych MAIN. */
function cb_smeny_pozadavky_main_den(array $rows, string $day): array
{
    $ids=[];
    foreach($rows as $row) {
        if (!empty($row['platnost_od']) && $row['platnost_od']>$day) continue;
        if (!empty($row['platnost_do']) && $row['platnost_do']<$day) continue;
        if ((int)$row['id_pob']>0) $ids[(int)$row['id_pob']]=true;
    }
    $ids=array_keys($ids); sort($ids,SORT_NUMERIC);
    return $ids;
}

/** Sloty nacitame bez priority; platnost se posuzuje az pro konkretni den pozadavku. */
function cb_smeny_pozadavky_sloty(mysqli $db, int $person): array
{
    $table=cb_hr_schema_table($db,'slot');
    $stmt=$db->prepare('SELECT id_slot,platnost_od,platnost_do FROM '.$table.' WHERE id_person=? AND platny=1 AND zruseno IS NULL');
    $stmt->bind_param('i',$person); $stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return $rows;
}

/** Jedna pozice se rezervuje jen jednou, i kdyz ma osoba vice navazujicich zaznamu. */
function cb_smeny_pozadavky_sloty_den(array $rows, string $day): array
{
    $ids=[];
    foreach($rows as $row) {
        if (!empty($row['platnost_od']) && $row['platnost_od']>$day) continue;
        if (!empty($row['platnost_do']) && $row['platnost_do']<$day) continue;
        if ((int)$row['id_slot']>0) $ids[(int)$row['id_slot']]=true;
    }
    $ids=array_keys($ids); sort($ids,SORT_NUMERIC);
    return $ids;
}

/** Bez dokoncene migrace nesmime zobrazit obsazeny den jako volny. Nic automaticky nezapisujeme. */
function cb_smeny_pozadavky_rezervace_schema(mysqli $db): void
{
    static $verified=[];
    $key=spl_object_id($db);
    if(isset($verified[$key])) return;
    $stmt=$db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='smeny_pozadavek_volno_slot'
        AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='smeny_pozadavek' AND column_name='volno_id_slot')");
    $stmt->execute(); $exists=$stmt->get_result()->fetch_row()!==null; $stmt->close();
    if(!$exists) throw new CbUserVisibleException('Požadavky čekají na dokončení aktualizace databáze. Správce musí spustit migraci rezervací volna.');
    $result=$db->query('SELECT 1 FROM smeny_pozadavek p WHERE p.volno_datum IS NOT NULL AND NOT EXISTS (SELECT 1 FROM smeny_pozadavek_volno_slot r WHERE r.id_smeny_pozadavek=p.id_smeny_pozadavek) LIMIT 1');
    $missing=$result->fetch_row()!==null; $result->free();
    if($missing) throw new CbUserVisibleException('Převod dřívějších rezervací volna není dokončen. Správce musí dokončit migraci; původní požadavky jsou zachované.');
    $verified[$key]=true;
}

/** Unikat pobocka/slot/den chrani vsechny pozice i pri soucasnem ulozeni dvou lidi. Volajici vlastni transakci. */
function cb_smeny_pozadavky_rezervace_ulozit(mysqli $db, int $request, array $branches, ?string $day, array $slots): void
{
    // Stare PK pozadavek/slot neumelo vice MAIN. Kontrola pred zapisem zabrani zavadejici kolizni hlasce.
    if(count($branches)>1) {
        $result=$db->query("SELECT COLUMN_NAME FROM information_schema.statistics
            WHERE table_schema=DATABASE() AND table_name='smeny_pozadavek_volno_slot' AND index_name='PRIMARY'
            ORDER BY seq_in_index");
        $columns=array_column($result->fetch_all(MYSQLI_ASSOC),'COLUMN_NAME'); $result->free();
        if($columns!==['id_smeny_pozadavek','id_pob','id_slot']) {
            throw new CbUserVisibleException('Volno pro více MAIN čeká na aktualizaci databáze rezervací. Dosavadní požadavky zůstaly zachované.');
        }
    }
    $stmt=$db->prepare('DELETE FROM smeny_pozadavek_volno_slot WHERE id_smeny_pozadavek=?');
    $stmt->bind_param('i',$request); $stmt->execute(); $stmt->close();
    if($day===null) return;
    $stmt=$db->prepare('INSERT INTO smeny_pozadavek_volno_slot(id_smeny_pozadavek,id_pob,id_slot,datum) VALUES(?,?,?,?)');
    // Serazene pobocky a sloty udrzuji stejny poradek zamku pri soubeznych zadostech.
    foreach($branches as $branch) foreach($slots as $slot) {
        $stmt->bind_param('iiis',$request,$branch,$slot,$day); $stmt->execute();
    }
    $stmt->close();
}
