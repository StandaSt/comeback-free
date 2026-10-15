<?php
// Nacita den a celou hodinu uzaverky ze set_system a urcuje termin pred planovanym tydnem.
declare(strict_types=1);

// Nazvy jsou spolecne pro nastaveni i zobrazeni konkretniho terminu pozadavku.
function cb_smeny_uzaverka_dny(): array
{
    return [1 => 'pondělí', 2 => 'úterý', 3 => 'středa', 4 => 'čtvrtek', 5 => 'pátek', 6 => 'sobota', 7 => 'neděle'];
}

// Rozsahy chrani formular i hodnoty nactene ze systemoveho nastaveni.
function cb_smeny_uzaverka_validace(mixed $day, mixed $hour): array
{
    $day = filter_var($day, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
    $hour = filter_var($hour, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 23]]);
    if ($day === false || $hour === false) {
        throw new CbUserVisibleException('Vyberte den v týdnu a celou hodinu od 0 do 23.');
    }
    return ['den' => $day, 'hodina' => $hour];
}

// Cte se jednou za pozadavek, nikdy ze session; zmena plati i pro jiz prihlasene uzivatele.
function cb_smeny_uzaverka_nacti(mysqli $db): array
{
    static $cache = [];
    $key = spl_object_id($db);
    if (isset($cache[$key])) { return $cache[$key]; }
    $result = $db->query("SELECT COUNT(*) AS pocet FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='set_system'
        AND COLUMN_NAME IN ('smeny_pozadavky_den','smeny_pozadavky_hodina')");
    // Do rucni instalace dvou sloupcu zachovame dosavadni stredu 20:00.
    if ((int)$result->fetch_assoc()['pocet'] !== 2) {
        return $cache[$key] = ['den' => 3, 'hodina' => 20, 'pripraveno' => false];
    }
    $result = $db->query('SELECT smeny_pozadavky_den, smeny_pozadavky_hodina FROM set_system WHERE id_set=1');
    $row = $result->fetch_assoc();
    if (!$row) { throw new RuntimeException('Chybí systémové nastavení id_set=1.'); }
    return $cache[$key] = cb_smeny_uzaverka_validace($row['smeny_pozadavky_den'], $row['smeny_pozadavky_hodina']) + ['pripraveno' => true];
}

// Den uzaverky lezi vzdy v tydnu pred pondelim planovaneho rozpisu.
function cb_smeny_uzaverka_datum(DateTimeImmutable $monday, array $setting): DateTimeImmutable
{
    return $monday->modify('-' . (8 - $setting['den']) . ' days')->setTime($setting['hodina'], 0, 0);
}
