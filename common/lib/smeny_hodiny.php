<?php
declare(strict_types=1);

// Sdili validaci jedne prestavky a rozpad hodin pro Provoz i HR; historie zachovava pomerny odpocet.

// Overi jednu prestavku uvnitr smeny; prazdny interval zachova pouze predanou historickou delku.
function cb_smena_pauza(string $start, string $end, ?string $pauseFrom, ?string $pauseTo, float $legacy = 0.0): array
{
    $parse = static function (string $time): ?int {
        if (preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/D', $time) !== 1) return null;
        $p = array_map('intval', explode(':', $time));
        return $p[0] * 3600 + $p[1] * 60 + ($p[2] ?? 0);
    };
    $from = trim((string)$pauseFrom);
    $to = trim((string)$pauseTo);
    $result = ['ok' => false, 'pause' => 0.0, 'from' => null, 'to' => null, 'offset_from' => null, 'offset_to' => null, 'reason' => ''];
    $a = $parse($start);
    $b = $parse($end);
    if ($a === null || $b === null || $a === $b) {
        $result['reason'] = 'Zadejte platny zacatek a konec smeny.';
        return $result;
    }
    if ($b < $a) $b += 86400;
    if ($from === '' && $to === '') {
        if (!is_finite($legacy) || $legacy < 0 || $legacy * 3600 >= $b - $a) {
            $result['reason'] = 'Pauza musi byt kratsi nez smena.';
            return $result;
        }
        $result['ok'] = true;
        $result['pause'] = $legacy;
        return $result;
    }
    $c = $parse($from);
    $d = $parse($to);
    if ($c === null || $d === null) {
        $result['reason'] = 'U prestavky vyplnte oba casy od-do ve formatu HH:MM.';
        return $result;
    }
    if ($c < $a) $c += 86400;
    if ($d < $a) $d += 86400;
    if ($d <= $c || $c < $a || $d > $b || $d - $c >= $b - $a) {
        $result['reason'] = 'Prestavka musi byt uvnitr smeny, konec po zacatku a delka kratsi nez smena.';
        return $result;
    }
    $result['ok'] = true;
    $result['pause'] = ($d - $c) / 3600;
    $result['from'] = strlen($from) === 5 ? $from . ':00' : $from;
    $result['to'] = strlen($to) === 5 ? $to . ':00' : $to;
    $result['offset_from'] = $c - $a;
    $result['offset_to'] = $d - $a;
    return $result;
}

if (!function_exists('ps_is_czech_holiday')) {
    // Sdilene rozpoznani svatku pro prehledy a exporty.
    function ps_is_czech_holiday(DateTimeImmutable $date): bool
    {
        return ps_czech_holiday_name($date) !== '';
    }
}

if (!function_exists('ps_czech_holiday_name')) {
    // Vraci nazev pevneho nebo velikonocniho svatku podle data smeny.
    function ps_czech_holiday_name(DateTimeImmutable $date): string
    {
        $ymd = $date->format('m-d');
        $fixed = [
            '01-01' => 'Den obnovy samostatného českého státu / Nový rok',
            '05-01' => 'Svátek práce',
            '05-08' => 'Den vítězství',
            '07-05' => 'Den slovanských věrozvěstů Cyrila a Metoděje',
            '07-06' => 'Den upálení mistra Jana Husa',
            '09-28' => 'Den české státnosti',
            '10-28' => 'Den vzniku samostatného československého státu',
            '11-17' => 'Den boje za svobodu a demokracii',
            '12-24' => 'Štědrý den',
            '12-25' => '1. svátek vánoční',
            '12-26' => '2. svátek vánoční',
        ];
        if (isset($fixed[$ymd])) {
            return $fixed[$ymd];
        }

        $year = (int)$date->format('Y');
        $easter = (new DateTimeImmutable('@' . (string)easter_date($year)))->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $goodFriday = $easter->modify('-2 days')->format('Y-m-d');
        $easterMonday = $easter->modify('+1 day')->format('Y-m-d');

        if ($date->format('Y-m-d') === $goodFriday) {
            return 'Velký pátek';
        }
        if ($date->format('Y-m-d') === $easterMonday) {
            return 'Velikonoční pondělí';
        }

        return '';
    }
}

if (!function_exists('ps_shift_parts')) {
    /**
     * @return array{day:float, night:float, weekend:float, holiday:float, holiday_night:float, holiday_details:array<string,array{date:string,name:string,hours:float}>}
     */
    // Presnou prestavku odecita podle casu; bez intervalu zachovava historicky pomerny vypocet.
    function ps_shift_parts(string $date, string $from, string $to, float $workedHours, ?string $pauseFrom = null, ?string $pauseTo = null): array
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $from);
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $to);
        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            return ['day' => 0.0, 'night' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'holiday_night' => 0.0, 'holiday_details' => []];
        }
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $duration = max(0.0, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
        if ($duration <= 0.0 || $workedHours <= 0.0) {
            return ['day' => 0.0, 'night' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'holiday_night' => 0.0, 'holiday_details' => []];
        }

        // Presny interval odecitame z prislusne casti dne; historie zustava pomerna.
        $pause = cb_smena_pauza($from, $to, $pauseFrom, $pauseTo);
        $exact = $pause['ok'] && $pause['from'] !== null;
        $ratio = $exact ? 1.0 : min(1.0, $workedHours / $duration);
        $out = ['day' => 0.0, 'night' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'holiday_night' => 0.0, 'holiday_details' => []];
        $cursor = $start;

        while ($cursor < $end) {
            $next = $exact ? $cursor->modify('next hour')->setTime((int)$cursor->modify('next hour')->format('G'), 0) : $cursor->modify('+1 hour');
            if ($next > $end) {
                $next = $end;
            }

            $hours = (($next->getTimestamp() - $cursor->getTimestamp()) / 3600) * $ratio;
            if ($exact) {
                $pauseStart = $start->getTimestamp() + $pause['offset_from'];
                $pauseEnd = $start->getTimestamp() + $pause['offset_to'];
                $hours -= max(0, min($next->getTimestamp(), $pauseEnd) - max($cursor->getTimestamp(), $pauseStart)) / 3600;
            }
            $hour = (int)$cursor->format('G');
            if ($hour >= 6 && $hour < 22) {
                $out['day'] += $hours;
            } else {
                $out['night'] += $hours;
            }

            $dayNo = (int)$cursor->format('N');
            if ($dayNo >= 6) {
                $out['weekend'] += $hours;
            }

            $holidayName = ps_czech_holiday_name($cursor);
            if ($holidayName !== '') {
                $out['holiday'] += $hours;
                // Prunik svatku a noci patri do stejneho pruchodu jako ostatni hodiny.
                if ($hour < 6 || $hour >= 22) {
                    $out['holiday_night'] += $hours;
                }
                $holidayKey = $cursor->format('Y-m-d') . '|' . $holidayName;
                if (!isset($out['holiday_details'][$holidayKey])) {
                    $out['holiday_details'][$holidayKey] = [
                        'date' => $cursor->format('Y-m-d'),
                        'name' => $holidayName,
                        'hours' => 0.0,
                    ];
                }
                $out['holiday_details'][$holidayKey]['hours'] += $hours;
            }

            $cursor = $next;
        }

        return $out;
    }
}

