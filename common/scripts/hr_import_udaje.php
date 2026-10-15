<?php
/* Ucel: Samostatny doplnovaci HR import osobnich udaju, smluvnich podminek a historie.
 * Stejny planovac pro nahled i zapis chrani pozdejsi rucni zmeny, validuje hodnoty podle DB a zachovava neplatne zaznamy.
 * Nacteni nic nespousti; puvodni import dokumentu zustava samostatny.
 * Starsi texty pohlavi z manifestu prevadi na ID aktualniho HR ciselniku.
 */
declare(strict_types=1);
require_once __DIR__.'/hr_import_smlouvy.php';
require_once __DIR__.'/../../hr/hr_db/ciselniky.php';
class HrUdajeImportException extends RuntimeException {}

/** Pevny seznam tabulek/sloupcu je hranici importu; manifest nesmi obsahovat libovolne SQL. */
function hru_tables(): array
{
    return [
        'hr_osobni_udaje'=>'id_osobni_udaje,id_person,titul_pred,jmeno,druhe_jmeno,prijmeni,rodne_prijmeni,titul_za,datum_narozeni,rodne_cislo,cislo_obcanskeho_prukazu,zdr_poj,statni_obcanstvi,misto_narozeni,pohlavi,vzdelani,rodinny_stav,sleva_na_poplatnika,duchodce,duchod_typ,ico,ridicsky_prukaz_cislo,ridicsky_prukaz_skupiny,ridicsky_prukaz_platnost_do,cizinec,zahranicni_identifikator,zahranicni_identifikator_typ,zahranicni_identifikator_stat,prvni_zamestnani_cr,pobyt_cr_typ,pobyt_cr_od,pobyt_cr_do,pracovni_opravneni_typ,pracovni_opravneni_cislo,pracovni_opravneni_od,pracovni_opravneni_do,poznamka,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_pracovni_vztah'=>'id_pracovni_vztah,id_person,id_pracovni_vztah_typ,datum_nastupu,datum_ukonceni,zkusebni_doba_mesice,poznamka,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_mzda'=>'id_mzda,id_pracovni_vztah,id_mzda_typ,castka,platnost_od,platnost_do,id_user_zadal,vytvoreno,platny,zruseno,id_user_zrusil',
        'hr_pracovni_uvazek'=>'id_pracovni_uvazek,id_pracovni_vztah,uvazek,hodin_tydne,platnost_od,platnost_do,id_user_zadal,vytvoreno,platny,zruseno,id_user_zrusil',
        'hr_bankovni_ucet'=>'id_bankovni_ucet,id_person,cislo_uctu,kod_banky,iban,hlavni,platnost_od,poznamka,id_user_zadal,zadano,zmena,platny',
        'hr_email'=>'id_email,id_person,id_email_typ,email,hlavni,poznamka,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_telefon'=>'id_telefon,id_person,id_telefon_typ,telefon,telefon_normalizovany,hlavni,poznamka,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_adresa'=>'id_adresa,id_person,ulice,cp,mesto,psc,stat,typ,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_zdravotni_pojisteni'=>'id_zdravotni_pojisteni,id_person,stat,kod_pojistovny,nazev_pojistovny,cislo_pojistence,cislo_prukazu,platnost_od,platnost_do,poznamka,id_user_zadal,vytvoreno,platny,zruseno,id_user_zrusil',
        'hr_slot'=>'id_hr_slot,id_person,id_slot,hlavni,platnost_od,platnost_do,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_funkce'=>'id_hr_funkce,id_person,id_funkce,hlavni,v_treninku,platnost_od,platnost_do,platny,id_person_zadal,vytvoreno',
        'hr_pracoviste'=>'id_pracoviste,id_person,id_pob,hlavni,platnost_od,platnost_do,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_pracoviste_main'=>'id_main,id_person,id_pob,platnost_od,platnost_do,id_user_zadal,vytvoreno,platny,zruseno',
        'hr_mzdy_mesic'=>'id_hr_mzda_mesic,id_firma,id_user,rok,mesic,datum_od,datum_do,mzda_typ,id_slot,hodiny,hodinova_sazba,mesicni_fix,isk,bonus_1,bonus_2,bonus_cista,cista_mzda,hruba_mzda,superhruba_mzda,naklad_col_hod,naklad_col_den,je_manager_col,import_jmeno,import_list,zdroj,stav,poznamka',
        'hr_poznamka'=>'id_poznamka,id_person,text_poznamky,id_user_zadal,vytvoreno',
    ];
}

/** Plan musi navazovat na stejny dokumentovy import, ne na lokalni id_dokument. */
function hru_manifest(): array
{
    $path=dirname(__DIR__,3).'/data/google_data/hr_udaje_import_manifest.json';
    if (!is_file($path)) throw new HrUdajeImportException('Chybí hr_udaje_import_manifest.json v data/google_data.');
    $plan=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $original=hr_smlouvy_manifest();
    if (($plan['version'] ?? null)!==1 || !hash_equals((string)$plan['source_manifest_sha256'],$original['manifest_hash'])) throw new HrUdajeImportException('Plán údajů neodpovídá schválenému importu dokumentů.');
    $plan['hash']=hash_file('sha256',$path);
    $plan['documents']=$original['documents'];
    return $plan;
}

/** Porovnani respektuje NULL i cisla vracena MySQL jako text, neztraci diakritiku jmen. */
function hru_equal(mixed $a, mixed $b): bool
{
    if ($a===null || $b===null) return $a===null && $b===null;
    if (is_numeric($a) && is_numeric($b)) return (string)(0+$a)===(string)(0+$b);
    return (string)$a===(string)$b;
}

/** Rozdil v predvolbe nebo velikosti pismen neni dalsi telefon/email stejne osoby. */
function hru_contact_equal(string $type, mixed $a, mixed $b): bool
{
    if ($a===null || $b===null) return hru_equal($a,$b);
    if ($type==='email') return mb_strtolower(trim((string)$a))===mb_strtolower(trim((string)$b));
    $normalize=static function($v): string {
        $digits=preg_replace('/\D/','',(string)$v);
        if (str_starts_with($digits,'00')) $digits=substr($digits,2);
        return strlen($digits)===9 ? '420'.$digits : $digits;
    };
    return $normalize($a)===$normalize($b);
}

/** Planovac simuluje zmeny v pameti; zapis zapne pouze potvrzeny controller. */
final class HrUdajePlanner
{
    public array $rows=[];
    public array $counts=[];
    public array $changes=[];
    public array $warnings=[];
    public array $created=[];
    public array $removeAfterCommit=[];
    private int $virtual=-1;
    private array $schema=[];

    /** Nacte stabilni poradi cilovych zaznamu pro simulaci a kontrolni otisk. */
    public function __construct(private mysqli $db, public array $plan, private int $user, private bool $write)
    {
        foreach (hru_tables() as $table=>$fields) {
            $this->rows[$table]=$db->query('SELECT '.$fields.' FROM '.$table.' ORDER BY '.explode(',',$fields)[0])->fetch_all(MYSQLI_ASSOC);
            $this->schema[$table]=array_column($db->query('SHOW FULL COLUMNS FROM '.$table)->fetch_all(MYSQLI_ASSOC),null,'Field');
        }
    }

    /** Nahled musi odmitnout stejna omezeni typu a delky jako skutecny zapis, bez zkracovani dat. */
    public function validate(string $table, array $values, int $pid, bool $insert=false): void
    {
        // Chybejici povinny sloupec bez defaultu by jinak selhal az v INSERTu.
        if ($insert) foreach ($this->schema[$table] as $field=>$col) {
            if ($col['Null']==='NO' && $col['Default']===null && !str_contains($col['Extra'],'auto_increment') && !array_key_exists($field,$values)) throw new HrUdajeImportException('Osoba '.$pid.': chybí povinný údaj '.$table.'.'.$field.'. Opravte importní plán.');
        }
        foreach ($values as $field=>$value) {
            $col=$this->schema[$table][$field]; $type=strtolower($col['Type']); $valid=true;
            if ($value===null) $valid=$col['Null']==='YES';
            elseif (preg_match('/^(?:var)?char\((\d+)\)/',$type,$m)) $valid=mb_strlen((string)$value,'UTF-8')<=(int)$m[1];
            elseif (preg_match('/^(tinytext|text|mediumtext|longtext)$/',$type,$m)) {
                $max=['tinytext'=>255,'text'=>65535,'mediumtext'=>16777215,'longtext'=>4294967295];
                $valid=strlen((string)$value)<=$max[$m[1]];
            } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/',$type,$m)) {
                // Zaporne virtualni FK existuji pouze v nahledu a pri zapisu dostanou skutecne ID.
                if (!$this->write && str_starts_with($field,'id_') && is_int($value) && $value<0) continue;
                $bits=['tinyint'=>8,'smallint'=>16,'mediumint'=>24,'int'=>32,'bigint'=>64][$m[1]];
                $unsigned=str_contains($type,'unsigned');
                $min=$unsigned ? 0 : -(2**($bits-1)); $max=$unsigned ? 2**$bits-1 : 2**($bits-1)-1;
                $valid=preg_match('/^-?\d+$/',(string)$value) && $value >= $min && $value <= $max;
            } elseif (preg_match('/^decimal\((\d+),(\d+)\)/',$type,$m)) {
                $valid=is_numeric($value) && is_finite((float)$value) && abs((float)$value)<10**((int)$m[1]-(int)$m[2]) && (!str_contains($type,'unsigned') || $value>=0);
                if ($valid) $valid=abs((float)$value-round((float)$value,(int)$m[2]))<0.00000001;
            } elseif (in_array($type,['date','datetime','timestamp'],true)) {
                $format=$type==='date' ? 'Y-m-d' : 'Y-m-d H:i:s';
                $date=DateTimeImmutable::createFromFormat('!'.$format,(string)$value);
                $valid=$date && $date->format($format)===(string)$value && (int)$date->format('Y')>=1000;
            } elseif (str_starts_with($type,'enum(')) {
                $options=str_getcsv(substr($col['Type'],5,-1),',',"'",'\\');
                $valid=in_array((string)$value,$options,true);
            }
            if (!$valid) throw new HrUdajeImportException('Osoba '.$pid.': údaj '.$table.'.'.$field.' neodpovídá typu nebo délce '.$col['Type'].'. Opravte importní plán; hodnota nebyla zkrácena.');
        }
    }

    /** Hleda prirozeny klic; automaticke ID se na server neprenasi. */
    public function find(string $table, array $key): array
    {
        return array_values(array_filter($this->rows[$table],static function(array $r) use($key): bool {
            foreach ($key as $field=>$value) {
                $equal=in_array($field,['email','telefon'],true) ? hru_contact_equal($field,$r[$field] ?? null,$value) : hru_equal($r[$field] ?? null,$value);
                if (!$equal) return false;
            }
            return true;
        }));
    }

    /** Zmena je viditelna s osobou v nahledu; pri simulaci se SQL zapis neprovadi. */
    public function record(string $table, int $pid, string $action): void
    {
        $this->counts[$table]=($this->counts[$table] ?? 0)+1;
        $this->changes[]=['id_person'=>$pid,'table'=>$table,'action'=>$action];
    }

    /** Aktualizace pouziva pevne sloupce a oddelene SQL parametry. */
    public function update(string $table, array $row, array $values, int $pid): array
    {
        $columns=explode(',',hru_tables()[$table]); $pk=$columns[0];
        foreach ($values as $k=>$v) if (!in_array($k,$columns,true) || $k===$pk) throw new LogicException('Nepovolený sloupec importu.');
        $different=array_filter($values,fn($v,$k)=>!hru_equal($row[$k] ?? null,$v),ARRAY_FILTER_USE_BOTH);
        if (!$different) return $row;
        $this->validate($table,$different,$pid);
        if ($this->write) {
            $set=implode(',',array_map(static fn($k)=>$k.'=?',array_keys($different)));
            hr_smlouvy_query($this->db,'UPDATE '.$table.' SET '.$set.' WHERE '.$pk.'=?',[...array_values($different),$row[$pk]]);
        }
        $new=array_replace($row,$different);
        foreach ($this->rows[$table] as &$r) if (hru_equal($r[$pk],$row[$pk])) $r=$new;
        unset($r);
        $this->record($table,$pid,'Úprava záznamu');
        $last=array_key_last($this->changes);
        $this->changes[$last]['before']=array_intersect_key($row,$different);
        $this->changes[$last]['after']=$different;
        return $new;
    }

    /** Virtualni ID umozni presny nahled mezd navazanych na dosud neexistujici vztah. */
    public function insert(string $table, array $values, int $pid): array
    {
        $columns=explode(',',hru_tables()[$table]); $pk=$columns[0];
        if (in_array('id_user_zadal',$columns,true)) $values['id_user_zadal']=$this->user;
        foreach ($values as $k=>$v) if (!in_array($k,$columns,true) || $k===$pk) throw new LogicException('Nepovolený sloupec importu.');
        $this->validate($table,$values,$pid,true);
        if ($this->write) {
            hr_smlouvy_query($this->db,'INSERT INTO '.$table.' ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));
            $id=$this->db->insert_id;
        } else $id=$this->virtual--;
        $row=[$pk=>$id,...$values];
        $this->rows[$table][]=$row;
        $this->record($table,$pid,'Nový záznam');
        $this->changes[array_key_last($this->changes)]['after']=$values;
        return $row;
    }

    /** Rucne upravena pole neprepisuje; baseline kontroluje jen menene hodnoty. */
    public function guarded(string $table, array $row, array $values, array $baselineKey, int $pid): bool
    {
        $redirect=$this->plan['redirect'];
        $matches=array_values(array_filter($this->plan['baseline'][$table] ?? [],static function($r) use($baselineKey,$redirect): bool {
            foreach ($baselineKey as $k=>$v) {
                $actual=$r[$k] ?? null;
                if ($k==='id_person') $actual=$redirect[(string)$actual] ?? $actual;
                if (!(in_array($k,['email','telefon'],true) ? hru_contact_equal($k,$actual,$v) : hru_equal($actual,$v))) return false;
            }
            return true;
        }));
        foreach ($values as $k=>$v) {
            if (hru_equal($row[$k] ?? null,$v)) continue;
            if (count($matches)!==1 || !hru_equal($row[$k] ?? null,$matches[0][$k] ?? null)) {
                $this->warn($pid,'Import nepřepsal mezitím změněný údaj '.$table.'.'.$k.'. Navrženo: '.(string)$v);
                return false;
            }
        }
        $this->update($table,$row,$values,$pid);
        return true;
    }

    /** Osobni upozorneni nepatri do historie pracovniho pomeru. */
    public function warn(int $pid, string $message): void
    {
        $this->warnings[]=['id_person'=>$pid,'text'=>$message];
    }

    /** Vice shodnych klicu se nikdy nevybira pomoci LIMIT1. */
    public function upsert(string $table, array $key, array $values, int $pid): ?array
    {
        $rows=$this->find($table,$key);
        if (count($rows)>1) { $this->warn($pid,'Více záznamů pro stejný klíč '.$table.'; nutné dořešit.'); return null; }
        if (!$rows) return $this->insert($table,[...$key,...$values],$pid);
        $this->guarded($table,$rows[0],$values,$key,$pid);
        $pk=explode(',',hru_tables()[$table])[0];
        return $this->find($table,[$pk=>$rows[0][$pk]])[0];
    }
}

/** Overi sloupce a transakcni uloziste; chybejici schema sam nevytvari. */
function hru_schema(mysqli $db): void
{
    foreach (['hr_dokument','hr_dokument_soubor'] as $table) {
        $engine=hr_smlouvy_query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->get_result()->fetch_assoc()['ENGINE'] ?? '';
        if (strcasecmp((string)$engine,'InnoDB')!==0) throw new HrUdajeImportException('Dokumentová tabulka '.$table.' nepodporuje bezpečný import.');
    }
    foreach (hru_tables() as $table=>$fields) {
        $engine=hr_smlouvy_query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->get_result()->fetch_assoc()['ENGINE'] ?? '';
        if (strcasecmp((string)$engine,'InnoDB')!==0) throw new HrUdajeImportException('Tabulka '.$table.' nepodporuje bezpečný import.');
        $cols=array_column($db->query('SHOW COLUMNS FROM '.$table)->fetch_all(MYSQLI_ASSOC),'Field');
        if (array_diff(explode(',',$fields),$cols)) throw new HrUdajeImportException('Tabulka '.$table.' nemá potřebné sloupce. Nejdřív spusťte připravené SQL.');
    }
}

/** Overi vsechny identity a originaly; zdroje museji byt nejdrive ulozeny prvnim importem. */
function hru_sources(mysqli $db, array $plan, ?callable $progress): void
{
    foreach ($plan['people'] as $person) {
        $r=hr_smlouvy_query($db,'SELECT id_user,id_firma,testovaci FROM hr_person WHERE id_person=?',[$person['id_person']])->get_result()->fetch_assoc();
        if (!$r || (int)$r['testovaci'] || (int)$r['id_user']!==$person['id_user'] || (int)$r['id_firma']!==$person['id_firma']) throw new HrUdajeImportException('Osoba '.$person['id_person'].' neodpovídá plánu. Srovnejte nejdřív USER → person.');
    }
    $root=realpath(dirname(__DIR__,2).'/hr/hr_dokumenty');
    foreach ($plan['documents'] as $i=>$doc) {
        $target=(int)($plan['redirect'][(string)$doc['id_person']] ?? $doc['id_person']);
        $files=hr_smlouvy_query($db,'SELECT ds.relativni_cesta FROM hr_dokument_soubor ds JOIN hr_dokument d ON d.id_dokument=ds.id_dokument AND d.verze=ds.verze WHERE d.id_person IN (?,?) AND d.id_firma=? AND ds.sha256=? AND d.platny=1',[$doc['id_person'],$target,$doc['id_firma'],$doc['sha256']])->get_result()->fetch_all(MYSQLI_ASSOC);
        if (!$files) throw new HrUdajeImportException('Nejdřív dokončete první import dokumentů. Chybí dokument osoby '.$target.': '.$doc['source']);
        foreach ($files as $file) {
            $path=realpath(dirname(__DIR__,2).'/'.$file['relativni_cesta']);
            if (!$root || !$path || !str_starts_with($path,$root.DIRECTORY_SEPARATOR) || !is_file($path) || !hash_equals($doc['sha256'],(string)hash_file('sha256',$path))) throw new HrUdajeImportException('Soubor osoby '.$target.' chybí nebo se liší od kontrolovaného obsahu.');
        }
        $progress && $progress(($i+1).'/'.count($plan['documents']).': ověřen dokument osoby '.$target);
    }
}

/** Kopie se vytvori vyhradne jako novy soubor; soubezne cizi soubory se neprepisuji ani neuklizi. */
function hru_copy_owned(HrUdajePlanner $p, string $source, string $dest, string $hash): void
{
    $root=realpath(dirname(__DIR__,2).'/hr/hr_dokumenty');
    $parent=realpath(dirname($dest));
    if (!$root || !$parent || !str_starts_with($parent,$root.DIRECTORY_SEPARATOR)) throw new HrUdajeImportException('Cíl kopírování není v HR dokumentech.');
    $output=fopen($dest,'x+b');
    if (!$output) throw new HrUdajeImportException('Cílový soubor už vznikl nebo nejde vytvořit. Zopakujte kontrolu.');
    $p->created[]=$dest;
    $input=null;
    try {
        $input=fopen($source,'rb');
        if (!$input || stream_copy_to_stream($input,$output)===false || !fflush($output)) throw new HrUdajeImportException('Kopírování dokumentu selhalo.');
    } finally {
        if (is_resource($input)) fclose($input);
        fclose($output);
    }
    if (!hash_equals($hash,(string)hash_file('sha256',$dest))) throw new HrUdajeImportException('Kontrolní součet kopie se liší od originálu.');
}

/** Opravi potvrzene prirazeni; soubory kopiruje a stare odstrani az po uspesnem commitu. */
function hru_redirect(HrUdajePlanner $p, mysqli $db): void
{
    foreach ($p->plan['redirect'] as $old=>$target) {
        $old=(int)$old; $target=(int)$target;
        foreach (['hr_email','hr_telefon','hr_adresa','hr_bankovni_ucet','hr_pracovni_vztah','hr_poznamka'] as $table) {
            foreach ($p->find($table,['id_person'=>$old]) as $row) {
                $values=['id_person'=>$target];
                if (isset($row['hlavni']) && $p->find($table,['id_person'=>$target,'hlavni'=>1,'platny'=>1])) $values['hlavni']=0;
                $p->update($table,$row,$values,$target);
            }
        }
        foreach (['hr_slot','hr_pracoviste','hr_pracoviste_main'] as $table) {
            foreach ($p->find($table,['id_person'=>$old]) as $row) {
                // Historicke zarazeni duplicity neprebije aktualni zarazeni spravne osoby.
                $values=['id_person'=>$target,'platny'=>0,'zruseno'=>date('Y-m-d H:i:s')];
                if (array_key_exists('hlavni',$row)) $values['hlavni']=0;
                $p->update($table,$row,$values,$target);
            }
        }
        foreach ($p->find('hr_funkce',['id_person'=>$old]) as $row) $p->update('hr_funkce',$row,['id_person'=>$target,'platny'=>0,'hlavni'=>0],$target);
        // Prava USER, provozni reporty ani pristupove profily nejsou soucasti opravy dokumentove identity.
        $docs=hr_smlouvy_query($db,'SELECT d.id_dokument,d.verze,d.id_firma,ds.id_dokument_soubor,ds.relativni_cesta,ds.sha256 FROM hr_dokument d LEFT JOIN hr_dokument_soubor ds ON ds.id_dokument=d.id_dokument AND ds.verze=d.verze WHERE d.id_person=?',[$old])->get_result()->fetch_all(MYSQLI_ASSOC);
        $seen=[];
        foreach ($docs as $doc) {
            $id=(int)$doc['id_dokument']; $ver=(int)$doc['verze'];
            if (!isset($seen[$id.'|'.$ver])) { $p->record('hr_dokument',$target,'Oprava přiřazení '.$old.' → '.$target); $seen[$id.'|'.$ver]=true; }
            if (!$doc['id_dokument_soubor']) continue;
            $www=dirname(__DIR__,2); $base=realpath($www.'/hr/hr_dokumenty');
            $source=realpath($www.'/'.$doc['relativni_cesta']);
            if (!$base || !$source || !str_starts_with($source,$base.DIRECTORY_SEPARATOR) || !is_file($source) || !hash_equals((string)$doc['sha256'],(string)hash_file('sha256',$source))) throw new HrUdajeImportException('Starý soubor opravy přiřazení není ověřen.');
            $relative='hr/hr_dokumenty/'.(int)$doc['id_firma'].'/'.$target.'/'.$id.'_'.$ver.'.'.strtolower(pathinfo($source,PATHINFO_EXTENSION));
            $dest=$www.'/'.$relative;
            if (is_file($dest) && !hash_equals((string)$doc['sha256'],(string)hash_file('sha256',$dest))) throw new HrUdajeImportException('Cílový soubor opravy přiřazení má jiný obsah.');
            if ($p->plan['_write']) {
                if (!is_dir(dirname($dest)) && !mkdir(dirname($dest),0770,true) && !is_dir(dirname($dest))) throw new HrUdajeImportException('Nelze vytvořit adresář zaměstnance.');
                if (!is_file($dest)) {
                    // Cizi existujici soubor se nikdy neprepisuje; nova kopie se pri rollbacku uklidi.
                    hru_copy_owned($p,$source,$dest,(string)$doc['sha256']);
                }
                if (in_array(strtolower(pathinfo($source,PATHINFO_EXTENSION)),['doc','docx'],true)) {
                    $pdf=substr($source,0,strrpos($source,'.')).'.pdf'; $newPdf=substr($dest,0,strrpos($dest,'.')).'.pdf';
                    if (is_file($pdf)) {
                        if (is_file($newPdf) && hash_file('sha256',$pdf)!==hash_file('sha256',$newPdf)) throw new HrUdajeImportException('Cílový PDF náhled se liší.');
                        if (!is_file($newPdf)) hru_copy_owned($p,$pdf,$newPdf,(string)hash_file('sha256',$pdf));
                        $p->removeAfterCommit[]=$pdf;
                    }
                }
                hr_smlouvy_query($db,'UPDATE hr_dokument_soubor SET relativni_cesta=?,ulozeny_nazev=?,puvodni_nazev=? WHERE id_dokument_soubor=?',[$relative,basename($dest),basename($dest),$doc['id_dokument_soubor']]);
                $p->removeAfterCommit[]=$source;
            }
        }
        if ($p->plan['_write']) hr_smlouvy_query($db,'UPDATE hr_dokument SET id_person=? WHERE id_person=?',[$target,$old]);
        if ($docs) $p->warn($target,'Opraveno dřívější chybné přiřazení dokumentů a HR údajů z osoby '.$old.'.');
    }
}

/** Prevede stare nazvy pohlavi z manifestu na ID, bez odhadu neznanych hodnot. */
function hru_gender_id(mixed $value, array $genders): ?int
{
    if ($value === null) return null;
    if (!is_string($value) && !is_int($value)) throw new HrUdajeImportException('Pohlaví v plánu nemá platnou hodnotu.');
    // Normalizace je jen pro stare zdrojove texty; formulare uz posilaji ciselne ID.
    $normalize = static fn(string $s): string => strtr(mb_strtolower(trim($s), 'UTF-8'), ['ž' => 'z', 'é' => 'e']);
    $text = trim((string)$value);
    foreach ($genders as $gender) {
        if ((preg_match('/^\d+$/D', $text) && $text === (string)$gender['id'])
            || $normalize($text) === $normalize($gender['label'])) return $gender['id'];
    }
    throw new HrUdajeImportException('Pohlaví „' . $text . '“ není v aktivním číselníku. Opravte importní plán.');
}

/** Osobni opravy se nepropisuji do historie pracovnich pomeru; pohlavi pouziva ID. */
function hru_personal(HrUdajePlanner $p, mysqli $db): void
{
    $allowed=explode(',',hru_tables()['hr_osobni_udaje']);
    $genders=hr_fetch_genders($db);
    foreach ($p->plan['personal'] as $change) {
        $pid=(int)$change['id_person']; $field=$change['pole'];
        if (!in_array($field,$allowed,true) || in_array($field,['id_person','id_osobni_udaje','platny','zruseno','id_user_zadal','poznamka'],true)) throw new HrUdajeImportException('Nepovolené osobní pole plánu.');
        $rows=$p->find('hr_osobni_udaje',['id_person'=>$pid,'platny'=>1]);
        if (count($rows)!==1) { $p->warn($pid,'Osobní údaje nemají jediný platný záznam; nutné dořešit.'); continue; }
        $row=$rows[0]; $value=$change['navrh'];
        if ($field==='pohlavi') $value=hru_gender_id($value,$genders);
        // Obrana i proti starsimu manifestu: URL prilohy neni typ pobytu.
        if ($field==='pobyt_cr_typ' && preg_match('~https?://~i',(string)$value)) {
            $p->warn($pid,'Nutné dořešit: příloha dotazníku neurčuje typ pobytu; odkaz nebyl zapsán jako typ pobytu.');
            continue;
        }
        if (hru_equal($row[$field] ?? null,$value)) continue;
        $ok=false;
        foreach ($change['allowed_old'] as $old) {
            if ($field==='pohlavi') $old=hru_gender_id($old,$genders);
            if (hru_equal($row[$field] ?? null,$old)) $ok=true;
        }
        if (!$ok) { $p->warn($pid,'Osobní údaj '.$field.' byl mezitím změněn. Navrženo: '.(string)$value); continue; }
        $p->update('hr_osobni_udaje',$row,[$field=>$value],$pid);
    }
}

/** Smluvni mzda patri do hr_mzda; nikdy neprepisuje provozni COL sazby. */
function hru_contracts(HrUdajePlanner $p, mysqli $db): void
{
    $types=[];
    foreach ($db->query('SELECT id_pracovni_vztah_typ,nazev FROM hr_cis_pracovni_vztah_typ')->fetch_all(MYSQLI_ASSOC) as $r) $types[$r['nazev']]=(int)$r['id_pracovni_vztah_typ'];
    $wageTypes=[];
    foreach ($db->query('SELECT id_mzda_typ,kod FROM cis_mzda_typ')->fetch_all(MYSQLI_ASSOC) as $r) $wageTypes[$r['kod']]=(int)$r['id_mzda_typ'];
    $ids=[];
    foreach ($p->plan['relations'] as $r) {
        $pid=$r['id_person'];
        if (!isset($types[$r['typ']])) throw new HrUdajeImportException('Chybí typ vztahu '.$r['typ'].'.');
        $key=['id_person'=>$pid,'id_pracovni_vztah_typ'=>$types[$r['typ']],'datum_nastupu'=>$r['od'],'platny'=>1];
        $values=$r['do']!==null ? ['datum_ukonceni'=>$r['do']] : [];
        if (isset($r['zkusebni_doba_mesice'])) $values['zkusebni_doba_mesice']=$r['zkusebni_doba_mesice'];
        $found=$p->find('hr_pracovni_vztah',$key);
        if (!$found) {
            // Migracni datum lze opravit jen u jedineho importovaneho vztahu daneho typu.
            $old=$p->find('hr_pracovni_vztah',['id_person'=>$pid,'id_pracovni_vztah_typ'=>$types[$r['typ']],'platny'=>1]);
            $desired=array_values(array_filter($p->plan['relations'],fn($x)=>$x['id_person']===$pid && $x['typ']===$r['typ']));
            if (count($old)===1 && count($desired)===1 && str_starts_with((string)($old[0]['poznamka'] ?? ''),'Import z HR.zip')) {
                if (!$p->guarded('hr_pracovni_vztah',$old[0],['datum_nastupu'=>$r['od']],['id_person'=>$pid,'id_pracovni_vztah_typ'=>$types[$r['typ']],'datum_nastupu'=>$old[0]['datum_nastupu'],'platny'=>1],$pid)) continue;
            }
        }
        $row=$p->upsert('hr_pracovni_vztah',$key,$values,$pid);
        if (!$row) continue;
        $ids[$r['key']]=$row['id_pracovni_vztah'];
        $migrationType=$types['Doplnit (migrace)'] ?? null;
        if ($migrationType) foreach ($p->find('hr_pracovni_vztah',['id_person'=>$pid,'id_pracovni_vztah_typ'=>$migrationType,'platny'=>1]) as $placeholder) {
            $p->guarded('hr_pracovni_vztah',$placeholder,['platny'=>0,'zruseno'=>date('Y-m-d H:i:s')],['id_person'=>$pid,'id_pracovni_vztah_typ'=>$migrationType,'datum_nastupu'=>$placeholder['datum_nastupu'],'platny'=>1],$pid);
        }
        $message='Import z dokumentu / podkladu: '.implode('; ',$r['zdroje']);
        if (!empty($r['pozice'])) $message.='; Pozice ve smlouvě: '.$r['pozice'];
        foreach ($r['dodatky'] ?? [] as $appendix) if (!empty($appendix['pozice'])) $message.='; Dodatek pozice od '.implode(',',$appendix['ucinnost']).': '.$appendix['pozice'];
        if (!str_contains((string)($row['poznamka'] ?? ''),$message)) $row=$p->update('hr_pracovni_vztah',$row,['poznamka'=>trim((string)($row['poznamka'] ?? '')."\n".$message)],$pid);
        if (isset($r['hodin_tydne'])) {
            $u=$p->find('hr_pracovni_uvazek',['id_pracovni_vztah'=>$row['id_pracovni_vztah'],'platnost_od'=>$r['od'],'platny'=>1]);
            if (!$u) $p->insert('hr_pracovni_uvazek',['id_pracovni_vztah'=>$row['id_pracovni_vztah'],'platnost_od'=>$r['od'],'platny'=>1,'hodin_tydne'=>$r['hodin_tydne'],'platnost_do'=>$r['do']],$pid);
            elseif (count($u)===1 && !hru_equal($u[0]['hodin_tydne'],$r['hodin_tydne'])) {
                $baselineRelations=array_values(array_filter($p->plan['baseline']['hr_pracovni_vztah'],fn($x)=>(int)$x['id_person']===$pid && (int)$x['id_pracovni_vztah_typ']===$types[$r['typ']] && $x['datum_nastupu']===$r['od']));
                $baseIds=array_column($baselineRelations,'id_pracovni_vztah');
                $baseline=array_values(array_filter($p->plan['baseline']['hr_pracovni_uvazek'],fn($x)=>in_array($x['id_pracovni_vztah'],$baseIds,true) && $x['platnost_od']===$r['od'] && (int)$x['platny']));
                if (count($baseline)===1 && hru_equal($baseline[0]['hodin_tydne'],$u[0]['hodin_tydne'])) {
                    $p->update('hr_pracovni_uvazek',$u[0],['platny'=>0,'zruseno'=>date('Y-m-d H:i:s'),'id_user_zrusil'=>$p->plan['_user']],$pid);
                    $p->insert('hr_pracovni_uvazek',['id_pracovni_vztah'=>$row['id_pracovni_vztah'],'platnost_od'=>$r['od'],'platny'=>1,'hodin_tydne'=>$r['hodin_tydne'],'platnost_do'=>$r['do']],$pid);
                } else $p->warn($pid,'Dosavadní úvazek byl mezitím změněn. Smlouva: '.$r['hodin_tydne'].' h/týdně; nutné ověřit.');
            }
        }
    }
    foreach ($p->plan['wages'] as $w) {
        $pid=$w['id_person']; $id=$ids[$w['relation']] ?? null;
        if (!$id) { $p->warn($pid,'Mzdu nelze připojit k jednoznačnému pracovnímu vztahu.'); continue; }
        if (!isset($wageTypes[$w['typ']]) || $w['castka']<0 || floor($w['castka'])!=$w['castka']) throw new HrUdajeImportException('Smluvní mzda není podporovaná celočíselná částka.');
        $key=['id_pracovni_vztah'=>$id,'platnost_od'=>$w['od'],'platny'=>1];
        $rows=$p->find('hr_mzda',$key);
        if (count($rows)>1) { $p->warn($pid,'Více platných mezd se stejným datem; nutné dořešit.'); continue; }
        $desired=['id_mzda_typ'=>$wageTypes[$w['typ']],'castka'=>$w['castka'],'platnost_do'=>$w['do']];
        $currentRelation=$p->find('hr_pracovni_vztah',['id_pracovni_vztah'=>$id])[0];
        // Baseline vazba pouziva osobu/typ/nastup; ID vztahu se mohou mezi DB lisit.
        $baseRelations=array_values(array_filter($p->plan['baseline']['hr_pracovni_vztah'],fn($r)=>(int)$r['id_person']===$pid && (int)$r['id_pracovni_vztah_typ']===$types[explode('|',$w['relation'])[1]] && $r['datum_nastupu']===$currentRelation['datum_nastupu']));
        $baseIds=array_column($baseRelations,'id_pracovni_vztah');
        $baseWages=array_values(array_filter($p->plan['baseline']['hr_mzda'],fn($r)=>in_array($r['id_pracovni_vztah'],$baseIds,true) && $r['platnost_od']===$w['od'] && (int)$r['platny']));
        if ($rows && (!hru_equal($rows[0]['castka'],$desired['castka']) || !hru_equal($rows[0]['id_mzda_typ'],$desired['id_mzda_typ']))) {
            if (count($baseWages)!==1 || !hru_equal($baseWages[0]['castka'],$rows[0]['castka']) || !hru_equal($baseWages[0]['id_mzda_typ'],$rows[0]['id_mzda_typ'])) {
                $p->warn($pid,'Mzda byla mezitím změněna; návrh '.$w['castka'].' Kč od '.$w['od'].' nepřepsán.'); continue;
            }
            $p->update('hr_mzda',$rows[0],['platny'=>0,'zruseno'=>date('Y-m-d H:i:s'),'id_user_zrusil'=>$p->plan['_user']],$pid);
            $rows=[];
        }
        if (!$rows) $p->insert('hr_mzda',[...$key,...$desired],$pid);
        elseif ($w['do']!==null && !hru_equal($rows[0]['platnost_do'],$w['do'])) {
            if (count($baseWages)===1 && hru_equal($rows[0]['platnost_do'],$baseWages[0]['platnost_do'])) $p->update('hr_mzda',$rows[0],['platnost_do'=>$w['do']],$pid);
            else $p->warn($pid,'Konec platnosti mzdy byl mezitím změněn; nutné ověřit.');
        }
    }
}

/** Kontakty a jejich hlavni zaznam nesmi prepsat pozdejsi rucni rozhodnuti. */
function hru_contacts(HrUdajePlanner $p, mysqli $db): void
{
    $types=[];
    foreach (['email','telefon'] as $type) {
        $r=$db->query('SELECT id_'.$type.'_typ FROM hr_cis_'.$type.'_typ WHERE nazev=\'Soukromý\' AND aktivni=1')->fetch_all(MYSQLI_ASSOC);
        if (count($r)!==1) throw new HrUdajeImportException('Chybí soukromý typ kontaktu '.$type.'.');
        $types[$type]=(int)$r[0]['id_'.$type.'_typ'];
    }
    foreach ($p->plan['contacts'] as $r) {
        $pid=$r['id_person']; $type=$r['typ']; $table='hr_'.$type; $main=$r['hlavni'];
        if ($main) foreach ($p->find($table,['id_person'=>$pid,'hlavni'=>1,'platny'=>1]) as $old) {
            if (!hru_contact_equal($type,$old[$type],$r['hodnota']) && !$p->guarded($table,$old,['hlavni'=>0],['id_person'=>$pid,$type=>$old[$type],'platny'=>1],$pid)) $main=0;
        }
        $key=['id_person'=>$pid,$type=>$r['hodnota'],'platny'=>1];
        $existing=$p->find($table,$key);
        if (count($existing)>1) { $p->warn($pid,'Duplicitní kontakt; nutné dořešit.'); continue; }
        if ($existing) {
            // Typ a popis existujiciho firemniho kontaktu nejsou udaje noveho soukromeho podani.
            $p->guarded($table,$existing[0],['hlavni'=>$main],$key,$pid);
        } else {
            $values=['id_'.$type.'_typ'=>$types[$type],'hlavni'=>$main,'poznamka'=>'Import z podkladů HR'];
            if ($type==='telefon') $values['telefon_normalizovany']=$r['hodnota'];
            $p->insert($table,[...$key,...$values],$pid);
        }
    }
}

/** Doplni vazebni udaje do jejich skutecnych tabulek, bez zmen prihlasovaciho USER. */
function hru_entities(HrUdajePlanner $p, mysqli $db): void
{
    foreach ($p->plan['addresses'] as $r) {
        $pid=$r['id_person']; $key=['id_person'=>$pid,'typ'=>$r['typ'],'platny'=>1];
        $values=array_filter(array_intersect_key($r,array_flip(['ulice','cp','mesto','psc','stat'])),static fn($v)=>$v!==null);
        $p->upsert('hr_adresa',$key,$values,$pid);
    }
    hru_contacts($p,$db);
    foreach ($p->plan['accounts'] as $r) {
        $pid=$r['id_person']; $main=$r['hlavni'];
        // Zahranicni ucet ma jen IBAN; povinne tuzemske cislo zustava prazdne, nevymyslime je.
        $key=['id_person'=>$pid,'cislo_uctu'=>$r['cislo_uctu'] ?? '', 'kod_banky'=>$r['kod_banky'] ?? null,'iban'=>$r['iban'] ?? null];
        if ($main) foreach ($p->find('hr_bankovni_ucet',['id_person'=>$pid,'platny'=>1]) as $old) {
            if (hru_equal($old['cislo_uctu'],$key['cislo_uctu']) && hru_equal($old['kod_banky'],$key['kod_banky']) && hru_equal($old['iban'],$key['iban'])) continue;
            if (!$p->guarded('hr_bankovni_ucet',$old,['platny'=>0,'hlavni'=>0],['id_person'=>$pid,'cislo_uctu'=>$old['cislo_uctu'],'kod_banky'=>$old['kod_banky'],'iban'=>$old['iban']],$pid)) $main=0;
        }
        $values=['hlavni'=>$main,'platnost_od'=>$r['od'],'platny'=>$main];
        $existing=$p->find('hr_bankovni_ucet',$key);
        if (!$existing) {
            $values['poznamka']=($r['kontrolni_soucet'] ? '' : 'Nutné dořešit: chybný kontrolní součet. ').'Datum od = datum doloženého podání; přesnou účinnost ověřit.';
            $values['zmena']=date('Y-m-d H:i:s');
        }
        $p->upsert('hr_bankovni_ucet',$key,$values,$pid);
    }
    foreach ($p->plan['insurances'] as $r) {
        $pid=$r['id_person']; $valid=$r['hlavni'];
        $key=['id_person'=>$pid,'stat'=>$r['stat'],'kod_pojistovny'=>$r['kod']];
        if ($valid) foreach ($p->find('hr_zdravotni_pojisteni',['id_person'=>$pid,'platny'=>1]) as $old) if ($old['kod_pojistovny']!==$r['kod'] || $old['stat']!==$r['stat']) {
            $end=$r['od'] ? date('Y-m-d',strtotime($r['od'].' -1 day')) : null;
            if (!$p->guarded('hr_zdravotni_pojisteni',$old,['platny'=>0,'platnost_do'=>$end],['id_person'=>$pid,'stat'=>$old['stat'],'kod_pojistovny'=>$old['kod_pojistovny']],$pid)) $valid=0;
        }
        $values=['nazev_pojistovny'=>$r['nazev'],'platnost_od'=>$r['od'],'platnost_do'=>$r['do'],'platny'=>$valid];
        $existing=$p->find('hr_zdravotni_pojisteni',$key);
        if (!$existing) $values['poznamka']='Datum od = datum doloženého podání; přesnou účinnost ověřit.';
        elseif (count($existing)===1 && $existing[0]['platnost_od']!==null) unset($values['platnost_od']);
        $p->upsert('hr_zdravotni_pojisteni',$key,$values,$pid);
    }
    foreach ($p->plan['slots'] as $r) {
        $slot=hr_smlouvy_query($db,'SELECT id_slot FROM cis_slot WHERE slot=? AND aktivni=1',[$r['slot']])->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($slot)!==1) throw new HrUdajeImportException('Chybí slot '.$r['slot'].'.');
        $pid=$r['id_person']; $id=(int)$slot[0]['id_slot'];
        if ($p->find('hr_slot',['id_person'=>$pid,'id_slot'=>$id,'platny'=>1])) continue;
        if ($p->find('hr_slot',['id_person'=>$pid,'hlavni'=>1,'platny'=>1])) { $p->warn($pid,'Slot ze zdroje '.$r['slot'].' se liší od hlavního slotu; ponechán k ověření.'); continue; }
        $p->insert('hr_slot',['id_person'=>$pid,'id_slot'=>$id,'hlavni'=>1,'platnost_od'=>$r['od'],'platny'=>1],$pid);
    }
    $branchCounts=array_count_values(array_column($p->plan['workplaces'],'id_person'));
    foreach ($p->plan['workplaces'] as $r) {
        $pid=$r['id_person'];
        $branch=hr_smlouvy_query($db,'SELECT id_pob FROM pobocka WHERE kod=?',[$r['kod']])->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($branch)!==1) throw new HrUdajeImportException('Chybí pobočka '.$r['kod'].'.');
        $id=(int)$branch[0]['id_pob'];
        if (!$p->find('hr_pracoviste',['id_person'=>$pid,'id_pob'=>$id,'platny'=>1])) $p->insert('hr_pracoviste',['id_person'=>$pid,'id_pob'=>$id,'hlavni'=>0,'platnost_od'=>$r['od'],'platny'=>1],$pid);
        if ($branchCounts[$pid]===1 && !$p->find('hr_pracoviste_main',['id_person'=>$pid,'platny'=>1])) $p->insert('hr_pracoviste_main',['id_person'=>$pid,'id_pob'=>$id,'platnost_od'=>$r['od'],'vytvoreno'=>date('Y-m-d H:i:s'),'platny'=>1],$pid);
    }
    foreach ($p->plan['historical_months'] as $r) {
        $pid=$r['id_person']; unset($r['id_person']);
        // Existujici mesic, zejmena uzavreny nebo rucne upraveny, se zde neprepisuje.
        $key=array_intersect_key($r,array_flip(['id_firma','id_user','rok','mesic']));
        if (!$p->find('hr_mzdy_mesic',$key)) $p->insert('hr_mzdy_mesic',$r,$pid);
    }
}

/** Simulace ma stejny postup jako zapis; zapis vyzaduje transakci a zamek proti soubehu. */
function hru_run(mysqli $db, int $user, bool $write=false, ?callable $progress=null, ?string $expectedFingerprint=null, ?string $expectedPlanHash=null): array
{
    $plan=hru_manifest();
    if ($write && (!$expectedPlanHash || !hash_equals($expectedPlanHash,$plan['hash']))) throw new HrUdajeImportException('Importní plán se od náhledu změnil. Zopakujte kontrolu.');
    hru_schema($db); hru_sources($db,$plan,$progress);
    $plan['_write']=$write; $plan['_user']=$user;
    $p=null; $commitAttempted=false; $locked=false;
    try {
        if ($write) {
            $locked=(int)$db->query("SELECT GET_LOCK(CONCAT(DATABASE(),':hr_udaje_import'),0) AS ok")->fetch_assoc()['ok']===1;
            if (!$locked) throw new HrUdajeImportException('Jiný HR import údajů právě běží.');
        }
        $db->begin_transaction();
        if ($write) {
            // Zamykame skutecne menene tabulky pred nactenim baseline, ne pouze hr_person.
            foreach (hru_tables() as $table=>$fields) $db->query('SELECT '.explode(',',$fields)[0].' FROM '.$table.' FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
            $db->query('SELECT id_dokument FROM hr_dokument FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
            $db->query('SELECT id_dokument_soubor FROM hr_dokument_soubor FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
        }
        $p=new HrUdajePlanner($db,$plan,$user,$write);
        // Token nahledu je vazan na puvodni stav cilovych dat, nikoli na cas simulovanych insertu.
        $fingerprint=hash('sha256',json_encode($p->rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        if ($write && (!$expectedFingerprint || !hash_equals($expectedFingerprint,$fingerprint))) throw new HrUdajeImportException('HR data se od náhledu změnila. Zopakujte kontrolu.');
        $progress && $progress('Oprava schváleného přiřazení a osobní údaje.');
        hru_redirect($p,$db); hru_personal($p,$db);
        $progress && $progress('Smlouvy, zkušební doba, úvazky a smluvní mzdy.');
        hru_contracts($p,$db);
        $progress && $progress('Adresy, kontakty, účty, pojištění, zařazení a historické měsíce.');
        hru_entities($p,$db);
        foreach ($plan['notes'] as $note) $p->warn($note['id_person'],$note['text']);
        foreach ($p->warnings as $note) {
            $text='Import údajů HR: '.$note['text'];
            if (!$p->find('hr_poznamka',['id_person'=>$note['id_person'],'text_poznamky'=>$text])) $p->insert('hr_poznamka',['id_person'=>$note['id_person'],'text_poznamky'=>$text],$note['id_person']);
        }
        if ($write) { $commitAttempted=true; $db->commit(); }
        else $db->rollback();
        // Pri nejistem commitu zustanou oba soubory; stare odstranime jen po potvrzenem commitu.
        if ($write) foreach (array_unique($p->removeAfterCommit) as $file) if (is_file($file) && !unlink($file)) $p->warnings[]=['id_person'=>415,'text'=>'Stará kopie souboru zůstala; nové přiřazení je uloženo.'];
        return ['hash'=>$plan['hash'],'fingerprint'=>$fingerprint,'counts'=>$p->counts,'changes'=>$p->changes,'people'=>count(array_unique(array_column($p->changes,'id_person'))),'warnings'=>$p->warnings,'unsupported_fields'=>$plan['unsupported_fields']];
    } catch (Throwable $e) {
        try { $db->rollback(); } catch (Throwable) {}
        if (!$commitAttempted && $p) foreach ($p->created as $file) if (is_file($file)) unlink($file);
        throw $e;
    } finally {
        if ($locked) $db->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':hr_udaje_import'))");
    }
}
