<?php
/*
 * Ucel souboru: Predava POST akce HR spravnemu modulovemu handleru.
 * Nova akce se vybira podle cb_action; starsi stranky zustavaji beze zmeny
 * do sve samostatne migrace.
 * Akce na testovaci osoby odmita pred predanim zapisovacimu handleru.
 */
declare(strict_types=1);

/**
 * Preda rozpoznanou POST akci HR prislusnemu handleru.
 */
function cb_hr_request_dispatch(mysqli $db, string $page, array $user): void
{
    $isShellRequest = isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE']);
    $isFormPost = ($_SERVER['REQUEST_METHOD'] === 'POST') && !$isShellRequest;
    if (!$isFormPost) {
        return;
    }

    $action = trim((string)($_POST['cb_action'] ?? ''));
    // Sprava oblasti ma vlastni kontrolu prava a firem; GET nikdy nic neuklada.
    if (in_array($action,['hr_oblast_ulozit','hr_pobocka_oblast_ulozit'],true)) {
        require_once __DIR__ . '/../hr_db/hr_oblasti_sprava.php';
        $url=cb_root_url('index.php?m=hr&page=nastaveni&navrat_person='.max(0,(int)($_POST['navrat_person']??0)));
        try {
            if ($action==='hr_oblast_ulozit') hr_oblast_ulozit($db,(int)($_POST['id']??0),(string)($_POST['nazev']??''),isset($_POST['aktivni']));
            else hr_pobocka_oblast_ulozit($db,(int)($_POST['id_pob']??0),(int)($_POST['id_oblast_prace']??0),hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od']??''),'Platí od'),(int)($user['id_user']??0));
            cb_form_finish($url,true,'Oblast byla uložena.');
        } catch (Throwable $e) { cb_form_finish($url,false,cb_hr_chyba_text($e,'Správa oblastí HR')); }
        return;
    }
    // Skryti testovaci osoby plati i pro rucne odeslany formular, nejen pro seznam.
    $employeeAction = str_starts_with($action, 'hr_zamestnanec_')
        || in_array($action, ['hr_pracovni_pomer_upravit', 'hr_pracovni_preruseni_ulozit',
            'hr_pracovni_preruseni_uzavrit', 'hr_pracovni_pomer_ukoncit'], true);
    if ($employeeAction && hr_zamestnanci_je_testovaci($db, (int)($_POST['id_person'] ?? 0))) {
        cb_form_finish(cb_root_url('index.php?m=hr&page=zamestnanci'), false,
            'Testovací účet není vedený jako zaměstnanec v HR.');
        return;
    }
    // Centralni sprava slotu a funkci ma vlastni kontrolu prava 319 v DB vrstve.
    if ($action === 'hr_ciselnik_ulozit') {
        hr_post_ciselnik_ulozit($db);
        return;
    }
    if ($action === 'hr_zamestnanec_ulozit') {
        hr_post_zamestnanec($db, (int)($user['id_user'] ?? 0));
        return;
    }
    if ($action === 'hr_zamestnanec_upravit') {
        hr_post_zamestnanec_uprava($db, (int)($user['id_user'] ?? 0));
        return;
    }
    if ($action === 'hr_pracovni_pomer_upravit') {
        hr_post_pracovni_pomer_uprava($db);
        return;
    }
    if ($action === 'hr_pracovni_preruseni_ulozit') {
        hr_post_pracovni_preruseni_ulozit($db, (int)($user['id_user'] ?? 0));
        return;
    }
    if ($action === 'hr_pracovni_preruseni_uzavrit') {
        hr_post_pracovni_preruseni_uzavrit($db, (int)($user['id_user'] ?? 0));
        return;
    }
    if ($action === 'hr_pracovni_pomer_ukoncit') {
        hr_post_pracovni_pomer_ukoncit($db, (int)($user['id_user'] ?? 0));
        return;
    }
    if ($action === 'hr_zamestnanec_overit') {
        hr_post_zamestnanec_overit($db);
        return;
    }
    if ($action === 'hr_pozadavek_vytvorit') {
        hr_post_pozadavek_vytvorit($db, $user);
        return;
    }
    if ($action === 'hr_pozadavek_zrusit') {
        hr_post_pozadavek_zrusit($db);
        return;
    }
    if ($action === 'hr_pozice_pridat') {
        hr_post_pozice_pridat($db);
        return;
    }
    if ($action === 'hr_pozice_zmenit_stav') {
        hr_post_pozice_zmenit_stav($db);
        return;
    }
    if ($action === 'hr_zamestnanec_pozice_zmenit') {
        hr_post_zamestnanec_pozice_zmenit($db, (int)($user['id_user'] ?? 0));
        return;
    }
    if ($action === 'hr_zamestnanec_funkce_zmenit') {
        hr_post_zamestnanec_funkce_zmenit($db, (int)($user['id_user'] ?? 0));
        return;
    }
    if ($action === 'hr_zamestnanec_pobocky_zmenit') {
        hr_post_zamestnanec_pobocky_zmenit($db, (int)($user['id_user'] ?? 0));
        return;
    }

    if ($action === 'hr_nabor_ulozit_akci' && $page === 'nabor') {
        hr_post_nabor($db);
    }

}
