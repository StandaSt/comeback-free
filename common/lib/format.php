<?php
// common/lib/format.php * Jednotné formátování hodnot pro uživatelské výstupy
declare(strict_types=1);

/**
 * Vrátí jednotný formát hodnoty pro uživatelské výstupy IS.
 * Funkce nemění hodnoty určené pro DB, formuláře ani API.
 *
 * d   = datum
 * dc  = datum a čas bez sekund
 * dcs = datum a čas včetně sekund
 * t   = čas bez sekund
 * p   = částka v Kč
 * p2  = částka v Kč se dvěma desetinnými místy
 * i   = celé číslo
 * n   = číslo se dvěma desetinnými místy
 * pr  = procento z desetinného poměru
 * m   = minuty z hodnoty v sekundách
 * ms  = minuty a sekundy z hodnoty v sekundách
 * h   = hodiny z desetinného počtu hodin
 * o   = číslo objednávky Restie
 *
 * Neznámý typ je chyba volání: výjimka chrání před tichým odlišným formátem
 * v novém výstupu.
 */
function cb_format(string $typ, mixed $hodnota, mixed $doplnek = null): string
{
    $typ = strtolower(trim($typ));

    if ($typ === 'o') {
        $objednavka = is_array($hodnota)
            ? $hodnota
            : [
                'restia_order_number' => $hodnota,
                'short_code' => $doplnek,
            ];
        $restiaOrderNumber = trim((string)($objednavka['restia_order_number'] ?? ''));
        $shortCode = trim((string)($objednavka['short_code'] ?? ''));
        $restiaIdObj = trim((string)($objednavka['restia_id_obj'] ?? ''));

        if ($restiaOrderNumber !== '' && $shortCode !== '') {
            return $restiaOrderNumber . ' (ID #' . $shortCode . ')';
        }
        if ($restiaOrderNumber !== '') {
            return $restiaOrderNumber;
        }
        if ($shortCode !== '') {
            return 'ID #' . $shortCode;
        }
        return $restiaIdObj;
    }

    if ($typ === 't') {
        if ($hodnota === null || $hodnota === '') {
            return '';
        }
        $raw = trim((string)$hodnota);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $matches) === 1) {
            return sprintf('%02d:%s', (int)$matches[1], $matches[2]);
        }

        try {
            return (new DateTimeImmutable($raw, new DateTimeZone('Europe/Prague')))->format('H:i');
        } catch (Throwable $e) {
            return $raw;
        }
    }

    if (in_array($typ, ['p', 'p2', 'i', 'n', 'pr', 'm', 'ms', 'h'], true)) {
        if ($hodnota === null || $hodnota === '') {
            return '';
        }
        $raw = is_string($hodnota)
            ? str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], trim($hodnota))
            : $hodnota;
        if (!is_numeric($raw)) {
            return trim((string)$hodnota);
        }
        $number = (float)$raw;
        if ($typ === 'p') {
            return number_format($number, 0, ',', ' ') . ' Kč';
        }
        if ($typ === 'p2') {
            return number_format($number, 2, ',', ' ') . ' Kč';
        }
        if ($typ === 'i') {
            return number_format($number, 0, ',', ' ');
        }
        if ($typ === 'n') {
            return number_format($number, 2, ',', ' ');
        }
        if ($typ === 'pr') {
            return number_format($number * 100, 2, ',', ' ') . ' %';
        }
        if ($typ === 'm') {
            return number_format($number / 60, 1, ',', ' ') . ' min.';
        }
        if ($typ === 'ms') {
            $seconds = (int)round($number);
            $prefix = $seconds < 0 ? '-' : '';
            $seconds = abs($seconds);
            return $prefix . intdiv($seconds, 60) . ' min ' . str_pad((string)($seconds % 60), 2, '0', STR_PAD_LEFT) . ' s';
        }

        $minutes = (int)round($number * 60);
        $prefix = $minutes < 0 ? '-' : '';
        $minutes = abs($minutes);
        return $prefix . intdiv($minutes, 60) . ':' . str_pad((string)($minutes % 60), 2, '0', STR_PAD_LEFT) . ' hod.';
    }

    if (!in_array($typ, ['d', 'dc', 'dcs'], true)) {
        throw new InvalidArgumentException('Neznámý formát hodnoty: ' . $typ);
    }
    if ($hodnota === null || $hodnota === '') {
        return '';
    }

    $timezone = new DateTimeZone('Europe/Prague');
    try {
        if ($hodnota instanceof DateTimeInterface) {
            $datumCas = DateTimeImmutable::createFromInterface($hodnota);
        } elseif (is_int($hodnota) || is_float($hodnota)) {
            $timestamp = (int)$hodnota;
            $datumCas = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
        } else {
            $raw = trim((string)$hodnota);
            if (preg_match('/^\d{13}$/', $raw) === 1) {
                $datumCas = (new DateTimeImmutable('@' . (int)floor((int)$raw / 1000)))->setTimezone($timezone);
            } elseif (preg_match('/^\d{10}$/', $raw) === 1) {
                $datumCas = (new DateTimeImmutable('@' . (int)$raw))->setTimezone($timezone);
            } else {
                $datumCas = new DateTimeImmutable($raw, $timezone);
            }
        }
    } catch (Throwable $e) {
        return trim((string)$hodnota);
    }

    $datumCas = $datumCas->setTimezone($timezone);
    return match ($typ) {
        'd' => $datumCas->format('j. n. Y'),
        'dc' => $datumCas->format('j. n. Y H:i'),
        'dcs' => $datumCas->format('j. n. Y H:i:s'),
    };
}
