<?php
// Polozky kontroly trzeb a presne vypocty v halerich bez desetinne chyby.
declare(strict_types=1);

// Pevne klice spojuji formular, vypocet a archiv nezavisle na popiscich.
function cb_kt_polozky(): array
{
    return [
        'hotovost' => ['Hotovost', true], 'terminal' => ['Terminál', true],
        'stravenky' => ['Stravenky', true], 'vydaje_benzin' => ['Benzín', true],
        'vydaje_auta' => ['Auta', true], 'vydaje_suroviny' => ['Suroviny', true],
        'vydaje_ostatni' => ['Ostatní', true], 'vydaje_phm_soukrome' => ['PHM – soukromé', true],
        'wolt' => ['Wolt', false], 'bolt' => ['Bolt', false],
        'damejidlo' => ['Dáme jídlo / Foodora', false], 'web' => ['Web', false],
        'wolt_cash' => ['Wolt drive cash', false], 'dj_cash' => ['DJ Cash', false],
        'trzba' => ['Celková tržba', false], 'rozdil' => ['Pokladna', false],
    ];
}

// Prijme nejvyse dva desetinne znaky; prazdne pole zustava nezadane, nikoli nula.
function cb_kt_halere(?string $value): ?int
{
    $value = str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], trim($value ?? ''));
    if ($value === '') { return null; }
    if (!preg_match('/^(-?)(\d{1,11})(?:\.(\d{1,2}))?$/D', $value, $m)) {
        throw new CbUserVisibleException('Částku zadejte jako číslo s nejvýše dvěma desetinnými místy.');
    }
    return ($m[1] === '-' ? -1 : 1) * ((int)$m[2] * 100 + (int)str_pad($m[3] ?? '', 2, '0'));
}

// Desetinny retezec patri do DB a formularu, nikoli prevod pres float.
function cb_kt_decimal(?int $value): ?string
{
    if ($value === null) { return null; }
    return ($value < 0 ? '-' : '') . intdiv(abs($value), 100) . '.' . str_pad((string)(abs($value) % 100), 2, '0', STR_PAD_LEFT);
}

// Pokladna zahrnuje DJ Cash uz v hotovosti; jeho opakovane pricteni by zdvojilo prijem.
function cb_kt_pokladna(array $values): ?int
{
    $sum = 0;
    foreach (array_keys(cb_kt_polozky()) as $key) {
        if (in_array($key, ['dj_cash', 'trzba', 'rozdil'], true)) { continue; }
        if (!isset($values[$key])) { return null; }
        $sum += $values[$key];
    }
    return isset($values['trzba']) ? $sum - $values['trzba'] : null;
}
