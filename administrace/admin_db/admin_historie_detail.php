<?php
declare(strict_types=1);
/* Účel: Čte detail již autorizované události; vrací pouze povolené hodnoty, nikdy hesla, tokeny, prompty ani technické JSON obsahy. */

/** Detail ukazuje uložený podklad; nedoplňuje neexistující audit zásahů mezi jednotlivými verzemi. */
function cb_historie_detail(mysqli $db, array $event, array $source, array $scope): array
{
    $fields = []; $note = ''; $link = '';
    $key = $event['zdroj'];
    if (str_starts_with($key, 'hr_')) {
        $person = (int)$event['osoba'];
        $link = cb_root_url('index.php?m=hr&page=zamestnanec&id='.$person);
        $note = 'Historický podklad. Datum platnosti se může lišit od času zápisu; ukončení záznamu samo o sobě nedokládá autora ukončení.';
        $labels = ['platnost_od'=>'Platnost od','platnost_do'=>'Platnost do','datum_nastupu'=>'Nástup','datum_ukonceni'=>'Ukončení','datum_od'=>'Od','datum_do'=>'Do',
            'jmeno'=>'Jméno','prijmeni'=>'Příjmení','telefon'=>'Telefon','email'=>'E-mail','ulice'=>'Ulice','cp'=>'Číslo domu','mesto'=>'Město','psc'=>'PSČ',
            'id_slot'=>'ID slotu','id_funkce'=>'ID funkce','v_treninku'=>'V tréninku','id_pob'=>'ID pobočky','hodin_tydne'=>'Hodin týdně','uvazek'=>'Úvazek','id_pracovni_vztah_typ'=>'ID typu vztahu'];
        if ($scope['mzdy']) $labels += ['castka'=>'Částka mzdy','id_mzda_typ'=>'ID typu mzdy','id_cis_benefit'=>'ID benefitu'];
        $stmt = $db->prepare('SELECT t.* FROM '.$source['from'].' WHERE '.$source['pk'].'=?');
        $id = (int)$event['id']; $stmt->bind_param('i', $id); $stmt->execute(); $data = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
        if ($key !== 'hr_osoba') foreach ($labels as $field=>$label) if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') $fields[$label] = (string)$data[$field];
        // U stabilních číselníků zobrazíme současný název i původní ID; změna názvu nemění identitu historického podkladu.
        foreach (['id_slot'=>['cis_slot','slot','Pracovní slot'], 'id_funkce'=>['cis_funkce','nazev','Funkce / pozice'], 'id_pob'=>['pobocka','nazev','Pobočka']] as $field=>[$table,$name,$label]) {
            if ((int)($data[$field] ?? 0) <= 0) continue;
            $stmt=$db->prepare("SELECT {$name} FROM {$table} WHERE {$field}=?"); $value=(int)$data[$field];
            $stmt->bind_param('i',$value); $stmt->execute(); $found=$stmt->get_result()->fetch_row(); $stmt->close();
            unset($fields[$labels[$field]]); $fields[$label]=$found?(string)$found[0].' · #'.$value:'#'.$value;
        }
        if ($key === 'hr_ucet') $note .= ' Původní autor založení bankovního účtu není spolehlivě doložen; číslo účtu se zde nezobrazuje.';
        if ($key === 'hr_mista') {
            $stmt=$db->prepare('SELECT b.nazev AS pobocka,o.nazev AS oblast FROM hr_misto_vykonu_prace_polozka p LEFT JOIN pobocka b ON b.id_pob=p.id_pob LEFT JOIN cis_oblast_prace o ON o.id_oblast_prace=p.id_oblast_prace WHERE p.id_hr_misto_vykonu_prace=? ORDER BY oblast,pobocka');
            $stmt->bind_param('i',$id); $stmt->execute(); $places=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
            $fields['Oblasti / pobočky']=implode(', ',array_map(static fn(array $place):string=>(string)($place['oblast']??$place['pobocka']),$places));
        }
    } elseif ($key === 'akce') {
        $stmt = $db->prepare('SELECT pole,hodnota_old,hodnota_new,detail_json FROM user_akce_new WHERE id_user_akce=?');
        $id = (int)$event['id']; $stmt->bind_param('i', $id); $stmt->execute(); $data = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
        $context = json_decode((string)($data['detail_json'] ?? ''), true);
        $context = is_array($context) ? $context : [];
        // Obecný log není oprávněním k osobním či mzdovým hodnotám ani k citlivému obsahu jiných modulů.
        if ((int)$event['modul'] === 1 && in_array($data['pole'] ?? '', ['aktivni','povoleno','pristup','poradi'], true)) {
            foreach (['pole'=>'Pole','hodnota_old'=>'Původní hodnota','hodnota_new'=>'Nová hodnota'] as $field=>$label) if (($data[$field] ?? '') !== '' && $data[$field] !== null) $fields[$label] = mb_substr((string)$data[$field], 0, 1000);
        }
        foreach (['udalost'=>'Událost','faze'=>'Fáze','puvod'=>'Původ','page'=>'Stránka','stranka'=>'Stránka','tyden_od'=>'Týden od','datum'=>'Datum','cas_od'=>'Čas od','cas_do'=>'Čas do','id_smeny_audit'=>'ID auditu Směn','prebrani_prav_id_user'=>'Profil použitých práv'] as $field=>$label) if (isset($context[$field]) && is_scalar($context[$field])) $fields[$label] = (string)$context[$field];
        $note = 'Ovládání v prohlížeči je odlišené od serverem potvrzeného uložení.';
    } elseif ($key === 'reporty') {
        $id = (int)$event['id'];
        $stmt = $db->prepare('SELECT r.platny,r.zdroj,p.hotovost,p.terminal,p.stravenky FROM reporty_is r LEFT JOIN reporty_is_pokladna p ON p.id_reportu=r.id_reportu WHERE r.id_reportu=?');
        $stmt->bind_param('i',$id); $stmt->execute(); $data = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
        $fields['Verze'] = !empty($data['platny']) ? 'Aktuálně platná' : 'Historická';
        $fields['Zdroj dat'] = (int)($data['zdroj'] ?? 0) === 1 ? 'Import Google' : 'IS';
        foreach (['hotovost'=>'Hotovost','terminal'=>'Terminál','stravenky'=>'Stravenky'] as $field=>$label) if (($data[$field] ?? null) !== null) $fields[$label] = cb_format('p', $data[$field]);
        $note = 'Finální uložená verze. Průběžné úpravy formuláře nejsou zpětně doplňovány.';
        // Odkaz je záměrně bez verze: původní stránka zobrazuje aktuálně platný report daného dne.
        $link = cb_root_url('index.php?'.http_build_query(['m'=>'provoz','page'=>'denni_report','zr_archive'=>1,'zr_id_pob'=>$event['pobocka'],'datum_reportu'=>$event['datum']]));
    } elseif ($key === 'franta') {
        $id = (int)$event['id']; $stmt = $db->prepare('SELECT model,status,duration_ms FROM ai_analytik_audit WHERE id_ai_analytik_audit=?');
        $stmt->bind_param('i',$id); $stmt->execute(); $data = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
        foreach (['model'=>'Model','status'=>'Stav','duration_ms'=>'Doba (ms)'] as $field=>$label) $fields[$label] = (string)($data[$field] ?? '');
    } elseif (in_array($key,['api_smeny','api_restia','vykon','push'],true)) {
        $columns=match($key){
            'api_smeny'=>['ms'=>'Doba (ms)','ok'=>'Výsledek API'],
            'api_restia'=>['ms'=>'Doba (ms)','metoda'=>'Metoda','endpoint'=>'Endpoint','http_status'=>'HTTP stav','pocet_zaznamu'=>'Počet záznamů'],
            'vykon'=>['metoda'=>'Metoda','request_ms'=>'Požadavek (ms)','sql_count'=>'SQL dotazů','sql_total_ms'=>'SQL celkem (ms)','status'=>'Stav měření'],
            'push'=>['typ'=>'Typ push','stav'=>'Stav odeslání','http_status'=>'HTTP stav']};
        $id=(int)$event['id'];$stmt=$db->prepare('SELECT '.implode(',',array_keys($columns)).' FROM '.$source['from'].' WHERE '.$source['pk'].'=?');
        $stmt->bind_param('i',$id);$stmt->execute();$data=$stmt->get_result()->fetch_assoc()?:[];$stmt->close();
        foreach($columns as $field=>$label) if(($data[$field]??null)!==null) $fields[$label]=(string)$data[$field];
        $note=$key==='push'?'Úspěšné odeslání push nepotvrzuje zobrazení na zařízení ani přečtení.':'Technický záznam může vzniknout automaticky při práci uživatele; nedokládá samostatný ruční zásah.';
    } elseif ($key === 'bad_login') {
        $note='Majitel zadaného účtu je pouze cílem pokusu. Původce nebyl přihlášen a nelze mu přiřadit ověřeného autora. Zadané heslo se zde nečte ani nezobrazuje.';
    } elseif ($key === 'prihlaseni') {
        $id=(int)$event['id']; $stmt=$db->prepare('SELECT akce,duvod FROM user_login WHERE id_login=?');
        $stmt->bind_param('i',$id); $stmt->execute(); $data=$stmt->get_result()->fetch_assoc()?:[]; $stmt->close();
        $fields['Událost']=(int)($data['akce']??0)===1?'Přihlášení':'Odhlášení / ukončení relace';
        $fields['Kód důvodu']=(string)($data['duvod']??'');
        $note='Záznam přihlášení není dokladem nepřetržité aktivity uživatele.';
    } elseif ($key === 'chyby') {
        $id=(int)$event['id']; $stmt=$db->prepare('SELECT modul,akce,kod FROM log_chyby WHERE id_log_chyby=?');
        $stmt->bind_param('i',$id); $stmt->execute(); $data=$stmt->get_result()->fetch_assoc()?:[]; $stmt->close();
        foreach (['modul'=>'Modul','akce'=>'Akce','kod'=>'Kód chyby'] as $field=>$label) $fields[$label]=(string)($data[$field]??'');
        $link=cb_root_url('index.php?m=administrace&page=log_chyby');
    }
    return ['fields'=>$fields, 'note'=>$note, 'link'=>$link];
}
