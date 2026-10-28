<?php
declare(strict_types=1);

/* Účel: Spojuje dochovanou historii zaměstnance s auditem skutečných změn HR.
 * Datum zápisu neodvozuje z účinnosti. Neznámý historický autor a začátek zůstanou neuvedené.
 */

/** Přeloží názvy měněných údajů; neznámý sloupec zůstane čitelný i po rozšíření evidence. */
function hr_historie_pole(string $field): string
{
    $names = ['jmeno'=>'Jméno','prijmeni'=>'Příjmení','druhe_jmeno'=>'Druhé jméno','rodne_prijmeni'=>'Rodné příjmení',
        'titul_pred'=>'Titul před jménem','titul_za'=>'Titul za jménem','datum_narozeni'=>'Datum narození','rodne_cislo'=>'Rodné číslo',
        'cislo_obcanskeho_prukazu'=>'Číslo dokladu','pohlavi'=>'Pohlaví','zdr_poj'=>'Zdravotní pojišťovna',
        'statni_obcanstvi'=>'Státní občanství','misto_narozeni'=>'Místo narození','osobni_cislo'=>'Osobní číslo',
        'telefon'=>'Telefon','email'=>'E-mail','ulice'=>'Ulice','cp'=>'Číslo domu','mesto'=>'Město','psc'=>'PSČ','stat'=>'Stát',
        'cislo_uctu'=>'Číslo účtu','kod_banky'=>'Kód banky','iban'=>'IBAN','id_slot'=>'Slot','id_funkce'=>'Funkce / pozice',
        'id_role'=>'Role','id_pob'=>'Pobočka','id_oblast_prace'=>'Oblast','v_treninku'=>'V tréninku','platny'=>'Platný záznam',
        'platnost_od'=>'Platí od','platnost_do'=>'Platí do','datum_nastupu'=>'Nástup','datum_ukonceni'=>'Ukončení',
        'aktivni'=>'Aktivní','overen'=>'Ověřen','kompletni'=>'Kompletní','poznamka'=>'Poznámka','castka'=>'Částka',
        'id_mzda_typ'=>'Typ mzdy','id_pracovni_vztah_typ'=>'Typ vztahu','id_cis_benefit'=>'Benefit','uvazek'=>'Úvazek',
        'hodin_tydne'=>'Hodin týdně','datum_od'=>'Od','datum_do'=>'Do','hlavni'=>'Hlavní','zruseno'=>'Zneplatněno',
        'foto'=>'Fotografie','vztah'=>'Vztah','id_firma'=>'Firma','id_user'=>'Uživatelský účet'];
    return $names[$field] ?? ucfirst(str_replace('_', ' ', $field));
}

/** Hodnoty z číselníků zobrazuje názvem, nikoli samotným ID. */
function hr_historie_hodnota(mysqli $db, string $field, mixed $value): string
{
    if ($value === null || $value === '') return '—';
    if (in_array($field, ['platny','aktivni','overen','kompletni','v_treninku','hlavni'], true)) return (int)$value === 1 ? 'ano' : 'ne';
    $catalogs = ['id_slot'=>['cis_slot','id_slot','slot'], 'id_funkce'=>['cis_funkce','id_funkce','nazev'],
        'id_pob'=>['pobocka','id_pob','nazev'], 'id_oblast_prace'=>['cis_oblast_prace','id_oblast_prace','nazev'],
        'id_cis_benefit'=>['hr_cis_benefit','id_cis_benefit','nazev'],
        'id_pracovni_vztah_typ'=>['hr_cis_pracovni_vztah_typ','id_pracovni_vztah_typ','nazev'],
        'id_mzda_typ'=>['cis_mzda_typ','id_mzda_typ','nazev']];
    if (isset($catalogs[$field])) {
        static $cache = [];
        [$table,$pk,$name] = $catalogs[$field]; $id = (int)$value;
        $key = $table.':'.$id;
        if (!array_key_exists($key,$cache)) $cache[$key] = $db->query("SELECT {$name} FROM {$table} WHERE {$pk}={$id}")->fetch_row()[0] ?? '#'.$id;
        return (string)$cache[$key];
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}(?: .*)?$/D',(string)$value)) {
        return date(strlen((string)$value)>10 ? 'j. n. Y H:i:s' : 'j. n. Y',strtotime((string)$value));
    }
    return (string)$value;
}

/** Změnu vyjádří jako původní → nová hodnota, bez opakování technických klíčů řádku. */
function hr_historie_detail(mysqli $db, array $old, array $new): string
{
    $lines = [];
    $internal = ['id_person','id_osobni_udaje','id_telefon','id_email','id_adresa','id_bankovni_ucet','id_nouzovy_kontakt',
        'id_hr_slot','id_hr_funkce','id_main','id_hr_misto_vykonu_prace','id_hr_misto_vykonu_prace_polozka',
        'id_pracovni_vztah','id_pracovni_uvazek','id_mzda','id_benefit','id_pracovni_preruseni','id_pracovni_ukonceni'];
    foreach (array_unique(array_merge(array_keys($old),array_keys($new))) as $field) {
        if (in_array($field,$internal,true)) continue;
        $lines[] = hr_historie_pole($field).': '.hr_historie_hodnota($db,$field,$old[$field] ?? null)
            .' → '.hr_historie_hodnota($db,$field,$new[$field] ?? null);
    }
    return implode("\n",$lines);
}

/** Vrátí audit karty a označení založení, která nahrazuje starší odvozené události. */
function hr_historie_audit(mysqli $db, int $idPerson): array
{
    $stmt = $db->prepare("SELECT a.*, CONCAT(u.prijmeni,' ',u.jmeno) AS zapsal FROM user_akce_new a
        LEFT JOIN hr_person p ON p.id_user=a.id_user
        LEFT JOIN hr_osobni_udaje u ON u.id_person=p.id_person AND u.platny=1
        WHERE a.id_modul=3 AND a.objekt='hr_person' AND a.id_objektu=?
        AND JSON_UNQUOTE(JSON_EXTRACT(a.detail_json,'$.zdroj'))='hr_audit' ORDER BY a.cas DESC,a.id_user_akce DESC");
    $stmt->bind_param('i',$idPerson); $stmt->execute();
    $events = []; $created = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $detail = json_decode((string)$row['detail_json'],true) ?: [];
        $success = (int)$row['vysledek'] === 1;
        if ($success && ($detail['udalost'] ?? '') === 'pridani') $created[$detail['tabulka'].':'.$detail['id_zaznamu']] = true;
        $events[] = ['kdy'=>$row['cas'],'akce'=>($success ? '' : 'Neuloženo: ').($detail['popis'] ?? 'Změna karty'),
            'plati_od'=>$detail['platnost_od'] ?? null,'plati_do'=>$detail['platnost_do'] ?? null,
            'zapsal'=>$row['zapsal'] ?: 'Uživatel #'.$row['id_user'],
            'poznamka'=>$success ? hr_historie_detail($db,json_decode((string)$row['hodnota_old'],true) ?: [],json_decode((string)$row['hodnota_new'],true) ?: []) : (string)$row['err_msg']];
    }
    $stmt->close();
    return ['events'=>$events,'created'=>$created];
}

/** Starší přiřazení a verze údajů doplní do stejné historie jako změny pracovního poměru. */
function hr_historie_starsi(mysqli $db, int $idPerson): array
{
    $definitions = [
        ['hr_person','id_person','id_user_zadal',"'Založen zaměstnanec'",'',null,null,'vytvoreno'],
        ['hr_slot','id_hr_slot','id_user_zadal',"CONCAT('Přidělen slot: ',c.slot)",'JOIN cis_slot c ON c.id_slot=t.id_slot','platnost_od','platnost_do','vytvoreno'],
        ['hr_funkce','id_hr_funkce','id_person_zadal',"CONCAT(IF(c.zarazeni='manazerske','Přidělena manažerská pozice: ','Přidělena funkce: '),c.nazev,IF(t.v_treninku=1,' – v tréninku',''))",'JOIN cis_funkce c ON c.id_funkce=t.id_funkce','platnost_od','platnost_do','vytvoreno'],
        ['hr_pracoviste_main','id_main','id_user_zadal',"CONCAT('MAIN pobočka: ',c.nazev)",'JOIN pobocka c ON c.id_pob=t.id_pob','platnost_od','platnost_do','vytvoreno'],
        ['hr_misto_vykonu_prace','id_hr_misto_vykonu_prace','id_user_zadal',"'Místo výkonu práce'",'','platnost_od','platnost_do','vytvoreno'],
        ['hr_osobni_udaje','id_osobni_udaje','id_user_zadal',"'Uložena verze osobních údajů'",'',null,null,'vytvoreno'],
        ['hr_telefon','id_telefon','id_user_zadal',"'Uložena verze telefonu'",'',null,null,'vytvoreno'],
        ['hr_email','id_email','id_user_zadal',"'Uložena verze e-mailu'",'',null,null,'vytvoreno'],
        ['hr_adresa','id_adresa','id_user_zadal',"'Uložena verze adresy'",'',null,null,'vytvoreno'],
        ['hr_nouzovy_kontakt','id_nouzovy_kontakt','id_user_zadal',"'Uložen nouzový kontakt'",'',null,null,'vytvoreno'],
        ['hr_bankovni_ucet','id_bankovni_ucet',null,"'Uložena verze bankovního účtu'",'',null,null,'zadano'],
        ['hr_poznamka','id_poznamka','id_user_zadal',"'Přidána poznámka'",'',null,null,'vytvoreno'],
    ];
    $events = [];
    foreach ($definitions as [$table,$pk,$actor,$action,$join,$od,$do,$time]) {
        // Starý bankovní účet přepisoval autora při zneplatnění; neuvádíme jej jako autora založení.
        $actorJoin = $actor === null ? '' : ($actor === 'id_person_zadal'
            ? 'LEFT JOIN hr_osobni_udaje au ON au.id_person=t.id_person_zadal AND au.platny=1'
            : 'LEFT JOIN hr_person ap ON ap.id_user=t.'.$actor.' LEFT JOIN hr_osobni_udaje au ON au.id_person=ap.id_person AND au.platny=1');
        $actorExpr = $actor === null ? 'NULL' : "CONCAT(au.prijmeni,' ',au.jmeno)";
        $odExpr = $od === null ? 'NULL' : 't.'.$od; $doExpr = $do === null ? 'NULL' : 't.'.$do;
        $note = $table === 'hr_poznamka' ? 't.text_poznamky' : 'NULL';
        $sql = "SELECT t.{$time} AS kdy,{$action} AS akce,{$odExpr} AS plati_od,{$doExpr} AS plati_do,
            {$actorExpr} AS zapsal,{$note} AS poznamka,'{$table}' AS tabulka,t.{$pk} AS id_zaznamu
            FROM {$table} t {$join} {$actorJoin} WHERE t.id_person=?";
        $stmt = $db->prepare($sql); $stmt->bind_param('i',$idPerson); $stmt->execute();
        array_push($events,...$stmt->get_result()->fetch_all(MYSQLI_ASSOC)); $stmt->close();
    }
    return $events;
}

/** Obě místa karty používají stejný seznam; již auditovaná založení se neukazují podruhé. */
function hr_historie_spojit(mysqli $db, int $idPerson, array $work): array
{
    $audit = hr_historie_audit($db,$idPerson);
    $events = $audit['events'];
    foreach (array_merge($work,hr_historie_starsi($db,$idPerson)) as $event) {
        $key = ($event['tabulka'] ?? '').':'.($event['id_zaznamu'] ?? '');
        if (isset($audit['created'][$key])) continue;
        $events[] = $event;
    }
    usort($events,static fn(array $a,array $b):int=>strcmp((string)$b['kdy'],(string)$a['kdy']));
    return $events;
}
