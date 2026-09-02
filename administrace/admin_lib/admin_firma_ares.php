<?php
declare(strict_types=1);

/* Načte a normalizuje oficiální údaje firmy a jejích aktuálních jednatelů z ARES. */

function cb_admin_firma_ico_normalizuj(string $ico): string
{
    $ico = preg_replace('/\s+/u', '', trim($ico)) ?? '';
    if (preg_match('/^\d{8}$/D', $ico) !== 1) {
        throw new RuntimeException('IČO musí obsahovat přesně 8 číslic.');
    }

    $soucet = 0;
    for ($i = 0; $i < 7; $i++) {
        $soucet += (int)$ico[$i] * (8 - $i);
    }
    $kontrola = 11 - ($soucet % 11);
    if ($kontrola === 10) {
        $kontrola = 0;
    } elseif ($kontrola === 11) {
        $kontrola = 1;
    }
    if ((int)$ico[7] !== $kontrola) {
        throw new RuntimeException('IČO nemá platný kontrolní součet.');
    }

    return $ico;
}

function cb_admin_firma_ares_json(string $cesta): array
{
    $url = 'https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/' . ltrim($cesta, '/');
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Nepodařilo se připravit spojení s ARES.');
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Comeback-IS/1.0',
    ]);

    try {
        $body = curl_exec($curl);
        if (!is_string($body)) {
            throw new RuntimeException('ARES není dostupný: ' . curl_error($curl));
        }
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    } finally {
        curl_close($curl);
    }

    if ($status === 404) {
        throw new RuntimeException('ARES pro zadané IČO nenašel ekonomický subjekt.');
    }
    if ($status !== 200) {
        throw new RuntimeException('ARES vrátil neočekávaný stav HTTP ' . $status . '.');
    }
    if (strlen($body) > 2 * 1024 * 1024) {
        throw new RuntimeException('Odpověď ARES je neočekávaně velká.');
    }

    try {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('ARES vrátil neplatná data.', 0, $e);
    }
    if (!is_array($data)) {
        throw new RuntimeException('ARES nevrátil očekávanou strukturu dat.');
    }

    return $data;
}

function cb_admin_firma_ares_aktualni_jednatele(array $vr): array
{
    $zaznamy = is_array($vr['zaznamy'] ?? null) ? $vr['zaznamy'] : [];
    $zaznam = null;
    foreach ($zaznamy as $polozka) {
        if (is_array($polozka) && !empty($polozka['primarniZaznam'])) {
            $zaznam = $polozka;
            break;
        }
    }
    if (!is_array($zaznam) && isset($zaznamy[0]) && is_array($zaznamy[0])) {
        $zaznam = $zaznamy[0];
    }
    if (!is_array($zaznam)) {
        throw new RuntimeException('ARES VR nevrátil primární záznam firmy.');
    }

    $jednatele = [];
    $duplicity = [];
    $organy = is_array($zaznam['statutarniOrgany'] ?? null) ? $zaznam['statutarniOrgany'] : [];
    foreach ($organy as $organ) {
        if (!is_array($organ)) {
            continue;
        }
        $clenove = is_array($organ['clenoveOrganu'] ?? null) ? $organ['clenoveOrganu'] : [];
        foreach ($clenove as $clen) {
            if (!is_array($clen) || !empty($clen['datumVymazu'])) {
                continue;
            }
            $osoba = is_array($clen['fyzickaOsoba'] ?? null) ? $clen['fyzickaOsoba'] : null;
            if (!is_array($osoba)) {
                continue;
            }

            $jmeno = trim((string)($osoba['jmeno'] ?? ''));
            $prijmeni = trim((string)($osoba['prijmeni'] ?? ''));
            $funkceData = is_array($clen['clenstvi']['funkce'] ?? null) ? $clen['clenstvi']['funkce'] : [];
            $funkce = trim((string)($funkceData['nazev'] ?? $clen['nazevAngazma'] ?? ''));
            if ($jmeno === '' || $prijmeni === '' || $funkce === '') {
                continue;
            }

            $jednatel = [
                'jmeno' => $jmeno,
                'prijmeni' => $prijmeni,
                'funkce' => $funkce,
                'datum_zapisu' => cb_admin_firma_ares_datum($clen['datumZapisu'] ?? null),
                'funkce_od' => cb_admin_firma_ares_datum($funkceData['vznikFunkce'] ?? null),
                'funkce_do' => null,
            ];
            $klic = mb_strtolower($jmeno . '|' . $prijmeni . '|' . $funkce . '|' . (string)$jednatel['funkce_od'], 'UTF-8');
            if (!isset($duplicity[$klic])) {
                $duplicity[$klic] = true;
                $jednatele[] = $jednatel;
            }
        }
    }

    if ($jednatele === []) {
        throw new RuntimeException('ARES VR nevrátil žádného aktuálního jednatele – fyzickou osobu.');
    }

    return $jednatele;
}

function cb_admin_firma_ares_datum(mixed $hodnota): ?string
{
    $datum = trim((string)$hodnota);
    if ($datum === '') {
        return null;
    }
    $objekt = DateTimeImmutable::createFromFormat('!Y-m-d', $datum);
    return $objekt instanceof DateTimeImmutable && $objekt->format('Y-m-d') === $datum ? $datum : null;
}

function cb_admin_firma_ares_nacti(string $ico): array
{
    $ico = cb_admin_firma_ico_normalizuj($ico);
    $firma = cb_admin_firma_ares_json('ekonomicke-subjekty/' . rawurlencode($ico));
    $vr = cb_admin_firma_ares_json('ekonomicke-subjekty-vr/' . rawurlencode($ico));

    $sidlo = is_array($firma['sidlo'] ?? null) ? $firma['sidlo'] : [];
    $aresIco = trim((string)($firma['ico'] ?? ''));
    $obchodniJmeno = trim((string)($firma['obchodniJmeno'] ?? ''));
    if ($aresIco !== $ico || $obchodniJmeno === '') {
        throw new RuntimeException('ARES vrátil neúplné nebo nesouhlasící údaje firmy.');
    }

    return [
        'ico' => $ico,
        'dic' => trim((string)($firma['dic'] ?? '')) ?: null,
        'obchodni_jmeno' => $obchodniJmeno,
        'pravni_forma' => trim((string)($firma['pravniForma'] ?? '')) ?: null,
        'datum_vzniku' => cb_admin_firma_ares_datum($firma['datumVzniku'] ?? null),
        'kod_statu' => trim((string)($sidlo['kodStatu'] ?? '')) ?: null,
        'nazev_ulice' => trim((string)($sidlo['nazevUlice'] ?? '')) ?: null,
        'cislo_domovni' => isset($sidlo['cisloDomovni']) ? (string)$sidlo['cisloDomovni'] : null,
        'cislo_orientacni' => isset($sidlo['cisloOrientacni']) ? (string)$sidlo['cisloOrientacni'] : null,
        'nazev_obce' => trim((string)($sidlo['nazevObce'] ?? '')) ?: null,
        'nazev_casti_obce' => trim((string)($sidlo['nazevCastiObce'] ?? '')) ?: null,
        'psc' => isset($sidlo['psc']) ? str_pad((string)$sidlo['psc'], 5, '0', STR_PAD_LEFT) : null,
        'textova_adresa' => trim((string)($sidlo['textovaAdresa'] ?? '')) ?: null,
        'datum_aktualizace_ares' => cb_admin_firma_ares_datum($firma['datumAktualizace'] ?? null),
        'jednatele' => cb_admin_firma_ares_aktualni_jednatele($vr),
    ];
}
