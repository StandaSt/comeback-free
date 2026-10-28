<?php
declare(strict_types=1);
/* Účel: Popisuje existující zdroje historie a jejich význam; neukládá ani nekopíruje události.
 * Mzdové změny HR chrání stejně v doménové historii i ve společném auditu. */

/** Pevná definice sjednotí sloupce různých evidencí pro společné filtrování a řazení. */
function cb_historie_zdroj(string $label, string $from, string $pk, string $time, string $actor, int $module, string $action, string $person = 'NULL', string $branch = 'NULL', string $day = 'NULL', string $where = '1=1'): array
{
    return ['label'=>$label, 'from'=>$from, 'pk'=>$pk, 'time'=>$time, 'actor'=>$actor, 'module'=>(string)$module,
        'action'=>"'".str_replace("'", "''", $action)."'", 'person'=>$person, 'branch'=>$branch, 'day'=>$day, 'where'=>$where,
        'object'=>"''", 'object_id'=>$pk, 'result'=>'1', 'type'=>'0', 'context'=>'NULL'];
}

/** HR používá jednotný rozsah osob; mzdy a benefity vyžadují navíc mzdové oprávnění. */
function cb_historie_zdroje(mysqli $db, array $scope): array
{
    $person = "CASE WHEN t.objekt IN ('hr_person','person','zamestnanec') THEN t.id_objektu ELSE CAST(JSON_UNQUOTE(JSON_EXTRACT(t.detail_json,'$.id_person')) AS UNSIGNED) END";
    $hrAllowed = "EXISTS (SELECT 1 FROM hr_person hp WHERE hp.id_person={$person} AND ({$scope['hr']}))";
    $hrAuditSalary = $scope['mzdy'] ? '1=1'
        : "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(t.detail_json,'$.tabulka')),'') NOT IN ('hr_mzda','hr_benefit','hr_vd_podminky')";
    $sources = [];
    $catalogAllowed=cb_historie_pravo(319)?"t.objekt IN ('cis_slot','cis_funkce','cis_org_funkce')":'0=1';
    $sources['akce'] = cb_historie_zdroj('Akce IS', 'user_akce_new t', 't.id_user_akce', 't.cas', 't.id_user', 0, '', $person,
        "CAST(JSON_UNQUOTE(JSON_EXTRACT(t.detail_json,'$.id_pob')) AS UNSIGNED)", "JSON_UNQUOTE(JSON_EXTRACT(t.detail_json,'$.datum'))",
        "(t.id_modul<>3 OR (({$hrAllowed} OR ({$catalogAllowed})) AND ({$hrAuditSalary})))");
    $sources['akce'] = array_replace($sources['akce'], ['module'=>'t.id_modul', 'object'=>'t.objekt', 'object_id'=>'t.id_objektu', 'type'=>'t.id_user_akce_typ', 'result'=>'t.vysledek', 'context'=>'t.detail_json']);
    $sources['prihlaseni'] = cb_historie_zdroj('Přihlášení', 'user_login t', 't.id_login', 't.kdy', 't.id_user', 0, 'Přihlášení / odhlášení');
    $sources['prihlaseni']['type'] = 't.akce';
    // Rozpisy s vazbou na společnou historii se nezobrazují podruhé. Starší samostatné audity zůstanou dostupné.
    if (cb_historie_pravo(403)) {
        $sources['smeny'] = cb_historie_zdroj('Audit Směn', 'smeny_audit t', 't.id_smeny_audit', 't.vytvoreno',
            '(SELECT a.id_user FROM hr_person a WHERE a.id_person=t.id_person_akce)', 4, 'Změna ve Směnách', 'NULL', 'NULL', 'NULL',
            "NOT EXISTS (SELECT 1 FROM user_akce_new a WHERE a.objekt=t.objekt AND a.id_objektu=t.id_objektu AND CAST(JSON_UNQUOTE(JSON_EXTRACT(a.detail_json,'$.id_smeny_audit')) AS UNSIGNED)=t.id_smeny_audit)");
        $sources['smeny']['action'] = 't.akce';
        $sources['smeny']['object'] = 't.objekt';
        $sources['smeny']['object_id'] = 't.id_objektu';
    }
    $hr = [
        'osoba'=>['hr_person','id_person','vytvoreno','id_user_zadal','Založen zaměstnanec'],
        'osobni'=>['hr_osobni_udaje','id_osobni_udaje','vytvoreno','id_user_zadal','Uložena verze osobních údajů'],
        'telefon'=>['hr_telefon','id_telefon','vytvoreno','id_user_zadal','Uložena verze telefonu'],
        'email'=>['hr_email','id_email','vytvoreno','id_user_zadal','Uložena verze e-mailu'],
        'adresa'=>['hr_adresa','id_adresa','vytvoreno','id_user_zadal','Uložena verze adresy'],
        'nouzovy'=>['hr_nouzovy_kontakt','id_nouzovy_kontakt','vytvoreno','id_user_zadal','Uložen nouzový kontakt'],
        'ucet'=>['hr_bankovni_ucet','id_bankovni_ucet','zadano',null,'Uložena verze bankovního účtu'],
        'vztah'=>['hr_pracovni_vztah','id_pracovni_vztah','vytvoreno','id_user_zadal','Uložena verze pracovního vztahu'],
        'slot'=>['hr_slot','id_hr_slot','vytvoreno','id_user_zadal','Přiřazen pracovní slot'],
        'funkce'=>['hr_funkce','id_hr_funkce','vytvoreno','id_person_zadal','Přiřazena funkce / pozice'],
        'main'=>['hr_pracoviste_main','id_main','vytvoreno','id_user_zadal','Přiřazena MAIN pobočka'],
        'mista'=>['hr_misto_vykonu_prace','id_hr_misto_vykonu_prace','vytvoreno','id_user_zadal','Uložena místa výkonu práce'],
        'uvazek'=>['hr_pracovni_uvazek','id_pracovni_uvazek','vytvoreno','id_user_zadal','Uložena verze úvazku'],
        'mzda'=>['hr_mzda','id_mzda','vytvoreno','id_user_zadal','Uložena verze mzdy'],
        'benefit'=>['hr_benefit','id_benefit','vytvoreno','id_user_zadal','Přiřazen benefit'],
        'preruseni'=>['hr_pracovni_preruseni','id_pracovni_preruseni','vytvoreno',null,'Evidováno přerušení pracovního poměru'],
        'ukonceni'=>['hr_pracovni_ukonceni','id_pracovni_ukonceni','vytvoreno','id_user_zadal','Evidováno ukončení pracovního poměru'],
    ];
    foreach ($hr as $key=>[$table,$pk,$time,$actor,$action]) {
        if (!cb_historie_pravo(301)) continue;
        if (in_array($key, ['mzda','benefit'], true) && !$scope['mzdy']) continue;
        $viaRelation = in_array($key, ['uvazek','mzda','benefit','preruseni','ukonceni'], true);
        $from = "{$table} t".($viaRelation ? ' JOIN hr_pracovni_vztah v ON v.id_pracovni_vztah=t.id_pracovni_vztah' : '');
        $personId = $viaRelation ? 'v.id_person' : 't.id_person';
        // Při ukončení účtu či úpravě přerušení se původní autor přepisoval; nesmí být vydáván za autora založení.
        $actorSql = $actor === null ? 'NULL' : ($actor === 'id_person_zadal' ? '(SELECT a.id_user FROM hr_person a WHERE a.id_person=t.id_person_zadal)' : 't.'.$actor);
        $sources['hr_'.$key] = cb_historie_zdroj('HR · '.$action, $from, 't.'.$pk, 't.'.$time, $actorSql, 3, $action, $personId,
            $key === 'main' ? 't.id_pob' : 'NULL', 'NULL', "EXISTS (SELECT 1 FROM hr_person hp WHERE hp.id_person={$personId} AND ({$scope['hr']}))");
    }
    if ($scope['report']!=='0=1') {
        $sources['reporty'] = cb_historie_zdroj('Denní reporty', 'reporty_is r', 'r.id_reportu', 'r.zadano', 'r.zadal', 2,
            'Uložena finální verze reportu', 'NULL', 'r.id_pob', 'r.datum_reportu', $scope['report']);
        $sources['reporty']['object'] = "'report'";
    }
    if (cb_historie_pravo(210)) {
        $sources['franta'] = cb_historie_zdroj('Franta', 'ai_analytik_audit t', 't.id_ai_analytik_audit', 't.created_at', 't.id_user', 2, 'Zadán požadavek Frantovi');
        $sources['franta']['result'] = "CASE WHEN t.status IN ('error','fatal_error','rejected') THEN 0 ELSE 1 END";
    }
    if (cb_historie_pravo(106)) $sources['chyby'] = cb_historie_zdroj('Chyby', 'log_chyby t', 't.id_log_chyby', 't.kdy', 't.id_user', 0, 'Zaznamenána chyba');
    if (isset($sources['chyby'])) {
        $sources['chyby']['result'] = '0';
        $sources['chyby']['module'] = "CASE LOWER(t.modul) WHEN 'hr' THEN 3 WHEN 'provoz' THEN 2 WHEN 'smeny' THEN 4 WHEN 'administrace' THEN 1 WHEN 'helpdesk' THEN 6 WHEN 'ukoly' THEN 5 ELSE 0 END";
    }
    if (cb_historie_pravo(110)) {
        $firms = implode(',', array_map('intval',$scope['firmy'])) ?: '0';
        // Stejně jako archiv sdělení nepovolí zprávu s příjemcem z cizí firmy.
        $allowed = "s.odeslano<=NOW() AND NOT EXISTS (SELECT 1 FROM admin_sdeleni_prijemce pr LEFT JOIN hr_person hp ON hp.id_user=pr.id_user WHERE pr.id_sdeleni=s.id_sdeleni AND (hp.id_firma IS NULL OR hp.id_firma NOT IN ({$firms})))";
        $sources['sdeleni'] = cb_historie_zdroj('Sdělení správce', 'admin_sdeleni s', 's.id_sdeleni', 's.odeslano', 's.id_user_vytvoril', 1, 'Odesláno sdělení', 'NULL','NULL','NULL',$allowed);
        foreach (['zobrazeno'=>'Zobrazeno sdělení','precteno'=>'Potvrzeno přečtení sdělení'] as $time=>$action) {
            $sources['sdeleni_'.$time] = cb_historie_zdroj('Sdělení · '.$action,'admin_sdeleni_prijemce t JOIN admin_sdeleni s ON s.id_sdeleni=t.id_sdeleni','t.id_sdeleni_prijemce','t.'.$time,'t.id_user',1,$action,'NULL','NULL','NULL',$allowed);
        }
    }
    if (cb_historie_pravo(600)) {
        require_once __DIR__.'/../../helpdesk/hl_lib/hl_prava.php';
        $user=(int)$scope['user'];
        // Přebíráme stávající SQL viditelnosti; parametry této definice jsou výhradně číselná ID.
        $visibility=cb_helpdesk_visible_scope($user); $parts=explode('?', $visibility['sql']); $visible=array_shift($parts);
        foreach ($visibility['params'] as $index=>$value) $visible.=(int)$value.$parts[$index];
        $company=$db->query('SELECT COALESCE(id_firma,1) FROM hr_person WHERE id_person='.$user)->fetch_row();
        $firm=max(1,(int)($company[0]??1));
        $areas=cb_helpdesk_is_admin()?'1=1':cb_helpdesk_allowed_area_condition('h');
        $allowed="COALESCE(h.id_firma,1)={$firm} AND ({$areas}) AND ({$visible})";
        $sources['helpdesk'] = cb_historie_zdroj('Helpdesk · zprávy','helpdesk_zprava t JOIN helpdesk h ON h.id_helpdesk=t.id_helpdesk','t.id_helpdesk_zprava','t.vytvoreno','t.id_user',6,'Přidána zpráva do Helpdesku','NULL','NULL','NULL',$allowed);
        $sources['helpdesk']['object']="'helpdesk'"; $sources['helpdesk']['object_id']='t.id_helpdesk';
    }
    if (cb_historie_pravo(106)) {
        // Zadaný účet je pouze cílem pokusu. Nepřihlášeného původce nepřipisujeme majiteli účtu a heslo nikdy nečteme.
        $sources['bad_login']=cb_historie_zdroj('Neúspěšná přihlášení','user_bad_login t','t.id_bad_login','t.kdy','NULL',0,'Neúspěšný pokus o přihlášení',
            '(SELECT p.id_person FROM hr_person p JOIN user u ON u.id_user=p.id_user WHERE u.email=t.email LIMIT 1)');
        $sources['bad_login']['result']='0';
        $sources['api_smeny']=cb_historie_zdroj('Diagnostika · API Směny','api_smeny t','t.id_api_smeny','t.kdy_start','t.id_user',4,'Volání API Směn');
        $sources['api_smeny']['result']='t.ok';
        $sources['api_restia']=cb_historie_zdroj('Diagnostika · API Restia','api_restia t','t.id_api_restia','t.kdy_start','t.id_user',2,'Volání API Restia');
        $sources['api_restia']['result']="CASE WHEN t.http_status BETWEEN 200 AND 299 AND (t.chyba IS NULL OR t.chyba='') THEN 1 ELSE 0 END";
        $sources['vykon']=cb_historie_zdroj('Diagnostika · požadavky a DB','user_akce_db t','t.id','t.cas_start','t.id_user',0,'Změřen požadavek');
        // U doručení push je uživatel příjemcem, nikoli autorem systémového odeslání.
        $sources['push']=cb_historie_zdroj('Diagnostika · push','push_audit t','t.id','t.vytvoreno','NULL',0,'Pokus o doručení push','(SELECT p.id_person FROM hr_person p WHERE p.id_user=t.id_user)');
        $sources['push']['result']="CASE WHEN t.stav='ok' THEN 1 ELSE 0 END";
        foreach (['api_smeny','api_restia','vykon','push'] as $key) $sources[$key]['technical']=true;
    }
    return $sources;
}
