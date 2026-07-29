<?php
declare(strict_types=1);

/**
 * Prevede datum z databaze na cesky citelny zapis.
 */
function hr_format_date(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }

    $ts = strtotime($date);
    return $ts === false ? '-' : date('j. n. Y', $ts);
}

/**
 * Vlozi mezery do ceskeho telefonu ulozeneho jako 9 cislic.
 */
function hr_format_phone(string $telefon): string
{
    return substr($telefon, 0, 3) . ' ' . substr($telefon, 3, 3) . ' ' . substr($telefon, 6, 3);
}
