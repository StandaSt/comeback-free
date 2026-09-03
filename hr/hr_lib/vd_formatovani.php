<?php
declare(strict_types=1);

/**
 * Pomocne formatovani verejnych dotazniku pro zobrazeni v HR.
 */

/**
 * Vrati pocet uchazecu ve spravnem ceskem tvaru.
 */
function hr_pocet_uchazecu_text(int $pocet): string
{
    if ($pocet === 1) {
        return '1 uchazeč';
    }

    if ($pocet >= 2 && $pocet <= 4) {
        return $pocet . ' uchazeči';
    }

    return $pocet . ' uchazečů';
}

/**
 * Doplni radek VD o hodnoty pripravene pro zobrazeni.
 */
function hr_normalizuj_radek_vd(array $row): array
{
    $jmeno = trim((string)($row['jmeno'] ?? ''));
    $prijmeni = trim((string)($row['prijmeni'] ?? ''));
    $celeJmeno = trim($prijmeni . ' ' . $jmeno);
    $stavNazev = trim((string)($row['stav_nazev'] ?? ''));
    $stavNazev = $stavNazev !== '' ? $stavNazev : '-';
    $stavDoplnek = trim((string)($row['stav_doplnek'] ?? ''));
    $stavLabel = $stavDoplnek !== ''
        ? $stavNazev . ' – ' . mb_strtolower($stavDoplnek, 'UTF-8')
        : $stavNazev;

    return [
        'cele_jmeno' => $celeJmeno !== '' ? $celeJmeno : 'Bez jména',
        'pozice' => trim((string)($row['pozice'] ?? '')) !== '' ? (string)$row['pozice'] : '-',
        'pracoviste_preference' => trim((string)($row['pracoviste_preference'] ?? '')) !== '' ? (string)$row['pracoviste_preference'] : '-',
        'telefon' => hr_format_phone((string)$row['telefon']),
        'email' => trim((string)($row['email'] ?? '')) !== '' ? (string)$row['email'] : '-',
        'stav_nazev' => $stavNazev,
        'stav_label' => $stavLabel,
        'zdroj_nazev' => trim((string)($row['zdroj_nazev'] ?? '')) !== '' ? (string)$row['zdroj_nazev'] : '-',
        'planovano_na' => (string)($row['planovano_na'] ?? ''),
        'odeslano' => (string)($row['odeslano'] ?? ''),
        'platnost_do' => (string)($row['platnost_do'] ?? ''),
        'posledni_aktivita' => (string)($row['posledni_aktivita'] ?? ''),
    ] + $row;
}
