<?php
declare(strict_types=1);

/* Ucel: Jednotne cteni mist vykonu prace, zarazeni osoby a chyb jejich vzajemnych vazeb.
 * Oblasti a konkretni pobocky se sjednocuji; rozsah nikdy nenahrazuje pravo k akci.
 */

// Primo vybrana pobocka pokryje celou verzi a nema se znovu pridavat pres casove clenstvi v oblasti.
function cb_hr_mista_sql(): string
{
    return "(SELECT h.id_hr_misto_vykonu_prace AS id_pracoviste,h.id_person,b.id_pob,
        h.platnost_od,h.platnost_do,h.id_user_zadal,h.vytvoreno,h.platny,h.zruseno
        FROM hr_misto_vykonu_prace h
        JOIN hr_misto_vykonu_prace_polozka i ON i.id_hr_misto_vykonu_prace=h.id_hr_misto_vykonu_prace
        JOIN hr_person osoba ON osoba.id_person=h.id_person
        JOIN pobocka b ON b.id_pob=i.id_pob AND b.id_firma=osoba.id_firma AND b.aktivni=1
        WHERE i.id_oblast_prace IS NULL
        UNION
        SELECT h.id_hr_misto_vykonu_prace,h.id_person,b.id_pob,
        GREATEST(h.platnost_od,a.platnost_od),
        NULLIF(LEAST(COALESCE(h.platnost_do,'9999-12-31'),COALESCE(a.platnost_do,'9999-12-31')),'9999-12-31'),
        h.id_user_zadal,h.vytvoreno,h.platny,h.zruseno
        FROM hr_misto_vykonu_prace h
        JOIN hr_misto_vykonu_prace_polozka i ON i.id_hr_misto_vykonu_prace=h.id_hr_misto_vykonu_prace
        JOIN cis_oblast_prace o ON o.id_oblast_prace=i.id_oblast_prace AND o.aktivni=1
        JOIN pobocka_oblast_prace a ON a.id_oblast_prace=o.id_oblast_prace AND a.platny=1 AND a.zruseno IS NULL
        JOIN hr_person osoba ON osoba.id_person=h.id_person
        JOIN pobocka b ON b.id_pob=a.id_pob AND b.id_firma=osoba.id_firma AND b.aktivni=1
        WHERE i.id_pob IS NULL
          AND NOT EXISTS (SELECT 1 FROM hr_misto_vykonu_prace_polozka prime
              WHERE prime.id_hr_misto_vykonu_prace=h.id_hr_misto_vykonu_prace AND prime.id_pob=b.id_pob)
          AND GREATEST(h.platnost_od,a.platnost_od)<=LEAST(COALESCE(h.platnost_do,'9999-12-31'),COALESCE(a.platnost_do,'9999-12-31')))";
}

// Spolecny predikat lze pouzit s parametrem data i SQL vyrazem; argumenty jsou vyhradne interni SQL.
function cb_hr_ucinne_sql(string $alias, string $datum = 'CURRENT_DATE()'): string
{
    return "$alias.platny=1 AND $alias.zruseno IS NULL
        AND ($alias.platnost_od IS NULL OR $alias.platnost_od<=$datum)
        AND ($alias.platnost_do IS NULL OR $alias.platnost_do>=$datum)";
}

// Cte konkretni funkci k datu; nerozhoduje podle premenovatelneho nazvu ani samotne role.
function cb_hr_funkce_osoby(mysqli $db, int $idPerson, ?string $datum = null): ?array
{
    $datum ??= date('Y-m-d');
    $stmt=$db->prepare('SELECT c.*,f.v_treninku FROM hr_funkce f JOIN cis_funkce c ON c.id_funkce=f.id_funkce
        WHERE f.id_person=? AND f.platny=1 AND (f.platnost_od IS NULL OR f.platnost_od<=?)
        AND (f.platnost_do IS NULL OR f.platnost_do>=?)');
    $stmt->bind_param('iss',$idPerson,$datum,$datum); $stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return count($rows)===1 ? $rows[0] : null;
}

// Nazev oblasti nebo vycet konkretnich pobocek se vypisuje i u vypomoci v planovani.
function cb_hr_mista_popis(mysqli $db, int $idPerson, ?string $datum = null): string
{
    $datum ??= date('Y-m-d');
    $stmt=$db->prepare("SELECT DISTINCT COALESCE(o.nazev,b.nazev) AS nazev
        FROM hr_misto_vykonu_prace h JOIN hr_misto_vykonu_prace_polozka i USING(id_hr_misto_vykonu_prace)
        LEFT JOIN cis_oblast_prace o ON o.id_oblast_prace=i.id_oblast_prace
        LEFT JOIN pobocka b ON b.id_pob=i.id_pob
        WHERE h.id_person=? AND " . cb_hr_ucinne_sql('h','?') . ' ORDER BY nazev');
    $stmt->bind_param('iss',$idPerson,$datum,$datum); $stmt->execute();
    $names=array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'nazev'); $stmt->close();
    return implode(', ',$names);
}

// Pravidla zarazeni jsou chybami HR, vcetne chybejici MAIN; neodhaduji zadnou opravnou hodnotu.
function cb_hr_zarazeni_chyby(mysqli $db, int $idPerson, ?string $datum = null): array
{
    $datum ??= date('Y-m-d');
    $person=(int)$idPerson;
    $date="'".$db->real_escape_string($datum)."'";
    $slots=$db->query("SELECT id_slot FROM hr_slot s WHERE s.id_person=$person AND ".cb_hr_ucinne_sql('s',$date))->fetch_all(MYSQLI_ASSOC);
    $main=$db->query("SELECT id_pob FROM hr_pracoviste_main m WHERE m.id_person=$person AND ".cb_hr_ucinne_sql('m',$date))->fetch_all(MYSQLI_ASSOC);
    $covered=array_map('intval',array_column($db->query('SELECT DISTINCT w.id_pob FROM '.cb_hr_mista_sql()." w WHERE w.id_person=$person AND ".cb_hr_ucinne_sql('w',$date))->fetch_all(MYSQLI_ASSOC),'id_pob'));
    $function=cb_hr_funkce_osoby($db,$person,$datum);
    $headers=(int)$db->query("SELECT COUNT(*) FROM hr_misto_vykonu_prace h WHERE h.id_person=$person AND ".cb_hr_ucinne_sql('h',$date))->fetch_row()[0];
    return cb_hr_zarazeni_chyby_z_dat($function,$slots,array_map('intval',array_column($main,'id_pob')),$covered,$headers);
}

// Cista pravidla lze overit i pro dosud neulozeny stav bez zapisu testovacich osob do databaze.
function cb_hr_zarazeni_chyby_z_dat(?array $function, array $slots, array $main, array $covered, int $headers): array
{
    $manager=($function['zarazeni']??'')==='manazerske';
    $errors=[];
    if ($headers!==1 || $covered===[]) $errors[]='Chybí jednoznačné místo výkonu práce.';
    if ($manager && $slots!==[]) $errors[]='Manažerská pozice nesmí mít pracovní slot.';
    if ($manager && $main!==[]) $errors[]='Manažerská pozice nesmí mít MAIN pobočku.';
    if (!$manager && $slots===[]) $errors[]='Chybí pracovní slot nebo manažerská pozice.';
    if (($function['zarazeni']??'')==='provozni' && $slots===[]) $errors[]='Provozní funkce osoby nemá pracovní slot.';
    if ($slots!==[] && $main===[]) $errors[]='Pracovník se slotem nemá MAIN pobočku.';
    $ids=array_map('intval',$main);
    if (count($ids)!==count(array_unique($ids))) $errors[]='Stejná MAIN pobočka je přidělena vícekrát.';
    if (array_diff($ids,$covered)!==[]) $errors[]='MAIN pobočka nepatří do místa výkonu práce.';
    return $errors;
}

// Zamknuti osoby sdileji zapis mist, slotu a funkci; soubezne formulare se nemohou obejit.
function cb_hr_osobu_zamkni(mysqli $db, int $idPerson): void
{
    // Sdileny zamek umozni soubezne editace osob, ale vycka na zmenu oblasti nebo clenstvi pobocky.
    $db->query('SELECT id_oblast_prace FROM cis_oblast_prace ORDER BY id_oblast_prace LOCK IN SHARE MODE')->free();
    $stmt=$db->prepare('SELECT id_person FROM hr_person WHERE id_person=? FOR UPDATE');
    $stmt->bind_param('i',$idPerson); $stmt->execute();
    if ($stmt->get_result()->num_rows!==1) throw new CbUserVisibleException('Zaměstnanec nebyl nalezen.');
    $stmt->close();
}
