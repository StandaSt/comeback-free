<?php
declare(strict_types=1);

/* Účel: Vymezuje skutečné zapisovací akce HR a bezpečné snímky jejich dat pro audit.
 * Identifikátory tabulek a podmínky pocházejí výhradně z této pevné definice, nikdy z POST.
 */

/** Každá akce dispatcheru má výslovně určený rozsah a srozumitelný název. */
function hr_audit_plan(string $action, array $input): ?array
{
    $actions = [
        'hr_zamestnanec_ulozit'=>['Založen zaměstnanec','osoba'],
        'hr_zamestnanec_upravit'=>['Změněny osobní údaje','osoba'],
        'hr_zamestnanec_foto_ulozit'=>['Fotografie zaměstnance','osoba'],
        'hr_zamestnanec_overit'=>['Ověřena karta zaměstnance','osoba'],
        'hr_pracovni_pomer_upravit'=>['Změněn pracovní poměr','osoba'],
        'hr_pracovni_preruseni_ulozit'=>['Evidováno přerušení','osoba'],
        'hr_pracovni_preruseni_uzavrit'=>['Ukončeno přerušení','osoba'],
        'hr_pracovni_pomer_ukoncit'=>['Ukončen pracovní poměr','osoba'],
        'hr_zamestnanec_pozice_zmenit'=>['Změněny pracovní sloty a funkce','osoba'],
        'hr_zamestnanec_funkce_zmenit'=>['Změněna manažerská pozice','osoba'],
        'hr_zamestnanec_pobocky_zmenit'=>['Změněno místo výkonu práce a MAIN','osoba'],
        'hr_nabor_ulozit_akci'=>['Uložena náborová akce','nabor'],
        'hr_pozadavek_vytvorit'=>['Vytvořen HR požadavek','pozadavek'],
        'hr_pozadavek_zrusit'=>['Zrušen HR požadavek','pozadavek'],
        'hr_pobocka_pridat'=>['Přidána pobočka','pobocky'],
        'hr_pobocka_zmenit_stav'=>['Změněna aktivita pobočky','pobocky'],
        'hr_pozice_pridat'=>['Přidán pracovní slot do číselníku','ciselniky'],
        'hr_pozice_zmenit_stav'=>['Změněna aktivita pracovního slotu','ciselniky'],
        'hr_ciselnik_ulozit'=>['Změněn číselník HR','ciselniky'],
        'hr_oblast_ulozit'=>['Změněna oblast výkonu práce','oblasti'],
        'hr_pobocka_oblast_ulozit'=>['Změněno zařazení pobočky do oblasti','oblasti'],
    ];
    if (!isset($actions[$action])) return null;
    [$label, $group] = $actions[$action];
    $date = DateTimeImmutable::createFromFormat('!d.m.Y',trim((string)($input['platnost_od'] ?? '')));
    return ['action'=>$action,'label'=>$label,'group'=>$group,'person'=>$group === 'osoba' ? max(0,(int)($input['id_person'] ?? 0)) : 0,
        'object'=>$group === 'nabor' ? 'hr_vd' : 'hr_'.$group,
        'id'=>max(0,(int)($input['id_vd'] ?? $input['id_pozadavek'] ?? $input['id_pob'] ?? $input['id'] ?? 0)),
        'od'=>$date === false ? null : $date->format('Y-m-d')];
}

/** Načte pouze související řádky; z účtu výslovně vynechá hesla a bezpečnostní údaje. */
function hr_audit_snimky(mysqli $db, array $plan): array
{
    $person = (int)$plan['person']; $id = (int)$plan['id'];
    $definitions = [];
    if ($plan['group'] === 'osoba' && $person > 0) {
        foreach (['hr_person'=>'id_person','hr_osobni_udaje'=>'id_osobni_udaje','hr_telefon'=>'id_telefon',
            'hr_email'=>'id_email','hr_adresa'=>'id_adresa','hr_nouzovy_kontakt'=>'id_nouzovy_kontakt',
            'hr_bankovni_ucet'=>'id_bankovni_ucet','hr_pracovni_vztah'=>'id_pracovni_vztah',
            'hr_slot'=>'id_hr_slot','hr_funkce'=>'id_hr_funkce','hr_pracoviste_main'=>'id_main',
            'hr_misto_vykonu_prace'=>'id_hr_misto_vykonu_prace','hr_pristupovy_profil'=>'id_person'] as $table=>$pk) {
            $definitions[$table] = [$pk, "SELECT t.* FROM {$table} t WHERE t.id_person={$person} ORDER BY t.{$pk} FOR UPDATE"];
        }
        foreach (['hr_mzda'=>'id_mzda','hr_pracovni_uvazek'=>'id_pracovni_uvazek','hr_benefit'=>'id_benefit',
            'hr_pracovni_preruseni'=>'id_pracovni_preruseni','hr_pracovni_ukonceni'=>'id_pracovni_ukonceni'] as $table=>$pk) {
            $definitions[$table] = [$pk, "SELECT t.* FROM {$table} t JOIN hr_pracovni_vztah v ON v.id_pracovni_vztah=t.id_pracovni_vztah WHERE v.id_person={$person} ORDER BY t.{$pk} FOR UPDATE"];
        }
        $definitions['hr_misto_vykonu_prace_polozka'] = ['id_hr_misto_vykonu_prace_polozka', "SELECT t.* FROM hr_misto_vykonu_prace_polozka t JOIN hr_misto_vykonu_prace m ON m.id_hr_misto_vykonu_prace=t.id_hr_misto_vykonu_prace WHERE m.id_person={$person} ORDER BY t.id_hr_misto_vykonu_prace_polozka FOR UPDATE"];
        // USER již může obsahovat jen přihlášení, zatímco starší server má ještě profilové sloupce.
        $userColumns = array_column($db->query('SHOW COLUMNS FROM user')->fetch_all(MYSQLI_ASSOC),'Field');
        $safeColumns = array_intersect(['id_user','email','aktivni','in_system','schvalen','id_firma','zdroj','jmeno','prijmeni'],$userColumns);
        $select = implode(',',array_map(static fn(string $column):string=>'u.'.$column,$safeColumns));
        $definitions['user'] = ['id_user', "SELECT {$select} FROM user u JOIN hr_person p ON p.id_user=u.id_user WHERE p.id_person={$person} FOR UPDATE"];
    } elseif ($plan['group'] === 'nabor') {
        foreach (['hr_vd'=>'id_vd','hr_vd_akce'=>'id_vd_akce','hr_vd_podminky'=>'id_vd_podminky'] as $table=>$pk) {
            $definitions[$table] = [$pk, "SELECT t.* FROM {$table} t WHERE t.id_vd={$id} ORDER BY t.{$pk} FOR UPDATE"];
        }
    } elseif ($plan['group'] === 'pozadavek') {
        $actor = (int)($_SESSION['cb_user']['id_user'] ?? 0);
        $where = $plan['action'] === 'hr_pozadavek_zrusit' ? "id_pozadavek={$id}" : "id_user_zadal={$actor}";
        $definitions['hr_pozadavek'] = ['id_pozadavek', "SELECT * FROM hr_pozadavek WHERE {$where} ORDER BY id_pozadavek FOR UPDATE"];
    } else {
        $catalogs = match ($plan['group']) {
            'pobocky'=>['pobocka'=>'id_pob'],
            'oblasti'=>['cis_oblast_prace'=>'id_oblast_prace','pobocka_oblast_prace'=>'id_pobocka_oblast_prace'],
            'ciselniky'=>['cis_slot'=>'id_slot','cis_funkce'=>'id_funkce','hr_pristupovy_profil'=>'id_person'],
            default=>[],
        };
        foreach ($catalogs as $table=>$pk) $definitions[$table] = [$pk, "SELECT * FROM {$table} ORDER BY {$pk} FOR UPDATE"];
    }
    $snapshots = [];
    foreach ($definitions as $table=>[$pk,$sql]) {
        $rows = $db->query($sql)->fetch_all(MYSQLI_ASSOC);
        $snapshots[$table] = [];
        foreach ($rows as $row) {
            // Veřejný dotazník obsahuje osobní údaje, ale ani jeho audit nepotřebuje síťovou diagnostiku.
            unset($row['ip_adresa'], $row['user_agent']);
            $snapshots[$table][(int)$row[$pk]] = $row;
        }
    }
    return $snapshots;
}

/** Změny zařazení pojmenuje názvem z číselníku; číselná ID zůstanou v podrobnostech. */
function hr_audit_popis_udalosti(mysqli $db, array $event, string $fallback): string
{
    $table = $event['table']; $row = $event['row'];
    $added = $event['kind'] === 'pridani';
    if ($fallback==='Fotografie zaměstnance' && $table==='hr_osobni_udaje') {
        return empty($event['old']['foto']) ? 'Vložena fotografie zaměstnance' : 'Změněna fotografie zaměstnance';
    }
    if ($table === 'hr_slot') {
        $id = (int)$row['id_slot'];
        $name = (string)($db->query('SELECT slot FROM cis_slot WHERE id_slot='.$id)->fetch_row()[0] ?? $id);
        $ended = array_key_exists('platnost_do',$event['new']) || (isset($event['new']['platny']) && (int)$event['new']['platny'] === 0);
        return ($added ? 'Přidělen slot: ' : ($ended ? 'Ukončen slot: ' : 'Změněn slot: ')).$name;
    }
    if ($table === 'hr_funkce') {
        $id = (int)$row['id_funkce'];
        $catalog = $db->query('SELECT nazev,zarazeni FROM cis_funkce WHERE id_funkce='.$id)->fetch_assoc();
        $manager = ($catalog['zarazeni'] ?? '') === 'manazerske';
        return ($added ? ($manager ? 'Přidělena manažerská pozice: ' : 'Přidělena funkce: ')
            : ((int)($row['platny'] ?? 1) === 0 ? ($manager ? 'Ukončena manažerská pozice: ' : 'Ukončena funkce: ')
            : ($manager ? 'Změněna manažerská pozice: ' : 'Změněna funkce: '))).($catalog['nazev'] ?? $id);
    }
    $names = ['hr_osobni_udaje'=>'Osobní údaje','hr_telefon'=>'Telefon','hr_email'=>'E-mail','hr_adresa'=>'Adresa',
        'hr_nouzovy_kontakt'=>'Nouzový kontakt','hr_bankovni_ucet'=>'Bankovní účet','hr_pracovni_vztah'=>'Pracovní vztah',
        'hr_mzda'=>'Mzda','hr_pracovni_uvazek'=>'Úvazek','hr_benefit'=>'Benefit','hr_pracoviste_main'=>'MAIN pobočka',
        'hr_misto_vykonu_prace'=>'Místo výkonu práce','hr_misto_vykonu_prace_polozka'=>'Rozsah místa výkonu práce',
        'hr_pristupovy_profil'=>'Přístupový profil','user'=>'Uživatelský účet'];
    return isset($names[$table]) ? $names[$table].' — '.($added ? 'přidání' : ($event['kind'] === 'odebrani' ? 'odebrání' : 'změna')) : $fallback;
}

/** Výměna verze kvůli portrétu je jediná změna fotografie, nikoli změna všech zkopírovaných osobních údajů. */
function hr_audit_foto_udalosti(array $before, array $after): array
{
    $active = static function (array $snapshot): ?array {
        $rows=array_filter($snapshot['hr_osobni_udaje'] ?? [],static fn(array $row):bool=>(int)$row['platny']===1);
        krsort($rows,SORT_NUMERIC);
        return $rows===[] ? null : reset($rows);
    };
    $old=$active($before);$new=$active($after);
    if ($old===null || $new===null) throw new LogicException('Audit fotografie nemá původní nebo novou verzi.');
    if (($old['foto'] ?? null)===($new['foto'] ?? null)) return [];
    // ID nové verze zabrání druhému zobrazení jejího založení v odvozené starší historii.
    return [['table'=>'hr_osobni_udaje','id'=>(int)$new['id_osobni_udaje'],'kind'=>'pridani','row'=>$new,
        'old'=>['foto'=>$old['foto'] ?? null],'new'=>['foto'=>$new['foto'] ?? null]]];
}
