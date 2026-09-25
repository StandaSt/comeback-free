<?php
declare(strict_types=1);

/*
 * Vytváří čtyři automaticky dostupné budoucí týdny a jejich pevné deadliny.
 */

/** @return array<int,array<string,mixed>> */
function cb_smeny_pozadavky_tydny(?DateTimeImmutable $now = null): array
{
    $zone = new DateTimeZone('Europe/Prague');
    $now = $now ? $now->setTimezone($zone) : new DateTimeImmutable('now', $zone);
    $thisMonday = $now->modify('monday this week')->setTime(0, 0);
    $names = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];
    $closingKeys = ['end_po', 'end_ut', 'end_st', 'end_ct', 'end_pa', 'end_so', 'end_ne'];
    $weeks = [];

    for ($index = 0; $index < 4; $index++) {
        $start = $thisMonday->modify('+' . ($index + 1) . ' weeks');
        $deadline = $start->modify('-5 days')->setTime(20, 0);
        $days = [];
        for ($day = 0; $day < 7; $day++) {
            $date = $start->modify('+' . $day . ' days');
            $days[] = [
                'name' => $names[$day],
                'date' => $date->format('Y-m-d'),
                'date_label' => $date->format('j. n. Y'),
                'closing_key' => $closingKeys[$day],
            ];
        }
        $weeks[] = [
            'index' => $index,
            'start' => $start,
            'start_day' => $start->format('Y-m-d'),
            'end' => $start->modify('+6 days'),
            'deadline' => $deadline,
            'open' => $now <= $deadline,
            'days' => $days,
        ];
    }
    return $weeks;
}

/** @param array<int,array<string,mixed>> $weeks */
function cb_smeny_pozadavky_index(array $weeks): int
{
    if (isset($_GET['week']) && preg_match('~^[0-3]$~', (string)$_GET['week']) === 1) {
        return (int)$_GET['week'];
    }
    foreach ($weeks as $week) {
        if (!empty($week['open'])) {
            return (int)$week['index'];
        }
    }
    return 0;
}

function cb_smeny_pozadavky_cas_na_index(string $time, bool $isEnd = false): int
{
    if (preg_match('~^(\d{2}):(\d{2})(?::\d{2})?$~', trim($time), $match) !== 1) {
        return 0;
    }
    $minutes = ((int)$match[1] * 60) + (int)$match[2];
    if ($minutes < 360 || ($isEnd && $minutes <= 240)) {
        $minutes += 1440;
    }
    $index = (int)(($minutes - 360) / 15) + 1;
    return max(1, min(89, $index));
}

function cb_smeny_pozadavky_index_na_cas(int $index): string
{
    $minutes = 360 + (($index - 1) * 15);
    $minutes %= 1440;
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}
