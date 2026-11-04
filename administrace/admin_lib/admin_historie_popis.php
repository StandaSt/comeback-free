<?php
declare(strict_types=1);
/* Účel: Převádí evidované události na stručné názvy a popisy; z klientského ovládání nevytváří potvrzenou změnu. */

/** Stejné popisky používá vykreslení i SQL filtr nad celým výsledkem. */
function cb_historie_popisky(): array
{
    $types = [1=>'Otevřen modul',2=>'Otevřena stránka',3=>'Změněno období',4=>'Vybrána pobočka',5=>'Změněn filtr',6=>'Zrušen filtr',7=>'Změněna stránka tabulky',8=>'Změněn počet řádků',9=>'Změněna hodnota',10=>'Přidána položka',11=>'Odebrána položka',12=>'Odeslán / uložen formulář',13=>'Zveřejněno',14=>'Správa / export / import',15=>'Spuštěna operace',16=>'Změna nastavení',17=>'Otevřen detail',18=>'Ukončeno ovládání',19=>'Kliknutí bez přechodu',20=>'Odmítnuto',21=>'Žádost o obnovu hesla'];
    $events = ['zverejneno'=>'Zveřejněn rozpis','pridana_smena'=>'Přidána směna','zmenena_smena'=>'Změněna směna','odebrana_smena'=>'Odebrána směna','pozadavky_ulozeny'=>'Uloženy požadavky','oznameni_potvrzeno'=>'Potvrzeno oznámení','oznameni_vyhrady'=>'Vyjádřeny výhrady','stranka_zobrazena'=>'Zobrazena stránka','stranka_opustena'=>'Opuštěna stránka','menu_klik'=>'Kliknutí v menu','formular_odeslan'=>'Odeslán formulář','formular_neplatny'=>'Neplatný formulář'];
    $objects=['cis_prava'=>'Úprava oprávnění','prava_global'=>'Změna práv role','prava_vyjimky'=>'Změna individuálního oprávnění','hr_import_udaje'=>'Import údajů HR','hr_import_smlouvy'=>'Import smluv','google_reporty_import'=>'Import Google reportů','restia_katalog'=>'Synchronizace katalogu Restia','server_data_export'=>'Export serverových dat','db_export'=>'Export databáze'];
    return ['typy'=>$types,'udalosti'=>$events,'objekty'=>$objects];
}

/** Typy společné historie odpovídají stávajícím zapisovačům, nikoli novému logování. */
function cb_historie_popis(array $row): array
{
    $context = json_decode((string)($row['kontext'] ?? ''), true);
    $context = is_array($context) ? $context : [];
    ['typy'=>$types, 'udalosti'=>$events, 'objekty'=>$objects] = cb_historie_popisky();
    $title = (string)$row['akce'];
    $state = (int)$row['vysledek'] === 1 ? 'Evidováno' : 'Neúspěch';
    if ($row['zdroj'] === 'akce') {
        $code = (string)($context['udalost'] ?? '');
        $title = $code !== '' ? ($events[$code] ?? ucfirst(str_replace('_', ' ', $code))) : ($types[(int)$row['typ']] ?? 'Akce IS');
        if ($code==='' && isset($objects[$row['objekt']])) $title=$objects[$row['objekt']];
        $state = ($context['puvod'] ?? '') === 'prohlizec' ? 'Ovládání' : (($context['faze'] ?? '') === 'potvrzeno' ? 'Potvrzeno' : $state);
        if ((int)$row['vysledek'] !== 1) $state = 'Neúspěch';
    } elseif ($row['zdroj'] === 'prihlaseni') {
        $title = (int)$row['typ'] === 1 ? 'Přihlášení' : 'Odhlášení / ukončení relace';
    } elseif ($row['zdroj'] === 'co_link') {
        $title = cb_historie_co_popisky()[$title] ?? ucfirst(str_replace('_', ' ', $title));
    } elseif ($row['zdroj'] === 'smeny') {
        $title = $events[$title] ?? ucfirst(str_replace('_', ' ', $title));
    } elseif (str_starts_with($row['zdroj'], 'hr_') || $row['zdroj'] === 'reporty') $state = 'Uložený podklad';
    $parts = [];
    foreach (['page','stranka'] as $key) if (!empty($context[$key]) && is_scalar($context[$key])) { $parts[] = str_replace('_', ' ', (string)$context[$key]); break; }
    if (!empty($row['objekt'])) $parts[] = str_replace('_', ' ', (string)$row['objekt']).((int)$row['objekt_id'] > 0 ? ' #'.$row['objekt_id'] : '');
    if ($row['zdroj'] === 'co_link' && !empty($context['cil'])) $parts[] = (string)$context['cil'];
    return ['nazev'=>$title, 'stav'=>$state, 'kontext'=>implode(' · ', $parts)];
}

/** Audit Co-Linku popisuje operaci bez obsahu zprávy. */
function cb_historie_co_popisky(): array
{
    return ['zprava_odeslana'=>'Odeslal zprávu','priloha_pripojena'=>'Přidal přílohu ke zprávě',
        'co_link_otevren'=>'Otevřel Co-Link','zprava_prectena'=>'Přečetl zprávu',
        'zprava_smazana'=>'Odstranil vlastní zprávu','zprava_upravena'=>'Upravil zprávu',
        'nastaveni_zmeneno'=>'Změnil nastavení Co-Linku','ztiseni_zmeneno'=>'Změnil ztišení konverzace',
        'kanal_nastaven'=>'Změnil nastavení kanálu','kanal_vytvoren'=>'Vytvořil kanál',
        'konverzace_vytvorena'=>'Založil konverzaci','reakce_pridana'=>'Přidal reakci','reakce_odebrana'=>'Odebral reakci',
        'zarizeni_pripojeno'=>'Připojil zařízení','zarizeni_odvolano'=>'Odvolal zařízení',
        'zarizeni_odhlaseno'=>'Odhlásil zařízení','push_povolen'=>'Povolil upozornění na zařízení'];
}
