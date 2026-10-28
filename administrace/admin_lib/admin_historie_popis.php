<?php
declare(strict_types=1);
/* Účel: Převádí evidované události na stručné názvy a popisy; z klientského ovládání nevytváří potvrzenou změnu. */

/** Typy společné historie odpovídají stávajícím zapisovačům, nikoli novému logování. */
function cb_historie_popis(array $row): array
{
    $context = json_decode((string)($row['kontext'] ?? ''), true);
    $context = is_array($context) ? $context : [];
    $types = [1=>'Otevřen modul',2=>'Otevřena stránka',3=>'Změněno období',4=>'Vybrána pobočka',5=>'Změněn filtr',6=>'Zrušen filtr',7=>'Změněna stránka tabulky',8=>'Změněn počet řádků',9=>'Změněna hodnota',10=>'Přidána položka',11=>'Odebrána položka',12=>'Odeslán / uložen formulář',13=>'Zveřejněno',14=>'Správa / export / import',15=>'Spuštěna operace',16=>'Změna nastavení',17=>'Otevřen detail',18=>'Ukončeno ovládání',19=>'Kliknutí bez přechodu',20=>'Odmítnuto',21=>'Žádost o obnovu hesla'];
    $events = ['zverejneno'=>'Zveřejněn rozpis','pridana_smena'=>'Přidána směna','zmenena_smena'=>'Změněna směna','odebrana_smena'=>'Odebrána směna','pozadavky_ulozeny'=>'Uloženy požadavky','oznameni_potvrzeno'=>'Potvrzeno oznámení','oznameni_vyhrady'=>'Vyjádřeny výhrady','stranka_zobrazena'=>'Zobrazena stránka','stranka_opustena'=>'Opuštěna stránka','menu_klik'=>'Kliknutí v menu','formular_odeslan'=>'Odeslán formulář','formular_neplatny'=>'Neplatný formulář'];
    $title = (string)$row['akce'];
    $state = (int)$row['vysledek'] === 1 ? 'Evidováno' : 'Neúspěch';
    if ($row['zdroj'] === 'akce') {
        $code = (string)($context['udalost'] ?? '');
        $title = $code !== '' ? ($events[$code] ?? ucfirst(str_replace('_', ' ', $code))) : ($types[(int)$row['typ']] ?? 'Akce IS');
        $objects=['cis_prava'=>'Úprava oprávnění','prava_global'=>'Změna práv role','prava_vyjimky'=>'Změna individuálního oprávnění','hr_import_udaje'=>'Import údajů HR','hr_import_smlouvy'=>'Import smluv','google_reporty_import'=>'Import Google reportů','restia_katalog'=>'Synchronizace katalogu Restia','server_data_export'=>'Export serverových dat','db_export'=>'Export databáze'];
        if ($code==='' && isset($objects[$row['objekt']])) $title=$objects[$row['objekt']];
        $state = ($context['puvod'] ?? '') === 'prohlizec' ? 'Ovládání' : (($context['faze'] ?? '') === 'potvrzeno' ? 'Potvrzeno' : $state);
        if ((int)$row['vysledek'] !== 1) $state = 'Neúspěch';
    } elseif ($row['zdroj'] === 'prihlaseni') {
        $title = (int)$row['typ'] === 1 ? 'Přihlášení' : 'Odhlášení / ukončení relace';
    } elseif ($row['zdroj'] === 'smeny') {
        $title = $events[$title] ?? ucfirst(str_replace('_', ' ', $title));
    } elseif (str_starts_with($row['zdroj'], 'hr_') || $row['zdroj'] === 'reporty') $state = 'Uložený podklad';
    $parts = [];
    foreach (['page','stranka'] as $key) if (!empty($context[$key]) && is_scalar($context[$key])) { $parts[] = str_replace('_', ' ', (string)$context[$key]); break; }
    if (!empty($row['objekt'])) $parts[] = str_replace('_', ' ', (string)$row['objekt']).((int)$row['objekt_id'] > 0 ? ' #'.$row['objekt_id'] : '');
    return ['nazev'=>$title, 'stav'=>$state, 'kontext'=>implode(' · ', $parts)];
}
