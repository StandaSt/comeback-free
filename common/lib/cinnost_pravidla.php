<?php
declare(strict_types=1);
/* Účel: Ověřuje jednotné parametry nové evidence činností podle číselníků; neprovádí dotazy ani zápisy. */

/** Stabilní ID sdílí SQL číselník a volající; názvy ani šablony nejsou podmínkou programu. */
final class CbCinnostAkce
{
    public const MODUL_OTEVREN = 1;
    public const STRANKA_OTEVRENA = 2;
    public const DETAIL_OTEVREN = 3;
    public const ADRESAR_OTEVREN = 4;
    public const FILTR_ZMENEN = 5;
    public const FILTRY_ZRUSENY = 6;
    public const RAZENI_ZMENENO = 7;
    public const STRANA_ZMENENA = 8;
    public const POCET_RADKU_ZMENEN = 9;
    public const POLE_ZMENENO = 10;
    public const FORMULAR_ODESLAN = 11;
    public const ZAZNAM_ULOZEN = 12;
    public const ZAZNAM_VYTVOREN = 13;
    public const ZAZNAM_ODSTRANEN = 14;
    public const EXPORT_PROVEDEN = 15;
    public const PRILOHA_STAZENA = 16;
    public const DETAIL_ZAVREN = 17;
    public const MENU_KLIK = 18;
    public const HLEDANI_ZMENENO = 19;
    public const ZPRAVA_ODESLANA = 20;
    public const ZPRAVA_PRECTENA = 21;
    public const POLE_ULOZENO = 22;
    public const KOMUNIKACE_OTEVRENA = 30;
    public const SKUPINA_OTEVRENA = 31;
    public const KANAL_OTEVREN = 32;
}

/** Prohlížeč nikdy nemůže potvrdit uložení; přípustné kombinace stavů určuje číselník akcí. */
final class CbCinnostPuvod
{
    public const SERVER = 1;
    public const PROHLIZEC = 2;
}

/** Stejné stavy mají stejný význam ve všech osmi modulech. */
final class CbCinnostStav
{
    public const ZOBRAZENO = 1;
    public const ROZPRACOVANO = 2;
    public const ODESLANO = 3;
    public const ULOZENO = 4;
    public const NEUSPECH = 5;
    public const PROVEDENO = 6;
}

/** Jediný pevný seznam SQL identifikátorů; jméno tabulky se nikdy neskládá ze vstupu. */
function cb_cinnost_tabulka(string $modul): string
{
    return match ($modul) {
        'administrace' => 'cinnost_administrace', 'provoz' => 'cinnost_provoz',
        'hr' => 'cinnost_hr', 'smeny' => 'cinnost_smeny', 'ukoly' => 'cinnost_ukoly',
        'helpdesk' => 'cinnost_helpdesk', 'email' => 'cinnost_email', 'co_link' => 'cinnost_co_link',
        default => throw new InvalidArgumentException('Neznámý modul činnosti.'),
    };
}

/** Odmítneme neznámá pole, aby se do historie omylem neposlal celý formulář nebo tajemství. */
function cb_cinnost_parametry_overit(array $parametry): void
{
    $allowed = ['identita','udalost_uuid','cas_udalosti','id_firma','id_stranka','id_puvod','id_stav',
        'id_cil_typ','cil_id','id_person','id_pob','id_pole','data'];
    foreach ($parametry as $key => $value) {
        if (!in_array($key, $allowed, true)) throw new InvalidArgumentException('Nepovolený parametr činnosti.');
        if ((str_starts_with((string)$key, 'id_') || $key === 'cil_id') && $value !== null) {
            $maximum = $key === 'cil_id' ? PHP_INT_MAX : 2147483647;
            if (!is_int($value) || $value < 1 || $value > $maximum) throw new InvalidArgumentException('Neplatné ID činnosti.');
        }
    }
}

/** Malý katalog datových typů sdílí všechna pravidla akcí; povoleny jsou jen omezené skalární hodnoty. */
function cb_cinnost_hodnota_overit(mixed $value, array $rule): void
{
    if ($value === null && !empty($rule['null'])) return;
    $valid = match ($rule['typ'] ?? '') {
        'id' => is_int($value) && $value > 0 && $value <= 2147483647,
        'int' => is_int($value) && $value >= (int)($rule['min'] ?? 0) && $value <= (int)($rule['max'] ?? 2147483647),
        'bool' => is_bool($value),
        'text' => is_string($value) && mb_check_encoding($value, 'UTF-8')
            && mb_strlen($value, 'UTF-8') <= (int)($rule['max'] ?? 300)
            && !preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value),
        'datum' => is_string($value) && ($date = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) !== false && $date->format('Y-m-d') === $value,
        default => false,
    };
    if (!$valid || (isset($rule['hodnoty']) && !in_array($value, $rule['hodnoty'], true))) {
        throw new InvalidArgumentException('Hodnota neodpovídá pravidlům činnosti.');
    }
}

/** Definice z číselníku určí modul, stav, cíl a přesně povolené proměnné; neznámé údaje se nezahazují potichu. */
function cb_cinnost_pravidla_overit(string $modul, array $p, array $action, ?array $field): array
{
    $rules = json_decode($action['pravidla_json'], true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($rules)) throw new RuntimeException('Neplatná definice činnosti.');
    if (isset($rules['moduly']) && !in_array($modul, $rules['moduly'], true)) throw new InvalidArgumentException('Akce nepatří do modulu.');
    $origin = $p['id_puvod'] ?? CbCinnostPuvod::SERVER;
    $state = $p['id_stav'] ?? (int)$action['id_stav_vychozi'];
    $states = $rules[$origin === CbCinnostPuvod::SERVER ? 'server' : 'prohlizec'] ?? [];
    if (!in_array($origin, [1,2], true) || !in_array($state, $states, true)) throw new InvalidArgumentException('Nepovolený původ nebo stav činnosti.');
    $target = $p['id_cil_typ'] ?? ($action['id_cil_typ'] === null ? null : (int)$action['id_cil_typ']);
    if ($action['id_cil_typ'] !== null && $target !== (int)$action['id_cil_typ']) throw new InvalidArgumentException('Nesprávný typ cíle činnosti.');
    if (($target === null) !== (($p['cil_id'] ?? null) === null)) throw new InvalidArgumentException('Cíl vyžaduje typ i ID.');
    $p['id_cil_typ'] = $target;
    foreach ($rules['povinne'] ?? [] as $key) if (!isset($p[$key])) throw new InvalidArgumentException('Chybí povinný parametr činnosti: '.$key);
    $data = $p['data'] ?? [];
    if (!is_array($data) || (array_is_list($data) && $data !== [])) throw new InvalidArgumentException('Data činnosti musí být pojmenovaná.');
    $definitions = $rules['data'] ?? [];
    foreach ($data as $key => $value) {
        if (!isset($definitions[$key])) throw new InvalidArgumentException('Nepovolený údaj činnosti.');
        $definition = $definitions[$key];
        if (($definition['typ'] ?? '') === 'pole') {
            if (!$field || !(int)$field['hodnoty_povoleny']) throw new InvalidArgumentException('Hodnoty tohoto pole se neevidují.');
            $definition = json_decode($field['pravidla_json'], true, 8, JSON_THROW_ON_ERROR);
        }
        cb_cinnost_hodnota_overit($value, $definition);
    }
    foreach ($definitions as $key => $definition) if (!empty($definition['povinne']) && !array_key_exists($key, $data)) throw new InvalidArgumentException('Chybí povinný údaj činnosti: '.$key);
    // Pořadí klíčů nemá měnit význam ani kontrolu opakovaného odeslání.
    ksort($data);
    return ['id_puvod'=>$origin, 'id_stav'=>$state, 'id_cil_typ'=>$target,
        'parametry_json'=>$data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
}
